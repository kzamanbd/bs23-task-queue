<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class JobDetails extends Action
{
    public function handle(array $get, array $post): array
    {
        $jobId = $get['job_id'] ?? '';
        if ($jobId === '') {
            throw new \InvalidArgumentException('Job ID is required');
        }

        $job = $this->manager->getJobById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }

        return [
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
    }
}

