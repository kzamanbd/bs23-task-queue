<?php

declare(strict_types=1);

namespace TaskQueue\Distributed;

use TaskQueue\Contracts\QueueDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ResourceManager
{
    private NodeDiscovery $nodeDiscovery;
    private QueueDriverInterface $queueDriver;
    private LoggerInterface $logger;
    private array $resourceQuotas = [];
    private array $scalingRules = [];
    private int $minWorkers = 1;
    private int $maxWorkers = 10;
    private float $scaleUpThreshold = 0.8;
    private float $scaleDownThreshold = 0.3;
    private int $scaleCheckInterval = 30; // seconds
    private int $lastScaleCheck = 0;

    public function __construct(
        NodeDiscovery $nodeDiscovery,
        QueueDriverInterface $queueDriver,
        LoggerInterface $logger = null
    ) {
        $this->nodeDiscovery = $nodeDiscovery;
        $this->queueDriver = $queueDriver;
        $this->logger = $logger ?? new NullLogger();
    }

    public function setResourceQuota(string $resource, int $limit): void
    {
        $this->resourceQuotas[$resource] = $limit;
        
        $this->logger->info('Resource quota set', [
            'resource' => $resource,
            'limit' => $limit
        ]);
    }

    public function getResourceQuota(string $resource): ?int
    {
        return $this->resourceQuotas[$resource] ?? null;
    }

    public function checkResourceUsage(string $resource): float
    {
        $quota = $this->getResourceQuota($resource);
        if ($quota === null) {
            return 0.0; // No quota set
        }

        $usage = $this->getCurrentResourceUsage($resource);
        return $usage / $quota;
    }

    private function getCurrentResourceUsage(string $resource): int
    {
        switch ($resource) {
            case 'memory':
                return $this->getTotalMemoryUsage();
            case 'cpu':
                return $this->getTotalCpuUsage();
            case 'workers':
                return count($this->nodeDiscovery->getActiveNodes());
            case 'jobs':
                return $this->getTotalQueuedJobs();
            default:
                return 0;
        }
    }

    private function getTotalMemoryUsage(): int
    {
        $totalMemory = 0;
        foreach ($this->nodeDiscovery->getActiveNodes() as $node) {
            $totalMemory += $node->getMemoryUsage();
        }
        return $totalMemory;
    }

    private function getTotalCpuUsage(): int
    {
        $totalLoad = 0;
        $nodeCount = 0;
        
        foreach ($this->nodeDiscovery->getActiveNodes() as $node) {
            $totalLoad += $node->getLoad();
            $nodeCount++;
        }
        
        return $nodeCount > 0 ? (int) ($totalLoad / $nodeCount) : 0;
    }

    private function getTotalQueuedJobs(): int
    {
        $stats = $this->queueDriver->getStats();
        $totalJobs = 0;
        
        foreach ($stats as $queueStats) {
            $totalJobs += $queueStats['total_jobs'];
        }
        
        return $totalJobs;
    }

    public function shouldScaleUp(): bool
    {
        $currentTime = time();
        if ($currentTime - $this->lastScaleCheck < $this->scaleCheckInterval) {
            return false;
        }

        $this->lastScaleCheck = $currentTime;

        // Check if we're at max workers
        $currentWorkers = count($this->nodeDiscovery->getActiveNodes());
        if ($currentWorkers >= $this->maxWorkers) {
            return false;
        }

        // Check queue depth
        $totalJobs = $this->getTotalQueuedJobs();
        $pendingJobs = $this->getPendingJobsCount();
        
        if ($pendingJobs > 0 && $currentWorkers < $this->maxWorkers) {
            $loadRatio = $pendingJobs / max($currentWorkers, 1);
            if ($loadRatio > $this->scaleUpThreshold * 10) { // 10 jobs per worker threshold
                $this->logger->info('Scale up triggered by queue depth', [
                    'pending_jobs' => $pendingJobs,
                    'current_workers' => $currentWorkers,
                    'load_ratio' => $loadRatio
                ]);
                return true;
            }
        }

        // Check resource usage
        foreach ($this->resourceQuotas as $resource => $quota) {
            $usage = $this->checkResourceUsage($resource);
            if ($usage > $this->scaleUpThreshold) {
                $this->logger->info('Scale up triggered by resource usage', [
                    'resource' => $resource,
                    'usage' => $usage,
                    'threshold' => $this->scaleUpThreshold
                ]);
                return true;
            }
        }

        return false;
    }

    public function shouldScaleDown(): bool
    {
        $currentTime = time();
        if ($currentTime - $this->lastScaleCheck < $this->scaleCheckInterval) {
            return false;
        }

        // Check if we're at min workers
        $currentWorkers = count($this->nodeDiscovery->getActiveNodes());
        if ($currentWorkers <= $this->minWorkers) {
            return false;
        }

        // Check if queue is mostly empty
        $pendingJobs = $this->getPendingJobsCount();
        $loadRatio = $pendingJobs / max($currentWorkers, 1);
        
        if ($loadRatio < $this->scaleDownThreshold) {
            $this->logger->info('Scale down triggered by low load', [
                'pending_jobs' => $pendingJobs,
                'current_workers' => $currentWorkers,
                'load_ratio' => $loadRatio
            ]);
            return true;
        }

        // Check resource usage
        $allResourcesLow = true;
        foreach ($this->resourceQuotas as $resource => $quota) {
            $usage = $this->checkResourceUsage($resource);
            if ($usage > $this->scaleDownThreshold) {
                $allResourcesLow = false;
                break;
            }
        }

        if ($allResourcesLow && !empty($this->resourceQuotas)) {
            $this->logger->info('Scale down triggered by low resource usage');
            return true;
        }

        return false;
    }

    private function getPendingJobsCount(): int
    {
        $stats = $this->queueDriver->getStats();
        $pendingJobs = 0;
        
        foreach ($stats as $queueStats) {
            $pendingJobs += $queueStats['by_state']['pending'] ?? 0;
        }
        
        return $pendingJobs;
    }

    public function addScalingRule(string $name, callable $rule): void
    {
        $this->scalingRules[$name] = $rule;
        
        $this->logger->info('Scaling rule added', [
            'rule_name' => $name
        ]);
    }

    public function evaluateScalingRules(): array
    {
        $recommendations = [];

        foreach ($this->scalingRules as $name => $rule) {
            try {
                $result = $rule($this->nodeDiscovery, $this->queueDriver);
                if (is_array($result)) {
                    $recommendations[$name] = $result;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Scaling rule failed', [
                    'rule_name' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $recommendations;
    }

    public function optimizeQueueDistribution(): array
    {
        $stats = $this->queueDriver->getStats();
        $nodes = $this->nodeDiscovery->getActiveNodes();
        
        if (empty($nodes)) {
            return [];
        }

        $optimizations = [];
        $totalCapacity = 0;
        $totalLoad = 0;

        foreach ($nodes as $node) {
            $capacity = 100 - $node->getLoad(); // Available capacity
            $totalCapacity += max(0, $capacity);
            $totalLoad += $node->getLoad();
        }

        $avgLoad = $totalLoad / count($nodes);

        foreach ($nodes as $node) {
            $nodeLoad = $node->getLoad();
            $capacity = 100 - $nodeLoad;
            
            if ($nodeLoad > $avgLoad * 1.5) { // Overloaded
                $optimizations[] = [
                    'type' => 'reduce_load',
                    'node_id' => $node->getId(),
                    'current_load' => $nodeLoad,
                    'recommendation' => 'Consider redistributing jobs or adding more workers'
                ];
            } elseif ($capacity > $avgLoad * 1.5) { // Underutilized
                $optimizations[] = [
                    'type' => 'increase_load',
                    'node_id' => $node->getId(),
                    'current_load' => $nodeLoad,
                    'recommendation' => 'Can handle more jobs'
                ];
            }
        }

        return $optimizations;
    }

    public function setScalingLimits(int $minWorkers, int $maxWorkers): void
    {
        $this->minWorkers = max(1, $minWorkers);
        $this->maxWorkers = max($this->minWorkers, $maxWorkers);
        
        $this->logger->info('Scaling limits updated', [
            'min_workers' => $this->minWorkers,
            'max_workers' => $this->maxWorkers
        ]);
    }

    public function setScalingThresholds(float $scaleUpThreshold, float $scaleDownThreshold): void
    {
        $this->scaleUpThreshold = max(0.1, min(1.0, $scaleUpThreshold));
        $this->scaleDownThreshold = max(0.1, min(1.0, $scaleDownThreshold));
        
        if ($this->scaleUpThreshold <= $this->scaleDownThreshold) {
            throw new \InvalidArgumentException('Scale up threshold must be greater than scale down threshold');
        }
        
        $this->logger->info('Scaling thresholds updated', [
            'scale_up_threshold' => $this->scaleUpThreshold,
            'scale_down_threshold' => $this->scaleDownThreshold
        ]);
    }

    public function getStats(): array
    {
        $nodes = $this->nodeDiscovery->getActiveNodes();
        $stats = $this->queueDriver->getStats();
        
        $resourceUsage = [];
        foreach ($this->resourceQuotas as $resource => $quota) {
            $resourceUsage[$resource] = [
                'quota' => $quota,
                'usage' => $this->getCurrentResourceUsage($resource),
                'percentage' => $this->checkResourceUsage($resource) * 100
            ];
        }

        return [
            'active_nodes' => count($nodes),
            'min_workers' => $this->minWorkers,
            'max_workers' => $this->maxWorkers,
            'scale_up_threshold' => $this->scaleUpThreshold,
            'scale_down_threshold' => $this->scaleDownThreshold,
            'resource_usage' => $resourceUsage,
            'queue_stats' => $stats,
            'scaling_recommendations' => $this->evaluateScalingRules(),
            'optimization_suggestions' => $this->optimizeQueueDistribution()
        ];
    }
}
