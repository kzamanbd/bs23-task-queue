<?php

declare(strict_types=1);

namespace TaskQueue\Conditions;

use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Contracts\JobInterface;

class ConditionEvaluator
{
    private QueueDriverInterface $queueDriver;
    private array $conditions = [];

    public function __construct(QueueDriverInterface $queueDriver)
    {
        $this->queueDriver = $queueDriver;
        $this->registerDefaultConditions();
    }

    public function registerCondition(string $name, callable $evaluator): void
    {
        $this->conditions[$name] = $evaluator;
    }

    public function evaluate(array $conditions, JobInterface $job): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $job)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(array $condition, JobInterface $job): bool
    {
        $type = $condition['type'] ?? '';
        $params = $condition['params'] ?? [];

        if (!isset($this->conditions[$type])) {
            throw new \InvalidArgumentException("Unknown condition type: {$type}");
        }

        return $this->conditions[$type]($job, $params, $this->queueDriver);
    }

    private function registerDefaultConditions(): void
    {
        // Queue depth condition
        $this->registerCondition('queue_depth', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $queue = $params['queue'] ?? $job->getQueue();
            $maxDepth = $params['max_depth'] ?? 100;
            
            $stats = $driver->getStats();
            $currentDepth = $stats[$queue]['total_jobs'] ?? 0;
            
            return $currentDepth < $maxDepth;
        });

        // Queue empty condition
        $this->registerCondition('queue_empty', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $queue = $params['queue'] ?? $job->getQueue();
            
            $stats = $driver->getStats();
            $currentDepth = $stats[$queue]['total_jobs'] ?? 0;
            
            return $currentDepth === 0;
        });

        // Time-based condition
        $this->registerCondition('time_based', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $startTime = $params['start_time'] ?? '00:00';
            $endTime = $params['end_time'] ?? '23:59';
            $timezone = $params['timezone'] ?? 'UTC';
            
            $now = new \DateTime('now', new \DateTimeZone($timezone));
            $currentTime = $now->format('H:i');
            
            return $currentTime >= $startTime && $currentTime <= $endTime;
        });

        // Day of week condition
        $this->registerCondition('day_of_week', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $allowedDays = $params['days'] ?? [1, 2, 3, 4, 5]; // Monday to Friday by default
            $timezone = $params['timezone'] ?? 'UTC';
            
            $now = new \DateTime('now', new \DateTimeZone($timezone));
            $currentDay = (int) $now->format('w'); // 0 = Sunday, 1 = Monday, etc.
            
            return in_array($currentDay, $allowedDays, true);
        });

        // Memory usage condition
        $this->registerCondition('memory_usage', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $maxMemoryMB = $params['max_memory_mb'] ?? 100;
            $maxMemoryBytes = $maxMemoryMB * 1024 * 1024;
            
            $currentMemory = memory_get_usage(true);
            
            return $currentMemory < $maxMemoryBytes;
        });

        // System load condition
        $this->registerCondition('system_load', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $maxLoad = $params['max_load'] ?? 5.0;
            
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg()[0]; // 1-minute load average
                return $load < $maxLoad;
            }
            
            return true; // If we can't get load, allow execution
        });

        // Failed jobs condition
        $this->registerCondition('failed_jobs_threshold', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $maxFailedJobs = $params['max_failed_jobs'] ?? 10;
            
            $stats = $driver->getStats();
            $totalFailed = 0;
            
            foreach ($stats as $queueStats) {
                $totalFailed += $queueStats['by_state']['failed'] ?? 0;
            }
            
            return $totalFailed < $maxFailedJobs;
        });

        // Custom condition
        $this->registerCondition('custom', function(JobInterface $job, array $params, QueueDriverInterface $driver) {
            $callback = $params['callback'] ?? null;
            
            if (!is_callable($callback)) {
                return true; // If no valid callback, allow execution
            }
            
            return $callback($job, $driver);
        });
    }

    public function getAvailableConditions(): array
    {
        return [
            'queue_depth' => 'Check if queue depth is below threshold',
            'queue_empty' => 'Check if queue is empty',
            'time_based' => 'Check if current time is within allowed range',
            'day_of_week' => 'Check if current day is in allowed days',
            'memory_usage' => 'Check if memory usage is below threshold',
            'system_load' => 'Check if system load is below threshold',
            'failed_jobs_threshold' => 'Check if failed jobs count is below threshold',
            'custom' => 'Execute custom condition callback'
        ];
    }

    public function validateConditions(array $conditions): array
    {
        $errors = [];

        foreach ($conditions as $index => $condition) {
            if (!isset($condition['type'])) {
                $errors[] = "Condition {$index}: Missing 'type' field";
                continue;
            }

            if (!isset($this->conditions[$condition['type']])) {
                $errors[] = "Condition {$index}: Unknown condition type '{$condition['type']}'";
                continue;
            }

            // Validate specific condition parameters
            $type = $condition['type'];
            $params = $condition['params'] ?? [];

            switch ($type) {
                case 'queue_depth':
                    if (!isset($params['max_depth']) || !is_numeric($params['max_depth'])) {
                        $errors[] = "Condition {$index}: 'max_depth' parameter required and must be numeric";
                    }
                    break;

                case 'time_based':
                    if (!isset($params['start_time']) || !isset($params['end_time'])) {
                        $errors[] = "Condition {$index}: 'start_time' and 'end_time' parameters required";
                    }
                    break;

                case 'day_of_week':
                    if (!isset($params['days']) || !is_array($params['days'])) {
                        $errors[] = "Condition {$index}: 'days' parameter required and must be array";
                    }
                    break;

                case 'custom':
                    if (!isset($params['callback']) || !is_callable($params['callback'])) {
                        $errors[] = "Condition {$index}: 'callback' parameter required and must be callable";
                    }
                    break;
            }
        }

        return $errors;
    }
}
