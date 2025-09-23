<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Recent extends Action
{
    public function handle(array $get, array $post): array
    {
        $limit = (int) ($get['limit'] ?? 50);
        $queue = $get['queue'] ?? null;
        $state = $get['state'] ?? null;

        $recentJobs = [];

        if ($state) {
            $jobs = $this->manager->getJobsByState($state, $queue, $limit);
            foreach ($jobs as $job) {
                $recentJobs[] = $this->serializeJob($job);
            }
        } else {
            $states = ['pending', 'processing', 'completed', 'failed'];
            foreach ($states as $s) {
                $jobs = $this->manager->getJobsByState($s, $queue, $limit);
                foreach ($jobs as $job) {
                    $recentJobs[] = $this->serializeJob($job);
                }
            }
        }

        usort($recentJobs, function ($a, $b) {
            return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
        });

        return array_slice($recentJobs, 0, $limit);
    }

    private function serializeJob($job): array
    {
        return [
            'id' => $job->getId(),
            'state' => $job->getState(),
            'queue' => $job->getQueue(),
            'priority' => $job->getPriority(),
            'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $job->getUpdatedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $job->getCompletedAt() ? $job->getCompletedAt()->format('Y-m-d H:i:s') : null,
            'failed_at' => $job->getFailedAt() ? $job->getFailedAt()->format('Y-m-d H:i:s') : null,
            'attempts' => $job->getAttempts(),
            'max_attempts' => $job->getMaxAttempts(),
            'tags' => $job->getTags(),
            'payload' => $job->getPayload(),
        ];
    }
}

