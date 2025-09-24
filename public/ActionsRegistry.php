<?php

declare(strict_types=1);

namespace TaskQueue\Public;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TaskQueue\Public\Actions\Action;

class ActionsRegistry
{
    /** @var array<string, Action> */
    private array $handlers = [];
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function register(string $action, Action $handler): void
    {
        $this->handlers[$action] = $handler;
        $this->logger->debug('API handler registered', ['action' => $action, 'handler' => $handler::class]);
    }

    public function has(string $action): bool
    {
        return isset($this->handlers[$action]);
    }

    public function get(string $action): Action
    {
        if (!$this->has($action)) {
            throw new \InvalidArgumentException("No handler registered for action: {$action}");
        }
        return $this->handlers[$action];
    }
}
