import { useCallback, useEffect, useState } from 'react';
import type { Job, QueueStats } from '../services/api';
import { apiService } from '../services/api';

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

interface DashboardState {
    stats: QueueStats | null;
    recentJobs: Job[];
    performanceData: PerformanceDataPoint[];
    queues: QueueSummaryItem[];
    isLoading: boolean;
    error: string | null;
    lastUpdated: Date | null;
}

export const useDashboard = () => {
    const [state, setState] = useState<DashboardState>({
        stats: null,
        recentJobs: [],
        performanceData: [],
        queues: [],
        isLoading: true,
        error: null,
        lastUpdated: null,
    });

    const [showRefreshIndicator, setShowRefreshIndicator] = useState(false);

    const calculateTotals = useCallback((stats: QueueStats | null) => {
        if (!stats) return { pending: 0, processing: 0, completed: 0, failed: 0 };

        return Object.values(stats).reduce(
            (totals, queueStats) => ({
                pending: totals.pending + (queueStats.by_state.pending || 0),
                processing: totals.processing + (queueStats.by_state.processing || 0),
                completed: totals.completed + (queueStats.by_state.completed || 0),
                failed: totals.failed + (queueStats.by_state.failed || 0),
            }),
            { pending: 0, processing: 0, completed: 0, failed: 0 }
        );
    }, []);

    const fetchData = useCallback(async (showIndicator = false) => {
        try {
            setState(prev => ({ ...prev, error: null }));

            const [stats, recentJobs, performance, queues] = await Promise.all([
                apiService.getStats(),
                apiService.getRecentJobs(20),
                apiService.getPerformanceMetrics(),
                apiService.getQueues(),
            ]);

            setState(prev => {
                const newPerformanceData = [...prev.performanceData];
                
                // Add new performance data point
                const now = new Date().toLocaleTimeString();
                newPerformanceData.push({
                    time: now,
                    total: performance.total_jobs,
                    pending: performance.pending,
                    processing: performance.processing,
                });

                // Keep only last 20 data points
                if (newPerformanceData.length > 20) {
                    newPerformanceData.shift();
                }

                return {
                    ...prev,
                    stats,
                    recentJobs,
                    performanceData: newPerformanceData,
                    queues,
                    isLoading: false,
                    lastUpdated: new Date(),
                };
            });

            if (showIndicator) {
                setShowRefreshIndicator(true);
            }
        } catch (error) {
            console.error('Error fetching dashboard data:', error);
            setState(prev => ({
                ...prev,
                error: error instanceof Error ? error.message : 'Failed to fetch data',
                isLoading: false,
            }));
        }
    }, []);

    const refreshData = useCallback(() => {
        fetchData(true);
    }, [fetchData]);

    const createTestJobs = useCallback(async () => {
        try {
            const result = await apiService.createTestJobs(100, 'default', 5);
            if (result.success) {
                alert(`Created ${result.count} test jobs successfully!`);
                fetchData(true);
            } else {
                alert('Error creating test jobs');
            }
        } catch (error) {
            alert('Error creating test jobs: ' + (error instanceof Error ? error.message : 'Unknown error'));
        }
    }, [fetchData]);

    const purgeQueue = useCallback(async (queueName: string) => {
        if (!confirm(`Are you sure you want to purge the "${queueName}" queue? This action cannot be undone.`)) {
            return;
        }

        try {
            const result = await apiService.purgeQueue(queueName);
            if (result.success) {
                alert(`Queue "${queueName}" purged successfully!`);
                fetchData(true);
            } else {
                alert('Error purging queue');
            }
        } catch (error) {
            alert('Error purging queue: ' + (error instanceof Error ? error.message : 'Unknown error'));
        }
    }, [fetchData]);

    // Initial data fetch
    useEffect(() => {
        fetchData();
    }, []);

    // Auto-refresh every 5 seconds
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
        purgeQueue,
    };
};
