<?php

declare(strict_types=1);

namespace TaskQueue\Jobs;

use TaskQueue\Contracts\JobInterface;
use TaskQueue\Support\Encryption;
use TaskQueue\Support\Compression;

abstract class AbstractJob implements JobInterface
{
    protected string $id;
    protected array $payload;
    protected string $state;
    protected int $priority;
    protected string $queue;
    protected int $attempts;
    protected int $maxAttempts;
    protected int $timeout;
    protected int $delay;
    protected \DateTimeImmutable $createdAt;
    protected \DateTimeImmutable $updatedAt;
    protected ?\DateTimeImmutable $failedAt = null;
    protected ?\DateTimeImmutable $completedAt = null;
    protected ?\Throwable $exception = null;
    protected array $dependencies = [];
    protected array $tags = [];

    public function __construct(array $payload = [], array $options = [])
    {
        $this->id = $options['id'] ?? uniqid('job_', true);
        $this->payload = $payload;
        $this->state = $options['state'] ?? self::STATE_PENDING;
        $this->priority = $options['priority'] ?? self::PRIORITY_NORMAL;
        $this->queue = $options['queue'] ?? 'default';
        $this->attempts = $options['attempts'] ?? 0;
        $this->maxAttempts = $options['max_attempts'] ?? 3;
        $this->timeout = $options['timeout'] ?? 60;
        $this->delay = $options['delay'] ?? 0;
        $this->dependencies = $options['dependencies'] ?? [];
        $this->tags = $options['tags'] ?? [];
        
        $now = new \DateTimeImmutable();
        $this->createdAt = $options['created_at'] ?? $now;
        $this->updatedAt = $options['updated_at'] ?? $now;
        
        if (isset($options['failed_at'])) {
            $this->failedAt = $options['failed_at'];
        }
        if (isset($options['completed_at'])) {
            $this->completedAt = $options['completed_at'];
        }
        if (isset($options['exception'])) {
            $this->exception = $options['exception'];
        }
    }

    public function handle(): void
    {
        // Override in concrete job classes
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(?\DateTimeImmutable $failedAt): void
    {
        $this->failedAt = $failedAt;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        $this->exception = $exception;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'payload' => $this->payload,
            'state' => $this->state,
            'priority' => $this->priority,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'timeout' => $this->timeout,
            'delay' => $this->delay,
            'dependencies' => $this->dependencies,
            'tags' => $this->tags,
            'class' => static::class,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];

        if ($this->failedAt) {
            $data['failed_at'] = $this->failedAt->format('Y-m-d H:i:s');
        }

        if ($this->completedAt) {
            $data['completed_at'] = $this->completedAt->format('Y-m-d H:i:s');
        }

        if ($this->exception) {
            $data['exception'] = [
                'message' => $this->exception->getMessage(),
                'trace' => $this->exception->getTraceAsString(),
            ];
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $options = [
            'state' => $data['state'] ?? self::STATE_PENDING,
            'priority' => (int) ($data['priority'] ?? self::PRIORITY_NORMAL),
            'queue' => $data['queue'] ?? 'default',
            'attempts' => (int) ($data['attempts'] ?? 0),
            'max_attempts' => (int) ($data['max_attempts'] ?? 3),
            'timeout' => (int) ($data['timeout'] ?? 60),
            'delay' => (int) ($data['delay'] ?? 0),
            'dependencies' => $data['dependencies'] ?? [],
            'tags' => $data['tags'] ?? [],
        ];

        if (isset($data['created_at'])) {
            $options['created_at'] = new \DateTimeImmutable($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $options['updated_at'] = new \DateTimeImmutable($data['updated_at']);
        }
        if (isset($data['failed_at'])) {
            $options['failed_at'] = new \DateTimeImmutable($data['failed_at']);
        }
        if (isset($data['completed_at'])) {
            $options['completed_at'] = new \DateTimeImmutable($data['completed_at']);
        }

        // Use the concrete class if available, otherwise use TestJob as fallback
        $className = $data['class'] ?? TestJob::class;
        if (!class_exists($className)) {
            $className = TestJob::class;
        }

        $job = new $className($data['payload'] ?? [], $options);
        $job->id = $data['id'] ?? uniqid('job_', true);

        return $job;
    }

    public function isExpired(): bool
    {
        if ($this->delay === 0) {
            return false;
        }

        return $this->createdAt->modify("+{$this->delay} seconds") < new \DateTimeImmutable();
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->state === self::STATE_PROCESSING;
    }

    public function isCancelled(): bool
    {
        return $this->state === self::STATE_CANCELLED;
    }
}
