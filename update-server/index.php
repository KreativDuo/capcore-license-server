<?php

// Database Connection
require __DIR__ . '/../inc/config.inc.php';

// License Class
require __DIR__ . '/../inc/capable-license.php';

// WP Update Server
require __DIR__ . '/loader.php';
require __DIR__ . '/capable-update-server.php';

// Start License Class
$license = new Capable_License();

// Start Server
$server = new Capable_UpdateServer();
$server->handleRequest();