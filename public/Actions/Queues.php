<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Queues extends Action
{
    public function handle(array $get, array $post): array
    {
        $stats = $this->manager->getQueueStats();
        $queues = [];

        foreach ($stats as $queueName => $queueStats) {
            $queues[] = [
                'name' => $queueName,
                'total_jobs' => $queueStats['total_jobs'],
                'by_state' => $queueStats['by_state'],
                'avg_priority' => $queueStats['avg_priority'],
                'oldest_job' => $queueStats['oldest_job'],
                'newest_job' => $queueStats['newest_job'],
            ];
        }

        return $queues;
    }
}

