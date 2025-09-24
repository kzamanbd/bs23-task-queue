# System Architecture and Data Flow

```mermaid
flowchart LR
  subgraph App["Producers (CLI/API/Code)"]
    A1["User code creates Job implements JobInterface"]
    A2["CLI: queue:test, schedule:create"]
    A3["Dashboard API: create_test_jobs"]
  end

  subgraph QM["QueueManager"]
    QMpush["push(job)"]
    QMpop["pop(queue)"]
    QMstats["getQueueStats / getJobs etc."]
  end

  subgraph Storage["Queue Storage (SQLite via DatabaseQueueDriver)"]
    S1["push -> INSERT encrypted(+compressed) payload"]
    S2["pop -> SELECT pending by priority, mark processing"]
    S3["update/delete/release/retry/cleanup"]
  end

  subgraph Worker["Workers"]
    W1["work(queue) loop"]
    W2["processJob(job)"]
    W3["exponential backoff, release(job, delay)"]
    W4["update state completed/failed"]
  end

  subgraph Scheduler["JobScheduler"]
    SCH1["schedule(ScheduledJob)"]
    SCH2["run() checkInterval"]
    SCH3["isDue -> push execution job"]
  end

  subgraph Dashboard["HTTP API + React UI"]
    API["public/api.php ActionsRegistry"]
    UI["client React dashboard"]
  end

  A1 --> QMpush
  A2 --> QMpush
  A3 --> API

  QMpush --> S1
  QMpop --> S2
  S2 --> W1
  W1 --> W2
  W2 -->|success| W4
  W2 -->|error & canRetry| W3 --> S3
  W2 -->|error & final| W4 --> S3
  W4 --> S3

  CFG["config/CLI"] -.-> SCH1
  SCH2 -->|due| SCH3 --> S1

  API <--> QMstats
  UI <--> API
```

## Worker Processing Lifecycle (Sequence)

```mermaid
sequenceDiagram
  autonumber
  participant Worker
  participant Driver as DatabaseQueueDriver
  participant Job
  Note over Worker: work(queue) loop
  Worker->>Driver: pop(queue)
  alt job available
    Driver-->>Worker: Job (state=processing)
    Worker->>Job: setState(processing); incrementAttempts()
    Worker->>Worker: setup timeout if configured
    Worker->>Job: handle()
    Worker->>Worker: pcntl_alarm(0)
    Worker->>Job: setState(completed); setCompletedAt()
    Worker->>Driver: update(job)
  else error thrown
    Worker->>Worker: pcntl_alarm(0)
    alt canRetry
      Worker->>Job: setState(retrying)
      Worker->>Driver: release(job, delay=2^attempts<=300)
    else final failure
      Worker->>Job: setState(failed); setFailedAt()
      Worker->>Driver: update(job) (kept for inspection)
    end
  end
```

## Scheduler Flow (Cron/Natural Language -> Dispatch)

```mermaid
flowchart TD
  NL["NaturalLanguageParser.parse('every 5 minutes' | 'at 3pm' | cron)"]
  CJ["CronExpression"]
  SJ["ScheduledJob (id, queue, priority, recurring, expires_at, tags)"]
  JS["JobScheduler"]
  DQ["DatabaseQueueDriver.push(executionJob)"]

  NL --> CJ
  CJ --> SJ
  SJ -->|schedule| JS
  JS -->|run loop (checkInterval=60s)| JS
  JS -->|isDue(now)| DQ
  JS -->|non-recurring| remove["unschedule(original)"]
  JS -->|recurring| updateNext["markAsRun + compute next_run_at"]
```

## CLI Overview (Commands and Flows)

```mermaid
mindmap
  root((CLI))
    queue:work
      starts worker loop
      reads from queue
      processes jobs with retry/backoff
    queue:test
      creates N test jobs
      pushes via QueueManager→Driver
    schedule:manage
      list
      create (--schedule cron|NL, --job-class, --payload, --queue, --priority, --recurring, --expires)
      delete (--job-id)
      next (preview next run times)
      stats
    dashboard:serve
      php -S host:port
      serves public/ and API endpoints for React UI
```

## Dashboard/API Integration

```mermaid
flowchart LR
  UI["React client (client/src)"] -- fetch --> API["public/api.php?action=..."]
  API --> Reg["ActionsRegistry"]
  Reg -->|overview| A1["Overview"]
  Reg -->|stats| A2["Stats"]
  Reg -->|failed| A3["Failed"]
  Reg -->|recent| A4["Recent"]
  Reg -->|retry| A5["Retry"]
  Reg -->|purge| A6["Purge"]
  Reg -->|create_test_jobs| A7["CreateTestJobs"]
  Reg -->|job_details| A8["JobDetails"]
  Reg -->|queues| A9["Queues"]
  Reg -->|performance| A10["Performance"]
  Reg -->|scheduled_jobs| A11["ScheduledJobs"]
  A1-.A11.-> QM["QueueManager → DatabaseQueueDriver"]
```
