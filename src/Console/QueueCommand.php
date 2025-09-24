<?php

declare(strict_types=1);

namespace TaskQueue\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Support\Encryption;
use TaskQueue\Jobs\TestJob;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;
use TaskQueue\Support\Database;

class QueueCommand extends Command
{
    protected static $defaultName = 'queue:test';

    protected function configure(): void
    {
        $this->setDescription('Test queue operations')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'Number of test jobs to create', 10)
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue name', 'default')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, 'Job priority', 5)
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Job delay in seconds', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jobCount = (int) $input->getOption('jobs');
        $queue = $input->getOption('queue');
        $priority = (int) $input->getOption('priority');
        $delay = (int) $input->getOption('delay');

        // Setup logger
        $logger = new Logger('queue-test');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

        // Setup database connection
        $pdo = Database::createSqlitePdo(__DIR__ . '/../../storage/queue.db');

        // Setup encryption
        $encryption = new Encryption('demo-encryption-key-32-characters');

        // Create queue manager
        $driver = new DatabaseQueueDriver($pdo, $encryption);
        $manager = new QueueManager($driver, $logger);
        $manager->connect();

        $io->title('Queue Test Operations');

        try {
            // Create test jobs
            $io->section('Creating Test Jobs');
            for ($i = 1; $i <= $jobCount; $i++) {
                $job = new TestJob([
                    'job_number' => $i,
                    'work_duration' => 1,
                    'should_fail' => $i % 5 === 0, // Every 5th job fails
                ], [
                    'queue' => $queue,
                    'priority' => $priority,
                    'delay' => $delay,
                    'tags' => ['test', 'batch-' . date('Y-m-d-H-i')],
                ]);

                $manager->push($job);
                $io->writeln("Created job {$i}/{$jobCount}: {$job->getId()}");
            }

            // Show queue stats
            $io->section('Queue Statistics');
            $stats = $manager->getQueueStats($queue);
            if (isset($stats[$queue])) {
                $queueStats = $stats[$queue];
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Jobs', $queueStats['total_jobs']],
                        ['Pending Jobs', $queueStats['by_state']['pending'] ?? 0],
                        ['Processing Jobs', $queueStats['by_state']['processing'] ?? 0],
                        ['Completed Jobs', $queueStats['by_state']['completed'] ?? 0],
                        ['Failed Jobs', $queueStats['by_state']['failed'] ?? 0],
                        ['Average Priority', $queueStats['avg_priority']],
                    ]
                );
            }

            // Show failed jobs
            $failedJobs = $manager->getFailedJobs($queue);
            if (!empty($failedJobs)) {
                $io->section('Failed Jobs');
                $io->writeln("Found " . count($failedJobs) . " failed jobs:");
                foreach ($failedJobs as $job) {
                    $io->writeln("- {$job->getId()} (attempts: {$job->getAttempts()})");
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $manager->disconnect();
        }
    }
}
