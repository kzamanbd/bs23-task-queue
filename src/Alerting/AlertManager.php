<?php

declare(strict_types=1);

namespace TaskQueue\Alerting;

use TaskQueue\Contracts\QueueDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AlertManager
{
    private QueueDriverInterface $queueDriver;
    private LoggerInterface $logger;
    private array $alerts = [];
    private array $notificationChannels = [];
    private array $escalationRules = [];

    public function __construct(
        QueueDriverInterface $queueDriver,
        ?LoggerInterface $logger = null
    ) {
        $this->queueDriver = $queueDriver;
        $this->logger = $logger ?? new NullLogger();
        $this->initializeDefaultAlerts();
    }

    private function initializeDefaultAlerts(): void
    {
        // Queue depth alerts
        $this->addAlert('queue_depth_high', [
            'type' => 'queue_depth',
            'threshold' => 100,
            'severity' => 'warning',
            'message' => 'Queue depth is high: {current} jobs (threshold: {threshold})'
        ]);

        $this->addAlert('queue_depth_critical', [
            'type' => 'queue_depth',
            'threshold' => 1000,
            'severity' => 'critical',
            'message' => 'Queue depth is critical: {current} jobs (threshold: {threshold})'
        ]);

        // Failed jobs alerts
        $this->addAlert('failed_jobs_high', [
            'type' => 'failed_jobs',
            'threshold' => 10,
            'severity' => 'warning',
            'message' => 'High number of failed jobs: {current} (threshold: {threshold})'
        ]);

        // Worker health alerts
        $this->addAlert('worker_down', [
            'type' => 'worker_health',
            'threshold' => 0,
            'severity' => 'critical',
            'message' => 'Worker is down: {worker_id}'
        ]);

        // Memory usage alerts
        $this->addAlert('memory_usage_high', [
            'type' => 'memory_usage',
            'threshold' => 80,
            'severity' => 'warning',
            'message' => 'High memory usage: {current}% (threshold: {threshold}%)'
        ]);

        // Performance alerts
        $this->addAlert('job_processing_slow', [
            'type' => 'job_processing_time',
            'threshold' => 30,
            'severity' => 'warning',
            'message' => 'Job processing is slow: {current}s average (threshold: {threshold}s)'
        ]);
    }

    public function addAlert(string $name, array $config): void
    {
        $this->alerts[$name] = array_merge([
            'enabled' => true,
            'cooldown' => 300, // 5 minutes
            'escalation_delay' => 1800, // 30 minutes
            'last_triggered' => 0,
            'escalation_level' => 0
        ], $config);

        $this->logger->info('Alert added', [
            'alert_name' => $name,
            'config' => $config
        ]);
    }

    public function removeAlert(string $name): void
    {
        unset($this->alerts[$name]);
        
        $this->logger->info('Alert removed', [
            'alert_name' => $name
        ]);
    }

    public function addNotificationChannel(string $name, callable $channel): void
    {
        $this->notificationChannels[$name] = $channel;
        
        $this->logger->info('Notification channel added', [
            'channel_name' => $name
        ]);
    }

    public function addEscalationRule(string $alertName, array $rule): void
    {
        if (!isset($this->escalationRules[$alertName])) {
            $this->escalationRules[$alertName] = [];
        }

        $this->escalationRules[$alertName][] = $rule;
        
        $this->logger->info('Escalation rule added', [
            'alert_name' => $alertName,
            'rule' => $rule
        ]);
    }

    public function checkAlerts(): array
    {
        $triggeredAlerts = [];
        $stats = $this->queueDriver->getStats();

        foreach ($this->alerts as $name => $alert) {
            if (!$alert['enabled']) {
                continue;
            }

            // Check cooldown
            if (time() - $alert['last_triggered'] < $alert['cooldown']) {
                continue;
            }

            if ($this->evaluateAlert($alert, $stats)) {
                $triggeredAlerts[] = $this->triggerAlert($name, $alert, $stats);
            }
        }

        return $triggeredAlerts;
    }

    private function evaluateAlert(array $alert, array $stats): bool
    {
        switch ($alert['type']) {
            case 'queue_depth':
                return $this->checkQueueDepth($alert, $stats);
            case 'failed_jobs':
                return $this->checkFailedJobs($alert, $stats);
            case 'worker_health':
                return $this->checkWorkerHealth($alert, $stats);
            case 'memory_usage':
                return $this->checkMemoryUsage($alert, $stats);
            case 'job_processing_time':
                return $this->checkJobProcessingTime($alert, $stats);
            default:
                return false;
        }
    }

    private function checkQueueDepth(array $alert, array $stats): bool
    {
        $totalJobs = 0;
        foreach ($stats as $queueStats) {
            $totalJobs += $queueStats['total_jobs'];
        }

        return $totalJobs >= $alert['threshold'];
    }

    private function checkFailedJobs(array $alert, array $stats): bool
    {
        $totalFailed = 0;
        foreach ($stats as $queueStats) {
            $totalFailed += $queueStats['by_state']['failed'] ?? 0;
        }

        return $totalFailed >= $alert['threshold'];
    }

    private function checkWorkerHealth(array $alert, array $stats): bool
    {
        // This would typically check worker health from a worker registry
        // For now, we'll assume this is always false unless explicitly triggered
        return false;
    }

    private function checkMemoryUsage(array $alert, array $stats): bool
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return false; // No memory limit
        }

        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;

        return $usagePercentage >= $alert['threshold'];
    }

    private function checkJobProcessingTime(array $alert, array $stats): bool
    {
        // This would typically track job processing times
        // For now, we'll use a simple heuristic based on queue depth
        $totalJobs = 0;
        foreach ($stats as $queueStats) {
            $totalJobs += $queueStats['total_jobs'];
        }

        // If queue is backing up, assume processing is slow
        return $totalJobs > ($alert['threshold'] * 10);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }

    private function triggerAlert(string $name, array $alert, array $stats): array
    {
        $this->alerts[$name]['last_triggered'] = time();

        $message = $this->formatAlertMessage($alert['message'], $alert, $stats);
        
        $alertData = [
            'name' => $name,
            'severity' => $alert['severity'],
            'message' => $message,
            'timestamp' => time(),
            'data' => $stats
        ];

        $this->sendNotification($alertData);
        $this->handleEscalation($name, $alert, $alertData);

        $this->logger->warning('Alert triggered', $alertData);

        return $alertData;
    }

    private function formatAlertMessage(string $template, array $alert, array $stats): string
    {
        $replacements = [
            '{threshold}' => $alert['threshold'],
            '{current}' => $this->getCurrentValue($alert['type'], $stats),
            '{worker_id}' => 'worker_' . getmypid()
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function getCurrentValue(string $type, array $stats): string
    {
        switch ($type) {
            case 'queue_depth':
                $total = 0;
                foreach ($stats as $queueStats) {
                    $total += $queueStats['total_jobs'];
                }
                return (string) $total;
            case 'failed_jobs':
                $total = 0;
                foreach ($stats as $queueStats) {
                    $total += $queueStats['by_state']['failed'] ?? 0;
                }
                return (string) $total;
            case 'memory_usage':
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = ini_get('memory_limit');
                if ($memoryLimit === '-1') {
                    return '0';
                }
                $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
                return (string) number_format(($memoryUsage / $memoryLimitBytes) * 100, 2);
            default:
                return '0';
        }
    }

    private function sendNotification(array $alertData): void
    {
        foreach ($this->notificationChannels as $name => $channel) {
            try {
                $channel($alertData);
                
                $this->logger->info('Notification sent', [
                    'channel' => $name,
                    'alert' => $alertData['name']
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Notification failed', [
                    'channel' => $name,
                    'alert' => $alertData['name'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function handleEscalation(string $alertName, array $alert, array $alertData): void
    {
        if (!isset($this->escalationRules[$alertName])) {
            return;
        }

        $escalationLevel = $this->alerts[$alertName]['escalation_level'];
        $timeSinceLastTriggered = time() - $alert['last_triggered'];

        // Check if escalation delay has passed
        if ($timeSinceLastTriggered < $alert['escalation_delay']) {
            return;
        }

        $escalationRules = $this->escalationRules[$alertName];
        
        if (isset($escalationRules[$escalationLevel])) {
            $rule = $escalationRules[$escalationLevel];
            
            $escalatedAlert = array_merge($alertData, [
                'escalation_level' => $escalationLevel + 1,
                'escalation_rule' => $rule
            ]);

            $this->sendNotification($escalatedAlert);
            $this->alerts[$alertName]['escalation_level']++;

            $this->logger->warning('Alert escalated', [
                'alert' => $alertName,
                'escalation_level' => $escalationLevel + 1
            ]);
        }
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    public function getNotificationChannels(): array
    {
        return array_keys($this->notificationChannels);
    }

    public function getEscalationRules(): array
    {
        return $this->escalationRules;
    }

    public function getStats(): array
    {
        $enabledAlerts = array_filter($this->alerts, fn($alert) => $alert['enabled']);
        
        return [
            'total_alerts' => count($this->alerts),
            'enabled_alerts' => count($enabledAlerts),
            'notification_channels' => count($this->notificationChannels),
            'escalation_rules' => count($this->escalationRules)
        ];
    }
}
