<?php

declare(strict_types=1);

namespace TaskQueue;

use TaskQueue\Contracts\JobInterface;
use TaskQueue\Contracts\QueueDriverInterface;
use TaskQueue\Contracts\WorkerInterface;
use TaskQueue\Workers\Worker;
use TaskQueue\Support\Encryption;
use TaskQueue\Scheduling\JobScheduler;
use TaskQueue\RateLimiting\RateLimiter;
use TaskQueue\Conditions\ConditionEvaluator;
use TaskQueue\Distributed\NodeDiscovery;
use TaskQueue\Distributed\LoadBalancer;
use TaskQueue\Distributed\ResourceManager;
use TaskQueue\Distributed\FaultTolerance;
use TaskQueue\Alerting\AlertManager;
use Psr\Log\LoggerInterface;

class QueueManager
{
    private QueueDriverInterface $driver;
    private LoggerInterface $logger;
    private array $workers = [];
    private array $config;
    private ?JobScheduler $scheduler = null;
    private ?RateLimiter $rateLimiter = null;
    private ?ConditionEvaluator $conditionEvaluator = null;
    private ?NodeDiscovery $nodeDiscovery = null;
    private ?LoadBalancer $loadBalancer = null;
    private ?ResourceManager $resourceManager = null;
    private ?FaultTolerance $faultTolerance = null;
    private ?AlertManager $alertManager = null;

    public function __construct(
        QueueDriverInterface $driver,
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->config = array_merge([
            'memory_limit' => 50 * 1024 * 1024, // 50MB
            'max_jobs_per_worker' => 1000,
            'worker_timeout' => 3600, // 1 hour
            'max_workers' => 10,
        ], $config);
    }

    public function push(JobInterface $job): void
    {
        $this->driver->push($job);
        $this->logger->info('Job pushed to queue', [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'priority' => $job->getPriority()
        ]);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        return $this->driver->pop($queue);
    }

    public function startWorker(string $queue, int $timeout = 0): WorkerInterface
    {
        $worker = new Worker(
            $this->driver,
            $this->logger,
            $this->config['memory_limit'],
            $this->config['max_jobs_per_worker']
        );

        $this->workers[] = $worker;

        $worker->work($queue, $timeout ?: $this->config['worker_timeout']);

        return $worker;
    }

    public function startMultipleWorkers(string $queue, int $count = 1): array
    {
        if ($count > $this->config['max_workers']) {
            throw new \InvalidArgumentException(
                "Cannot start {$count} workers. Maximum allowed: {$this->config['max_workers']}"
            );
        }

        $workers = [];
        for ($i = 0; $i < $count; $i++) {
            $worker = new Worker(
                $this->driver,
                $this->logger,
                $this->config['memory_limit'],
                $this->config['max_jobs_per_worker']
            );

            $workers[] = $worker;
            $this->workers[] = $worker;
        }

        return $workers;
    }

    public function stopAllWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $this->workers = [];
    }

    public function getActiveWorkers(): array
    {
        return array_filter($this->workers, fn($worker) => $worker->isRunning());
    }

    public function getQueueSize(string $queue = 'default'): int
    {
        return $this->driver->size($queue);
    }

    public function getQueueStats(?string $queue = null): array
    {
        return $this->driver->getQueueStats($queue);
    }

    public function getFailedJobs(?string $queue = null): array
    {
        return $this->driver->getFailedJobs($queue);
    }

    public function retryFailedJob(string $jobId): bool
    {
        return $this->driver->retryFailedJob($jobId);
    }

    public function purgeQueue(string $queue): void
    {
        $this->driver->purge($queue);
        $this->logger->info('Queue purged', ['queue' => $queue]);
    }

    public function cleanupOldCompletedJobs(int $hoursOld = 1): int
    {
        $deletedCount = $this->driver->cleanupOldCompletedJobs($hoursOld);
        if ($deletedCount > 0) {
            $this->logger->info('Cleaned up old completed jobs', [
                'deleted_count' => $deletedCount,
                'hours_old' => $hoursOld
            ]);
        }
        return $deletedCount;
    }

    public function getJobById(string $jobId): ?JobInterface
    {
        return $this->driver->getJobById($jobId);
    }

    public function getJobsByState(string $state, ?string $queue = null, int $limit = 100): array
    {
        return $this->driver->getJobsByState($state, $queue, $limit);
    }

    public function connect(): void
    {
        $this->driver->connect();
    }

    public function disconnect(): void
    {
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function getDriver(): QueueDriverInterface
    {
        return $this->driver;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    // Scheduling Methods
    public function getScheduler(): JobScheduler
    {
        if ($this->scheduler === null) {
            $this->scheduler = new JobScheduler($this->driver, $this->logger);
        }
        return $this->scheduler;
    }

    public function getRateLimiter(): RateLimiter
    {
        if ($this->rateLimiter === null) {
            $this->rateLimiter = new RateLimiter($this->driver);
        }
        return $this->rateLimiter;
    }

    public function getConditionEvaluator(): ConditionEvaluator
    {
        if ($this->conditionEvaluator === null) {
            $this->conditionEvaluator = new ConditionEvaluator($this->driver);
        }
        return $this->conditionEvaluator;
    }

    public function scheduleJob(\TaskQueue\Jobs\ScheduledJob $job): void
    {
        $this->getScheduler()->schedule($job);
    }

    public function unscheduleJob(string $jobId): void
    {
        $this->getScheduler()->unschedule($jobId);
    }

    public function getScheduledJob(string $jobId): ?\TaskQueue\Jobs\ScheduledJob
    {
        return $this->getScheduler()->getScheduledJob($jobId);
    }

    public function setRateLimit(string $key, int $maxRequests, int $windowSeconds): void
    {
        $this->getRateLimiter()->setLimit($key, $maxRequests, $windowSeconds);
    }

    public function isRateLimited(string $key, int $requests = 1): bool
    {
        return !$this->getRateLimiter()->isAllowed($key, $requests);
    }

    public function recordRateLimit(string $key, int $requests = 1): void
    {
        $this->getRateLimiter()->record($key, $requests);
    }

    public function evaluateConditions(array $conditions, JobInterface $job): bool
    {
        return $this->getConditionEvaluator()->evaluate($conditions, $job);
    }

    public function getScheduledJobs(): array
    {
        return $this->getScheduler()->getScheduledJobs();
    }

    public function getNextRunTimes(int $limit = 10): array
    {
        return $this->getScheduler()->getNextRunTimes($limit);
    }

    public function getSchedulerStats(): array
    {
        return $this->getScheduler()->getStats();
    }

    public function getRateLimiterStats(): array
    {
        return $this->getRateLimiter()->getStats();
    }

    // Distributed Processing Methods
    public function getNodeDiscovery(): NodeDiscovery
    {
        if ($this->nodeDiscovery === null) {
            $this->nodeDiscovery = new NodeDiscovery(
                $this->config['node_id'] ?? uniqid('node_', true),
                $this->logger
            );
        }
        return $this->nodeDiscovery;
    }

    public function getLoadBalancer(): LoadBalancer
    {
        if ($this->loadBalancer === null) {
            $this->loadBalancer = new LoadBalancer(
                $this->getNodeDiscovery(),
                $this->config['load_balancing_strategy'] ?? LoadBalancer::STRATEGY_LEAST_LOADED,
                $this->logger
            );
        }
        return $this->loadBalancer;
    }

    public function getResourceManager(): ResourceManager
    {
        if ($this->resourceManager === null) {
            $this->resourceManager = new ResourceManager(
                $this->getNodeDiscovery(),
                $this->driver,
                $this->logger
            );
        }
        return $this->resourceManager;
    }

    public function getFaultTolerance(): FaultTolerance
    {
        if ($this->faultTolerance === null) {
            $this->faultTolerance = new FaultTolerance(
                $this->driver,
                $this->getNodeDiscovery(),
                $this->logger
            );
        }
        return $this->faultTolerance;
    }

    public function registerWorkerNode(string $nodeId, string $host, int $port, array $capabilities = []): void
    {
        $node = new \TaskQueue\Distributed\WorkerNode($nodeId, $host, $port, $capabilities);
        $this->getNodeDiscovery()->registerNode($node);
    }

    public function selectBestNode(\TaskQueue\Contracts\JobInterface $job, array $requirements = []): ?\TaskQueue\Distributed\WorkerNode
    {
        return $this->getLoadBalancer()->selectNode($job, $requirements);
    }

    public function setLoadBalancingStrategy(string $strategy): void
    {
        $this->getLoadBalancer()->setStrategy($strategy);
    }

    public function setResourceQuota(string $resource, int $limit): void
    {
        $this->getResourceManager()->setResourceQuota($resource, $limit);
    }

    public function checkResourceUsage(string $resource): float
    {
        return $this->getResourceManager()->checkResourceUsage($resource);
    }

    public function shouldScaleUp(): bool
    {
        return $this->getResourceManager()->shouldScaleUp();
    }

    public function shouldScaleDown(): bool
    {
        return $this->getResourceManager()->shouldScaleDown();
    }

    public function ensureIdempotency(\TaskQueue\Contracts\JobInterface $job): bool
    {
        return $this->getFaultTolerance()->ensureIdempotency($job);
    }

    public function ensureDataConsistency(): array
    {
        return $this->getFaultTolerance()->ensureDataConsistency();
    }

    public function getDistributedStats(): array
    {
        return [
            'node_discovery' => $this->getNodeDiscovery()->getStats(),
            'load_balancer' => $this->getLoadBalancer()->getStats(),
            'resource_manager' => $this->getResourceManager()->getStats(),
            'fault_tolerance' => $this->getFaultTolerance()->getStats()
        ];
    }

    // Alerting Methods
    public function getAlertManager(): AlertManager
    {
        if ($this->alertManager === null) {
            $this->alertManager = new AlertManager($this->driver, $this->logger);
        }
        return $this->alertManager;
    }

    public function addAlert(string $name, array $config): void
    {
        $this->getAlertManager()->addAlert($name, $config);
    }

    public function removeAlert(string $name): void
    {
        $this->getAlertManager()->removeAlert($name);
    }

    public function addNotificationChannel(string $name, callable $channel): void
    {
        $this->getAlertManager()->addNotificationChannel($name, $channel);
    }

    public function checkAlerts(): array
    {
        return $this->getAlertManager()->checkAlerts();
    }

    public function getAlertStats(): array
    {
        return $this->getAlertManager()->getStats();
    }
}
