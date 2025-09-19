<?php

declare(strict_types=1);

namespace TaskQueue\Drivers;

use PDO;
use TaskQueue\Contracts\JobInterface;
use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Jobs\AbstractJob;
use TaskQueue\Support\Encryption;
use TaskQueue\Support\Compression;

class DatabaseQueueDriver implements QueueDriverInterface
{
    private PDO $pdo;
    private Encryption $encryption;
    private bool $compressionEnabled;
    private string $tableName;
    private bool $connected = false;

    public function __construct(
        PDO $pdo,
        Encryption $encryption,
        bool $compressionEnabled = true,
        string $tableName = 'job_queue'
    ) {
        $this->pdo = $pdo;
        $this->encryption = $encryption;
        $this->compressionEnabled = $compressionEnabled;
        $this->tableName = $tableName;
    }

    public function connect(): void
    {
        $this->createTableIfNotExists();
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function push(JobInterface $job): void
    {
        $this->ensureConnected();

        $data = $job->toArray();
        $payload = json_encode($data['payload']);
        
        if ($this->compressionEnabled && strlen($payload) > 1024) {
            $payload = Compression::compress($payload);
        }

        $encryptedPayload = $this->encryption->encrypt($payload);

        $sql = "INSERT INTO {$this->tableName} (
            id, payload, state, priority, queue_name, attempts, max_attempts, 
            timeout_seconds, delay_seconds, dependencies, tags, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $job->getId(),
            $encryptedPayload,
            $job->getState(),
            $job->getPriority(),
            $job->getQueue(),
            $job->getAttempts(),
            $job->getMaxAttempts(),
            $job->getTimeout(),
            $job->getDelay(),
            json_encode($job->getDependencies()),
            json_encode($job->getTags()),
            $job->getCreatedAt()->format('Y-m-d H:i:s'),
            $job->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function pop(string $queue): ?JobInterface
    {
        $this->ensureConnected();

        $sql = "SELECT * FROM {$this->tableName} 
                WHERE queue_name = ? AND state = ? AND (delay_seconds = 0 OR created_at <= ?)
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $queue,
            JobInterface::STATE_PENDING,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        // Update job state to processing
        $updateSql = "UPDATE {$this->tableName} SET state = ?, updated_at = ? WHERE id = ?";
        $updateStmt = $this->pdo->prepare($updateSql);
        $updateStmt->execute([
            JobInterface::STATE_PROCESSING,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $row['id']
        ]);

        return $this->rowToJob($row);
    }

    public function peek(string $queue): ?JobInterface
    {
        $this->ensureConnected();

        $sql = "SELECT * FROM {$this->tableName} 
                WHERE queue_name = ? AND state = ? AND (delay_seconds = 0 OR created_at <= ?)
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $queue,
            JobInterface::STATE_PENDING,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->rowToJob($row) : null;
    }

    public function delete(JobInterface $job): void
    {
        $this->ensureConnected();

        $sql = "DELETE FROM {$this->tableName} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$job->getId()]);
    }

    public function update(JobInterface $job): void
    {
        $this->ensureConnected();

        $sql = "UPDATE {$this->tableName} SET 
                state = ?, 
                attempts = ?, 
                updated_at = ?, 
                completed_at = ?, 
                failed_at = ?,
                exception = ?
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $job->getState(),
            $job->getAttempts(),
            $job->getUpdatedAt()->format('Y-m-d H:i:s'),
            $job->getCompletedAt() ? $job->getCompletedAt()->format('Y-m-d H:i:s') : null,
            $job->getFailedAt() ? $job->getFailedAt()->format('Y-m-d H:i:s') : null,
            $job->getException() ? $job->getException()->getMessage() : null,
            $job->getId()
        ]);
    }

    public function cleanupOldCompletedJobs(int $hoursOld = 1): int
    {
        $this->ensureConnected();

        $sql = "DELETE FROM {$this->tableName} 
                WHERE state = 'completed' 
                AND completed_at < datetime('now', '-{$hoursOld} hours')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        $this->ensureConnected();

        $sql = "UPDATE {$this->tableName} 
                SET state = ?, delay_seconds = ?, updated_at = ? 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            JobInterface::STATE_PENDING,
            $delay,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $job->getId()
        ]);
    }

    public function size(string $queue): int
    {
        $this->ensureConnected();

        $sql = "SELECT COUNT(*) FROM {$this->tableName} WHERE queue_name = ? AND state = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$queue, JobInterface::STATE_PENDING]);
        
        return (int) $stmt->fetchColumn();
    }

    public function purge(string $queue): void
    {
        $this->ensureConnected();

        $sql = "DELETE FROM {$this->tableName} WHERE queue_name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$queue]);
    }

    public function getFailedJobs(string $queue = null): array
    {
        $this->ensureConnected();

        $sql = "SELECT * FROM {$this->tableName} WHERE state = ?";
        $params = [JobInterface::STATE_FAILED];

        if ($queue !== null) {
            $sql .= " AND queue_name = ?";
            $params[] = $queue;
        }

        $sql .= " ORDER BY updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $jobs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobs[] = $this->rowToJob($row);
        }

        return $jobs;
    }

    public function retryFailedJob(string $jobId): bool
    {
        $this->ensureConnected();

        $sql = "UPDATE {$this->tableName} 
                SET state = ?, attempts = 0, failed_at = NULL, updated_at = ? 
                WHERE id = ? AND state = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            JobInterface::STATE_PENDING,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $jobId,
            JobInterface::STATE_FAILED
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getJobById(string $jobId): ?JobInterface
    {
        $this->ensureConnected();

        $sql = "SELECT * FROM {$this->tableName} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jobId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->rowToJob($row) : null;
    }

    public function getJobsByState(string $state, string $queue = null, int $limit = 100): array
    {
        $this->ensureConnected();

        $sql = "SELECT * FROM {$this->tableName} WHERE state = ?";
        $params = [$state];

        if ($queue !== null) {
            $sql .= " AND queue_name = ?";
            $params[] = $queue;
        }

        $sql .= " ORDER BY updated_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $jobs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobs[] = $this->rowToJob($row);
        }

        return $jobs;
    }

    public function getQueueStats(string $queue = null): array
    {
        $this->ensureConnected();

        $sql = "SELECT 
                    queue_name,
                    state,
                    COUNT(*) as count,
                    AVG(priority) as avg_priority,
                    MIN(created_at) as oldest_job,
                    MAX(created_at) as newest_job
                FROM {$this->tableName}";

        $params = [];
        if ($queue !== null) {
            $sql .= " WHERE queue_name = ?";
            $params[] = $queue;
        }

        $sql .= " GROUP BY queue_name, state ORDER BY queue_name, state";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $queueName = $row['queue_name'];
            $state = $row['state'];
            
            if (!isset($stats[$queueName])) {
                $stats[$queueName] = [
                    'total_jobs' => 0,
                    'by_state' => [],
                    'avg_priority' => 0,
                    'oldest_job' => null,
                    'newest_job' => null,
                ];
            }

            $stats[$queueName]['by_state'][$state] = (int) $row['count'];
            $stats[$queueName]['total_jobs'] += (int) $row['count'];
            $stats[$queueName]['avg_priority'] = (float) $row['avg_priority'];
            $stats[$queueName]['oldest_job'] = $row['oldest_job'];
            $stats[$queueName]['newest_job'] = $row['newest_job'];
        }

        return $stats;
    }

    private function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id VARCHAR(255) PRIMARY KEY,
            payload TEXT NOT NULL,
            state VARCHAR(50) NOT NULL DEFAULT 'pending',
            priority INT NOT NULL DEFAULT 5,
            queue_name VARCHAR(100) NOT NULL DEFAULT 'default',
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            timeout_seconds INT NOT NULL DEFAULT 60,
            delay_seconds INT NOT NULL DEFAULT 0,
            dependencies TEXT,
            tags TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            failed_at DATETIME NULL,
            completed_at DATETIME NULL,
            exception TEXT NULL
        )";

        $this->pdo->exec($sql);

        // Create indexes separately for SQLite compatibility
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_queue_state ON {$this->tableName} (queue_name, state)",
            "CREATE INDEX IF NOT EXISTS idx_priority ON {$this->tableName} (priority)",
            "CREATE INDEX IF NOT EXISTS idx_created_at ON {$this->tableName} (created_at)",
            "CREATE INDEX IF NOT EXISTS idx_state ON {$this->tableName} (state)"
        ];

        foreach ($indexes as $indexSql) {
            $this->pdo->exec($indexSql);
        }
    }

    private function rowToJob(array $row): JobInterface
    {
        $payload = $this->encryption->decrypt($row['payload']);
        
        if ($this->compressionEnabled && Compression::isCompressed($payload)) {
            $payload = Compression::decompress($payload);
        }

        $jobData = [
            'id' => $row['id'],
            'payload' => json_decode($payload, true) ?: [],
            'state' => $row['state'],
            'priority' => (int) $row['priority'],
            'queue' => $row['queue_name'],
            'attempts' => (int) $row['attempts'],
            'max_attempts' => (int) $row['max_attempts'],
            'timeout' => (int) $row['timeout_seconds'],
            'delay' => (int) $row['delay_seconds'],
            'dependencies' => json_decode($row['dependencies'], true) ?: [],
            'tags' => json_decode($row['tags'], true) ?: [],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];

        if ($row['failed_at']) {
            $jobData['failed_at'] = $row['failed_at'];
        }

        if ($row['completed_at']) {
            $jobData['completed_at'] = $row['completed_at'];
        }

        if ($row['exception']) {
            $jobData['exception'] = $row['exception'];
        }

        return AbstractJob::fromArray($jobData);
    }

    private function ensureConnected(): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Database queue driver is not connected');
        }
    }

    public function getStats(): array
    {
        $this->ensureConnected();

        $sql = "SELECT 
                    queue_name,
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN state = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN state = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN state = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN state = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(priority) as avg_priority,
                    MIN(created_at) as oldest_job,
                    MAX(created_at) as newest_job
                FROM {$this->tableName} 
                GROUP BY queue_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $queueName = $row['queue_name'];
            $stats[$queueName] = [
                'total_jobs' => (int) $row['total_jobs'],
                'by_state' => [
                    'pending' => (int) $row['pending'],
                    'processing' => (int) $row['processing'],
                    'completed' => (int) $row['completed'],
                    'failed' => (int) $row['failed']
                ],
                'avg_priority' => round((float) $row['avg_priority'], 2),
                'oldest_job' => $row['oldest_job'],
                'newest_job' => $row['newest_job']
            ];
        }

        return $stats;
    }
}
