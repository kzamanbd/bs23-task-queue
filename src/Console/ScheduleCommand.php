<?php

declare(strict_types=1);

namespace TaskQueue\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TaskQueue\Jobs\ScheduledJob;
use TaskQueue\Scheduling\NaturalLanguageParser;

class ScheduleCommand extends Command
{
    protected static $defaultName = 'schedule:manage';

    protected function configure(): void
    {
        $this->setDescription('Manage scheduled jobs')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (list, create, delete, next)')
            ->addOption('schedule', 's', InputOption::VALUE_REQUIRED, 'Schedule expression (cron or natural language)')
            ->addOption('job-class', 'j', InputOption::VALUE_REQUIRED, 'Job class to schedule', 'TaskQueue\\Jobs\\TestJob')
            ->addOption('payload', 'p', InputOption::VALUE_REQUIRED, 'Job payload (JSON string)')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue name', 'default')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Job priority', 5)
            ->addOption('recurring', 'r', InputOption::VALUE_NONE, 'Make job recurring')
            ->addOption('expires', 'e', InputOption::VALUE_REQUIRED, 'Expiration date (Y-m-d H:i:s)')
            ->addOption('job-id', null, InputOption::VALUE_REQUIRED, 'Job ID for delete action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        // Setup queue manager (simplified for CLI)
        $pdo = new \PDO('sqlite:' . __DIR__ . '/../../storage/queue.db');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        $encryption = new \TaskQueue\Support\Encryption('demo-encryption-key-32-characters');
        $driver = new \TaskQueue\Drivers\DatabaseQueueDriver($pdo, $encryption);
        $manager = new \TaskQueue\QueueManager($driver, new \Monolog\Logger('schedule-cli'));

        switch ($action) {
            case 'list':
                return $this->listScheduledJobs($io, $manager);
            case 'create':
                return $this->createScheduledJob($input, $io, $manager);
            case 'delete':
                return $this->deleteScheduledJob($input, $io, $manager);
            case 'next':
                return $this->showNextRuns($io, $manager);
            case 'stats':
                return $this->showSchedulerStats($io, $manager);
            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }
    }

    private function listScheduledJobs(SymfonyStyle $io, \TaskQueue\QueueManager $manager): int
    {
        $scheduledJobs = $manager->getScheduledJobs();

        if (empty($scheduledJobs)) {
            $io->info('No scheduled jobs found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($scheduledJobs as $job) {
            $rows[] = [
                $job->getId(),
                $job->getCronExpression()->getExpression(),
                $job->getNextRunAt() ? $job->getNextRunAt()->format('Y-m-d H:i:s') : 'N/A',
                $job->isRecurring() ? 'Yes' : 'No',
                $job->getQueue(),
                $job->getPriority()
            ];
        }

        $io->title('Scheduled Jobs');
        $io->table(
            ['Job ID', 'Schedule', 'Next Run', 'Recurring', 'Queue', 'Priority'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function createScheduledJob(InputInterface $input, SymfonyStyle $io, \TaskQueue\QueueManager $manager): int
    {
        $schedule = $input->getOption('schedule');
        $jobClass = $input->getOption('job-class');
        $payload = $input->getOption('payload');
        $queue = $input->getOption('queue');
        $priority = (int) $input->getOption('priority');
        $recurring = $input->getOption('recurring');
        $expires = $input->getOption('expires');

        if (!$schedule) {
            $io->error('Schedule expression is required (--schedule)');
            return Command::FAILURE;
        }

        // Parse payload
        $jobPayload = [];
        if ($payload) {
            $jobPayload = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON payload: ' . json_last_error_msg());
                return Command::FAILURE;
            }
        }

        // Parse expiration date
        $expiresAt = null;
        if ($expires) {
            try {
                $expiresAt = new \DateTimeImmutable($expires);
            } catch (\Exception $e) {
                $io->error('Invalid expiration date format. Use Y-m-d H:i:s');
                return Command::FAILURE;
            }
        }

        // Create scheduled job
        $options = [
            'queue' => $queue,
            'priority' => $priority,
            'recurring' => $recurring,
            'expires_at' => $expiresAt,
            'tags' => ['scheduled', 'cli-created']
        ];

        $scheduledJob = new ScheduledJob($jobPayload, $options);
        $scheduledJob->setSchedule($schedule);

        // Schedule the job
        $manager->scheduleJob($scheduledJob);

        $io->success([
            'Scheduled job created successfully!',
            'Job ID: ' . $scheduledJob->getId(),
            'Schedule: ' . $scheduledJob->getCronExpression()->getExpression(),
            'Next Run: ' . ($scheduledJob->getNextRunAt() ? $scheduledJob->getNextRunAt()->format('Y-m-d H:i:s') : 'N/A'),
            'Queue: ' . $scheduledJob->getQueue(),
            'Priority: ' . $scheduledJob->getPriority()
        ]);

        return Command::SUCCESS;
    }

    private function deleteScheduledJob(InputInterface $input, SymfonyStyle $io, \TaskQueue\QueueManager $manager): int
    {
        $jobId = $input->getOption('job-id');

        if (!$jobId) {
            $io->error('Job ID is required (--job-id)');
            return Command::FAILURE;
        }

        $manager->unscheduleJob($jobId);
        $io->success("Scheduled job '{$jobId}' deleted successfully!");

        return Command::SUCCESS;
    }

    private function showNextRuns(SymfonyStyle $io, \TaskQueue\QueueManager $manager): int
    {
        $nextRuns = $manager->getNextRunTimes(10);

        if (empty($nextRuns)) {
            $io->info('No scheduled jobs found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($nextRuns as $run) {
            $rows[] = [
                $run['job_id'],
                $run['next_run_at'],
                $run['cron_expression'],
                $run['recurring'] ? 'Yes' : 'No'
            ];
        }

        $io->title('Next Scheduled Job Runs');
        $io->table(
            ['Job ID', 'Next Run', 'Schedule', 'Recurring'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function showSchedulerStats(SymfonyStyle $io, \TaskQueue\QueueManager $manager): int
    {
        $stats = $manager->getSchedulerStats();

        $io->title('Scheduler Statistics');
        $io->definitionList(
            ['Total Scheduled', $stats['total_scheduled']],
            ['Recurring Jobs', $stats['recurring']],
            ['One-time Jobs', $stats['one_time']],
            ['Expired Jobs', $stats['expired']]
        );

        return Command::SUCCESS;
    }
}
