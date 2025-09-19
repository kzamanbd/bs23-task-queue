<?php

declare(strict_types=1);

namespace TaskQueue\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Task Queue', '1.0.0');
        
        $this->addCommands([
            new WorkCommand(),
            new QueueCommand(),
            new DashboardCommand(),
        ]);
    }

    protected function getDefaultCommands(): array
    {
        return parent::getDefaultCommands();
    }
}
