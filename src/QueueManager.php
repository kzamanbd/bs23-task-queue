<?php

declare(strict_types=1);

namespace TaskQueue;

use TaskQueue\Contracts\JobInterface;
use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Contracts\WorkerInterface;
use TaskQueue\Workers\Worker;
use TaskQueue\Support\Encryption;
use Psr\Log\LoggerInterface;

class QueueManager
{
    private QueueDriverInterface $driver;
    private LoggerInterface $logger;
    private array $workers = [];
    private array $config;

    public function __construct(
        QueueDriverInterface $driver,
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->config = array_merge([
            'memory_limit' => 50 * 1024 * 1024, // 50MB
            'max_jobs_per_worker' => 1000,
            'worker_timeout' => 3600, // 1 hour
            'max_workers' => 10,
        ], $config);
    }

    public function push(JobInterface $job): void
    {
        $this->driver->push($job);
        $this->logger->info('Job pushed to queue', [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'priority' => $job->getPriority()
        ]);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        return $this->driver->pop($queue);
    }

    public function startWorker(string $queue, int $timeout = 0): WorkerInterface
    {
        $worker = new Worker(
            $this->driver,
            $this->logger,
            $this->config['memory_limit'],
            $this->config['max_jobs_per_worker']
        );

        $this->workers[] = $worker;
        
        $worker->work($queue, $timeout ?: $this->config['worker_timeout']);
        
        return $worker;
    }

    public function startMultipleWorkers(string $queue, int $count = 1): array
    {
        if ($count > $this->config['max_workers']) {
            throw new \InvalidArgumentException(
                "Cannot start {$count} workers. Maximum allowed: {$this->config['max_workers']}"
            );
        }

        $workers = [];
        for ($i = 0; $i < $count; $i++) {
            $worker = new Worker(
                $this->driver,
                $this->logger,
                $this->config['memory_limit'],
                $this->config['max_jobs_per_worker']
            );
            
            $workers[] = $worker;
            $this->workers[] = $worker;
        }

        return $workers;
    }

    public function stopAllWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $this->workers = [];
    }

    public function getActiveWorkers(): array
    {
        return array_filter($this->workers, fn($worker) => $worker->isRunning());
    }

    public function getQueueSize(string $queue = 'default'): int
    {
        return $this->driver->size($queue);
    }

    public function getQueueStats(string $queue = null): array
    {
        return $this->driver->getQueueStats($queue);
    }

    public function getFailedJobs(string $queue = null): array
    {
        return $this->driver->getFailedJobs($queue);
    }

    public function retryFailedJob(string $jobId): bool
    {
        return $this->driver->retryFailedJob($jobId);
    }

    public function purgeQueue(string $queue): void
    {
        $this->driver->purge($queue);
        $this->logger->info('Queue purged', ['queue' => $queue]);
    }

    public function getJobById(string $jobId): ?JobInterface
    {
        return $this->driver->getJobById($jobId);
    }

    public function getJobsByState(string $state, string $queue = null, int $limit = 100): array
    {
        return $this->driver->getJobsByState($state, $queue, $limit);
    }

    public function connect(): void
    {
        $this->driver->connect();
    }

    public function disconnect(): void
    {
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function getDriver(): QueueDriverInterface
    {
        return $this->driver;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}
