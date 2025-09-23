import { useCallback, useEffect, useState } from 'react';
import type { Job } from '../services/api';
import { getAllJobs } from '../services/api';

interface JobsState {
    jobs: Job[];
    isLoading: boolean;
    error: string | null;
    lastUpdated: Date | null;
}

export const useJobs = (options: { limit?: number; state?: string; queue?: string } = {}) => {
    const [state, setState] = useState<JobsState>({
        jobs: [],
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const fetchJobs = useCallback(async () => {
        try {
            setState((prev) => ({ ...prev, isLoading: true, error: null }));
            const jobs = await getAllJobs(options);
            setState({ jobs, isLoading: false, error: null, lastUpdated: new Date() });
        } catch (error) {
            setState((prev) => ({
                ...prev,
                isLoading: false,
                error: error instanceof Error ? error.message : 'Failed to fetch jobs'
            }));
        }
    }, [options.limit, options.state, options.queue]);

    useEffect(() => {
        fetchJobs();
    }, [fetchJobs]);

    return {
        allJobs: state.jobs,
        isLoading: state.isLoading,
        error: state.error,
        lastUpdated: state.lastUpdated,
        refreshData: fetchJobs
    };
};

