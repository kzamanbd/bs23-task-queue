<?php

declare(strict_types=1);

namespace TaskQueue\Distributed;

use TaskQueue\Contracts\JobInterface;
use TaskQueue\Contracts\QueueDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FaultTolerance
{
    private QueueDriverInterface $queueDriver;
    private NodeDiscovery $nodeDiscovery;
    private LoggerInterface $logger;
    private array $duplicateJobs = [];
    private array $networkPartitions = [];
    private int $partitionTimeout = 300; // 5 minutes
    private array $consistencyChecks = [];

    public function __construct(
        QueueDriverInterface $queueDriver,
        NodeDiscovery $nodeDiscovery,
        LoggerInterface $logger = null
    ) {
        $this->queueDriver = $queueDriver;
        $this->nodeDiscovery = $nodeDiscovery;
        $this->logger = $logger ?? new NullLogger();
    }

    public function ensureIdempotency(JobInterface $job): bool
    {
        $jobId = $job->getId();
        
        // Check if job was already processed
        if (isset($this->duplicateJobs[$jobId])) {
            $this->logger->warning('Duplicate job detected', [
                'job_id' => $jobId,
                'original_time' => $this->duplicateJobs[$jobId]
            ]);
            return false;
        }

        // Mark job as being processed
        $this->duplicateJobs[$jobId] = time();

        // Clean up old entries (older than 1 hour)
        $this->cleanupDuplicateTracking();

        return true;
    }

    public function handleNetworkPartition(string $nodeId, bool $isPartitioned): void
    {
        if ($isPartitioned) {
            $this->networkPartitions[$nodeId] = time();
            $this->logger->warning('Network partition detected', [
                'node_id' => $nodeId,
                'timestamp' => time()
            ]);
        } else {
            unset($this->networkPartitions[$nodeId]);
            $this->logger->info('Network partition resolved', [
                'node_id' => $nodeId
            ]);
        }
    }

    public function isNodePartitioned(string $nodeId): bool
    {
        if (!isset($this->networkPartitions[$nodeId])) {
            return false;
        }

        $partitionTime = $this->networkPartitions[$nodeId];
        $timeSincePartition = time() - $partitionTime;

        // If partition is older than timeout, consider it resolved
        if ($timeSincePartition > $this->partitionTimeout) {
            unset($this->networkPartitions[$nodeId]);
            return false;
        }

        return true;
    }

    public function handleNodeFailure(string $nodeId, string $reason = 'unknown'): void
    {
        $node = $this->nodeDiscovery->getNode($nodeId);
        if (!$node) {
            return;
        }

        $this->logger->error('Node failure detected', [
            'node_id' => $nodeId,
            'reason' => $reason,
            'address' => $node->getAddress()
        ]);

        // Mark node as inactive
        $node->setActive(false);

        // Redistribute any pending jobs from this node
        $this->redistributeJobsFromNode($nodeId);
    }

    private function redistributeJobsFromNode(string $nodeId): void
    {
        // This would typically involve:
        // 1. Finding jobs assigned to the failed node
        // 2. Moving them back to pending state
        // 3. Reassigning them to healthy nodes
        
        $this->logger->info('Redistributing jobs from failed node', [
            'failed_node_id' => $nodeId
        ]);
    }

    public function ensureDataConsistency(): array
    {
        $inconsistencies = [];
        $stats = $this->queueDriver->getStats();
        $activeNodes = $this->nodeDiscovery->getActiveNodes();

        // Check if queue statistics match across nodes
        $expectedTotalJobs = 0;
        foreach ($stats as $queueStats) {
            $expectedTotalJobs += $queueStats['total_jobs'];
        }

        // Check for orphaned jobs
        $orphanedJobs = $this->findOrphanedJobs();
        if (!empty($orphanedJobs)) {
            $inconsistencies[] = [
                'type' => 'orphaned_jobs',
                'count' => count($orphanedJobs),
                'jobs' => $orphanedJobs
            ];
        }

        // Check for stuck jobs
        $stuckJobs = $this->findStuckJobs();
        if (!empty($stuckJobs)) {
            $inconsistencies[] = [
                'type' => 'stuck_jobs',
                'count' => count($stuckJobs),
                'jobs' => $stuckJobs
            ];
        }

        // Check for duplicate jobs
        $duplicateJobs = $this->findDuplicateJobs();
        if (!empty($duplicateJobs)) {
            $inconsistencies[] = [
                'type' => 'duplicate_jobs',
                'count' => count($duplicateJobs),
                'jobs' => $duplicateJobs
            ];
        }

        return $inconsistencies;
    }

    private function findOrphanedJobs(): array
    {
        // Jobs that are in processing state but no active worker is handling them
        $orphanedJobs = [];
        
        // This would typically involve querying the database for jobs
        // that are in processing state but haven't been updated recently
        
        return $orphanedJobs;
    }

    private function findStuckJobs(): array
    {
        // Jobs that have been processing for too long
        $stuckJobs = [];
        
        // This would typically involve finding jobs that have been
        // in processing state for longer than their timeout
        
        return $stuckJobs;
    }

    private function findDuplicateJobs(): array
    {
        // Jobs with identical content that might have been duplicated
        $duplicateJobs = [];
        
        // This would involve checking for jobs with identical payloads
        // and creation times within a small window
        
        return $duplicateJobs;
    }

    public function recoverFromFailure(array $inconsistencies): array
    {
        $recoveryActions = [];

        foreach ($inconsistencies as $inconsistency) {
            switch ($inconsistency['type']) {
                case 'orphaned_jobs':
                    $recoveryActions[] = $this->recoverOrphanedJobs($inconsistency['jobs']);
                    break;
                case 'stuck_jobs':
                    $recoveryActions[] = $this->recoverStuckJobs($inconsistency['jobs']);
                    break;
                case 'duplicate_jobs':
                    $recoveryActions[] = $this->recoverDuplicateJobs($inconsistency['jobs']);
                    break;
            }
        }

        return $recoveryActions;
    }

    private function recoverOrphanedJobs(array $jobs): array
    {
        $actions = [];
        
        foreach ($jobs as $job) {
            // Reset job to pending state
            $actions[] = [
                'action' => 'reset_to_pending',
                'job_id' => $job['id'],
                'reason' => 'Orphaned job recovery'
            ];
        }

        $this->logger->info('Recovering orphaned jobs', [
            'count' => count($jobs)
        ]);

        return $actions;
    }

    private function recoverStuckJobs(array $jobs): array
    {
        $actions = [];
        
        foreach ($jobs as $job) {
            // Mark job as failed and retry if possible
            $actions[] = [
                'action' => 'mark_as_failed',
                'job_id' => $job['id'],
                'reason' => 'Stuck job recovery'
            ];
        }

        $this->logger->info('Recovering stuck jobs', [
            'count' => count($jobs)
        ]);

        return $actions;
    }

    private function recoverDuplicateJobs(array $jobs): array
    {
        $actions = [];
        
        foreach ($jobs as $job) {
            // Remove duplicate jobs, keeping only the first one
            $actions[] = [
                'action' => 'remove_duplicate',
                'job_id' => $job['id'],
                'reason' => 'Duplicate job removal'
            ];
        }

        $this->logger->info('Recovering duplicate jobs', [
            'count' => count($jobs)
        ]);

        return $actions;
    }

    public function addConsistencyCheck(string $name, callable $check): void
    {
        $this->consistencyChecks[$name] = $check;
        
        $this->logger->info('Consistency check added', [
            'check_name' => $name
        ]);
    }

    public function runConsistencyChecks(): array
    {
        $results = [];

        foreach ($this->consistencyChecks as $name => $check) {
            try {
                $result = $check($this->queueDriver, $this->nodeDiscovery);
                $results[$name] = $result;
            } catch (\Throwable $e) {
                $this->logger->error('Consistency check failed', [
                    'check_name' => $name,
                    'error' => $e->getMessage()
                ]);
                $results[$name] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function cleanupDuplicateTracking(): void
    {
        $oneHourAgo = time() - 3600;
        
        $this->duplicateJobs = array_filter(
            $this->duplicateJobs,
            fn($timestamp) => $timestamp > $oneHourAgo,
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function setPartitionTimeout(int $seconds): void
    {
        $this->partitionTimeout = $seconds;
    }

    public function getStats(): array
    {
        return [
            'duplicate_jobs_tracked' => count($this->duplicateJobs),
            'active_partitions' => count($this->networkPartitions),
            'partition_timeout' => $this->partitionTimeout,
            'consistency_checks' => count($this->consistencyChecks)
        ];
    }
}
