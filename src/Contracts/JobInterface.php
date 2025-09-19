<?php

declare(strict_types=1);

namespace TaskQueue\Contracts;

interface JobInterface
{
    public const STATE_PENDING = 'pending';
    public const STATE_PROCESSING = 'processing';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';
    public const STATE_RETRYING = 'retrying';
    public const STATE_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_HIGH = 10;
    public const PRIORITY_URGENT = 15;

    public function handle(): void;

    public function getId(): string;

    public function getPayload(): array;

    public function getState(): string;

    public function setState(string $state): void;

    public function getPriority(): int;

    public function getQueue(): string;

    public function getAttempts(): int;

    public function incrementAttempts(): void;

    public function getMaxAttempts(): int;

    public function getTimeout(): int;

    public function getDelay(): int;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function getFailedAt(): ?\DateTimeImmutable;

    public function setFailedAt(?\DateTimeImmutable $failedAt): void;

    public function getCompletedAt(): ?\DateTimeImmutable;

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void;

    public function getException(): ?\Throwable;

    public function setException(?\Throwable $exception): void;

    public function getDependencies(): array;

    public function getTags(): array;

    public function toArray(): array;

    public static function fromArray(array $data): self;
}
