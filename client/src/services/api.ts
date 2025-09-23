import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
    timeout: 10000,
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

class ApiService {
    async getStats(): Promise<QueueStats> {
        const response = await api.get('/api.php', { params: { action: 'stats' } });
        return response.data;
    }

    async getRecentJobs(limit: number = 20, queue?: string, state?: string): Promise<Job[]> {
        const params: any = { action: 'recent', limit };
        if (queue) params.queue = queue;
        if (state) params.state = state;
        
        const response = await api.get('/api.php', { params });
        return response.data;
    }

    async getFailedJobs(queue?: string): Promise<Job[]> {
        const params: any = { action: 'failed' };
        if (queue) params.queue = queue;
        
        const response = await api.get('/api.php', { params });
        return response.data;
    }

    async getPerformanceMetrics(): Promise<PerformanceMetrics> {
        const response = await api.get('/api.php', { params: { action: 'performance' } });
        return response.data;
    }

    async getJobDetails(jobId: string): Promise<Job> {
        const response = await api.get('/api.php', { params: { action: 'job_details', job_id: jobId } });
        return response.data;
    }

    async createTestJobs(count: number = 10, queue: string = 'default', priority: number = 5): Promise<{ success: boolean; created_jobs: string[]; count: number }> {
        const response = await api.post('/api.php', null, {
            params: { action: 'create_test_jobs' },
            data: new URLSearchParams({ count: count.toString(), queue, priority: priority.toString() }),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        return response.data;
    }

    async purgeQueue(queue: string): Promise<{ success: boolean }> {
        const response = await api.post('/api.php', null, {
            params: { action: 'purge' },
            data: new URLSearchParams({ queue }),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        return response.data;
    }

    async retryFailedJob(jobId: string): Promise<{ success: boolean }> {
        const response = await api.post('/api.php', null, {
            params: { action: 'retry' },
            data: new URLSearchParams({ job_id: jobId }),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        return response.data;
    }

    async getQueues(): Promise<Array<{
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
    }>> {
        const response = await api.get('/api.php', { params: { action: 'queues' } });
        return response.data;
    }
}

export const apiService = new ApiService();
