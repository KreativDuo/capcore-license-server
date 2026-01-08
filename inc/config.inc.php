<?php

global $db;

/** Database Wrapper Class */
require_once('mysqli.php');

/** Maintenance Mode */
const MAINTENANCE_MODE = false;

/** MySQL hostname */
const LS_DB_HOST = '';

/** MySQL database name*/
const LS_DB_NAME = '';

/** MySQL database username */
const LS_DB_USER = '';

/** MySQL database password */
const LS_DB_PASSWORD = '';

$db = new MysqliDb( array(
    'host'      => LS_DB_HOST,
    'username'  => LS_DB_USER,
    'password'  => LS_DB_PASSWORD,
    'db'        => LS_DB_NAME )
);

/** Envato Username */
const EN_USER = '';

/** Envato Token */
const EN_TOKEN = '';

/** Envato Client */
const EN_CLIENT = '';

/** Envato Secret */
const EN_SECRET = '';

/** License Server Base URL */
const BASE_URL = 'https://license.capable-themes.com/';
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/' );

/** Amazon S3 **/
const S3_Bucket = '';

/** Valid Messages **/
const VALID = 'Purchase Code valid.';
const VALID_SERVER = 'Purchase Code valid and registered with server.';
const VALID_NO_SERVER = 'License valid but not registered with server.';

/** Invalid Messages **/
const INVALID = 'Purchase Code is not valid.';
const INVALID_WRONG_SERVER = 'License already registered with different server.';
const PURCHASE_CODE_EMPTY = 'No Purchase Code entered.';
const FILE_NOT_FOUND = 'File not found!';

/**
 * Helper Function
 *
 * @param string $new_ip
 * @param string $old_ip
 *
 * @return boolean
 *
 */

function check_ip_range( $new_ip, $old_ip ) {

    $min = $max = $split = explode( ".", $new_ip );

    $min[3] = max(($split[3] - 20), 0);
    $max[3] = min(($split[3] + 20), 255);

    $min = implode( '.', $min );
    $max = implode( '.', $max );

    return (ip2long($min) <= ip2long($old_ip) && ip2long($old_ip) <= ip2long($max));

}

function license_black_list( $license ) {

    $black_list = array(
        '',
    );

    return in_array( $license, $black_list );

}

function license_white_list( $license ) {

    $white_list = array(
    );

    return in_array( $license, $white_list );

}

function valid_product_ids( $id ) {

    $ids = array(
    );

    return in_array( $id, $ids );

}

function plugin_white_list( $license ) {

    $white_list = array(
        'capcore-extensions'
    );

    return in_array( $license, $white_list );

}
