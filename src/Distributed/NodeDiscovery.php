<?php

declare(strict_types=1);

namespace TaskQueue\Distributed;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NodeDiscovery
{
    private array $nodes = [];
    private LoggerInterface $logger;
    private int $heartbeatTimeoutSeconds;
    private string $localNodeId;
    private array $discoveryMethods = [];

    public function __construct(
        string $localNodeId,
        LoggerInterface $logger = null,
        int $heartbeatTimeoutSeconds = 60
    ) {
        $this->localNodeId = $localNodeId;
        $this->logger = $logger ?? new NullLogger();
        $this->heartbeatTimeoutSeconds = $heartbeatTimeoutSeconds;
    }

    public function registerNode(WorkerNode $node): void
    {
        $this->nodes[$node->getId()] = $node;
        
        $this->logger->info('Node registered', [
            'node_id' => $node->getId(),
            'address' => $node->getAddress(),
            'capabilities' => $node->getCapabilities(),
            'specializations' => $node->getSpecializations()
        ]);
    }

    public function unregisterNode(string $nodeId): void
    {
        if (isset($this->nodes[$nodeId])) {
            unset($this->nodes[$nodeId]);
            
            $this->logger->info('Node unregistered', [
                'node_id' => $nodeId
            ]);
        }
    }

    public function updateNodeHeartbeat(string $nodeId, array $status = []): void
    {
        if (!isset($this->nodes[$nodeId])) {
            return;
        }

        $node = $this->nodes[$nodeId];
        $node->updateHeartbeat();

        if (isset($status['load'])) {
            $node->setLoad($status['load']);
        }

        if (isset($status['memory_usage'])) {
            $node->setMemoryUsage($status['memory_usage']);
        }

        if (isset($status['jobs_processed'])) {
            $node->setJobsProcessed($status['jobs_processed']);
        }

        $this->nodes[$nodeId] = $node;
    }

    public function getNode(string $nodeId): ?WorkerNode
    {
        return $this->nodes[$nodeId] ?? null;
    }

    public function getAllNodes(): array
    {
        return $this->nodes;
    }

    public function getActiveNodes(): array
    {
        return array_filter($this->nodes, function(WorkerNode $node) {
            return $node->isHealthy($this->heartbeatTimeoutSeconds);
        });
    }

    public function getNodesByCapability(string $capability): array
    {
        return array_filter($this->getActiveNodes(), function(WorkerNode $node) use ($capability) {
            return $node->hasCapability($capability);
        });
    }

    public function getNodesBySpecialization(string $specialization): array
    {
        return array_filter($this->getActiveNodes(), function(WorkerNode $node) use ($specialization) {
            return $node->hasSpecialization($specialization);
        });
    }

    public function findBestNode(array $requirements = []): ?WorkerNode
    {
        $candidateNodes = $this->getActiveNodes();

        // Filter by capabilities
        if (isset($requirements['capabilities'])) {
            $candidateNodes = array_filter($candidateNodes, function(WorkerNode $node) use ($requirements) {
                foreach ($requirements['capabilities'] as $capability) {
                    if (!$node->hasCapability($capability)) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Filter by specializations
        if (isset($requirements['specializations'])) {
            $candidateNodes = array_filter($candidateNodes, function(WorkerNode $node) use ($requirements) {
                foreach ($requirements['specializations'] as $specialization) {
                    if (!$node->hasSpecialization($specialization)) {
                        return false;
                    }
                }
                return true;
            });
        }

        if (empty($candidateNodes)) {
            return null;
        }

        // Sort by load score (ascending - lower is better)
        usort($candidateNodes, function(WorkerNode $a, WorkerNode $b) {
            return $a->getLoadScore() <=> $b->getLoadScore();
        });

        return $candidateNodes[0];
    }

    public function getLoadBalancedNodes(array $requirements = [], int $count = 1): array
    {
        $candidateNodes = $this->getActiveNodes();

        // Apply filters
        if (isset($requirements['capabilities'])) {
            $candidateNodes = array_filter($candidateNodes, function(WorkerNode $node) use ($requirements) {
                foreach ($requirements['capabilities'] as $capability) {
                    if (!$node->hasCapability($capability)) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Sort by load score
        usort($candidateNodes, function(WorkerNode $a, WorkerNode $b) {
            return $a->getLoadScore() <=> $b->getLoadScore();
        });

        return array_slice($candidateNodes, 0, $count);
    }

    public function cleanupDeadNodes(): array
    {
        $deadNodes = [];

        foreach ($this->nodes as $nodeId => $node) {
            if (!$node->isHealthy($this->heartbeatTimeoutSeconds)) {
                $node->setActive(false);
                $deadNodes[] = $nodeId;
                
                $this->logger->warning('Node marked as dead', [
                    'node_id' => $nodeId,
                    'address' => $node->getAddress(),
                    'last_heartbeat' => $node->getLastHeartbeat()->format('Y-m-d H:i:s')
                ]);
            }
        }

        return $deadNodes;
    }

    public function getStats(): array
    {
        $activeNodes = $this->getActiveNodes();
        $totalNodes = count($this->nodes);
        $deadNodes = count($this->nodes) - count($activeNodes);

        $totalLoad = 0;
        $totalMemory = 0;
        $totalJobsProcessed = 0;

        foreach ($activeNodes as $node) {
            $totalLoad += $node->getLoad();
            $totalMemory += $node->getMemoryUsage();
            $totalJobsProcessed += $node->getJobsProcessed();
        }

        $avgLoad = count($activeNodes) > 0 ? $totalLoad / count($activeNodes) : 0;
        $avgMemory = count($activeNodes) > 0 ? $totalMemory / count($activeNodes) : 0;

        return [
            'total_nodes' => $totalNodes,
            'active_nodes' => count($activeNodes),
            'dead_nodes' => $deadNodes,
            'average_load' => number_format($avgLoad, 2),
            'average_memory_usage' => $avgMemory,
            'total_jobs_processed' => $totalJobsProcessed,
            'local_node_id' => $this->localNodeId
        ];
    }

    public function addDiscoveryMethod(string $name, callable $method): void
    {
        $this->discoveryMethods[$name] = $method;
    }

    public function discoverNodes(): array
    {
        $discoveredNodes = [];

        foreach ($this->discoveryMethods as $name => $method) {
            try {
                $nodes = $method();
                if (is_array($nodes)) {
                    $discoveredNodes = array_merge($discoveredNodes, $nodes);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Discovery method failed', [
                    'method' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $discoveredNodes;
    }

    public function getLocalNodeId(): string
    {
        return $this->localNodeId;
    }

    public function setHeartbeatTimeout(int $seconds): void
    {
        $this->heartbeatTimeoutSeconds = $seconds;
    }

    public function getHeartbeatTimeout(): int
    {
        return $this->heartbeatTimeoutSeconds;
    }
}
