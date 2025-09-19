<?php

declare(strict_types=1);

namespace TaskQueue\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DashboardCommand extends Command
{
    protected static $defaultName = 'dashboard:serve';

    protected function configure(): void
    {
        $this->setDescription('Start the web dashboard server')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to bind to', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port to bind to', '8080');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        
        $output->writeln("<info>🚀 Task Queue Dashboard Server</info>");
        $output->writeln("<info>==============================</info>");
        $output->writeln("");
        $output->writeln("<info>Starting server on http://{$host}:{$port}</info>");
        $output->writeln("<info>Dashboard: http://{$host}:{$port}</info>");
        $output->writeln("<info>Press Ctrl+C to stop the server</info>");
        $output->writeln("");

        // Change to public directory
        $publicDir = __DIR__ . '/../../public';
        if (!is_dir($publicDir)) {
            $output->writeln("<error>Public directory not found: {$publicDir}</error>");
            return Command::FAILURE;
        }

        chdir($publicDir);

        // Start PHP built-in server
        $command = "php -S {$host}:{$port}";
        passthru($command);

        return Command::SUCCESS;
    }
}
