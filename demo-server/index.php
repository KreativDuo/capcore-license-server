<?php

// Database Connection
require __DIR__ . '/../inc/config.inc.php';

// License Class
require __DIR__ . '/../inc/capable-license.php';

// Update Server
require __DIR__ . '/capable-demo-server.php';

// Start License Class
$license = new Capable_License();

// Start Server
$server = new Capable_Demo_Server();