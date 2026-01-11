<?php
/**
 * CapCore License Verification Handler
 *
 * This script processes activation and deactivation requests for theme licenses.
 * It manages two slots per purchase code (1x Production, 1x Development).
 * It features a secure HMAC handshake for public domains and a bypass for local
 * development environments if the 'development' environment is selected.
 *
 * @package   CapCore_License_Server
 * @author    Capable Themes
 * @version   1.3.0
 */

use JetBrains\PhpStorm\NoReturn;

require_once( '../inc/capable-envato-api.php' );

/**
 * Handles the secure registration, slot management, and validation of licenses.
 */
final class Capable_Verification {

	/** @var string The purchase code to be verified. */
	public string $purchase_code;

	/** @var string The database table for license records. */
	protected string $verification_table = 'verification';

	/** @var MysqliDb The database connection instance. */
	private $database;

	/** @var Capable_Envato_API The API client for Envato. */
	private $envato;

	/** @var object The purchase data retrieved from Envato. */
	private $purchase;

	/** @var string The requested environment type ('production'|'development'). */
	private $environment;

	/** @var array Sanitized meta-information about the domain. */
	private $domain_data = array();

    /**
     * Constructor. Sanitizes incoming data and initializes the verification.
     * @throws Exception
     */
	public function __construct() {

		global $db;
		$this->database = $db;

		// 1. Initial Debug Logging.
		if ( defined( 'LS_DEBUG_MODE' ) && LS_DEBUG_MODE ) {
			$this->log_debug( '--- New Request Start ---' );
			$this->log_debug( 'Raw GET Data: ' . print_r( $_GET, true ) );
		}

		if ( ! isset( $_GET['purchase_code'] ) ) {
			return;
		}

		// 2. Sanitize and Normalize Input.
		$this->environment = isset( $_GET['environment'] ) ? $this->database->escape( $_GET['environment'] ) : 'production';
		if ( ! in_array( $this->environment, array( 'production', 'development' ), true ) ) {
			$this->environment = 'production';
		}

		try {
			$this->purchase_code = $this->database->escape( $_GET['purchase_code'] );
			$this->domain_data   = array(
				'domain'        => isset( $_GET['domain'] ) ? $this->normalize_domain( $_GET['domain'] ) : '',
				'admin_email'   => isset( $_GET['admin_email'] ) ? $this->database->escape( $_GET['admin_email'] ) : '',
				'revoke_domain' => isset( $_GET['revoke_domain'] ) ? (int) $_GET['revoke_domain'] : 0,
			);
		} catch ( Exception $e ) {
			$this->respond_error( 'Critical: Input sanitization failed.' );
		}

		$this->start_verification();

	}

	/**
	 * Normalizes a domain by removing protocols, www, and trailing slashes.
	 *
	 * @param string $domain The domain or URL to normalize.
	 * @return string        The cleaned domain string.
	 */
	private function normalize_domain( string $domain ): string {
		$domain = strtolower( $domain );
		$domain = preg_replace( '/^https?:\/\//i', '', $domain );
		$domain = preg_replace( '/^www\./i', '', $domain );
		return rtrim( $domain, '/' );
	}

	/**
	 * Checks if a domain is a known local development environment.
	 *
	 * @param string $domain The normalized domain.
	 * @return boolean       True if local environment detected.
	 */
	private function is_local_domain( string $domain ): bool {
		$local_suffixes = array( '.local', '.test', '.example', '.localhost', '.dev.cc' );
		if ( in_array( $domain, array( 'localhost', '127.0.0.1', '::1' ), true ) || strpos( $domain, 'localhost:' ) === 0 ) {
			return true;
		}
		foreach ( $local_suffixes as $suffix ) {
			if ( substr( $domain, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Performs a secure HMAC handshake to verify that the client controls the domain.
	 *
	 * @return boolean True if handshake signature matches.
	 */
	private function verify_handshake(): bool {

		$token = bin2hex( random_bytes( 16 ) );
		// Protocol detection for handshake call.
		$protocol = ( strpos( $this->domain_data['domain'], 'localhost' ) !== false ) ? 'http://' : 'https://';
		$url      = $protocol . $this->domain_data['domain'] . '/?capcore_handshake=' . $token;

		$this->log_debug( 'Starting Handshake with: ' . $url );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 12 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'CapCore Handshake Bot/1.1' );
		$response  = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$data = json_decode( $response, true );

		if ( 200 === $http_code && isset( $data['success'] ) && true === $data['success'] ) {
			$expected_signature = hash_hmac( 'sha256', $token, $this->purchase_code );
			$client_signature   = $data['data']['signature'] ?? '';

			if ( hash_equals( $expected_signature, $client_signature ) ) {
				$this->log_debug( 'Handshake Signature Verified.' );
				return true;
			}
		}

		$this->log_debug( "Handshake Failed. HTTP: $http_code | Response: $response" );
		return false;

	}

	/**
	 * Orchestrates the activation logic and slot validation.
	 * * It ensures that the current domain's registration is always updated
	 * while correctly counting slots occupied by OTHER domains.
	 *
	 * @return void
	 */
	public function db_report(): void {

		try {
			$is_local = $this->is_local_domain( $this->domain_data['domain'] );

			if ( ! $is_local || 'production' === $this->environment ) {
				if ( ! $this->verify_handshake() ) {
					$this->respond_error( 'Security Handshake failed. Remote server cannot reach your domain.' );
				}
			} else {
				$this->log_debug( 'Local domain detected. Handshake bypassed.' );
			}

			/**
			 * Fetch all active registrations for this purchase code.
			 */
			$this->database->where( 'purchase_code', $this->purchase_code );
			$this->database->where( 'revoke_domain', 0 );
			$registrations = $this->database->get( $this->verification_table );

			$prod_count = 0;
			$dev_count  = 0;

			if ( $registrations ) {
				foreach ( $registrations as $reg ) {
					// We only count slots from OTHER domains.
					if ( $this->normalize_domain( $reg['domain'] ) !== $this->domain_data['domain'] ) {
						if ( 'production' === $reg['environment'] ) {
							$prod_count++;
						} else {
							$dev_count++;
						}
					}
				}
			}

			// Handle Revocation Request.
			if ( 0 !== $this->domain_data['revoke_domain'] ) {
				$this->revoke_license();
				return;
			}

			// Slot Enforcement for DIFFERENT domains.
			if ( 'production' === $this->environment && $prod_count >= 1 ) {
				$this->respond_error( 'Production slot already in use by another domain.' );
			}

			if ( 'development' === $this->environment && $dev_count >= 1 ) {
				$this->respond_error( 'Development slot already in use by another domain.' );
			}

			// Proceed to update or insert the record.
			$this->insert_license();

		} catch ( Exception $e ) {
			$this->respond_error( $e->getMessage() );
		}

		$this->database->disconnect();
		die();

	}

    /**
     * Inserts or updates the license record.
     * * Uses a fuzzy match to catch legacy records with protocols and
     * ensures 'revoke_domain' is reset to 0 during reactivation.
     *
     * @return void
     * @throws Exception
     */
	public function insert_license(): void {

		$data = array(
			'customer_id'        => 0,
			'product_id'         => $this->purchase->product_id,
			'purchase_code'      => $this->purchase_code,
			'domain'             => $this->domain_data['domain'],
			'environment'        => $this->environment,
			'server_real_ip'     => $_SERVER['REMOTE_ADDR'],
			'domain_admin_email' => $this->domain_data['admin_email'],
			'supported_until'    => $this->purchase->supported_until,
			'revoke_domain'      => 0,
		);

		// Robust Search for legacy or normalized records.
		$this->database->where( 'purchase_code', $this->purchase_code );
		$this->database->where( '(domain = ? OR domain = ? OR domain = ?)', array(
			$this->domain_data['domain'],
			'https://' . $this->domain_data['domain'],
			'http://' . $this->domain_data['domain']
		) );

		$existing = $this->database->getOne( $this->verification_table );

		if ( $existing ) {
			$this->database->where( 'id', $existing['id'] );
			$result = $this->database->update( $this->verification_table, array(
				'revoke_domain'      => 0,
				'domain'             => $this->domain_data['domain'], // Normalize on update
				'environment'        => $this->environment,
				'server_real_ip'     => $_SERVER['REMOTE_ADDR'],
				'domain_admin_email' => $this->domain_data['admin_email']
			) );
		} else {
			$result = $this->database->insert( $this->verification_table, $data );
		}

		if ( ! $result ) {
			$this->respond_error( 'Database write failed.' );
		}

		$this->respond_success();

	}

    /**
     * Revokes the license for the specified domain.
     * * Matches normalized and legacy domain strings to ensure the
     * slot is correctly freed in the database.
     *
     * @return void
     * @throws Exception
     */
	public function revoke_license(): void {

		$this->database->where( 'purchase_code', $this->purchase_code );
		$this->database->where( '(domain = ? OR domain = ? OR domain = ?)', array(
			$this->domain_data['domain'],
			'https://' . $this->domain_data['domain'],
			'http://' . $this->domain_data['domain']
		) );

		$this->database->update( $this->verification_table, array( 'revoke_domain' => 1 ) );

		header( 'Content-Type: application/json' );
		echo json_encode( array( 'success' => true, 'result' => 'revoke_success' ) );
		die();

	}

    /**
     * Terminates the request with a detailed JSON error response.
     * Includes safety checks to prevent crashes if the database is unavailable.
     *
     * @param string $reason Human-readable error description.
     * @return void
     * @throws Exception
     */
	private function respond_error( string $reason ): void {

		// Safely retrieve the last DB error only if the database object exists.
		$db_error    = ( $this->database && method_exists( $this->database, 'getLastError' ) ) ? $this->database->getLastError() : '';
		$full_reason = $reason . ( $db_error ? ' | DB: ' . $db_error : '' );

		if ( method_exists( $this, 'log_debug' ) ) {
			$this->log_debug( 'Access Denied: ' . $full_reason );
		}

		header( 'Content-Type: application/json' );
		echo json_encode(
			array(
				'success' => false,
				'result'  => 'access_denied',
				'reason'  => $full_reason,
			)
		);

		// Flush buffer and terminate.
		if ( ob_get_length() ) {
			ob_end_clean();
		}
		exit;

	}

	/**
	 * Terminates the request with success.
	 */

    private function respond_success(): void {
		header( 'Content-Type: application/json' );
		echo json_encode( array(
			'success'         => true,
			'result'          => 'access_success',
			'user'            => $this->purchase->buyer,
			'supported_until' => $this->purchase->supported_until,
		) );
		die();
	}

    /**
     * Validates the purchase code using the Envato Market API.
     * Prevents object casting errors if the API returns false or null.
     *
     * @return void
     * @throws Exception
     */
	public function start_verification(): void {

		$this->envato = new Capable_Envato_API();
		$this->envato->set_api_key( EN_TOKEN );

		$purchase_data = $this->envato->verify_purchase( $this->purchase_code );

		/**
		 * Check if the purchase is valid and contains a product ID.
		 * We use array access first to avoid "Trying to get property of non-object" errors.
		 */
		if ( ! $purchase_data || ! isset( $purchase_data['product_id'] ) || ! valid_product_ids( $purchase_data['product_id'] ) ) {
			$this->respond_error( INVALID );
		} else {
			$this->purchase = (object) $purchase_data;
			$this->db_report();
		}

	}

	/**
	 * Logs debug information to debug.log.
	 */
	private function log_debug( string $message ): void {
		if ( ! defined( 'LS_DEBUG_MODE' ) || ! LS_DEBUG_MODE ) return;
		error_log( '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL, 3, __DIR__ . '/../debug.log' );
	}
}

new Capable_Verification();