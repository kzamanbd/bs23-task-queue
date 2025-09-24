import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
    timeout: 10000
});

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

export async function createTestJobs(
    count: number = 10,
    queue: string = 'default',
    priority: number = 5
): Promise<{ success: boolean; created_jobs: string[]; count: number }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'create_test_jobs' },
        data: new URLSearchParams({
            count: count.toString(),
            queue,
            priority: priority.toString()
        }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

export async function purgeQueue(queue: string): Promise<{ success: boolean }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'purge' },
        data: new URLSearchParams({ queue }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

export async function retryFailedJob(jobId: string): Promise<{ success: boolean }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'retry' },
        data: new URLSearchParams({ job_id: jobId }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

export async function getJobDetails(jobId: string): Promise<any> {
    const response = await api.get('/api.php', {
        params: { action: 'job_details', job_id: jobId }
    });
    return response.data;
}

export async function getOverview(limit: number = 20): Promise<OverviewResponse> {
    const response = await api.get('/api.php', { params: { action: 'overview', limit } });
    return response.data as OverviewResponse;
}

export async function getAllJobs(
    options: {
        limit?: number;
        state?: string;
        queue?: string;
    } = {}
): Promise<Job[]> {
    const params: any = { action: 'recent' };

    if (options.limit) params.limit = options.limit.toString();
    if (options.state) params.state = options.state;
    if (options.queue) params.queue = options.queue;

    const response = await api.get('/api.php', { params });
    return response.data as Job[];
}

// Scheduled Jobs API
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

export async function getScheduledJobs(): Promise<ScheduledJobsResponse> {
    const response = await api.get('/api.php', {
        params: { action: 'scheduled_jobs', sub_action: 'list' }
    });
    return response.data;
}

export async function createScheduledJob(data: {
    schedule: string;
    job_class?: string;
    payload?: string;
    queue?: string;
    priority?: number;
    recurring?: boolean;
    expires_at?: string;
}): Promise<{ success: boolean; job_id: string; message: string }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'scheduled_jobs', sub_action: 'create' },
        data: new URLSearchParams({
            schedule: data.schedule,
            job_class: data.job_class || 'TaskQueue\\Jobs\\TestJob',
            payload: data.payload || '{}',
            queue: data.queue || 'default',
            priority: (data.priority || 5).toString(),
            recurring: data.recurring ? '1' : '0',
            expires_at: data.expires_at || ''
        }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

export async function deleteScheduledJob(
    jobId: string
): Promise<{ success: boolean; message: string }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'scheduled_jobs', sub_action: 'delete' },
        data: new URLSearchParams({ job_id: jobId }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

export async function runScheduledJob(
    jobId: string
): Promise<{ success: boolean; job_id: string; message: string }> {
    const response = await api.post('/api.php', null, {
        params: { action: 'scheduled_jobs', sub_action: 'run' },
        data: new URLSearchParams({ job_id: jobId }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    return response.data;
}

