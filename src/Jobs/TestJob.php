<?php

declare(strict_types=1);

namespace TaskQueue\Jobs;

class TestJob extends AbstractJob
{
    public function handle(): void
    {
        // Simulate some work
        $workDuration = $this->payload['work_duration'] ?? 1;
        sleep($workDuration);

        // Simulate success/failure based on payload
        if (isset($this->payload['should_fail']) && $this->payload['should_fail']) {
            throw new \Exception('Job intentionally failed');
        }

        // Store result in payload
        $this->payload['result'] = 'Job completed successfully at ' . date('Y-m-d H:i:s');
        $this->payload['processed_by'] = getmypid();
    }
}
