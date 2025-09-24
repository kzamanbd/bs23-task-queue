<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Support\Encryption;
use TaskQueue\Support\Database;

// Setup database connection
$pdo = Database::createSqlitePdo(__DIR__ . '/../storage/queue.db');

// Setup encryption
$encryption = new Encryption('demo-encryption-key-32-characters');

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, new \Monolog\Logger('dashboard'));
$manager->connect();

// Redirect API requests to api.php
if (isset($_GET['action']) || isset($_POST['action'])) {
    include 'api.php';
    exit;
}


// return json response
echo json_encode([
    'message' => 'Dashboard moved to React UI'
]);
