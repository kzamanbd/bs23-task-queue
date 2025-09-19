<?php

declare(strict_types=1);

namespace TaskQueue\Distributed;

class WorkerNode
{
    private string $id;
    private string $host;
    private int $port;
    private array $capabilities;
    private int $load;
    private int $memoryUsage;
    private int $jobsProcessed;
    private \DateTimeImmutable $lastHeartbeat;
    private bool $isActive;
    private array $specializations;

    public function __construct(
        string $id,
        string $host,
        int $port,
        array $capabilities = [],
        array $specializations = []
    ) {
        $this->id = $id;
        $this->host = $host;
        $this->port = $port;
        $this->capabilities = $capabilities;
        $this->specializations = $specializations;
        $this->load = 0;
        $this->memoryUsage = 0;
        $this->jobsProcessed = 0;
        $this->lastHeartbeat = new \DateTimeImmutable();
        $this->isActive = true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getAddress(): string
    {
        return "{$this->host}:{$this->port}";
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function addCapability(string $capability): void
    {
        if (!in_array($capability, $this->capabilities, true)) {
            $this->capabilities[] = $capability;
        }
    }

    public function removeCapability(string $capability): void
    {
        $this->capabilities = array_values(array_filter(
            $this->capabilities,
            fn($cap) => $cap !== $capability
        ));
    }

    public function getSpecializations(): array
    {
        return $this->specializations;
    }

    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specializations, true);
    }

    public function addSpecialization(string $specialization): void
    {
        if (!in_array($specialization, $this->specializations, true)) {
            $this->specializations[] = $specialization;
        }
    }

    public function removeSpecialization(string $specialization): void
    {
        $this->specializations = array_values(array_filter(
            $this->specializations,
            fn($spec) => $spec !== $specialization
        ));
    }

    public function getLoad(): int
    {
        return $this->load;
    }

    public function setLoad(int $load): void
    {
        $this->load = max(0, $load);
    }

    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(int $memoryUsage): void
    {
        $this->memoryUsage = max(0, $memoryUsage);
    }

    public function getJobsProcessed(): int
    {
        return $this->jobsProcessed;
    }

    public function setJobsProcessed(int $jobsProcessed): void
    {
        $this->jobsProcessed = max(0, $jobsProcessed);
    }

    public function incrementJobsProcessed(): void
    {
        $this->jobsProcessed++;
    }

    public function getLastHeartbeat(): \DateTimeImmutable
    {
        return $this->lastHeartbeat;
    }

    public function updateHeartbeat(): void
    {
        $this->lastHeartbeat = new \DateTimeImmutable();
        $this->isActive = true;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
    }

    public function isHealthy(int $heartbeatTimeoutSeconds = 60): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $timeSinceHeartbeat = time() - $this->lastHeartbeat->getTimestamp();
        return $timeSinceHeartbeat <= $heartbeatTimeoutSeconds;
    }

    public function getLoadScore(): float
    {
        // Calculate a load score based on multiple factors
        $loadFactor = $this->load / 100.0; // Normalize load (0-1)
        $memoryFactor = $this->memoryUsage / (1024 * 1024 * 1024); // Normalize memory (0-1 for 1GB)
        
        // Higher score = more loaded
        return ($loadFactor * 0.7) + ($memoryFactor * 0.3);
    }

    public function canHandleJob(array $jobRequirements = []): bool
    {
        // Check if node has required capabilities
        foreach ($jobRequirements as $requirement) {
            if (!$this->hasCapability($requirement)) {
                return false;
            }
        }

        // Check if node is healthy
        if (!$this->isHealthy()) {
            return false;
        }

        // Check if load is acceptable (configurable threshold)
        $maxLoad = $jobRequirements['max_load'] ?? 80;
        if ($this->load > $maxLoad) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'port' => $this->port,
            'capabilities' => $this->capabilities,
            'specializations' => $this->specializations,
            'load' => $this->load,
            'memory_usage' => $this->memoryUsage,
            'jobs_processed' => $this->jobsProcessed,
            'last_heartbeat' => $this->lastHeartbeat->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive,
            'load_score' => $this->getLoadScore()
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['host'],
            $data['port'],
            $data['capabilities'] ?? [],
            $data['specializations'] ?? []
        );

        $node->setLoad($data['load'] ?? 0);
        $node->setMemoryUsage($data['memory_usage'] ?? 0);
        $node->setJobsProcessed($data['jobs_processed'] ?? 0);
        $node->setActive($data['is_active'] ?? true);

        if (isset($data['last_heartbeat'])) {
            $node->lastHeartbeat = new \DateTimeImmutable($data['last_heartbeat']);
        }

        return $node;
    }

    public function __toString(): string
    {
        return "WorkerNode({$this->id}@{$this->getAddress()})";
    }
}
