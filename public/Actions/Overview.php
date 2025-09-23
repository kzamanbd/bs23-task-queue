<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Overview extends Action
{
    public function handle(array $get, array $post): array
    {
        $limit = (int) ($get['limit'] ?? 20);
        $queue = $get['queue'] ?? null;

        // Stats per queue
        $stats = $this->manager->getQueueStats();

        // Queues summary derived from stats
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

        // Performance aggregate derived from stats
        $totalJobs = 0;
        $totalPending = 0;
        $totalProcessing = 0;
        $totalCompleted = 0;
        $totalFailed = 0;
        foreach ($stats as $queueStats) {
            $totalJobs += $queueStats['total_jobs'];
            $totalPending += $queueStats['by_state']['pending'] ?? 0;
            $totalProcessing += $queueStats['by_state']['processing'] ?? 0;
            $totalCompleted += $queueStats['by_state']['completed'] ?? 0;
            $totalFailed += $queueStats['by_state']['failed'] ?? 0;
        }
        $performance = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_jobs' => $totalJobs,
            'pending' => $totalPending,
            'processing' => $totalProcessing,
            'completed' => $totalCompleted,
            'failed' => $totalFailed,
            'success_rate' => $totalJobs > 0 ? round(($totalCompleted / $totalJobs) * 100, 2) : 0,
            'failure_rate' => $totalJobs > 0 ? round(($totalFailed / $totalJobs) * 100, 2) : 0,
        ];

        // Recent jobs across states (optionally filtered by queue)
        $states = ['pending', 'processing', 'completed', 'failed'];
        $recentJobs = [];
        foreach ($states as $s) {
            $jobs = $this->manager->getJobsByState($s, $queue, $limit);
            foreach ($jobs as $job) {
                $recentJobs[] = [
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
        usort($recentJobs, function ($a, $b) {
            return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
        });
        $recentJobs = array_slice($recentJobs, 0, $limit);

        return [
            'stats' => $stats,
            'queues' => $queues,
            'performance' => $performance,
            'recent' => $recentJobs,
        ];
    }
}
