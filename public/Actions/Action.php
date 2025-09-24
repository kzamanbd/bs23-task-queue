<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

use TaskQueue\QueueManager;

abstract class Action
{
    protected QueueManager $manager;

    public function __construct(QueueManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle the request and return a serializable result.
     *
     * @param array $get
     * @param array $post
     * @return array<mixed>
     */
    abstract public function handle(array $get, array $post): array;
}
