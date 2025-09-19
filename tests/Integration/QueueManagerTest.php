<?php

declare(strict_types=1);

namespace TaskQueue\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Support\Encryption;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use PDO;

class QueueManagerTest extends TestCase
{
    private QueueManager $manager;
    private PDO $pdo;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $encryption = new Encryption('test-encryption-key-32-characters');
        $driver = new DatabaseQueueDriver($this->pdo, $encryption);

        $this->logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($this->logHandler);

        $this->manager = new QueueManager($driver, $logger);
        $this->manager->connect();
    }

    protected function tearDown(): void
    {
        $this->manager->disconnect();
    }

    public function testPushAndPopJob(): void
    {
        $job = new TestJob(['test' => 'data']);
        $this->manager->push($job);

        $poppedJob = $this->manager->pop('default');
        
        $this->assertNotNull($poppedJob);
        $this->assertEquals($job->getId(), $poppedJob->getId());
        $this->assertEquals(['test' => 'data'], $poppedJob->getPayload());
    }

    public function testQueueSize(): void
    {
        $this->assertEquals(0, $this->manager->getQueueSize());

        // Push 5 jobs
        for ($i = 0; $i < 5; $i++) {
            $job = new TestJob(['number' => $i]);
            $this->manager->push($job);
        }

        $this->assertEquals(5, $this->manager->getQueueSize());
    }

    public function testQueueStats(): void
    {
        // Create jobs with different states
        $job1 = new TestJob(['test' => 1]);
        $this->manager->push($job1);

        $job2 = new TestJob(['test' => 2]);
        $this->manager->push($job2);

        // Pop one job (it becomes processing)
        $poppedJob = $this->manager->pop('default');

        $stats = $this->manager->getQueueStats('default');
        
        $this->assertArrayHasKey('default', $stats);
        $this->assertGreaterThan(0, $stats['default']['total_jobs']);
    }

    public function testPurgeQueue(): void
    {
        // Push some jobs
        for ($i = 0; $i < 3; $i++) {
            $job = new TestJob(['number' => $i]);
            $this->manager->push($job);
        }

        $this->assertEquals(3, $this->manager->getQueueSize());

        $this->manager->purgeQueue('default');
        $this->assertEquals(0, $this->manager->getQueueSize());
    }

    public function testGetJobById(): void
    {
        $job = new TestJob(['test' => 'data']);
        $this->manager->push($job);

        $retrievedJob = $this->manager->getJobById($job->getId());
        
        $this->assertNotNull($retrievedJob);
        $this->assertEquals($job->getId(), $retrievedJob->getId());
        $this->assertEquals(['test' => 'data'], $retrievedJob->getPayload());
    }
}
