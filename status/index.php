<?php

// Database Connection
require __DIR__ . '/../inc/config.inc.php';

if( MAINTENANCE_MODE ) {

    header('Content-Type: application/json');
    echo json_encode( array(
        'server_status' => 'maintenance'
    ) );

    die(1);

}

if( isset( $_GET['server'] ) ) {

    header('Content-Type: application/json');
    echo json_encode( array(
        'server_status' => 'running'
    ) );

    die(1);
}