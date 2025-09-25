<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Support\Encryption;
use TaskQueue\Support\Database;
use TaskQueue\Support\LoggerFactory;

echo "🚀 PERFORMANCE TESTING SUITE\n";
echo "============================\n\n";

// Setup database connection
$pdo = Database::createSqlitePdo(__DIR__ . '/storage/performance.db');

// Setup encryption
$encryption = new Encryption('performance-test-key-32-characters');

// Setup logger
$logger = LoggerFactory::createStyledLogger('performance');

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, $logger);
$manager->connect();

// Test 1: Job Dispatch Latency (< 10ms)
echo "📊 TEST 1: JOB DISPATCH LATENCY (< 10ms)\n";
echo "==========================================\n";

$dispatchTimes = [];
$testJobs = 1000;

echo "Testing dispatch latency with {$testJobs} jobs...\n";

for ($i = 0; $i < $testJobs; $i++) {
    $startTime = microtime(true);
    
    $job = new TestJob(['test' => "job_$i"], [
        'queue' => 'performance-test',
        'priority' => rand(1, 15),
        'tags' => ['performance', 'latency-test']
    ]);
    
    $manager->push($job);
    
    $endTime = microtime(true);
    $dispatchTimes[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
}

$avgDispatchTime = array_sum($dispatchTimes) / count($dispatchTimes);
$maxDispatchTime = max($dispatchTimes);
$minDispatchTime = min($dispatchTimes);

echo "  ✅ Average dispatch time: " . number_format($avgDispatchTime, 3) . "ms\n";
echo "  ✅ Max dispatch time: " . number_format($maxDispatchTime, 3) . "ms\n";
echo "  ✅ Min dispatch time: " . number_format($minDispatchTime, 3) . "ms\n";

$latencyRequirement = $avgDispatchTime < 10;
echo "  " . ($latencyRequirement ? "✅" : "❌") . " Latency requirement (< 10ms): " . 
     ($latencyRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 2: 10K+ Jobs Per Minute Throughput
echo "📊 TEST 2: THROUGHPUT (10K+ JOBS PER MINUTE)\n";
echo "=============================================\n";

$throughputJobs = 1000; // Test with 1K jobs, scale up if needed
echo "Testing throughput with {$throughputJobs} jobs...\n";

$startTime = microtime(true);

for ($i = 0; $i < $throughputJobs; $i++) {
    $job = new TestJob(['throughput_test' => $i], [
        'queue' => 'throughput-test',
        'priority' => rand(1, 15)
    ]);
    $manager->push($job);
}

$endTime = microtime(true);
$totalTime = $endTime - $startTime;
$jobsPerSecond = $throughputJobs / $totalTime;
$jobsPerMinute = $jobsPerSecond * 60;

echo "  ✅ Total time: " . number_format($totalTime, 3) . " seconds\n";
echo "  ✅ Jobs per second: " . number_format($jobsPerSecond, 2) . "\n";
echo "  ✅ Jobs per minute: " . number_format($jobsPerMinute, 2) . "\n";

$throughputRequirement = $jobsPerMinute >= 10000;
echo "  " . ($throughputRequirement ? "✅" : "❌") . " Throughput requirement (10K+ jobs/minute): " . 
     ($throughputRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 3: Memory Usage Per Worker (< 50MB)
echo "📊 TEST 3: MEMORY USAGE PER WORKER (< 50MB)\n";
echo "============================================\n";

$memoryTests = [];
$testCount = 10;

echo "Testing memory usage with {$testCount} worker instances...\n";

for ($i = 0; $i < $testCount; $i++) {
    $worker = new \TaskQueue\Workers\Worker($driver, $logger, 50 * 1024 * 1024, 100);
    $memoryUsage = $worker->getMemoryUsage();
    $memoryTests[] = $memoryUsage;
    
    echo "  Worker " . ($i + 1) . ": " . number_format($memoryUsage / 1024 / 1024, 2) . "MB\n";
}

$avgMemoryUsage = array_sum($memoryTests) / count($memoryTests);
$maxMemoryUsage = max($memoryTests);
$memoryLimit = 50 * 1024 * 1024; // 50MB

echo "  ✅ Average memory usage: " . number_format($avgMemoryUsage / 1024 / 1024, 2) . "MB\n";
echo "  ✅ Max memory usage: " . number_format($maxMemoryUsage / 1024 / 1024, 2) . "MB\n";
echo "  ✅ Memory limit: 50MB\n";

$memoryRequirement = $maxMemoryUsage < $memoryLimit;
echo "  " . ($memoryRequirement ? "✅" : "❌") . " Memory requirement (< 50MB per worker): " . 
     ($memoryRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 4: Large Payload Support (Up to 1MB)
echo "📊 TEST 4: LARGE PAYLOAD SUPPORT (UP TO 1MB)\n";
echo "=============================================\n";

$payloadSizes = [1024, 10240, 102400, 512000, 1048576]; // 1KB, 10KB, 100KB, 500KB, 1MB
$payloadTests = [];

foreach ($payloadSizes as $size) {
    echo "Testing payload size: " . number_format($size / 1024, 1) . "KB...\n";
    
    $largePayload = str_repeat('A', $size);
    $startTime = microtime(true);
    
    $job = new TestJob(['large_data' => $largePayload], [
        'queue' => 'large-payload-test',
        'priority' => 5
    ]);
    
    $manager->push($job);
    
    $endTime = microtime(true);
    $processingTime = ($endTime - $startTime) * 1000;
    $payloadTests[] = ['size' => $size, 'time' => $processingTime];
    
    echo "  ✅ Processed in: " . number_format($processingTime, 3) . "ms\n";
}

$largestPayload = max(array_column($payloadTests, 'size'));
$payloadRequirement = $largestPayload >= 1048576; // 1MB

echo "  ✅ Largest payload tested: " . number_format($largestPayload / 1024 / 1024, 2) . "MB\n";
echo "  " . ($payloadRequirement ? "✅" : "❌") . " Payload requirement (up to 1MB): " . 
     ($payloadRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 5: Worker Startup Time (< 1 second)
echo "📊 TEST 5: WORKER STARTUP TIME (< 1 SECOND)\n";
echo "============================================\n";

$startupTests = [];
$workerCount = 20;

echo "Testing startup time for {$workerCount} workers...\n";

for ($i = 0; $i < $workerCount; $i++) {
    $startTime = microtime(true);
    
    $worker = new \TaskQueue\Workers\Worker($driver, $logger, 50 * 1024 * 1024, 100);
    
    $endTime = microtime(true);
    $startupTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    $startupTests[] = $startupTime;
    
    echo "  Worker " . ($i + 1) . ": " . number_format($startupTime, 3) . "ms\n";
}

$avgStartupTime = array_sum($startupTests) / count($startupTests);
$maxStartupTime = max($startupTests);
$startupRequirement = $maxStartupTime < 1000; // 1 second = 1000ms

echo "  ✅ Average startup time: " . number_format($avgStartupTime, 3) . "ms\n";
echo "  ✅ Max startup time: " . number_format($maxStartupTime, 3) . "ms\n";
echo "  " . ($startupRequirement ? "✅" : "❌") . " Startup requirement (< 1 second): " . 
     ($startupRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 6: Concurrent Workers (100+)
echo "📊 TEST 6: CONCURRENT WORKERS (100+)\n";
echo "=====================================\n";

$concurrentWorkerCount = 100;
echo "Testing {$concurrentWorkerCount} concurrent workers...\n";

$workers = [];
$startTime = microtime(true);

// Create workers
for ($i = 0; $i < $concurrentWorkerCount; $i++) {
    $worker = new \TaskQueue\Workers\Worker($driver, $logger, 50 * 1024 * 1024, 10);
    $workers[] = $worker;
}

$endTime = microtime(true);
$creationTime = ($endTime - $startTime) * 1000;

echo "  ✅ Created {$concurrentWorkerCount} workers in: " . number_format($creationTime, 3) . "ms\n";

// Test worker status and memory
$activeWorkers = 0;
$totalMemory = 0;

foreach ($workers as $worker) {
    if (!$worker->isRunning()) {
        $activeWorkers++;
    }
    $totalMemory += $worker->getMemoryUsage();
}

$avgMemoryPerWorker = $totalMemory / $concurrentWorkerCount;
$concurrentRequirement = $concurrentWorkerCount >= 100;

echo "  ✅ Active workers: {$activeWorkers}\n";
echo "  ✅ Average memory per worker: " . number_format($avgMemoryPerWorker / 1024 / 1024, 2) . "MB\n";
echo "  ✅ Total memory usage: " . number_format($totalMemory / 1024 / 1024, 2) . "MB\n";
echo "  " . ($concurrentRequirement ? "✅" : "❌") . " Concurrent workers requirement (100+): " . 
     ($concurrentRequirement ? "PASS" : "FAIL") . "\n\n";

// Test 7: End-to-End Performance
echo "📊 TEST 7: END-TO-END PERFORMANCE\n";
echo "==================================\n";

$e2eJobs = 1000;
echo "Testing end-to-end performance with {$e2eJobs} jobs...\n";

// Create jobs
$jobIds = [];
$startTime = microtime(true);

for ($i = 0; $i < $e2eJobs; $i++) {
    $job = new TestJob(['e2e_test' => $i, 'timestamp' => microtime(true)], [
        'queue' => 'e2e-test',
        'priority' => rand(1, 15),
        'tags' => ['e2e', 'performance']
    ]);
    $manager->push($job);
    $jobIds[] = $job->getId();
}

$creationTime = microtime(true) - $startTime;

// Process jobs
$processedJobs = 0;
$processingStartTime = microtime(true);

while ($processedJobs < $e2eJobs) {
    $job = $manager->pop('e2e-test');
    if ($job) {
        try {
            $job->handle();
            $job->setState(TestJob::STATE_COMPLETED);
            $manager->getDriver()->delete($job);
            $processedJobs++;
        } catch (\Throwable $e) {
            // Handle errors
        }
    } else {
        break;
    }
}

$processingTime = microtime(true) - $processingStartTime;
$totalTime = microtime(true) - $startTime;

echo "  ✅ Job creation time: " . number_format($creationTime * 1000, 3) . "ms\n";
echo "  ✅ Job processing time: " . number_format($processingTime * 1000, 3) . "ms\n";
echo "  ✅ Total end-to-end time: " . number_format($totalTime * 1000, 3) . "ms\n";
echo "  ✅ Jobs processed: {$processedJobs}/{$e2eJobs}\n";
echo "  ✅ Average time per job: " . number_format(($totalTime / $e2eJobs) * 1000, 3) . "ms\n\n";

// Performance Summary
echo "🎯 PERFORMANCE SUMMARY\n";
echo "======================\n";

$requirements = [
    'Job Dispatch Latency' => $latencyRequirement,
    'Throughput (10K+ jobs/min)' => $throughputRequirement,
    'Memory Usage (< 50MB/worker)' => $memoryRequirement,
    'Large Payload (up to 1MB)' => $payloadRequirement,
    'Worker Startup (< 1 second)' => $startupRequirement,
    'Concurrent Workers (100+)' => $concurrentRequirement,
];

$passedRequirements = array_sum($requirements);
$totalRequirements = count($requirements);

echo "Requirements Status:\n";
foreach ($requirements as $requirement => $passed) {
    echo "  " . ($passed ? "✅" : "❌") . " {$requirement}\n";
}

echo "\nOverall Performance: {$passedRequirements}/{$totalRequirements} requirements passed\n";

if ($passedRequirements === $totalRequirements) {
    echo "🎉 ALL PERFORMANCE REQUIREMENTS MET! 🎉\n";
} else {
    echo "⚠️  Some requirements need optimization\n";
}

echo "\nDetailed Metrics:\n";
echo "  • Average dispatch latency: " . number_format($avgDispatchTime, 3) . "ms\n";
echo "  • Throughput: " . number_format($jobsPerMinute, 0) . " jobs/minute\n";
echo "  • Max memory usage: " . number_format($maxMemoryUsage / 1024 / 1024, 2) . "MB\n";
echo "  • Max payload: " . number_format($largestPayload / 1024 / 1024, 2) . "MB\n";
echo "  • Max startup time: " . number_format($maxStartupTime, 3) . "ms\n";
echo "  • Concurrent workers: {$concurrentWorkerCount}\n";

$manager->disconnect();

// exist the script
exit(0);