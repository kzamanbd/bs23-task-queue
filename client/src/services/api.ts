import type { Job, OverviewResponse, ScheduledJobsResponse } from '@/types/api';
import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    }
});

type HttpMethod = 'get' | 'post' | 'delete';

const request = async <T = any>(options: {
    url?: string;
    method?: HttpMethod;
    params?: any;
    data?: any;
}): Promise<T> => {
    const { url = '/api.php', method = 'post', params, data } = options;

    if (method === 'get') {
        const response = await api.get(url, { params });
        return response.data as T;
    }

    const response = await api.post(url, data ?? null, { params });
    return response.data as T;
};

export async function createTestJobs(
    count: number = 10,
    queue: string = 'default',
    priority: number = 5
): Promise<{ success: boolean; created_jobs: string[]; count: number }> {
    const data = {
        count: count.toString(),
        queue,
        priority: priority.toString()
    };
    const params = { action: 'create_test_jobs' };
    return request({ data, params });
}

export async function purgeQueue(queue: string): Promise<{ success: boolean }> {
    const data = { queue };
    const params = { action: 'purge' };
    return request({ data, params });
}

export async function retryFailedJob(jobId: string): Promise<{ success: boolean }> {
    return request({
        params: { action: 'retry' },
        data: new URLSearchParams({ job_id: jobId })
    });
}

export async function getJobDetails(jobId: string): Promise<any> {
    return request({
        method: 'get',
        params: { action: 'job_details', job_id: jobId }
    });
}

export async function getOverview(limit: number = 20): Promise<OverviewResponse> {
    return request<OverviewResponse>({ method: 'get', params: { action: 'overview', limit } });
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

    return request<Job[]>({ method: 'get', params });
}

// Scheduled Jobs API types moved to ../types/api-types

export async function getScheduledJobs(): Promise<ScheduledJobsResponse> {
    return request<ScheduledJobsResponse>({
        method: 'get',
        params: { action: 'scheduled_jobs', sub_action: 'list' }
    });
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
    // Format expires_at to "Y-m-d H:i:s" if provided (backend expects this format)
    const formattedExpiresAt = data.expires_at
        ? (() => {
              const raw = data.expires_at; // e.g. "2025-09-24T12:34" from datetime-local
              const withSpace = raw.replace('T', ' ');
              // If seconds are missing, append ":00"
              return withSpace.length === 16 ? `${withSpace}:00` : withSpace;
          })()
        : '';

    const body = new URLSearchParams({
        schedule: data.schedule,
        job_class: data.job_class || 'TaskQueue\\Jobs\\TestJob',
        payload: data.payload || '{}',
        queue: data.queue || 'default',
        priority: (data.priority || 5).toString(),
        recurring: data.recurring ? '1' : '0',
        expires_at: formattedExpiresAt
    });

    return request({
        data: body,
        params: { action: 'scheduled_jobs', sub_action: 'create' }
    });
}

export async function deleteScheduledJob(
    jobId: string
): Promise<{ success: boolean; message: string }> {
    return request({
        params: { action: 'scheduled_jobs', sub_action: 'delete' },
        data: new URLSearchParams({ job_id: jobId })
    });
}

export async function runScheduledJob(
    jobId: string
): Promise<{ success: boolean; job_id: string; message: string }> {
    return request({
        params: { action: 'scheduled_jobs', sub_action: 'run' },
        data: new URLSearchParams({ job_id: jobId })
    });
}

