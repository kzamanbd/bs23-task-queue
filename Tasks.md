# PHP Developer - Project Task

**Time Duration:** 3 Hours

## Enterprise Task Queue & Job Processing System

### Scenario

You are developing a sophisticated job queue system similar to Laravel Horizon or Symfony Messenger that can handle millions of background jobs, provide advanced retry mechanisms, job prioritization, distributed processing, and comprehensive monitoring. The system must be fault-tolerant, scalable, and provide real-time insights into job processing.

### Problem Statement

Build a comprehensive job processing system that can handle complex workflows, manage job dependencies, provide sophisticated retry strategies, and offer enterprise-grade monitoring and management capabilities while maintaining high performance and reliability.

### Your Task

#### Milestone 1: Core Job Queue Architecture

- **a. Design flexible job queue system:** [5 pts]  
  - Support multiple queue driver implementations (Database, Redis, File) [5 pts]  
  - Job payload encryption and compression [5 pts]  
  - Queue priority levels and weighted processing [3 pts]  
  - Dead letter queue for failed jobs [3 pts]  
- **b. Implement job lifecycle management:** [5 pts]  
  - Job state machine (pending, processing, completed, failed, retrying) [5 pts]  
  - Job timeout and heartbeat monitoring [3 pts]  
  - Job cancellation and cleanup mechanisms [8 pts]  
  - Job dependency resolution and chaining [8 pts]  
- **c. Create worker process management:** [8 pts]  
  - Multi-process worker architecture [5 pts]  
  - Worker health monitoring and restart mechanisms [5 pts]  
  - Memory leak detection and worker recycling [5 pts]  
  - Graceful worker shutdown and signal handling [5 pts]  

#### Milestone 2: Advanced Scheduling & Workflow Engine

- **a. Build sophisticated job scheduler:** [8 pts]  
  - Cron-like scheduling with natural language parsing [5 pts]  
  - Recurring job management with timezone support [5 pts]  
  - Job rate limiting and throttling mechanisms [5 pts]  
  - Conditional job execution based on system state [5 pts]  

#### Milestone 3: Distributed Processing & Load Balancing

- **a. Implement distributed job processing:** [8 pts]  
  - Worker node discovery and worker specialization [13 pts]  
  - Load balancing across multiple workers and failover [8 pts]  
- **b. Create resource management:** [5 pts]  
  - Dynamic worker scaling based on queue depth [13 pts]  
  - Resource quotas and queue optimization algorithms [8 pts]  
- **c. Add fault tolerance mechanisms:** [5 pts]  
  - Idempotency and network partition handling [13 pts]  
  - Job duplication prevention [8 pts]  
  - Data consistency guarantees across nodes [13 pts]  

#### Milestone 4: Monitoring & Management Dashboard

- **a. Implement comprehensive monitoring:** [5 pts]  
  - Real-time job processing metrics and worker performance statistics [5 pts]  
  - Queue depth monitoring and alert categorization [5 pts]  
- **b. Create management interface:** [8 pts]  
  - Web-based dashboard for queue management [5 pts]  
  - Bulk job operations (retry, cancel, prioritize) [5 pts]  
  - Job search and filtering capabilities and configuration [5 pts]  
- **c. Add alerting and notification system:** [5 pts]  
  - Configurable alerts for queue thresholds [5 pts]  
  - Job failure notification with escalation [3 pts]  
  - Performance degradation alerts [5 pts]  
  - Custom metric tracking and dashboards [5 pts]  

### Technical Requirements

- PHP 8.2+ with process control extensions (pcntl, posix)
- Pure PHP implementation without external library systems
- SQLite/MySQL/PostgreSQL for job storage
- Redis for caching and real-time features (optional)
- Symfony Console for CLI management
- Implement SOLID principles and design patterns
- Use event-driven architecture for job events
- Follow PSR standards for logging and caching
- Implement comprehensive error handling

### Performance Requirements

- Process 10K+ jobs per minute per worker
- Support latency under 10ms
- Memory usage under 50MB per worker
- Job payload up to 1MB with compression
- Worker startup time under 1 second

### Instructions

- Submit your codebase in a public repository and email the link after completion
- All Git pushes must be within the design time limit. Exceeding will result in disqualification
- Include CLI tools for queue management and monitoring
- Implement comprehensive test suite including stress tests
- Create example benchmarks and load testing results
- Include deployment guides for production environments
- Document scaling strategies and best practices
- If out of time, prioritize features with lower story points (3-5 pts) first, then medium complexity (8 pts), and finally high complexity (13 pts) features
- Be honest while accomplishing this assessment. Please be confidential about the assessment

### Job Types to Support

- Synchronous jobs with immediate execution
- Asynchronous jobs with background processing
- Scheduled jobs with cron-like timing
- Recurring jobs with interval-based execution
- Batch jobs for bulk operations
- Chain jobs with sequential dependencies
- Parallel jobs with concurrent execution
- Webhook jobs for external API calls

### Advanced Features

- Job middleware for cross-cutting concerns
- Job tagging and categorization
- Job metrics collection and analysis
- Job templates for common patterns
- Job versioning and migration
- Job debugging and profiling tools

### Monitoring Capabilities

- Real-time dashboards with job statistics
- Historical performance trends and analysis
- Worker health monitoring and alerting
- Queue backlog analysis and predictions
- Failed job categorization and root cause analysis
- Performance bottleneck identification
- Resource utilization tracking and optimization
- SLA monitoring and compliance reporting

### Evaluation Criteria

- System Architecture: Scalability, fault tolerance, performance
- Job Processing: Reliability, retry strategies, error handling
- Code Quality: Clean code, design patterns, testing
- Monitoring: Observability, alerting, management capabilities
- Documentation: Setup guides, API docs, operational procedures
- Innovation: Unique features, optimization techniques

### Error Handling & Recovery

- Graceful degradation under high load
- Circuit breaker patterns for external dependencies
- Poison message handling and quarantine
- Job corruption detection and recovery
- Database connection failure handling
- Memory exhaustion prevention and restart
- Worker crash detection and restart
- Data consistency guarantees during failures

### Job Support

- Synchronous jobs with immediate execution
- Scheduled jobs with cron-like processing
- Batch jobs with interval-based execution
- Chain jobs with sequential dependencies
- Parallel jobs with concurrent execution
- Webhook jobs for external API calls
