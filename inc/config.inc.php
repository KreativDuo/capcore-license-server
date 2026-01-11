<?php
/**
 * CapCore License Server - Central Configuration
 *
 * This file serves as the core configuration hub for the license server.
 * It manages database connections, API credentials for Envato, and
 * provides utility functions for security-related checks such as IP
 * validation and product whitelisting.
 *
 * @package   CapCore_License_Server
 * @author    Capable Themes
 * @version   1.1.0
 * @link      https://capable-themes.com
 */

// Global database instance.
global $db;

/** * Database Wrapper Class
 * @link http://github.com/joshcam/PHP-MySQLi-Database-Class
 */
require_once('mysqli.php');

/** * Debug Mode - Set to true to enable detailed error logging */
const LS_DEBUG_MODE = true;

if ( LS_DEBUG_MODE ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', 0 ); // Errors into logs, not to client
	ini_set( 'log_errors', 1 );
	ini_set( 'error_log', __DIR__ . '/../debug.log' );
}

/** * Maintenance Mode
 * If set to true, the server should block or return maintenance notices for all requests.
 */
const MAINTENANCE_MODE = false;

/** * MySQL Database Settings
 */
const LS_DB_HOST     = ''; // e.g., 'localhost'
const LS_DB_NAME     = ''; // Your database name
const LS_DB_USER     = ''; // Your database username
const LS_DB_PASSWORD = ''; // Your database password

/** * Initialize the global Database instance using the MysqliDb wrapper.
 */
$db = new MysqliDb(array(
    'host'     => LS_DB_HOST,
    'username' => LS_DB_USER,
    'password' => LS_DB_PASSWORD,
    'db'       => LS_DB_NAME
));

/** * Envato API Settings
 * Used for verifying purchase codes against the Envato Market API.
 */
const EN_USER   = ''; // Your Envato username
const EN_TOKEN  = ''; // Your personal API token
const EN_CLIENT = ''; // Optional: API Client ID
const EN_SECRET = ''; // Optional: API Client Secret

/** * License Server Path Configuration
 */
const BASE_URL = 'https://license.capable-themes.com/';
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');

/** * Success and Information Messages
 */
const VALID           = 'Purchase Code valid.';
const VALID_SERVER    = 'Purchase Code valid and registered with server.';
const VALID_NO_SERVER = 'License valid but not registered with server.';

/** * Error and Denial Messages
 */
const INVALID              = 'Purchase Code is not valid.';
const INVALID_WRONG_SERVER = 'License already registered with different server.';
const PURCHASE_CODE_EMPTY  = 'No Purchase Code entered.';
const FILE_NOT_FOUND       = 'File not found!';

/**
 * Validates if an IP address is within an acceptable range or matches exactly.
 * * This function is protocol-agnostic and supports both IPv4 and IPv6.
 * For IPv6, it checks the /64 subnet prefix, as many hostings rotate
 * addresses within this block. For IPv4, it allows a small deviation
 * of +/- 20 addresses to handle dynamic IPs within the same data center.
 *
 * @param string $new_ip The IP address from the current incoming request.
 * @param string $old_ip The IP address stored in the database for this domain.
 * @return boolean       True if the IP is valid or within range, false otherwise.
 */
function check_ip_range($new_ip, $old_ip) {

    // Immediate match
    if ($new_ip === $old_ip) {
        return true;
    }

    // Convert both strings to binary representation
    $new_packed = @inet_pton($new_ip);
    $old_packed = @inet_pton($old_ip);

    // If either IP is invalid, return false
    if (false === $new_packed || false === $old_packed) {
        return false;
    }

    // --- IPv6 Logic ---
    // If length is 16 bytes, it is an IPv6 address.
    if (strlen($new_packed) === 16) {
        // Compare the first 8 bytes (the /64 prefix)
        return substr($new_packed, 0, 8) === substr($old_packed, 0, 8);
    }

    // --- IPv4 Logic ---
    // Convert to long integer for range calculation
    $new_long = ip2long($new_ip);
    $old_long = ip2long($old_ip);

    if (!$new_long || !$old_long) {
        return false;
    }

    // Allow a range of +/- 20 addresses
    return (abs($new_long - $old_long) <= 20);
}

/**
 * Checks if a specific license is blacklisted.
 *
 * @param string $license The purchase code to check.
 * @return boolean        True if blacklisted, false otherwise.
 */
function license_black_list($license) {

    $black_list = array(
        'PLACEHOLDER-KEY-1',
        'PLACEHOLDER-KEY-2',
    );

    return in_array($license, $black_list);
}

/**
 * Checks if a specific license is on the bypass/white list.
 *
 * @param string $license The purchase code to check.
 * @return boolean        True if whitelisted, false otherwise.
 */
function license_white_list($license) {

    $white_list = array(
        // Add manual override keys here
    );

    return in_array($license, $white_list);
}

/**
 * Verifies if a product ID is officially supported by this server.
 *
 * @param integer|string $id The Envato item ID.
 * @return boolean           True if valid, false otherwise.
 */
function valid_product_ids($id) {

    $ids = array(
        // Add your Envato Item IDs here
    );

    return in_array($id, $ids);
}

/**
 * Defines which plugins do not require a license check for metadata or downloads.
 *
 * @param string $slug The plugin slug (e.g., 'capcore-extensions').
 * @return boolean     True if it is a free/open core plugin, false otherwise.
 */
function plugin_white_list($slug) {

    $white_list = array(
        'capcore-extensions',
    );

    return in_array($slug, $white_list);
}