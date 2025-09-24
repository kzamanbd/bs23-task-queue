<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

use Exception;
use DateTimeImmutable;
use InvalidArgumentException;
use TaskQueue\Jobs\ScheduledJob;

class ScheduledJobs extends Action
{
    public function handle(array $get, array $post): array
    {
        $action = $get['sub_action'] ?? 'list';

        switch ($action) {
            case 'list':
                return $this->listScheduledJobs();
            case 'create':
                return $this->createScheduledJob($post);
            case 'delete':
                return $this->deleteScheduledJob($post);
            case 'run':
                return $this->runScheduledJob($post);
            case 'stats':
                return $this->getSchedulerStats();
            default:
                throw new InvalidArgumentException('Invalid sub_action');
        }
    }

    private function listScheduledJobs(): array
    {
        $scheduledJobs = $this->manager->getScheduledJobs();
        $jobs = [];

        foreach ($scheduledJobs as $job) {
            $jobs[] = [
                'id' => $job->getId(),
                'cron_expression' => $job->getCronExpression()->getExpression(),
                'next_run_at' => $job->getNextRunAt() ? $job->getNextRunAt()->format('Y-m-d H:i:s') : null,
                'recurring' => $job->isRecurring(),
                'expires_at' => $job->getExpiresAt() ? $job->getExpiresAt()->format('Y-m-d H:i:s') : null,
                'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
                'queue' => $job->getQueue(),
                'priority' => $job->getPriority(),
                'tags' => $job->getTags(),
                'payload' => $job->getPayload(),
                'is_active' => $job->isActive(),
            ];
        }

        return [
            'scheduled_jobs' => $jobs,
            'count' => count($jobs)
        ];
    }

    private function createScheduledJob(array $post): array
    {
        $schedule = $post['schedule'] ?? '';
        $jobClass = $post['job_class'] ?? 'TaskQueue\\Jobs\\TestJob';
        $payload = $post['payload'] ?? '{}';
        $queue = $post['queue'] ?? 'default';
        $priority = (int) ($post['priority'] ?? 5);
        $recurring = filter_var($post['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $expires = $post['expires_at'] ?? null;

        if (empty($schedule)) {
            throw new InvalidArgumentException('Schedule expression is required');
        }

        // Parse payload
        $jobPayload = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
        }

        // Parse expiration date
        $expiresAt = null;
        if ($expires) {
            try {
                $expiresAt = new DateTimeImmutable($expires);
            } catch (Exception $e) {
                throw new InvalidArgumentException('Invalid expiration date format. Use Y-m-d H:i:s');
            }
        }

        // Create scheduled job
        $options = [
            'queue' => $queue,
            'priority' => $priority,
            'recurring' => $recurring,
            'expires_at' => $expiresAt,
            'tags' => ['scheduled', 'dashboard-created']
        ];

        $scheduledJob = new ScheduledJob($jobPayload, $options);
        $scheduledJob->setSchedule($schedule);

        // Schedule the job
        $this->manager->scheduleJob($scheduledJob);

        return [
            'success' => true,
            'job_id' => $scheduledJob->getId(),
            'message' => 'Scheduled job created successfully'
        ];
    }

    private function deleteScheduledJob(array $post): array
    {
        $jobId = $post['job_id'] ?? '';

        if (empty($jobId)) {
            throw new InvalidArgumentException('Job ID is required');
        }

        $this->manager->unscheduleJob($jobId);

        return [
            'success' => true,
            'message' => 'Scheduled job deleted successfully'
        ];
    }

    private function runScheduledJob(array $post): array
    {
        $jobId = $post['job_id'] ?? '';

        if (empty($jobId)) {
            throw new InvalidArgumentException('Job ID is required');
        }

        $scheduledJob = $this->manager->getScheduledJob($jobId);
        if (!$scheduledJob) {
            throw new \RuntimeException('Scheduled job not found');
        }

        // Create a regular job from the scheduled job and push it to the queue
        $regularJob = new \TaskQueue\Jobs\TestJob($scheduledJob->getPayload(), [
            'queue' => $scheduledJob->getQueue(),
            'priority' => $scheduledJob->getPriority(),
            'tags' => array_merge($scheduledJob->getTags(), ['manual-run'])
        ]);

        $this->manager->push($regularJob);

        return [
            'success' => true,
            'job_id' => $regularJob->getId(),
            'message' => 'Scheduled job executed manually'
        ];
    }

    private function getSchedulerStats(): array
    {
        $stats = $this->manager->getSchedulerStats();
        $nextRuns = $this->manager->getNextRunTimes(10);

        return [
            'stats' => $stats,
            'next_runs' => $nextRuns
        ];
    }
}
