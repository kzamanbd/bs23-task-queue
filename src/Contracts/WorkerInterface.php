<?php

declare(strict_types=1);

namespace TaskQueue\Contracts;

interface WorkerInterface
{
    public function work(string $queue, int $timeout = 0): void;

    public function stop(): void;

    public function pause(): void;

    public function resume(): void;

    public function isRunning(): bool;

    public function isPaused(): bool;

    public function getProcessId(): ?int;

    public function getMemoryUsage(): int;

    public function getJobsProcessed(): int;

    public function getJobsFailed(): int;

    public function getStartTime(): \DateTimeImmutable;

    public function getStatus(): array;

    public function kill(): void;
}
