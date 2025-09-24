<?php

declare(strict_types=1);

namespace TaskQueue\Public\Actions;

class Purge extends Action
{
    public function handle(array $get, array $post): array
    {
        $queue = $post['queue'] ?? '';
        if ($queue === '') {
            throw new \InvalidArgumentException('Queue name is required');
        }

        $this->manager->purgeQueue($queue);
        return ['success' => true];
    }
}

