<?php

declare(strict_types=1);

namespace TaskQueue\RateLimiting;

use TaskQueue\Contracts\QueueDriverInterface;

class RateLimiter
{
    private QueueDriverInterface $queueDriver;
    private array $limits = [];
    private array $windows = [];

    public function __construct(QueueDriverInterface $queueDriver)
    {
        $this->queueDriver = $queueDriver;
    }

    public function setLimit(string $key, int $maxRequests, int $windowSeconds): void
    {
        $this->limits[$key] = [
            'max_requests' => $maxRequests,
            'window_seconds' => $windowSeconds
        ];
    }

    public function isAllowed(string $key, int $requests = 1): bool
    {
        if (!isset($this->limits[$key])) {
            return true; // No limit set
        }

        $limit = $this->limits[$key];
        $now = time();
        $windowStart = $now - $limit['window_seconds'];

        // Clean old entries
        $this->cleanOldEntries($key, $windowStart);

        // Count current requests in window
        $currentCount = $this->getCurrentCount($key, $windowStart);

        return ($currentCount + $requests) <= $limit['max_requests'];
    }

    public function record(string $key, int $requests = 1): void
    {
        if (!isset($this->limits[$key])) {
            return; // No limit set
        }

        $now = time();
        
        if (!isset($this->windows[$key])) {
            $this->windows[$key] = [];
        }

        // Add new requests
        for ($i = 0; $i < $requests; $i++) {
            $this->windows[$key][] = $now;
        }
    }

    public function getRemaining(string $key): int
    {
        if (!isset($this->limits[$key])) {
            return PHP_INT_MAX;
        }

        $limit = $this->limits[$key];
        $now = time();
        $windowStart = $now - $limit['window_seconds'];

        $this->cleanOldEntries($key, $windowStart);
        $currentCount = $this->getCurrentCount($key, $windowStart);

        return max(0, $limit['max_requests'] - $currentCount);
    }

    public function getResetTime(string $key): int
    {
        if (!isset($this->limits[$key])) {
            return 0;
        }

        $limit = $this->limits[$key];
        $now = time();
        $windowStart = $now - $limit['window_seconds'];

        $this->cleanOldEntries($key, $windowStart);

        if (empty($this->windows[$key])) {
            return $now;
        }

        // Return the time when the oldest request will expire
        return min($this->windows[$key]) + $limit['window_seconds'];
    }

    public function getLimits(): array
    {
        return $this->limits;
    }

    public function clear(string $key): void
    {
        unset($this->windows[$key]);
    }

    public function clearAll(): void
    {
        $this->windows = [];
    }

    private function cleanOldEntries(string $key, int $windowStart): void
    {
        if (!isset($this->windows[$key])) {
            return;
        }

        $this->windows[$key] = array_filter(
            $this->windows[$key],
            fn($timestamp) => $timestamp > $windowStart
        );
    }

    private function getCurrentCount(string $key, int $windowStart): int
    {
        if (!isset($this->windows[$key])) {
            return 0;
        }

        return count(array_filter(
            $this->windows[$key],
            fn($timestamp) => $timestamp > $windowStart
        ));
    }

    public function getStats(): array
    {
        $stats = [];

        foreach ($this->limits as $key => $limit) {
            $stats[$key] = [
                'limit' => $limit['max_requests'],
                'window' => $limit['window_seconds'],
                'remaining' => $this->getRemaining($key),
                'reset_time' => $this->getResetTime($key)
            ];
        }

        return $stats;
    }
}
