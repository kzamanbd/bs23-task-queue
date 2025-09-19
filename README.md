# Task Queue - Enterprise Job Processing System

A powerful, enterprise-grade job queue and processing system built in PHP 8.2+ with PSR-4 standards. This system provides reliable background job processing, worker management, and real-time monitoring capabilities.

## 🚀 Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- SQLite (included) or MySQL/PostgreSQL
- PCNTL extension (for worker processes on Unix systems)

### Installation

```bash
# Clone or download the project
git clone <repository-url>
cd task-queue

# Install dependencies
composer install

# Make CLI executable
chmod +x bin/queue
```

### First Run

```bash
# Test the system
php demo.php

# Create some test jobs
php bin/queue queue:test --jobs=5

# Start a worker to process jobs
php bin/queue queue:work
```

## 📋 Basic Usage

### Creating Jobs

```php
<?php

require_once 'vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Jobs\TestJob;
use TaskQueue\Support\Encryption;
use PDO;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup database connection
$pdo = new PDO('sqlite:queue.db');
$encryption = new Encryption('your-32-character-encryption-key');
$driver = new DatabaseQueueDriver($pdo, $encryption);

// Setup logger
$logger = new Logger('queue');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Create queue manager
$manager = new QueueManager($driver, $logger);
$manager->connect();

// Create and push a job
$job = new TestJob(['data' => 'Hello World'], [
    'queue' => 'default',
    'priority' => 5,
    'tags' => ['example', 'test']
]);

$manager->push($job);
echo "Job created: " . $job->getId() . "\n";

$manager->disconnect();
```

### Creating Custom Jobs

```php
<?php

use TaskQueue\Jobs\AbstractJob;

class SendEmailJob extends AbstractJob
{
    public function handle(): void
    {
        $recipient = $this->payload['recipient'];
        $subject = $this->payload['subject'];
        $body = $this->payload['body'];
        
        // Your email sending logic here
        mail($recipient, $subject, $body);
        
        $this->payload['sent_at'] = date('Y-m-d H:i:s');
    }
}

// Usage
$emailJob = new SendEmailJob([
    'recipient' => 'user@example.com',
    'subject' => 'Welcome!',
    'body' => 'Thank you for joining us!'
], [
    'queue' => 'emails',
    'priority' => 10, // High priority
    'tags' => ['email', 'welcome']
]);

$manager->push($emailJob);
```

### Processing Jobs with Workers

```bash
# Start a single worker
php bin/queue queue:work

# Start multiple workers
php bin/queue queue:work --workers=4

# Start worker with custom settings
php bin/queue queue:work --memory=100 --max-jobs=500 --timeout=1800
```

## 🎯 Job Priorities

The system supports 4 priority levels:

- **Low Priority (1)**: Background tasks, cleanup jobs
- **Normal Priority (5)**: Regular business operations
- **High Priority (10)**: Important notifications, user actions
- **Urgent Priority (15)**: Critical system operations

```php
$lowPriorityJob = new TestJob(['task' => 'cleanup'], [
    'priority' => TestJob::PRIORITY_LOW
]);

$urgentJob = new TestJob(['task' => 'payment'], [
    'priority' => TestJob::PRIORITY_URGENT
]);
```

## 📊 Monitoring and Statistics

### Queue Statistics

```php
// Get statistics for all queues
$stats = $manager->getQueueStats();

// Get statistics for specific queue
$defaultStats = $manager->getQueueStats('default');

foreach ($stats as $queueName => $queueStats) {
    echo "Queue: $queueName\n";
    echo "  Total Jobs: " . $queueStats['total_jobs'] . "\n";
    echo "  Pending: " . ($queueStats['by_state']['pending'] ?? 0) . "\n";
    echo "  Processing: " . ($queueStats['by_state']['processing'] ?? 0) . "\n";
    echo "  Completed: " . ($queueStats['by_state']['completed'] ?? 0) . "\n";
    echo "  Failed: " . ($queueStats['by_state']['failed'] ?? 0) . "\n";
}
```

### Failed Jobs

```php
// Get all failed jobs
$failedJobs = $manager->getFailedJobs();

// Get failed jobs from specific queue
$failedEmails = $manager->getFailedJobs('emails');

foreach ($failedJobs as $job) {
    echo "Failed Job: " . $job->getId() . "\n";
    echo "Error: " . ($job->getException() ? $job->getException()->getMessage() : 'Unknown') . "\n";
    echo "Attempts: " . $job->getAttempts() . "/" . $job->getMaxAttempts() . "\n";
}

// Retry a failed job
$manager->retryFailedJob('job_id_here');
```

## 🔧 CLI Commands

### Available Commands

```bash
# List all available commands
php bin/queue list

# Get help for a specific command
php bin/queue help queue:work
```

### queue:test Command

Create test jobs and view statistics:

```bash
# Create 10 test jobs
php bin/queue queue:test --jobs=10

# Create jobs in specific queue with custom priority
php bin/queue queue:test --jobs=5 --queue=high-priority --priority=10

# Create jobs with delay
php bin/queue queue:test --jobs=3 --delay=30
```

### queue:work Command

Start workers to process jobs:

```bash
# Start single worker for default queue
php bin/queue queue:work

# Start worker for specific queue
php bin/queue queue:work emails

# Start multiple workers
php bin/queue queue:work --workers=4

# Start worker with custom memory limit (in MB)
php bin/queue queue:work --memory=100

# Start worker with custom timeout (in seconds)
php bin/queue queue:work --timeout=3600

# Start worker with max jobs limit
php bin/queue queue:work --max-jobs=1000
```

## 🏗️ Configuration

### Environment Variables

Create a `.env` file or set environment variables:

```bash
# Database configuration
DB_CONNECTION=sqlite
DB_PATH=storage/queue.db

# For MySQL/PostgreSQL
# DB_CONNECTION=mysql
# DB_HOST=localhost
# DB_PORT=3306
# DB_DATABASE=task_queue
# DB_USERNAME=root
# DB_PASSWORD=password

# Encryption key (32 characters recommended)
ENCRYPTION_KEY=your-32-character-encryption-key

# Worker configuration
WORKER_MEMORY_LIMIT=52428800  # 50MB
WORKER_MAX_JOBS=1000
WORKER_TIMEOUT=3600  # 1 hour
MAX_WORKERS=10
```

### Queue Manager Configuration

```php
$config = [
    'memory_limit' => 50 * 1024 * 1024, // 50MB
    'max_jobs_per_worker' => 1000,
    'worker_timeout' => 3600, // 1 hour
    'max_workers' => 10,
];

$manager = new QueueManager($driver, $logger, $config);
```

## 🔒 Security Features

### Encryption

All job payloads are encrypted using AES-256-GCM:

```php
$encryption = new Encryption('your-32-character-encryption-key');

// Data is automatically encrypted when stored
$job = new TestJob(['sensitive' => 'data']);
$manager->push($job); // Automatically encrypted
```

### Compression

Large payloads (>1KB) are automatically compressed:

```php
// Large payloads are automatically compressed
$largeJob = new TestJob([
    'large_data' => str_repeat('data', 1000)
]);
$manager->push($largeJob); // Automatically compressed
```

## 🎨 Job Types and Examples

### Email Jobs

```php
class SendEmailJob extends AbstractJob
{
    public function handle(): void
    {
        $to = $this->payload['to'];
        $subject = $this->payload['subject'];
        $body = $this->payload['body'];
        
        // Send email logic
        if (mail($to, $subject, $body)) {
            $this->payload['status'] = 'sent';
        } else {
            throw new \Exception('Failed to send email');
        }
    }
}
```

### File Processing Jobs

```php
class ProcessFileJob extends AbstractJob
{
    public function handle(): void
    {
        $filePath = $this->payload['file_path'];
        $operation = $this->payload['operation'];
        
        switch ($operation) {
            case 'resize':
                $this->resizeImage($filePath);
                break;
            case 'convert':
                $this->convertFile($filePath);
                break;
            default:
                throw new \InvalidArgumentException('Unknown operation');
        }
    }
    
    private function resizeImage(string $filePath): void
    {
        // Image resizing logic
    }
}
```

### API Integration Jobs

```php
class ApiSyncJob extends AbstractJob
{
    public function handle(): void
    {
        $endpoint = $this->payload['endpoint'];
        $data = $this->payload['data'];
        
        $response = $this->makeApiCall($endpoint, $data);
        
        if ($response['status'] !== 'success') {
            throw new \Exception('API call failed: ' . $response['error']);
        }
        
        $this->payload['response'] = $response;
    }
}
```

## 📈 Performance Tips

### Optimizing Worker Performance

1. **Use multiple workers** for high throughput:

   ```bash
   php bin/queue queue:work --workers=4
   ```

2. **Set appropriate memory limits**:

   ```bash
   php bin/queue queue:work --memory=100  # 100MB
   ```

3. **Monitor worker health**:

   ```bash
   # Check queue statistics regularly
   php bin/queue queue:test --jobs=0
   ```

### Database Optimization

1. **Use proper indexes** (automatically created):
   - Queue name and state
   - Priority
   - Created date
   - Job state

2. **Regular cleanup**:

   ```php
   // Purge completed jobs older than 30 days
   $oldJobs = $manager->getJobsByState('completed', null, 1000);
   foreach ($oldJobs as $job) {
       if ($job->getCompletedAt() < new DateTime('-30 days')) {
           $manager->getDriver()->delete($job);
       }
   }
   ```

## 🐛 Troubleshooting

### Common Issues

1. **Jobs not processing**:
   - Check if workers are running
   - Verify database connection
   - Check job state in database

2. **High memory usage**:
   - Reduce worker memory limit
   - Check for memory leaks in job handlers
   - Restart workers periodically

3. **Failed jobs**:
   - Check job exceptions
   - Verify job payload validity
   - Adjust retry settings

### Debugging

Enable verbose logging:

```php
$logger = new Logger('queue');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
```

Check job details:

```php
$job = $manager->getJobById('job_id');
echo "State: " . $job->getState() . "\n";
echo "Attempts: " . $job->getAttempts() . "\n";
echo "Exception: " . ($job->getException() ? $job->getException()->getMessage() : 'None') . "\n";
```

## 🚀 Production Deployment

### Process Management

Use a process manager like Supervisor:

```ini
[program:task-queue-worker]
command=php /path/to/task-queue/bin/queue queue:work --workers=4
directory=/path/to/task-queue
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/task-queue-worker.log
```

### Monitoring

Set up monitoring for:

- Queue depth (pending jobs)
- Worker health and memory usage
- Failed job count
- Processing time

### Scaling

1. **Horizontal scaling**: Run workers on multiple servers
2. **Queue partitioning**: Use different queues for different job types
3. **Database optimization**: Use read replicas for statistics

## 📚 Advanced Features

### Job Dependencies

```php
$job1 = new TestJob(['task' => 'prepare']);
$job2 = new TestJob(['task' => 'process'], [
    'dependencies' => [$job1->getId()]
]);

$manager->push($job1);
$manager->push($job2); // Will wait for job1 to complete
```

### Job Scheduling

```php
$scheduledJob = new TestJob(['task' => 'daily_report'], [
    'delay' => 3600 // 1 hour delay
]);
```

### Custom Job Tags

```php
$taggedJob = new TestJob(['task' => 'cleanup'], [
    'tags' => ['maintenance', 'daily', 'low-priority']
]);
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details.

## 🆘 Support

For issues and questions:

1. Check the troubleshooting section
2. Run the demo: `php demo.php`
3. Check test cases in `tests/` directory
4. Create an issue in the repository

---

**Ready to get started?** Run `php demo.php` to see the system in action!
