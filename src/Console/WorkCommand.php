<?php

declare(strict_types=1);

namespace TaskQueue\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Support\Encryption;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;

class WorkCommand extends Command
{
    protected static $defaultName = 'queue:work';

    protected function configure(): void
    {
        $this->setDescription('Start a queue worker')
            ->addArgument('queue', InputArgument::OPTIONAL, 'Queue name', 'default')
            ->addOption('workers', 'w', InputOption::VALUE_REQUIRED, 'Number of workers to start', 1)
            ->addOption('timeout', 't', InputOption::VALUE_REQUIRED, 'Worker timeout in seconds', 3600)
            ->addOption('memory', 'm', InputOption::VALUE_REQUIRED, 'Memory limit in MB', 50)
            ->addOption('max-jobs', 'j', InputOption::VALUE_REQUIRED, 'Maximum jobs per worker', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getArgument('queue');
        $workerCount = (int) $input->getOption('workers');
        $timeout = (int) $input->getOption('timeout');
        $memoryLimit = (int) $input->getOption('memory') * 1024 * 1024;
        $maxJobs = (int) $input->getOption('max-jobs');

        // Setup logger
        $logger = new Logger('queue-worker');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

        // Setup database connection
        $pdo = new PDO('sqlite:' . __DIR__ . '/../../storage/queue.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Setup encryption (using a simple key for demo)
        $encryption = new Encryption('demo-encryption-key-32-characters');

        // Create queue driver and manager
        $driver = new DatabaseQueueDriver($pdo, $encryption);
        $manager = new QueueManager($driver, $logger, [
            'memory_limit' => $memoryLimit,
            'max_jobs_per_worker' => $maxJobs,
            'worker_timeout' => $timeout,
            'max_workers' => $workerCount,
        ]);

        $manager->connect();

        $output->writeln("<info>Starting {$workerCount} worker(s) for queue: {$queue}</info>");
        $output->writeln("<info>Memory limit: " . ($memoryLimit / 1024 / 1024) . "MB</info>");
        $output->writeln("<info>Max jobs per worker: {$maxJobs}</info>");
        $output->writeln("<info>Worker timeout: {$timeout} seconds</info>");

        try {
            if ($workerCount > 1) {
                $workers = $manager->startMultipleWorkers($queue, $workerCount);
                $output->writeln("<info>Started {$workerCount} workers</info>");
                
                // Wait for all workers to complete
                while (true) {
                    $activeWorkers = $manager->getActiveWorkers();
                    if (empty($activeWorkers)) {
                        break;
                    }
                    sleep(1);
                }
            } else {
                $worker = $manager->startWorker($queue, $timeout);
                $output->writeln("<info>Worker completed</info>");
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Worker error: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        } finally {
            $manager->disconnect();
        }
    }
}
