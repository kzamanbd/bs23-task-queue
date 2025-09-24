export interface QueueStats {
    [queueName: string]: {
        total_jobs: number;
        by_state: {
            pending: number;
            processing: number;
            completed: number;
            failed: number;
        };
        avg_priority: number;
        oldest_job: string | null;
        newest_job: string | null;
    };
}

export interface Job {
    id: string;
    state: 'pending' | 'processing' | 'completed' | 'failed';
    queue: string;
    priority: number;
    created_at: string;
    updated_at: string;
    completed_at?: string | null;
    failed_at?: string | null;
    attempts: number;
    max_attempts: number;
    tags: string[];
    payload: any;
    exception?: string | null;
}

export interface PerformanceMetrics {
    timestamp: string;
    total_jobs: number;
    pending: number;
    processing: number;
    completed: number;
    failed: number;
    success_rate: number;
    failure_rate: number;
}

export interface ApiResponse<T> {
    data?: T;
    error?: boolean;
    message?: string;
}

export interface OverviewResponse {
    stats: QueueStats;
    queues: Array<{
        name: string;
        total_jobs: number;
        by_state: {
            pending: number;
            processing: number;
            completed: number;
            failed: number;
        };
        avg_priority: number;
        oldest_job: string | null;
        newest_job: string | null;
    }>;
    performance: PerformanceMetrics;
    recent: Job[];
}

export interface ScheduledJob {
    id: string;
    cron_expression: string;
    next_run_at: string | null;
    recurring: boolean;
    expires_at: string | null;
    created_at: string;
    queue: string;
    priority: number;
    tags: string[];
    payload: any;
    is_active: boolean;
}

export interface ScheduledJobsResponse {
    scheduled_jobs: ScheduledJob[];
    count: number;
}


