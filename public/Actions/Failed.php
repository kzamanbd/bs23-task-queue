<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Failed extends Action
{
    public function handle(array $get, array $post): array
    {
        $queue = $get['queue'] ?? null;
        $failedJobs = $this->manager->getFailedJobs($queue);

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

        return $result;
    }
}

