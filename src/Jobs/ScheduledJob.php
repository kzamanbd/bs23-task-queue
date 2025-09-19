<?php

declare(strict_types=1);

namespace TaskQueue\Jobs;

use TaskQueue\Scheduling\CronExpression;
use TaskQueue\Scheduling\NaturalLanguageParser;

class ScheduledJob extends AbstractJob
{
    private CronExpression $cronExpression;
    private ?\DateTimeInterface $nextRunAt = null;
    private ?\DateTimeInterface $lastRunAt = null;
    private bool $isRecurring = true;
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct(array $payload = [], array $options = [])
    {
        parent::__construct($payload, $options);
        
        $this->isRecurring = $options['recurring'] ?? true;
        $this->expiresAt = $options['expires_at'] ?? null;
        
        if (isset($options['cron_expression'])) {
            $this->setCronExpression($options['cron_expression']);
        } elseif (isset($options['schedule'])) {
            $this->setSchedule($options['schedule']);
        }
    }

    public function setCronExpression(string $expression): void
    {
        $this->cronExpression = new CronExpression($expression);
        $this->calculateNextRun();
    }

    public function setSchedule(string $schedule): void
    {
        try {
            // Try parsing as natural language first
            $parser = new NaturalLanguageParser();
            $this->cronExpression = $parser->parse($schedule);
        } catch (\InvalidArgumentException $e) {
            // Fall back to cron expression
            $this->cronExpression = new CronExpression($schedule);
        }
        
        $this->calculateNextRun();
    }

    public function getCronExpression(): CronExpression
    {
        return $this->cronExpression;
    }

    public function getNextRunAt(): ?\DateTimeInterface
    {
        return $this->nextRunAt;
    }

    public function getLastRunAt(): ?\DateTimeInterface
    {
        return $this->lastRunAt;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setRecurring(bool $recurring): void
    {
        $this->isRecurring = $recurring;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function isDue(\DateTimeInterface $date = null): bool
    {
        $date = $date ?? new \DateTime();
        
        // Check if job has expired
        if ($this->expiresAt && $date > $this->expiresAt) {
            return false;
        }
        
        // Check if job is due according to cron expression
        return $this->cronExpression->isDue($date);
    }

    public function isExpired(\DateTimeInterface $date = null): bool
    {
        $date = $date ?? new \DateTime();
        return $this->expiresAt && $date > $this->expiresAt;
    }

    public function calculateNextRun(\DateTimeInterface $date = null): void
    {
        if (!$this->isRecurring) {
            $this->nextRunAt = null;
            return;
        }
        
        $date = $date ?? new \DateTime();
        $this->nextRunAt = $this->cronExpression->getNextRunDate($date);
    }

    public function markAsRun(\DateTimeInterface $date = null): void
    {
        $this->lastRunAt = $date ?? new \DateTime();
        
        if ($this->isRecurring) {
            $this->calculateNextRun($this->lastRunAt);
        } else {
            $this->nextRunAt = null;
        }
    }

    public function shouldReschedule(): bool
    {
        return $this->isRecurring && $this->nextRunAt !== null && !$this->isExpired();
    }

    public function handle(): void
    {
        // Mark as run before executing
        $this->markAsRun();
        
        // Store run information in payload
        $this->payload['last_run_at'] = $this->lastRunAt->format('Y-m-d H:i:s');
        $this->payload['next_run_at'] = $this->nextRunAt ? $this->nextRunAt->format('Y-m-d H:i:s') : null;
        $this->payload['run_count'] = ($this->payload['run_count'] ?? 0) + 1;
        
        // Call parent handle method
        parent::handle();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        
        $data['cron_expression'] = $this->cronExpression->getExpression();
        $data['next_run_at'] = $this->nextRunAt ? $this->nextRunAt->format('Y-m-d H:i:s') : null;
        $data['last_run_at'] = $this->lastRunAt ? $this->lastRunAt->format('Y-m-d H:i:s') : null;
        $data['recurring'] = $this->isRecurring;
        $data['expires_at'] = $this->expiresAt ? $this->expiresAt->format('Y-m-d H:i:s') : null;
        
        return $data;
    }

    public static function fromArray(array $data): self
    {
        $options = [
            'id' => $data['id'] ?? null,
            'state' => $data['state'] ?? self::STATE_PENDING,
            'priority' => $data['priority'] ?? self::PRIORITY_NORMAL,
            'queue' => $data['queue'] ?? 'default',
            'attempts' => $data['attempts'] ?? 0,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'timeout' => $data['timeout'] ?? 60,
            'delay' => $data['delay'] ?? 0,
            'dependencies' => $data['dependencies'] ?? [],
            'tags' => $data['tags'] ?? [],
            'recurring' => $data['recurring'] ?? true,
            'expires_at' => $data['expires_at'] ? new \DateTimeImmutable($data['expires_at']) : null,
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

        $job = new self($data['payload'] ?? [], $options);
        
        if (isset($data['cron_expression'])) {
            $job->setCronExpression($data['cron_expression']);
        }
        
        if (isset($data['next_run_at'])) {
            $job->nextRunAt = new \DateTimeImmutable($data['next_run_at']);
        }
        
        if (isset($data['last_run_at'])) {
            $job->lastRunAt = new \DateTimeImmutable($data['last_run_at']);
        }

        return $job;
    }
}
