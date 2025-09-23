<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use TaskQueue\QueueManager;
use TaskQueue\Support\Encryption;
use TaskQueue\Public\Actions\Purge;
use TaskQueue\Public\Actions\Retry;
use TaskQueue\Public\Actions\Stats;
use TaskQueue\Public\Actions\Failed;
use TaskQueue\Public\Actions\Queues;
use TaskQueue\Public\Actions\Recent;
use TaskQueue\Public\ActionsRegistry;
use TaskQueue\Public\Actions\JobDetails;
use TaskQueue\Public\Actions\Performance;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Public\Actions\CreateTestJobs;

// Setup database connection
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/queue.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup encryption
$encryption = new Encryption('demo-encryption-key-32-characters');

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, new Logger('api'));
$manager->connect();

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Build registry and register handlers
$registry = new ActionsRegistry(new Logger('api-handlers'));
$registry->register('stats', new Stats($manager));
$registry->register('failed', new Failed($manager));
$registry->register('recent', new Recent($manager));
$registry->register('retry', new Retry($manager));
$registry->register('purge', new Purge($manager));
$registry->register('create_test_jobs', new CreateTestJobs($manager));
$registry->register('job_details', new JobDetails($manager));
$registry->register('queues', new Queues($manager));
$registry->register('performance', new Performance($manager));

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === '' || !$registry->has($action)) {
        throw new Exception('Invalid action');
    }

    $handler = $registry->get($action);
    $result = $handler->handle($_GET, $_POST);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$manager->disconnect();
