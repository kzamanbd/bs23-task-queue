<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Support\Encryption;

// Setup database connection
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/queue.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup encryption
$encryption = new Encryption('demo-encryption-key-32-characters');

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, new \Monolog\Logger('api'));
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'stats':
            echo json_encode($manager->getQueueStats());
            break;
            
        case 'failed':
            $queue = $_GET['queue'] ?? null;
            $failedJobs = $manager->getFailedJobs($queue);
            
            $result = [];
            foreach ($failedJobs as $job) {
                $result[] = [
                    'id' => $job->getId(),
                    'queue' => $job->getQueue(),
                    'priority' => $job->getPriority(),
                    'attempts' => $job->getAttempts(),
                    'max_attempts' => $job->getMaxAttempts(),
                    'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
                    'failed_at' => $job->getFailedAt() ? $job->getFailedAt()->format('Y-m-d H:i:s') : null,
                    'exception' => $job->getException() ? $job->getException()->getMessage() : null,
                    'payload' => $job->getPayload(),
                    'tags' => $job->getTags(),
                ];
            }
            
            echo json_encode($result);
            break;
            
        case 'recent':
            $limit = (int) ($_GET['limit'] ?? 50);
            $queue = $_GET['queue'] ?? null;
            $state = $_GET['state'] ?? null;
            
            $recentJobs = [];
            
            if ($state) {
                $jobs = $manager->getJobsByState($state, $queue, $limit);
            } else {
                // Get recent jobs from all states
                $states = ['pending', 'processing', 'completed', 'failed'];
                foreach ($states as $s) {
                    $jobs = $manager->getJobsByState($s, $queue, $limit);
                    foreach ($jobs as $job) {
                        $recentJobs[] = [
                            'id' => $job->getId(),
                            'state' => $job->getState(),
                            'queue' => $job->getQueue(),
                            'priority' => $job->getPriority(),
                            'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
                            'updated_at' => $job->getUpdatedAt()->format('Y-m-d H:i:s'),
                            'completed_at' => $job->getCompletedAt() ? $job->getCompletedAt()->format('Y-m-d H:i:s') : null,
                            'failed_at' => $job->getFailedAt() ? $job->getFailedAt()->format('Y-m-d H:i:s') : null,
                            'attempts' => $job->getAttempts(),
                            'max_attempts' => $job->getMaxAttempts(),
                            'tags' => $job->getTags(),
                            'payload' => $job->getPayload(),
                        ];
                    }
                }
            }
            
            // Sort by updated_at descending
            usort($recentJobs, function($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });
            
            echo json_encode(array_slice($recentJobs, 0, $limit));
            break;
            
        case 'retry':
            $jobId = $_POST['job_id'] ?? '';
            if (empty($jobId)) {
                throw new Exception('Job ID is required');
            }
            
            $result = $manager->retryFailedJob($jobId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'purge':
            $queue = $_POST['queue'] ?? '';
            if (empty($queue)) {
                throw new Exception('Queue name is required');
            }
            
            $manager->purgeQueue($queue);
            echo json_encode(['success' => true]);
            break;
            
        case 'create_test_jobs':
            $count = (int) ($_POST['count'] ?? 10);
            $queue = $_POST['queue'] ?? 'default';
            $priority = (int) ($_POST['priority'] ?? 5);
            
            $createdJobs = [];
            for ($i = 0; $i < $count; $i++) {
                $job = new TestJob([
                    'test_data' => "Test job #{$i}",
                    'timestamp' => time(),
                    'random_data' => bin2hex(random_bytes(16))
                ], [
                    'queue' => $queue,
                    'priority' => $priority,
                    'tags' => ['test', 'dashboard', 'auto-generated']
                ]);
                
                $manager->push($job);
                $createdJobs[] = $job->getId();
            }
            
            echo json_encode([
                'success' => true,
                'created_jobs' => $createdJobs,
                'count' => $count
            ]);
            break;
            
        case 'job_details':
            $jobId = $_GET['job_id'] ?? '';
            if (empty($jobId)) {
                throw new Exception('Job ID is required');
            }
            
            $job = $manager->getJobById($jobId);
            if (!$job) {
                throw new Exception('Job not found');
            }
            
            $result = [
                'id' => $job->getId(),
                'state' => $job->getState(),
                'queue' => $job->getQueue(),
                'priority' => $job->getPriority(),
                'attempts' => $job->getAttempts(),
                'max_attempts' => $job->getMaxAttempts(),
                'timeout' => $job->getTimeout(),
                'delay' => $job->getDelay(),
                'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $job->getUpdatedAt()->format('Y-m-d H:i:s'),
                'completed_at' => $job->getCompletedAt() ? $job->getCompletedAt()->format('Y-m-d H:i:s') : null,
                'failed_at' => $job->getFailedAt() ? $job->getFailedAt()->format('Y-m-d H:i:s') : null,
                'exception' => $job->getException() ? [
                    'message' => $job->getException()->getMessage(),
                    'trace' => $job->getException()->getTraceAsString()
                ] : null,
                'payload' => $job->getPayload(),
                'dependencies' => $job->getDependencies(),
                'tags' => $job->getTags(),
            ];
            
            echo json_encode($result);
            break;
            
        case 'queues':
            $stats = $manager->getQueueStats();
            $queues = [];
            
            foreach ($stats as $queueName => $queueStats) {
                $queues[] = [
                    'name' => $queueName,
                    'total_jobs' => $queueStats['total_jobs'],
                    'by_state' => $queueStats['by_state'],
                    'avg_priority' => $queueStats['avg_priority'],
                    'oldest_job' => $queueStats['oldest_job'],
                    'newest_job' => $queueStats['newest_job'],
                ];
            }
            
            echo json_encode($queues);
            break;
            
        case 'performance':
            // Get performance metrics
            $stats = $manager->getQueueStats();
            $totalJobs = 0;
            $totalPending = 0;
            $totalProcessing = 0;
            $totalCompleted = 0;
            $totalFailed = 0;
            
            foreach ($stats as $queueStats) {
                $totalJobs += $queueStats['total_jobs'];
                $totalPending += $queueStats['by_state']['pending'] ?? 0;
                $totalProcessing += $queueStats['by_state']['processing'] ?? 0;
                $totalCompleted += $queueStats['by_state']['completed'] ?? 0;
                $totalFailed += $queueStats['by_state']['failed'] ?? 0;
            }
            
            $performance = [
                'timestamp' => date('Y-m-d H:i:s'),
                'total_jobs' => $totalJobs,
                'pending' => $totalPending,
                'processing' => $totalProcessing,
                'completed' => $totalCompleted,
                'failed' => $totalFailed,
                'success_rate' => $totalJobs > 0 ? round(($totalCompleted / $totalJobs) * 100, 2) : 0,
                'failure_rate' => $totalJobs > 0 ? round(($totalFailed / $totalJobs) * 100, 2) : 0,
            ];
            
            echo json_encode($performance);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

$manager->disconnect();
