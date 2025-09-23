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

export async function getOverview(limit: number = 20): Promise<OverviewResponse> {
    const response = await api.get('/api.php', { params: { action: 'overview', limit } });
    return response.data as OverviewResponse;
}

