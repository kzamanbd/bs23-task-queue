<?php

declare(strict_types=1);

namespace TaskQueue\Scheduling;

use TaskQueue\Jobs\ScheduledJob;
use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Contracts\JobInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class JobScheduler
{
    private QueueDriverInterface $queueDriver;
    private LoggerInterface $logger;
    private array $scheduledJobs = [];
    private bool $running = false;
    private int $checkInterval = 60; // seconds

    public function __construct(
        QueueDriverInterface $queueDriver,
        LoggerInterface $logger = null
    ) {
        $this->queueDriver = $queueDriver;
        $this->logger = $logger ?? new NullLogger();
    }

    public function schedule(ScheduledJob $job): void
    {
        $this->scheduledJobs[$job->getId()] = $job;
        
        $this->logger->info('Job scheduled', [
            'job_id' => $job->getId(),
            'cron_expression' => $job->getCronExpression()->getExpression(),
            'next_run_at' => $job->getNextRunAt() ? $job->getNextRunAt()->format('Y-m-d H:i:s') : null,
            'recurring' => $job->isRecurring()
        ]);
    }

    public function unschedule(string $jobId): void
    {
        if (isset($this->scheduledJobs[$jobId])) {
            unset($this->scheduledJobs[$jobId]);
            
            $this->logger->info('Job unscheduled', [
                'job_id' => $jobId
            ]);
        }
    }

    public function getScheduledJobs(): array
    {
        return $this->scheduledJobs;
    }

    public function getScheduledJob(string $jobId): ?ScheduledJob
    {
        return $this->scheduledJobs[$jobId] ?? null;
    }

    public function run(): void
    {
        $this->running = true;
        $this->logger->info('Job scheduler started');

        while ($this->running) {
            try {
                $this->processScheduledJobs();
                sleep($this->checkInterval);
            } catch (\Throwable $e) {
                $this->logger->error('Scheduler error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Continue running even if there's an error
                sleep($this->checkInterval);
            }
        }

        $this->logger->info('Job scheduler stopped');
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function setCheckInterval(int $seconds): void
    {
        $this->checkInterval = max(1, $seconds);
    }

    public function getCheckInterval(): int
    {
        return $this->checkInterval;
    }

    private function processScheduledJobs(): void
    {
        $now = new \DateTime();
        $dueJobs = [];

        foreach ($this->scheduledJobs as $job) {
            if ($job->isDue($now)) {
                $dueJobs[] = $job;
            }
        }

        if (empty($dueJobs)) {
            return;
        }

        $this->logger->info('Processing due scheduled jobs', [
            'count' => count($dueJobs)
        ]);

        foreach ($dueJobs as $job) {
            $this->processScheduledJob($job);
        }
    }

    private function processScheduledJob(ScheduledJob $job): void
    {
        try {
            // Create a new job instance for execution
            $executionJob = new ScheduledJob($job->getPayload(), [
                'id' => uniqid('scheduled_', true),
                'queue' => $job->getQueue(),
                'priority' => $job->getPriority(),
                'timeout' => $job->getTimeout(),
                'tags' => array_merge($job->getTags(), ['scheduled', 'auto-generated']),
                'cron_expression' => $job->getCronExpression()->getExpression(),
                'recurring' => $job->isRecurring(),
                'expires_at' => $job->getExpiresAt()
            ]);

            // Push to queue for execution
            $this->queueDriver->push($executionJob);

            // Mark original job as run
            $job->markAsRun();

            $this->logger->info('Scheduled job dispatched', [
                'original_job_id' => $job->getId(),
                'execution_job_id' => $executionJob->getId(),
                'next_run_at' => $job->getNextRunAt() ? $job->getNextRunAt()->format('Y-m-d H:i:s') : null
            ]);

            // Remove non-recurring jobs
            if (!$job->shouldReschedule()) {
                $this->unschedule($job->getId());
            }

        } catch (\Throwable $e) {
            $this->logger->error('Failed to process scheduled job', [
                'job_id' => $job->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getNextRunTimes(int $limit = 10): array
    {
        $nextRuns = [];

        foreach ($this->scheduledJobs as $job) {
            if ($job->getNextRunAt()) {
                $nextRuns[] = [
                    'job_id' => $job->getId(),
                    'next_run_at' => $job->getNextRunAt()->format('Y-m-d H:i:s'),
                    'cron_expression' => $job->getCronExpression()->getExpression(),
                    'recurring' => $job->isRecurring()
                ];
            }
        }

        // Sort by next run time
        usort($nextRuns, function($a, $b) {
            return strtotime($a['next_run_at']) - strtotime($b['next_run_at']);
        });

        return array_slice($nextRuns, 0, $limit);
    }

    public function getStats(): array
    {
        $stats = [
            'total_scheduled' => count($this->scheduledJobs),
            'recurring' => 0,
            'one_time' => 0,
            'expired' => 0
        ];

        $now = new \DateTime();

        foreach ($this->scheduledJobs as $job) {
            if ($job->isRecurring()) {
                $stats['recurring']++;
            } else {
                $stats['one_time']++;
            }

            if ($job->isExpired($now)) {
                $stats['expired']++;
            }
        }

        return $stats;
    }
}
