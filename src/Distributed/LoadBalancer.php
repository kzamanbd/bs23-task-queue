<?php

declare(strict_types=1);

namespace TaskQueue\Distributed;

use TaskQueue\Contracts\JobInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LoadBalancer
{
    public const STRATEGY_ROUND_ROBIN = 'round_robin';
    public const STRATEGY_LEAST_LOADED = 'least_loaded';
    public const STRATEGY_RANDOM = 'random';
    public const STRATEGY_WEIGHTED = 'weighted';

    private NodeDiscovery $nodeDiscovery;
    private LoggerInterface $logger;
    private string $strategy;
    private array $weights = [];
    private array $roundRobinIndex = [];

    public function __construct(
        NodeDiscovery $nodeDiscovery,
        string $strategy = self::STRATEGY_LEAST_LOADED,
        LoggerInterface $logger = null
    ) {
        $this->nodeDiscovery = $nodeDiscovery;
        $this->strategy = $strategy;
        $this->logger = $logger ?? new NullLogger();
    }

    public function selectNode(JobInterface $job, array $requirements = []): ?WorkerNode
    {
        $candidateNodes = $this->filterNodes($job, $requirements);

        if (empty($candidateNodes)) {
            $this->logger->warning('No suitable nodes found for job', [
                'job_id' => $job->getId(),
                'requirements' => $requirements
            ]);
            return null;
        }

        $selectedNode = $this->applyStrategy($candidateNodes, $job);

        $this->logger->debug('Node selected for job', [
            'job_id' => $job->getId(),
            'selected_node' => $selectedNode->getId(),
            'strategy' => $this->strategy,
            'node_load' => $selectedNode->getLoad()
        ]);

        return $selectedNode;
    }

    public function selectMultipleNodes(JobInterface $job, int $count, array $requirements = []): array
    {
        $candidateNodes = $this->filterNodes($job, $requirements);

        if (empty($candidateNodes)) {
            return [];
        }

        $selectedNodes = [];

        switch ($this->strategy) {
            case self::STRATEGY_ROUND_ROBIN:
                $selectedNodes = $this->selectRoundRobin($candidateNodes, $count, $job);
                break;
            case self::STRATEGY_LEAST_LOADED:
                $selectedNodes = $this->selectLeastLoaded($candidateNodes, $count);
                break;
            case self::STRATEGY_RANDOM:
                $selectedNodes = $this->selectRandom($candidateNodes, $count);
                break;
            case self::STRATEGY_WEIGHTED:
                $selectedNodes = $this->selectWeighted($candidateNodes, $count);
                break;
            default:
                $selectedNodes = $this->selectLeastLoaded($candidateNodes, $count);
        }

        return $selectedNodes;
    }

    private function filterNodes(JobInterface $job, array $requirements = []): array
    {
        $nodes = $this->nodeDiscovery->getActiveNodes();

        // Filter by queue if specified
        $queue = $job->getQueue();
        if ($queue !== 'default') {
            $nodes = array_filter($nodes, function(WorkerNode $node) use ($queue) {
                return $node->hasCapability("queue:{$queue}");
            });
        }

        // Filter by job requirements
        if (isset($requirements['capabilities'])) {
            $nodes = array_filter($nodes, function(WorkerNode $node) use ($requirements) {
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
            $nodes = array_filter($nodes, function(WorkerNode $node) use ($requirements) {
                foreach ($requirements['specializations'] as $specialization) {
                    if (!$node->hasSpecialization($specialization)) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Filter by load threshold
        $maxLoad = $requirements['max_load'] ?? 90;
        $nodes = array_filter($nodes, function(WorkerNode $node) use ($maxLoad) {
            return $node->getLoad() < $maxLoad;
        });

        // Filter by memory threshold
        $maxMemory = $requirements['max_memory_mb'] ?? 500; // 500MB
        $nodes = array_filter($nodes, function(WorkerNode $node) use ($maxMemory) {
            return ($node->getMemoryUsage() / (1024 * 1024)) < $maxMemory;
        });

        return array_values($nodes);
    }

    private function applyStrategy(array $nodes, JobInterface $job): WorkerNode
    {
        switch ($this->strategy) {
            case self::STRATEGY_ROUND_ROBIN:
                return $this->selectRoundRobin($nodes, 1, $job)[0];
            case self::STRATEGY_LEAST_LOADED:
                return $this->selectLeastLoaded($nodes, 1)[0];
            case self::STRATEGY_RANDOM:
                return $this->selectRandom($nodes, 1)[0];
            case self::STRATEGY_WEIGHTED:
                return $this->selectWeighted($nodes, 1)[0];
            default:
                return $this->selectLeastLoaded($nodes, 1)[0];
        }
    }

    private function selectRoundRobin(array $nodes, int $count, JobInterface $job): array
    {
        $selectedNodes = [];
        $jobQueue = $job->getQueue();
        
        if (!isset($this->roundRobinIndex[$jobQueue])) {
            $this->roundRobinIndex[$jobQueue] = 0;
        }

        $startIndex = $this->roundRobinIndex[$jobQueue];
        $nodeCount = count($nodes);

        for ($i = 0; $i < $count; $i++) {
            $index = ($startIndex + $i) % $nodeCount;
            $selectedNodes[] = $nodes[$index];
        }

        $this->roundRobinIndex[$jobQueue] = ($startIndex + $count) % $nodeCount;

        return $selectedNodes;
    }

    private function selectLeastLoaded(array $nodes, int $count): array
    {
        usort($nodes, function(WorkerNode $a, WorkerNode $b) {
            return $a->getLoadScore() <=> $b->getLoadScore();
        });

        return array_slice($nodes, 0, $count);
    }

    private function selectRandom(array $nodes, int $count): array
    {
        if (count($nodes) <= $count) {
            return $nodes;
        }

        $selectedNodes = [];
        $indices = array_rand($nodes, $count);

        if (!is_array($indices)) {
            $indices = [$indices];
        }

        foreach ($indices as $index) {
            $selectedNodes[] = $nodes[$index];
        }

        return $selectedNodes;
    }

    private function selectWeighted(array $nodes, int $count): array
    {
        $weightedNodes = [];

        foreach ($nodes as $node) {
            $weight = $this->weights[$node->getId()] ?? 1.0;
            $loadScore = $node->getLoadScore();
            
            // Higher weight and lower load = better chance
            $score = $weight / (1 + $loadScore);
            
            for ($i = 0; $i < (int) ($score * 100); $i++) {
                $weightedNodes[] = $node;
            }
        }

        if (empty($weightedNodes)) {
            return $this->selectLeastLoaded($nodes, $count);
        }

        $selectedNodes = [];
        $selectedCount = 0;

        while ($selectedCount < $count && !empty($weightedNodes)) {
            $randomIndex = array_rand($weightedNodes);
            $selectedNode = $weightedNodes[$randomIndex];
            
            // Avoid duplicates
            $alreadySelected = false;
            foreach ($selectedNodes as $existingNode) {
                if ($existingNode->getId() === $selectedNode->getId()) {
                    $alreadySelected = true;
                    break;
                }
            }
            
            if (!$alreadySelected) {
                $selectedNodes[] = $selectedNode;
                $selectedCount++;
            }
            
            // Remove this node from weighted list to avoid infinite loops
            unset($weightedNodes[$randomIndex]);
        }

        return $selectedNodes;
    }

    public function setStrategy(string $strategy): void
    {
        if (!in_array($strategy, [
            self::STRATEGY_ROUND_ROBIN,
            self::STRATEGY_LEAST_LOADED,
            self::STRATEGY_RANDOM,
            self::STRATEGY_WEIGHTED
        ], true)) {
            throw new \InvalidArgumentException("Invalid load balancing strategy: {$strategy}");
        }

        $this->strategy = $strategy;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function setNodeWeight(string $nodeId, float $weight): void
    {
        $this->weights[$nodeId] = max(0.1, $weight);
    }

    public function getNodeWeight(string $nodeId): float
    {
        return $this->weights[$nodeId] ?? 1.0;
    }

    public function removeNodeWeight(string $nodeId): void
    {
        unset($this->weights[$nodeId]);
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function getStats(): array
    {
        $nodes = $this->nodeDiscovery->getActiveNodes();
        $totalNodes = count($nodes);

        if ($totalNodes === 0) {
            return [
                'total_nodes' => 0,
                'average_load' => 0,
                'strategy' => $this->strategy
            ];
        }

        $totalLoad = 0;
        foreach ($nodes as $node) {
            $totalLoad += $node->getLoad();
        }

        return [
            'total_nodes' => $totalNodes,
            'average_load' => round($totalLoad / $totalNodes, 2),
            'strategy' => $this->strategy,
            'weights' => $this->weights
        ];
    }
}
