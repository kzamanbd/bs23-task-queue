<?php

declare(strict_types=1);

namespace TaskQueue\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Jobs\AbstractJob;

class AbstractJobTest extends TestCase
{
    public function testJobCreation(): void
    {
        $payload = ['test' => 'data'];
        $job = new TestJob($payload);

        $this->assertNotEmpty($job->getId());
        $this->assertEquals($payload, $job->getPayload());
        $this->assertEquals(AbstractJob::STATE_PENDING, $job->getState());
        $this->assertEquals(AbstractJob::PRIORITY_NORMAL, $job->getPriority());
        $this->assertEquals('default', $job->getQueue());
        $this->assertEquals(0, $job->getAttempts());
        $this->assertEquals(3, $job->getMaxAttempts());
        $this->assertInstanceOf(\DateTimeImmutable::class, $job->getCreatedAt());
    }

    public function testJobStateManagement(): void
    {
        $job = new TestJob();

        $job->setState(AbstractJob::STATE_PROCESSING);
        $this->assertEquals(AbstractJob::STATE_PROCESSING, $job->getState());

        $job->setState(AbstractJob::STATE_COMPLETED);
        $this->assertEquals(AbstractJob::STATE_COMPLETED, $job->getState());
        $this->assertTrue($job->isCompleted());
    }

    public function testJobAttempts(): void
    {
        $job = new TestJob();

        $this->assertEquals(0, $job->getAttempts());
        $this->assertTrue($job->canRetry());

        $job->incrementAttempts();
        $this->assertEquals(1, $job->getAttempts());
        $this->assertTrue($job->canRetry());

        $job->incrementAttempts();
        $job->incrementAttempts();
        $this->assertEquals(3, $job->getAttempts());
        $this->assertFalse($job->canRetry());
    }

    public function testJobSerialization(): void
    {
        $payload = ['test' => 'data'];
        $job = new TestJob($payload, [
            'priority' => AbstractJob::PRIORITY_HIGH,
            'queue' => 'test-queue',
            'tags' => ['test', 'unit']
        ]);

        $array = $job->toArray();
        $this->assertIsArray($array);
        $this->assertEquals($payload, $array['payload']);
        $this->assertEquals(AbstractJob::PRIORITY_HIGH, $array['priority']);
        $this->assertEquals('test-queue', $array['queue']);
        $this->assertEquals(['test', 'unit'], $array['tags']);

        $restoredJob = TestJob::fromArray($array);
        $this->assertEquals($job->getId(), $restoredJob->getId());
        $this->assertEquals($job->getPayload(), $restoredJob->getPayload());
        $this->assertEquals($job->getPriority(), $restoredJob->getPriority());
        $this->assertEquals($job->getQueue(), $restoredJob->getQueue());
    }

    public function testJobDelay(): void
    {
        $job = new TestJob([], ['delay' => 60]);
        
        $this->assertEquals(60, $job->getDelay());
        $this->assertFalse($job->isExpired());

        // Create a job with delay in the past
        $pastJob = new TestJob([], [
            'delay' => 1,
            'created_at' => new \DateTimeImmutable('-2 seconds')
        ]);
        
        $this->assertTrue($pastJob->isExpired());
    }
}
