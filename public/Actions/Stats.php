<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Stats extends Action
{
    public function handle(array $get, array $post): array
    {
        return $this->manager->getQueueStats();
    }
}
