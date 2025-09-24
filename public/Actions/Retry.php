<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Retry extends Action
{
    public function handle(array $get, array $post): array
    {
        $jobId = $post['job_id'] ?? '';
        if ($jobId === '') {
            throw new \InvalidArgumentException('Job ID is required');
        }

        $result = $this->manager->retryFailedJob($jobId);
        return ['success' => $result];
    }
}
