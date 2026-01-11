<?php
/**
 * Capable License Utility Class
 *
 * This class is utilized by the update and demo servers to verify if a
 * remote request originates from an authorized domain with a valid IP.
 *
 * @package   CapCore_License_Server
 * @author    Capable Themes
 */

class Capable_License {

	/** @var string The table used for verification. */
	protected $verification_table = 'verification';

	/** @var string|bool The purchase code being checked. */
	public $purchase_code = false;

	/** @var string|bool The domain license being checked. */
	public $domain_license = false;

	/** @var boolean Final validation state. */
	public $license_valid = false;

	/** @var string Information regarding the license state. */
	public $license_info = 'Your license is not valid.';

	/**
	 * Constructor.
	 */
	public function __construct() { }

	/**
	 * Sets the required data for validation.
	 *
	 * @param string $purchase_code
	 * @param string $domain
	 */
	public function set_purchase_code($purchase_code, $domain) {
		$this->purchase_code  = $purchase_code;
		$this->domain_license = $domain;
	}

	/**
	 * Retrieves the current purchase code.
	 *
	 * @return string|bool
	 */
	public function get_purchase_code() {
		return $this->purchase_code;
	}

	/**
	 * Validates the license status against the database and incoming IP.
	 * Uses the modernized check_ip_range function for IPv4/IPv6 support.
	 *
	 * @return void
	 */
	public function set_license_status() {
		global $db;

		try {
			// Query only active records for this purchase code and domain
			$db->where('purchase_code', $db->escape($this->purchase_code));
			$db->where('domain', $db->escape($this->domain_license));
			$db->where('revoke_domain', 0);

			$verification = $db->get($this->verification_table);

			if ($verification) {
				$match = false;
				foreach ($verification as $row) {
					// Use the modernized IP check from config.inc.php
					if (check_ip_range($_SERVER['REMOTE_ADDR'], $row['server_real_ip'])) {
						$this->license_valid = true;
						$this->license_info  = VALID_SERVER;
						$match = true;
						break;
					}
				}

				if (!$match) {
					$this->license_valid = false;
					$this->license_info  = INVALID_WRONG_SERVER;
				}
			} else {
				$this->license_valid = false;
				$this->license_info  = VALID_NO_SERVER;
			}

		} catch (Exception $e) {
			// Log error if database interaction fails
			error_log("License System Error: " . $e->getMessage());
		}
	}
}