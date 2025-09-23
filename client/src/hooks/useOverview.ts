import { useCallback, useEffect, useState } from 'react';
import type { Job, QueueStats } from '../services/api';
import {
    createTestJobs as apiCreateTestJobs,
    purgeQueue as apiPurgeQueue,
    getOverview
} from '../services/api';

interface PerformanceDataPoint {
    time: string;
    total: number;
    pending: number;
    processing: number;
}

interface QueueSummaryItem {
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
}

interface OverviewState {
    stats: QueueStats | null;
    recentJobs: Job[];
    performanceData: PerformanceDataPoint[];
    queues: QueueSummaryItem[];
    isLoading: boolean;
    error: string | null;
    lastUpdated: Date | null;
}

const PERFORMANCE_STORAGE_KEY = 'dashboard.performanceData.v1';
const PERFORMANCE_MAX_POINTS = 20;

export const useOverview = () => {
    const [state, setState] = useState<OverviewState>({
        stats: null,
        recentJobs: [],
        performanceData: [],
        queues: [],
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const [showRefreshIndicator, setShowRefreshIndicator] = useState(false);

    useEffect(() => {
        try {
            const raw = localStorage.getItem(PERFORMANCE_STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw) as PerformanceDataPoint[];
                if (Array.isArray(parsed)) {
                    setState((prev) => ({
                        ...prev,
                        performanceData: parsed.slice(-PERFORMANCE_MAX_POINTS)
                    }));
                }
            }
        } catch (_) {}
    }, []);

    const savePerformanceToStorage = useCallback((points: PerformanceDataPoint[]) => {
        try {
            localStorage.setItem(
                PERFORMANCE_STORAGE_KEY,
                JSON.stringify(points.slice(-PERFORMANCE_MAX_POINTS))
            );
        } catch (_) {}
    }, []);

    const calculateTotals = useCallback((stats: QueueStats | null) => {
        if (!stats) return { pending: 0, processing: 0, completed: 0, failed: 0 };
        return Object.values(stats).reduce(
            (totals, queueStats) => ({
                pending: totals.pending + (queueStats.by_state.pending || 0),
                processing: totals.processing + (queueStats.by_state.processing || 0),
                completed: totals.completed + (queueStats.by_state.completed || 0),
                failed: totals.failed + (queueStats.by_state.failed || 0)
            }),
            { pending: 0, processing: 0, completed: 0, failed: 0 }
        );
    }, []);

    const fetchData = useCallback(
        async (showIndicator = false) => {
            try {
                setState((prev) => ({ ...prev, error: null }));

                const [overview] = await Promise.all([getOverview(20)]);

                setState((prev) => {
                    const newPerformanceData = [...prev.performanceData];
                    const now = new Date().toLocaleTimeString();
                    newPerformanceData.push({
                        time: now,
                        total: overview.performance.total_jobs,
                        pending: overview.performance.pending,
                        processing: overview.performance.processing
                    });
                    if (newPerformanceData.length > PERFORMANCE_MAX_POINTS) {
                        newPerformanceData.shift();
                    }
                    savePerformanceToStorage(newPerformanceData);

                    return {
                        ...prev,
                        stats: overview.stats,
                        recentJobs: overview.recent,
                        performanceData: newPerformanceData,
                        queues: overview.queues,
                        isLoading: false,
                        lastUpdated: new Date()
                    };
                });

                if (showIndicator) setShowRefreshIndicator(true);
            } catch (error) {
                console.error('Error fetching overview data:', error);
                setState((prev) => ({
                    ...prev,
                    error: error instanceof Error ? error.message : 'Failed to fetch data',
                    isLoading: false
                }));
            }
        },
        [savePerformanceToStorage]
    );

    const refreshData = useCallback(() => {
        fetchData(true);
    }, [fetchData]);

    const createTestJobs = useCallback(async () => {
        try {
            const result = await apiCreateTestJobs(100, 'default', 5);
            if (result.success) {
                alert(`Created ${result.count} test jobs successfully!`);
                fetchData(true);
            } else {
                alert('Error creating test jobs');
            }
        } catch (error) {
            alert(
                'Error creating test jobs: ' +
                    (error instanceof Error ? error.message : 'Unknown error')
            );
        }
    }, [fetchData]);

    const purgeQueue = useCallback(
        async (queueName: string) => {
            if (
                !confirm(
                    `Are you sure you want to purge the "${queueName}" queue? This action cannot be undone.`
                )
            ) {
                return;
            }
            try {
                const result = await apiPurgeQueue(queueName);
                if (result.success) {
                    alert(`Queue "${queueName}" purged successfully!`);
                    fetchData(true);
                } else {
                    alert('Error purging queue');
                }
            } catch (error) {
                alert(
                    'Error purging queue: ' +
                        (error instanceof Error ? error.message : 'Unknown error')
                );
            }
        },
        [fetchData]
    );

    useEffect(() => {
        fetchData();
    }, []);

    useEffect(() => {
        const interval = setInterval(() => {
            fetchData();
        }, 5000);
        return () => clearInterval(interval);
    }, [fetchData]);

    const totals = calculateTotals(state.stats);

    return {
        ...state,
        totals,
        showRefreshIndicator,
        refreshData,
        createTestJobs,
        purgeQueue
    };
};

export type { PerformanceDataPoint };

