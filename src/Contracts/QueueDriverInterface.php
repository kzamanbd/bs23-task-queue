<?php

declare(strict_types=1);

namespace TaskQueue\Contracts;

interface QueueDriverInterface
{
    public function push(JobInterface $job): void;

    public function pop(string $queue): ?JobInterface;

    public function peek(string $queue): ?JobInterface;

    public function delete(JobInterface $job): void;

    public function release(JobInterface $job, int $delay = 0): void;

    public function size(string $queue): int;

    public function purge(string $queue): void;

    public function getFailedJobs(string $queue = null): array;

    public function retryFailedJob(string $jobId): bool;

    public function getJobById(string $jobId): ?JobInterface;

    public function getJobsByState(string $state, string $queue = null, int $limit = 100): array;

    public function getQueueStats(string $queue = null): array;

    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;
}
