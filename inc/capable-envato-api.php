<?php
/**
 * CapCore Envato API Wrapper
 *
 * This class handles all communication with the Envato Market API, including
 * purchase verification, user data retrieval, and local caching of license data.
 *
 * @package   CapCore_License_Server
 * @author    Capable Themes
 * @version   1.3.1
 */

// Required core files.
require_once( 'config.inc.php' );

/**
 * Wrapper class for Envato API V1, V2, and V3.
 */
class Capable_Envato_API {

	/**
	 * The Envato Personal Token / API Key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Optional API key to initialize the class.
	 */
	public function __construct( $api_key = null ) {
		if ( ! $api_key ) {
			return;
		}
		$this->api_key = $api_key;
	}

	/**
	 * Sets the API key for subsequent requests.
	 *
	 * @param string $api_key The Envato Personal Token.
	 * @return void
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Returns the request URL for various Envato API endpoints.
	 *
	 * @param string $type     The type of endpoint (e.g., 'base', 'author_sale').
	 * @param string $username Optional username for user-specific endpoints.
	 * @return string          The full API request URL.
	 */
	public function api_request_urls( $type = 'base', $username = '' ) {
		$api_request_urls = array(
			'token'           => 'https://api.envato.com/token',
			'authorize'       => 'https://api.envato.com/authorization',
			'base'            => 'https://api.envato.com/v3/market',
			'email'           => 'https://api.envato.com/v1/market/private/user/email.json',
			'username'        => 'https://api.envato.com/v1/market/private/user/username.json',
			'account_details' => 'https://api.envato.com/v1/market/private/user/account.json',
			'purchases'       => 'https://api.envato.com/v2/market/buyer/purchases',
			'list_purchases'  => 'https://api.envato.com/v3/market/buyer/list-purchases',
			'user_details'    => 'https://api.envato.com/v1/market/user:' . $username . '.json',
			'user_badges'     => 'https://api.envato.com/v1/market/user-badges:' . $username . '.json',
			'author_sales'    => 'https://api.envato.com/v2/market/author/sales',
			'author_sale'     => 'https://api.envato.com/v3/market/author/sale',
			'user_items'      => 'https://api.envato.com/v1/market/user-items-by-site:' . $username . '.json',
		);

		return $api_request_urls[ $type ];
	}

	/**
	 * Queries the Envato API using a Bearer Token.
	 *
	 * @param string $url The URL to access via cURL.
	 * @return object|false The decoded JSON response or false on failure.
	 */
	protected function curl_token( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CapCore API Wrapper)' );

		$header   = array();
		$header[] = 'Content-length: 0';
		$header[] = 'Content-type: application/json';
		$header[] = 'Authorization: Bearer ' . $this->api_key;

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

		$data = curl_exec( $ch );
		curl_close( $ch );

		return json_decode( $data );
	}

	/**
	 * Executes a Bearer-authenticated request (OAuth flow).
	 *
	 * @param array  $initial_data The OAuth token data.
	 * @param string $request_url  The endpoint to target.
	 * @return string              The raw response.
	 */
	public function curl_bearer( $initial_data, $request_url = '' ) {
		$bearer   = 'bearer ' . $initial_data['access_token'];
		$header   = array();
		$header[] = 'Content-length: 0';
		$header[] = 'Content-type: application/json';
		$header[] = 'Authorization: ' . $bearer;

		$request_url = ! empty( $request_url ) ? $request_url : $this->api_request_urls( 'username' );

		$ch = curl_init( $request_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_USERAGENT, EN_CLIENT );

		$data = curl_exec( $ch );
		curl_close( $ch );

		return $data;
	}

	/**
	 * General purpose function to query the Envato API for OAuth tokens.
	 *
	 * @param array $custom_fields Optional POST fields.
	 * @return array               The initial token data.
	 */
	public function call_envato_api( $custom_fields = array() ) {
		$fields = ! empty( $custom_fields ) ? $custom_fields : array(
			'grant_type'    => urlencode( 'authorization_code' ),
			'client_id'     => EN_CLIENT,
			'client_secret' => EN_SECRET,
		);

		$fields_string = http_build_query( $fields, '', '&' );

		$ch = curl_init( $this->api_request_urls( 'token' ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, count( $fields ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_USERAGENT, EN_CLIENT );

		$cinit_data   = curl_exec( $ch );
		$initial_data = json_decode( $cinit_data, true );
		curl_close( $ch );

		return $initial_data;
	}

	/**
	 * Loads the Envato Access Token based on the current request parameters.
	 *
	 * @return array|false The token data or false.
	 */
	public function load_envato_access_token() {
		if ( isset( $_GET['envato_registration'] ) && isset( $_GET['code'] ) ) {
			$fields = array(
				'grant_type'    => urlencode( 'authorization_code' ),
				'code'          => urlencode( $_GET['code'] ),
				'client_id'     => urlencode( EN_CLIENT ),
				'client_secret' => urlencode( EN_SECRET ),
			);
			return $this->call_envato_api( $fields );
		} elseif ( isset( $_GET['envato_user_data'] ) && isset( $_GET['code'] ) ) {
			$fields = array(
				'grant_type'    => urlencode( 'refresh_token' ),
				'refresh_token' => urlencode( $_GET['code'] ),
				'client_id'     => urlencode( EN_CLIENT ),
				'client_secret' => urlencode( EN_SECRET ),
			);
			return $this->call_envato_api( $fields );
		}

		return false;
	}

	/**
	 * Returns decoded Envato API data or prints errors.
	 *
	 * @param array  $initial_data The OAuth data.
	 * @param string $request_url  The target URL.
	 * @return array|void
	 */
	public function return_envato_data( $initial_data, $request_url = '' ) {
		$errors = array();
		if ( empty( $initial_data['access_token'] ) ) {
			$errors[] = 'Could not get bearer, please contact admin';
		}

		if ( ! $errors ) {
			$data = $this->curl_bearer( $initial_data, $request_url );
			return json_decode( $data, true );
		} else {
			print_r( $errors );
		}
	}

	/**
	 * Loads user data using a refresh token.
	 *
	 * @param string $refresh_code The OAuth refresh token.
	 * @param string $request_url  The endpoint URL.
	 * @return array
	 */
	public function load_envato_user_data( $refresh_code, $request_url = '' ) {
		$fields       = array(
			'grant_type'    => urlencode( 'refresh_token' ),
			'refresh_token' => urlencode( $refresh_code ),
			'client_id'     => urlencode( EN_CLIENT ),
			'client_secret' => urlencode( EN_SECRET ),
		);
		$initial_data = $this->call_envato_api( $fields );

		return $this->return_envato_data( $initial_data, $request_url );
	}

	/**
	 * Fetches all purchases for a user and caches them in the database.
	 *
	 * @param array $custom_args User identification arguments.
	 * @return array             List of purchase data.
	 */
	public function get_user_purchases( $custom_args ) {
		global $db;
		$purchases    = array();
		$default_args = array(
			'user_id'      => 0,
			'username'     => '',
			'refresh_code' => '',
		);

		$args         = array_merge( $default_args, $custom_args );
		$username     = $args['username'];
		$refresh_code = $args['refresh_code'];

		if ( ! empty( $username ) && ! empty( $refresh_code ) ) {
			$data = $this->load_envato_user_data( $refresh_code, $this->api_request_urls( 'purchases' ) );
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					foreach ( $item as $single_item ) {
						if ( is_array( $single_item ) && ! empty( $single_item['item']['id'] ) ) {
							$purchases[] = array(
								'id'              => $single_item['item']['id'],
								'name'            => $single_item['item']['name'],
								'date'            => $single_item['sold_at'],
								'license'         => $single_item['license'],
								'code'            => $single_item['code'],
								'support_amount'  => $single_item['support_amount'],
								'supported_until' => $single_item['supported_until'],
							);

							$db->connection( 'default' )->setPrefix();
							$db->connection( 'default' )->insert( 'envato_purchase_codes', array(
								'purchase_code'   => $single_item['code'],
								'product_name'    => $single_item['item']['name'],
								'product_id'      => $single_item['item']['id'],
								'buyer'           => $username,
								'license'         => $single_item['license'],
								'supported_until' => $single_item['supported_until'],
							) );
						}
					}
				}
			}
		}

		return $purchases;
	}

	/**
	 * Returns full user account information.
	 *
	 * @param array $custom_args User identification arguments.
	 * @return array
	 */
	public function get_user_info( $custom_args ) {
		$default_args = array(
			'user_id'      => 0,
			'username'     => '',
			'refresh_code' => '',
		);

		$args         = array_merge( $default_args, $custom_args );
		$username     = $args['username'];
		$refresh_code = $args['refresh_code'];

		if ( ! empty( $username ) && ! empty( $refresh_code ) ) {
			$profile = $this->load_envato_user_data( $refresh_code, $this->api_request_urls( 'account_details' ) );
			$email   = $this->load_envato_user_data( $refresh_code, $this->api_request_urls( 'email' ) );

			return array_merge( $profile, $email );
		}

		return array();
	}

	/**
	 * Calculates support data based on a list of purchases.
	 *
	 * @param array $purchases List of purchases.
	 * @return array           Data for the purchase with the latest support date.
	 */
	public function get_support_data( $purchases ) {
		$last_purchase = array();
		foreach ( $purchases as $purchase ) {
			if ( empty( $last_purchase ) ) {
				$last_purchase = $purchase;
			} else {
				if ( new DateTime( $purchase['supported_until'] ) > new DateTime( $last_purchase['supported_until'] ) ) {
					$last_purchase = $purchase;
				}
			}
		}

		return $last_purchase;
	}

	/**
	 * Verifies a purchase code via the Envato API.
	 * Stability Patch: Implements strict null checks to prevent database fatal errors.
	 *
	 * @param string  $purchase_code The code to verify.
	 * @param boolean $force_refresh Whether to skip the local cache.
	 * @return array|false           The purchase record or false on failure.
	 * @throws Exception
	 */
	public function verify_purchase( $purchase_code = '', $force_refresh = false ) {
		if ( empty( $purchase_code ) ) {
			return false;
		}

		global $db;

		if ( ! $force_refresh ) {
			$db->connection( 'default' )->setPrefix();
			$db->connection( 'default' )->where( 'purchase_code', $purchase_code );
			$response = $db->connection( 'default' )->getOne( 'envato_purchase_codes' );
		} else {
			$response = false;
		}

		if ( $response ) {
			return $response;
		} else {
			// API Call to Envato.
			$api_response = $this->curl_token( $this->api_request_urls( 'author_sale' ) . '?code=' . $purchase_code );

			// Check for 404 or other API error objects.
			if ( ! $api_response || ( isset( $api_response->error ) && '404' == $api_response->error ) ) {
				return false;
			}

			/**
			 * Safety Validation: Ensure the item object and required properties exist.
			 * This prevents "Attempt to read property on null" warnings and database fatal errors.
			 */
			if ( ! isset( $api_response->item ) || ! isset( $api_response->item->name ) || ! isset( $api_response->item->id ) ) {
				return false;
			}

			$formatted_response = array(
				'purchase_code'   => $purchase_code,
				'product_name'    => (string) $api_response->item->name,
				'product_id'      => (int) $api_response->item->id,
				'buyer'           => isset( $api_response->buyer ) ? (string) $api_response->buyer : 'Unknown',
				'license'         => isset( $api_response->license ) ? (string) $api_response->license : 'Standard',
				'supported_until' => isset( $api_response->supported_until ) ? (string) $api_response->supported_until : null,
			);

			// Check if purchase code is already in database to decide between update or insert.
			$db->connection( 'default' )->setPrefix();
			$db->connection( 'default' )->where( 'purchase_code', $purchase_code );
			$purchase_code_data = $db->connection( 'default' )->getOne( 'envato_purchase_codes' );

			if ( $purchase_code_data ) {
				$db->connection( 'default' )->where( 'id', $purchase_code_data['id'] );
				$db->connection( 'default' )->update( 'envato_purchase_codes', $formatted_response );
			} else {
				/**
				 * Log to envato_purchase_codes.
				 * We only perform the insert if product_name is valid to satisfy NOT NULL constraints.
				 */
				if ( ! empty( $formatted_response['product_name'] ) ) {
					$db->connection( 'default' )->insert( 'envato_purchase_codes', $formatted_response );
				}
			}

			return $formatted_response;
		}
	}

	/**
	 * Checks if a string is serialized.
	 *
	 * @param string $data   Value to check.
	 * @param bool   $strict Optional. Strict check. Default true.
	 * @return bool
	 */
	public function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}

		$data = trim( $data );
		if ( 'N;' == $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}

		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}

		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( false === strpos( $data, '"' ) ) {
					return false;
				}
				// fall through
			case 'a':
			case 'O':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
		}

		return false;
	}

	/**
	 * Check whether serialized data is of string type.
	 *
	 * @param string $data Serialized data.
	 * @return bool
	 */
	public function is_serialized_string( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( strlen( $data ) < 4 ) {
			return false;
		} elseif ( ':' !== $data[1] ) {
			return false;
		} elseif ( ';' !== substr( $data, -1 ) ) {
			return false;
		} elseif ( 's' !== $data[0] ) {
			return false;
		} elseif ( '"' !== substr( $data, -2, 1 ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Unserialize value only if it was serialized.
	 *
	 * @param string $original Original data.
	 * @return mixed
	 */
	public function maybe_unserialize( $original ) {
		if ( $this->is_serialized( $original ) ) {
			return @unserialize( $original );
		}
		return $original;
	}

	/**
	 * Serialize data, if needed.
	 *
	 * @param mixed $data Data to potentially serialize.
	 * @return mixed
	 */
	public function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}
		if ( $this->is_serialized( $data, false ) ) {
			return serialize( $data );
		}
		return $data;
	}

	/**
	 * Testing helper to display data in a readable format.
	 *
	 * @param mixed $data The data to print.
	 * @return void
	 */
	public function prettyPrint( $data ) {
		echo '<pre>';
		print_r( $data );
		echo '</pre>';
	}
}