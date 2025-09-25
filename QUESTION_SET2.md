# Task Queue Exam Preparation

## For PHP Developer Exam Preparation

### Architecture & System Design

- **Q: Describe the overall architecture of this task queue system. What are the core components and responsibilities?**
  - **A:** Core components include `QueueManager` (orchestration), `QueueDriverInterface` implementations (persistence and fetching), `Worker` (execution and lifecycle management), `JobInterface`/`AbstractJob` (job contract and shared behavior), `JobScheduler` (cron and natural language scheduling), distributed modules (`NodeDiscovery`, `LoadBalancer`, `ResourceManager`, `FaultTolerance`), `RateLimiter`, `ConditionEvaluator`, and an Alerting/Monitoring stack surfaced via a web dashboard. Responsibilities are cleanly separated using SOLID and patterns (Factory, Strategy, Observer, Command, Repository) as documented in `docs/IMPLEMENTATION.md`.

- **Q: How is extensibility achieved for different queue backends (e.g., DB, Redis, File)?**
  - **A:** Via the `QueueDriverInterface` abstraction and a Factory pattern to instantiate drivers. Each driver encapsulates persistence concerns (push/pop/update/release/retry/purge/stats) while the rest of the system depends only on the interface. This enables swapping implementations without touching orchestration logic.

- **Q: How does the system support distributed processing and load balancing?**
  - **A:** `NodeDiscovery` registers worker nodes; `LoadBalancer` selects nodes based on a strategy (e.g., least-loaded, round-robin, weighted). `ResourceManager` tracks queue depth and resource usage for autoscaling decisions. `FaultTolerance` provides idempotency and partition handling. `QueueManager` aggregates distributed stats to inform decisions.

- **Q: What are the trade‑offs of a database-backed queue versus a dedicated broker?**
  - **A:** Pros: simpler operationally, strong consistency, easy introspection, ACID transactions. Cons: higher latency at extreme scale, potential lock contention, limited pub/sub semantics. Mitigations include proper indexing, batched operations, tuned isolation levels, and optional caching.

### Jobs & Execution Model

- **Q: Walk through a job’s lifecycle and states.**
  - **A:** States: pending → processing → completed | failed | retrying. Workers pop from a queue, mark processing, enforce timeout/heartbeat, run `handle()`, update state, and either complete, release with backoff (retrying), or fail (dead-letter semantics are supported by retention and retry APIs).

- **Q: How are retries implemented? What backoff is used?**
  - **A:** Retries are governed per job with `attempts/max_attempts`. Workers use exponential backoff with a cap (e.g., `min(2^attempts, 300s)`) and transition to failed when max is reached. Jitter can be added to avoid thundering herds.

- **Q: How does the worker enforce timeouts and handle graceful shutdown?**
  - **A:** Uses `pcntl` signals. For timeouts, an alarm is set prior to `handle()` and cleared on completion; a timeout throws and is handled as a retry/fail. Graceful stop/pause/resume respond to `SIGTERM/SIGINT/SIGUSR1/SIGUSR2`. Worker recycles on memory thresholds or max processed jobs.

- **Q: How do you ensure idempotent job processing?**
  - **A:** By design: `FaultTolerance->ensureIdempotency` and job‑level idempotency keys or dedup checks in the driver. Handlers should be written to be idempotent (e.g., UPSERTs, checking side effects, external operation guards).

### Scheduling & Workflows

- **Q: How is scheduling implemented?**
  - **A:** `JobScheduler` maintains an in‑memory map of `ScheduledJob`s with cron expressions or natural language schedules. It periodically evaluates due jobs, pushes execution jobs, and handles recurring vs one‑time scheduling with `next_run_at` updates.

- **Q: How are dependencies and chaining handled?**
  - **A:** Jobs can declare dependencies; the driver defers execution until dependencies complete successfully. Pipelines (sequential and parallel) are supported at the orchestration layer as part of workflow features in the README.

### Database & Data Modeling

- **Q: Why this schema and which indexes matter the most?**
  - **A:** `job_queue` stores core fields: `state`, `queue_name`, `priority`, timing fields, and metadata. Critical indexes: `(queue_name, state)` for fast eligible-job lookup, `priority`, `created_at`, and `state` standalone. Additional compound and partial indexes optimize processing and statistics queries. See `docs/IMPLEMENTATION.md` and `docs/PRODUCTION_DEPLOYMENT.md` for index sets.

- **Q: How do you avoid table hot spots and lock contention?**
  - **A:** Use covering indexes for pop queries, short transactions, `UPDATE … WHERE id` by PK, batched updates, and optionally partitioning queues by table or name. Work stealing is tempered with backoff. Read-mostly metrics can be cached.

### Performance & Scalability

- **Q: How does the system reach high throughput and low latency?**
  - **A:** Priority-aware fetching, batched operations, minimal driver contention, worker recycling, payload compression, and encryption offload. Measurements show ~175k jobs/min, ~0.34ms dispatch, 4MB per worker (per README). Tuning includes indexes, prepared statements, and bounded retries.

- **Q: What are bottlenecks at 100+ workers and how to scale?**
  - **A:** DB connection saturation, lock contention, I/O latency, log I/O, and hot queues. Remedies: connection pooling, read replicas for metrics, queue sharding, Redis caching for stats, adaptive backoff, horizontal scaling of workers, and moving to a broker when DB ceilings are hit.

- **Q: How do you measure and optimize slow jobs?**
  - **A:** Leverage structured logs with processing time, job tags, and worker stats; analyze P95/P99, identify heavy handlers, add rate limits, parallelize, and cache external calls. Use the dashboard’s performance endpoints for trend analysis.

### Reliability & Fault Tolerance

- **Q: How are failures and poison messages handled?**
  - **A:** Exponential backoff with jitter, circuit breakers around flaky dependencies, dead-letter retention for manual inspection, and alerting on failure spikes. Poison messages are quarantined after max attempts.

- **Q: What happens on worker crash or network partition?**
  - **A:** Heartbeats and timeouts return jobs to eligible state after lease expiry. `FaultTolerance` addresses split-brain prevention and consistency checks. Supervisors/systemd auto-restart workers; idempotency prevents duplicate side effects.

### Security

- **Q: How are payloads protected at rest and in transit?**
  - **A:** AES-256-GCM encryption with per-payload IV and auth tag via `Support\Encryption`. Compression for large payloads prior to storage. Transport protection is expected via TLS at the web/API layer; secrets managed via `.env` with restricted permissions.

- **Q: What other security controls exist?**
  - **A:** Prepared statements in drivers, input validation, role separation for DB users, dashboard hardening (auth, HTTPS, rate limiting, firewall), and log minimization of sensitive fields.

### Monitoring, Alerting, and Dashboard

- **Q: What metrics are exposed and how are they consumed?**
  - **A:** Queue stats by state/priority, throughput, worker health (memory, uptime, processed/failed), and performance trends. Served via `public/api.php` actions (stats, recent, failed, performance) and visualized in the React dashboard.

- **Q: How does alerting work?**
  - **A:** `AlertManager` enables configurable alerts (e.g., high queue depth, worker failure, performance degradation) with pluggable notification channels (email, webhooks). Production guide includes cron-based monitoring scripts for ops.

### OOP & Design Patterns

- **Q: Which design principles and patterns are used?**
  - **A:** SOLID throughout; Strategy (drivers, load balancing), Factory (drivers), Observer (job lifecycle events/logging), Command (CLI), Repository (data access), and Template Method via `AbstractJob`.

- **Q: How do interfaces improve testability here?**
  - **A:** Contracts (`JobInterface`, `QueueDriverInterface`, `WorkerInterface`) decouple components, allowing mocks/stubs for units. Scheduler, rate limiter, and distributed modules depend on interfaces, enabling targeted tests.

### CLI and Operations

- **Q: Key operational commands and parameters?**
  - **A:** `queue:test` (seed), `queue:work` (workers with `--workers`, `--memory`, `--timeout`, `--max-jobs`), `dashboard:serve`, and scheduling commands. Supervisor/systemd configs in `docs/PRODUCTION_DEPLOYMENT.md` provide production-grade process management.

- **Q: How do you perform safe deployments and rollbacks?**
  - **A:** Immutable artifacts, migrations gated, Supervisor/systemd for zero‑downtime restarts, DB backups/retention as per backup scripts, and environment‑specific configs in `.env` with restricted permissions.

### Testing & Quality

- **Q: What’s the testing strategy?**
  - **A:** Unit tests for `AbstractJob` and core components; integration tests for `QueueManager` and driver operations; stress tests and performance benchmarks (see `PERFORMANCE_REPORT.md`). Mocks for drivers enable deterministic unit tests.

- **Q: How do you test retry and timeout logic deterministically?**
  - **A:** Inject a fake clock or configurable delay calculation, mock `QueueDriverInterface` to track `release` calls and delays, simulate `SIGALRM` behavior, and assert state transitions under controlled conditions.

### Performance Engineering Scenarios

- **Q: DB CPU is high and pending jobs keep rising—what do you do?**
  - **A:** Verify indexes, enable slow query log, batch `pop`/`update`, reduce polling frequency under emptiness, shard queues, scale read replicas for metrics, and temporarily increase workers with backpressure adjustments.

- **Q: External API latency spikes are causing retries—how to stabilize?**
  - **A:** Add rate limits per job type, increase backoff/jitter, implement circuit breakers with half‑open probes, cache responses, and isolate those jobs to separate queues with capacity limits.

### Distributed & Scaling Scenarios

- **Q: How do you scale across nodes without duplicate processing?**
  - **A:** Use atomic pop/lease semantics in the driver, heartbeat/timeout recovery, idempotent handlers, and `FaultTolerance` dedup. Load-balancer strategies distribute job families to nodes; quotas prevent overloads.

- **Q: How to choose a load balancing strategy?**
  - **A:** For uniform jobs use round‑robin; for variable costs use least‑loaded; for priority or hardware differences use weighted strategies. Observe P95 processing time and re-evaluate periodically.

### Observability & Troubleshooting

- **Q: What’s your approach to debugging a stuck queue?**
  - **A:** Inspect stats (pending vs processing), worker health/heartbeats, DB locks, and recent exceptions; retry selectively; purge poison messages; and correlate with deployment changes. Use dashboard analytics and logs with job IDs.

- **Q: How do you trace a job end-to-end?**
  - **A:** Use job IDs/tags across logs, include processing time and attempts, and expose a “job details” endpoint/page for state, payload metadata, and exception history.

### Security & Compliance Scenarios

- **Q: Sensitive data in payloads—how to reduce exposure?**
  - **A:** Encrypt fields at source, redact logs, use field‑level encryption or tokenization, restrict DB roles, and set short retention for completed jobs via `cleanupOldCompletedJobs`.

### PHP Platform & Language

- **Q: Which PHP 8.1/8.2 features are leveraged or recommended here?**
  - **A:** Strict types, union and intersection types where appropriate, readonly properties for immutable config/value objects, enums for job states/priorities if desired, fibers are not required here, and `match` for concise branching. Emphasize typed signatures across public APIs.

- **Q: How do `pcntl` and `posix` extensions power workers?**
  - **A:** `pcntl_signal`, `pcntl_alarm`, and signal dispatch enable timeouts and graceful lifecycle (stop/pause/resume). `posix_kill` sends termination signals. Fallbacks exist for environments without these extensions (reduced features or supervisor-driven restarts).

- **Q: How are PDO and transactions used safely?**
  - **A:** Use prepared statements everywhere, short-lived transactions around pop/lease/update, appropriate isolation (often READ COMMITTED), and id-based updates to avoid full scans. Consider persistent connections with caution; monitor connection storms under high concurrency.

### Standards & Tooling (PHP)

- **Q: Which PSRs and ecosystem tools fit this codebase?**
  - **A:** PSR-4 autoloading, PSR-3 logging (Monolog), PSR-12 coding style, PSR-11 for potential containerization. Tooling: Composer scripts, PHPUnit for unit/integration, PHPStan for static analysis, PHPCS for style, and PHP_CodeSniffer/PHPCBF for autofixes.

- **Q: How do you structure tests for concurrency/timeouts?**
  - **A:** Separate deterministic unit tests using driver mocks and fake clocks from integration tests that spin a worker loop with small timeouts; assert state transitions, delays, and update calls.

### Runtime & Tuning (PHP-FPM/CLI)

- **Q: Key performance tunings for PHP in this system?**
  - **A:** Enable OPcache with sane `opcache.memory_consumption`, `opcache.max_accelerated_files`, and `validate_timestamps=0` in production; tune PHP-FPM pool sizes to CPU and I/O; set `pm.max_requests` to recycle; adjust `memory_limit` per worker; ensure realpath cache is sized; and isolate CLI worker processes from web pool where possible.

- **Q: What about logging performance and backpressure?**
  - **A:** Use async-friendly handlers or buffered streams, lower log level on hot paths, rotate logs, and avoid synchronous remote handlers in the request path. Consider sampling for high-volume info/debug logs.

### Deployment & Docker

- **Q: Outline a production deployment.**
  - **A:** Nginx → PHP‑FPM app/API → Supervisor‑managed workers; database (MySQL/PostgreSQL/SQLite) and optional Redis; logs rotated; backups scheduled; metrics scraped. See `docs/PRODUCTION_DEPLOYMENT.md` for configs and hardening.

- **Q: How do you run locally with Docker and scale workers?**
  - **A:** `docker-compose up -d`, inspect logs, and scale with `docker-compose up -d --scale worker=3`. Use provided exec commands to seed and inspect queue state.

### Behavioral & Ownership

- **Q: A critical queue is lagging; how do you respond?**
  - **A:** Triage impact, raise an alert, enable canary scaling of workers, throttle non‑critical queues, purge/park poison messages, communicate status, and open an incident report with follow‑up action items.

---

## Rapid Reference (Snippets)

- **Worker retry delay formula (conceptual):** exponential backoff with cap and optional jitter.
- **Key indexes:** `(queue_name, state)`, `priority`, `created_at`, partial on `state='processing'` for recovery.
- **Signals:** `SIGTERM/SIGINT` (stop), `SIGUSR1/2` (pause/resume), `SIGALRM` (timeout).
- **Security:** AES‑256‑GCM, `.env` secrets, least‑privilege DB user, HTTPS.
- **Scaling levers:** worker count, queue sharding, caching metrics, load balancing strategy, resource quotas.

## Practice Prompts

1. Design a new `ImageThumbnailJob` that is idempotent and resilient to timeouts.
2. Add a per‑tenant rate limit and discuss where keys should be derived.
3. Propose indexes for a new `scheduled_jobs` table with heavy reads and light writes.
4. Migrate queue stats reads to Redis cache; outline invalidation.
5. Add an alert for “processing jobs > N for M minutes” and describe notification wiring.
