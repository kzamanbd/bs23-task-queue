<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

use TaskQueue\Jobs\TestJob;

class CreateTestJobs extends Action
{
    public function handle(array $get, array $post): array
    {
        $count = (int) ($post['count'] ?? 10);
        $queue = $post['queue'] ?? 'default';
        $priority = (int) ($post['priority'] ?? 5);

        $createdJobs = [];
        for ($i = 0; $i < $count; $i++) {
            $job = new TestJob([
                'test_data' => "Test job #{$i}",
                'timestamp' => time(),
                'random_data' => bin2hex(random_bytes(16))
            ], [
                'queue' => $queue,
                'priority' => $priority,
                'tags' => ['test', 'dashboard', 'auto-generated']
            ]);

            $this->manager->push($job);
            $createdJobs[] = $job->getId();
        }

        return [
            'success' => true,
            'created_jobs' => $createdJobs,
            'count' => $count
        ];
    }
}
