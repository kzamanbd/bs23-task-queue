import { CheckCircle, Clock, XCircle, Zap } from 'lucide-react';
import React from 'react';
import PerformanceChart from '../components/dashboard/performance-chart';
import QuickActions from '../components/dashboard/quick-actions';
import StatCard from '../components/dashboard/stat-card';
import RecentJobs from '../components/jobs/recent-jobs';
import QueueChart from '../components/queues/queue-chart';
import QueuesSummary from '../components/queues/queues-summary';
import { useOverview } from '../hooks/useOverview';

const OverviewStats: React.FC = () => {
    const {
        totals,
        recentJobs,
        performanceData,
        isLoading,
        error,
        refreshData,
        createTestJobs,
        purgeQueue,
        queues
    } = useOverview();

    return (
        <div className="space-y-8">
            <QuickActions
                onRefresh={refreshData}
                onCreateTestJobs={createTestJobs}
                onPurgeQueue={purgeQueue}
                isLoading={isLoading}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Pending Jobs"
                    count={totals.pending}
                    color="text-amber-500"
                    isLoading={isLoading}
                    icon={<Clock className="h-6 w-6" />}
                />
                <StatCard
                    title="Processing Jobs"
                    count={totals.processing}
                    color="text-blue-500"
                    isLoading={isLoading}
                    icon={<Zap className="h-6 w-6" />}
                />
                <StatCard
                    title="Completed Jobs"
                    count={totals.completed}
                    color="text-green-500"
                    isLoading={isLoading}
                    icon={<CheckCircle className="h-6 w-6" />}
                />
                <StatCard
                    title="Failed Jobs"
                    count={totals.failed}
                    color="text-red-500"
                    isLoading={isLoading}
                    icon={<XCircle className="h-6 w-6" />}
                />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <QueueChart
                    pending={totals.pending}
                    processing={totals.processing}
                    completed={totals.completed}
                    failed={totals.failed}
                />
                <div className="lg:col-span-2">
                    <PerformanceChart data={performanceData} />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <RecentJobs
                    jobs={recentJobs}
                    isLoading={isLoading}
                    error={error}
                    onRefresh={refreshData}
                />
                <QueuesSummary queues={queues} isLoading={isLoading} />
            </div>
        </div>
    );
};

export default OverviewStats;

