<?php

declare(strict_types=1);

namespace TaskQueue\Workers;

use TaskQueue\Contracts\JobInterface;
use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Contracts\WorkerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Worker implements WorkerInterface
{
    private QueueDriverInterface $queueDriver;
    private LoggerInterface $logger;
    private bool $running = false;
    private bool $paused = false;
    private ?int $processId = null;
    private \DateTimeImmutable $startTime;
    private int $jobsProcessed = 0;
    private int $jobsFailed = 0;
    private int $memoryLimit;
    private int $maxJobs;
    private array $signals = [];

    public function __construct(
        QueueDriverInterface $queueDriver,
        LoggerInterface $logger = null,
        int $memoryLimit = 50 * 1024 * 1024, // 50MB
        int $maxJobs = 1000
    ) {
        $this->queueDriver = $queueDriver;
        $this->logger = $logger ?? $this->createDefaultLogger();
        $this->memoryLimit = $memoryLimit;
        $this->maxJobs = $maxJobs;
        $this->processId = getmypid();
        $this->startTime = new \DateTimeImmutable();

        $this->setupSignalHandlers();
    }

    public function work(string $queue, int $timeout = 0): void
    {
        $this->running = true;
        $this->logger->info("Worker started", [
            'queue' => $queue,
            'process_id' => $this->processId,
            'memory_limit' => $this->memoryLimit,
            'max_jobs' => $this->maxJobs
        ]);

        $startTime = time();
        $lastHeartbeat = time();
        $lastCleanup = time();

        while ($this->running && ($timeout === 0 || (time() - $startTime) < $timeout)) {
            try {
                // Check if we should pause
                if ($this->paused) {
                    usleep(100000); // 100ms
                    continue;
                }

                // Check memory usage
                if ($this->getMemoryUsage() > $this->memoryLimit) {
                    $this->logger->warning('Memory limit exceeded, restarting worker', [
                        'memory_usage' => $this->getMemoryUsage(),
                        'memory_limit' => $this->memoryLimit
                    ]);
                    break;
                }

                // Check if we've processed too many jobs
                if ($this->jobsProcessed >= $this->maxJobs) {
                    $this->logger->info('Maximum jobs processed, restarting worker', [
                        'jobs_processed' => $this->jobsProcessed,
                        'max_jobs' => $this->maxJobs
                    ]);
                    break;
                }

                // Try to get a job
                $job = $this->queueDriver->pop($queue);
                
                if ($job === null) {
                    usleep(100000); // 100ms
                    continue;
                }

                $this->processJob($job);

                // Send heartbeat every 30 seconds
                if ((time() - $lastHeartbeat) >= 30) {
                    $this->sendHeartbeat($queue);
                    $lastHeartbeat = time();
                }

                // Cleanup old completed jobs every 5 minutes
                if ((time() - $lastCleanup) >= 300) {
                    $this->cleanupOldCompletedJobs();
                    $lastCleanup = time();
                }

            } catch (\Throwable $e) {
                $this->logger->error('Worker error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                usleep(1000000); // 1 second before retrying
            }
        }

        $this->running = false;
        $this->logger->info("Worker stopped", [
            'jobs_processed' => $this->jobsProcessed,
            'jobs_failed' => $this->jobsFailed,
            'uptime' => time() - $this->startTime->getTimestamp()
        ]);
    }

    public function stop(): void
    {
        $this->running = false;
        $this->logger->info('Worker stop requested');
    }

    public function pause(): void
    {
        $this->paused = true;
        $this->logger->info('Worker paused');
    }

    public function resume(): void
    {
        $this->paused = false;
        $this->logger->info('Worker resumed');
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function getProcessId(): ?int
    {
        return $this->processId;
    }

    public function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    public function getJobsProcessed(): int
    {
        return $this->jobsProcessed;
    }

    public function getJobsFailed(): int
    {
        return $this->jobsFailed;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'paused' => $this->paused,
            'process_id' => $this->processId,
            'memory_usage' => $this->getMemoryUsage(),
            'memory_limit' => $this->memoryLimit,
            'jobs_processed' => $this->jobsProcessed,
            'jobs_failed' => $this->jobsFailed,
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'uptime' => time() - $this->startTime->getTimestamp(),
        ];
    }

    public function kill(): void
    {
        $this->running = false;
        if ($this->processId && posix_kill($this->processId, SIGTERM)) {
            $this->logger->info('Worker killed', ['process_id' => $this->processId]);
        }
    }

    private function processJob(JobInterface $job): void
    {
        $this->logger->info('Processing job', [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts() + 1
        ]);

        $startTime = microtime(true);
        $job->setState(JobInterface::STATE_PROCESSING);
        $job->incrementAttempts();

        try {
            // Set up job timeout
            $timeout = $job->getTimeout();
            if ($timeout > 0) {
                pcntl_signal_dispatch();
                pcntl_alarm($timeout);
            }

            $job->handle();
            
            // Clear alarm
            pcntl_alarm(0);

            $job->setState(JobInterface::STATE_COMPLETED);
            $job->setCompletedAt(new \DateTimeImmutable());
            // Keep completed jobs for 1 hour instead of deleting immediately
            $this->queueDriver->update($job);

            $this->jobsProcessed++;
            $processingTime = microtime(true) - $startTime;

            $this->logger->info('Job completed', [
                'job_id' => $job->getId(),
                'processing_time' => round($processingTime, 3),
                'attempts' => $job->getAttempts()
            ]);

        } catch (\Throwable $e) {
            pcntl_alarm(0); // Clear any pending alarm
            
            $job->setException($e);
            $processingTime = microtime(true) - $startTime;

            if ($job->canRetry()) {
                $job->setState(JobInterface::STATE_RETRYING);
                $delay = $this->calculateRetryDelay($job);
                $this->queueDriver->release($job, $delay);
                
                $this->logger->warning('Job failed, will retry', [
                    'job_id' => $job->getId(),
                    'error' => $e->getMessage(),
                    'attempts' => $job->getAttempts(),
                    'max_attempts' => $job->getMaxAttempts(),
                    'retry_delay' => $delay,
                    'processing_time' => round($processingTime, 3)
                ]);
            } else {
                $job->setState(JobInterface::STATE_FAILED);
                $job->setFailedAt(new \DateTimeImmutable());
                // Keep the job in the database for inspection
                
                $this->jobsFailed++;
                $this->logger->error('Job failed permanently', [
                    'job_id' => $job->getId(),
                    'error' => $e->getMessage(),
                    'attempts' => $job->getAttempts(),
                    'max_attempts' => $job->getMaxAttempts(),
                    'processing_time' => round($processingTime, 3)
                ]);
            }
        }
    }

    private function calculateRetryDelay(JobInterface $job): int
    {
        // Exponential backoff: 2^attempts seconds
        return min(pow(2, $job->getAttempts()), 300); // Max 5 minutes
    }

    private function sendHeartbeat(string $queue): void
    {
        $this->logger->debug('Worker heartbeat', [
            'queue' => $queue,
            'memory_usage' => $this->getMemoryUsage(),
            'jobs_processed' => $this->jobsProcessed,
            'jobs_failed' => $this->jobsFailed
        ]);
    }

    private function setupSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGUSR1, [$this, 'handleSignal']); // Pause
        pcntl_signal(SIGUSR2, [$this, 'handleSignal']); // Resume
        pcntl_signal(SIGALRM, [$this, 'handleSignal']); // Job timeout
    }

    public function handleSignal(int $signal): void
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->logger->info('Received termination signal', ['signal' => $signal]);
                $this->stop();
                break;
            case SIGUSR1:
                $this->logger->info('Received pause signal', ['signal' => $signal]);
                $this->pause();
                break;
            case SIGUSR2:
                $this->logger->info('Received resume signal', ['signal' => $signal]);
                $this->resume();
                break;
            case SIGALRM:
                $this->logger->warning('Job timeout signal received');
                throw new \RuntimeException('Job timeout exceeded');
        }
    }

    public function setProcessId(int $processId): void
    {
        $this->processId = $processId;
    }

    private function cleanupOldCompletedJobs(): void
    {
        try {
            // Clean up completed jobs older than 1 hour
            $deletedCount = $this->queueDriver->cleanupOldCompletedJobs(1);
            if ($deletedCount > 0) {
                $this->logger->info('Cleaned up old completed jobs', [
                    'deleted_count' => $deletedCount
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to cleanup old completed jobs', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createDefaultLogger(): LoggerInterface
    {
        $logger = new Logger('worker');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
        return $logger;
    }
}
