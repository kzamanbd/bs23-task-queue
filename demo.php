<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Support\Encryption;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "🚀 Task Queue System Demo\n";
echo "========================\n\n";

// Setup database connection
$pdo = new PDO('sqlite:' . __DIR__ . '/storage/demo.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup encryption
$encryption = new Encryption('demo-encryption-key-32-characters');

// Setup logger
$logger = new Logger('demo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, $logger);
$manager->connect();

echo "📊 Creating test jobs...\n";

// Create various types of jobs
$jobs = [
    new TestJob(['task' => 'send_email', 'recipient' => 'user@example.com'], [
        'queue' => 'high-priority',
        'priority' => TestJob::PRIORITY_HIGH,
        'tags' => ['email', 'notification']
    ]),
    new TestJob(['task' => 'process_payment', 'amount' => 99.99], [
        'queue' => 'default',
        'priority' => TestJob::PRIORITY_URGENT,
        'tags' => ['payment', 'financial']
    ]),
    new TestJob(['task' => 'generate_report', 'type' => 'monthly'], [
        'queue' => 'low-priority',
        'priority' => TestJob::PRIORITY_LOW,
        'delay' => 30, // 30 second delay
        'tags' => ['report', 'analytics']
    ]),
    new TestJob(['task' => 'backup_database', 'should_fail' => true], [
        'queue' => 'default',
        'priority' => TestJob::PRIORITY_NORMAL,
        'tags' => ['backup', 'maintenance']
    ]),
    new TestJob(['task' => 'cleanup_files', 'path' => '/tmp'], [
        'queue' => 'low-priority',
        'priority' => TestJob::PRIORITY_LOW,
        'tags' => ['cleanup', 'maintenance']
    ]),
];

foreach ($jobs as $job) {
    $manager->push($job);
    echo "  ✓ Created job: {$job->getId()} in queue '{$job->getQueue()}' (priority: {$job->getPriority()})\n";
}

echo "\n📈 Queue Statistics:\n";
$stats = $manager->getQueueStats();
foreach ($stats as $queueName => $queueStats) {
    echo "  Queue '{$queueName}':\n";
    echo "    Total Jobs: {$queueStats['total_jobs']}\n";
    echo "    Pending: " . ($queueStats['by_state']['pending'] ?? 0) . "\n";
    echo "    Processing: " . ($queueStats['by_state']['processing'] ?? 0) . "\n";
    echo "    Completed: " . ($queueStats['by_state']['completed'] ?? 0) . "\n";
    echo "    Failed: " . ($queueStats['by_state']['failed'] ?? 0) . "\n";
    echo "    Average Priority: {$queueStats['avg_priority']}\n\n";
}

echo "🔍 Job Details:\n";
foreach ($jobs as $job) {
    $retrieved = $manager->getJobById($job->getId());
    if ($retrieved) {
        echo "  Job {$retrieved->getId()}:\n";
        echo "    Queue: {$retrieved->getQueue()}\n";
        echo "    Priority: {$retrieved->getPriority()}\n";
        echo "    State: {$retrieved->getState()}\n";
        echo "    Delay: {$retrieved->getDelay()}s\n";
        echo "    Tags: " . implode(', ', $retrieved->getTags()) . "\n";
        echo "    Payload: " . json_encode($retrieved->getPayload()) . "\n\n";
    }
}

echo "⚡ Starting worker simulation (processing 3 jobs)...\n";

// Simulate worker processing
$processedCount = 0;
$maxProcess = 3;

while ($processedCount < $maxProcess) {
    $job = $manager->pop('default');
    if ($job) {
        echo "  🔄 Processing job: {$job->getId()}\n";

        try {
            $job->handle();
            $job->setState(TestJob::STATE_COMPLETED);
            $job->setCompletedAt(new \DateTimeImmutable());
            $manager->getDriver()->delete($job);
            echo "    ✅ Job completed successfully\n";
        } catch (\Throwable $e) {
            $job->setException($e);
            if ($job->canRetry()) {
                $job->setState(TestJob::STATE_RETRYING);
                echo "    ⚠️  Job failed, will retry: " . $e->getMessage() . "\n";
            } else {
                $job->setState(TestJob::STATE_FAILED);
                $job->setFailedAt(new \DateTimeImmutable());
                echo "    ❌ Job failed permanently: " . $e->getMessage() . "\n";
            }
        }

        $processedCount++;
    } else {
        echo "  📭 No jobs available in default queue\n";
        break;
    }
}

echo "\n📊 Final Statistics:\n";
$finalStats = $manager->getQueueStats();
foreach ($finalStats as $queueName => $queueStats) {
    echo "  Queue '{$queueName}':\n";
    echo "    Total Jobs: {$queueStats['total_jobs']}\n";
    echo "    Pending: " . ($queueStats['by_state']['pending'] ?? 0) . "\n";
    echo "    Processing: " . ($queueStats['by_state']['processing'] ?? 0) . "\n";
    echo "    Completed: " . ($queueStats['by_state']['completed'] ?? 0) . "\n";
    echo "    Failed: " . ($queueStats['by_state']['failed'] ?? 0) . "\n";
    echo "    Retrying: " . ($queueStats['by_state']['retrying'] ?? 0) . "\n\n";
}

$failedJobs = $manager->getFailedJobs();
if (!empty($failedJobs)) {
    echo "❌ Failed Jobs:\n";
    foreach ($failedJobs as $job) {
        echo "  - {$job->getId()}: " . ($job->getException() ? $job->getException()->getMessage() : 'Unknown error') . "\n";
    }
}

echo "\n🎉 Demo completed! Check the database file: " . __DIR__ . "/storage/demo.db\n";
echo "💡 Try running: php worker queue:work --workers=2 --timeout=30\n";

$manager->disconnect();
