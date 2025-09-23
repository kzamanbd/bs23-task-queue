<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Performance extends Action
{
    public function handle(array $get, array $post): array
    {
        $stats = $this->manager->getQueueStats();
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

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_jobs' => $totalJobs,
            'pending' => $totalPending,
            'processing' => $totalProcessing,
            'completed' => $totalCompleted,
            'failed' => $totalFailed,
            'success_rate' => $totalJobs > 0 ? round(($totalCompleted / $totalJobs) * 100, 2) : 0,
            'failure_rate' => $totalJobs > 0 ? round(($totalFailed / $totalJobs) * 100, 2) : 0,
        ];
    }
}

