import { CheckCircle, Clock, XCircle, Zap } from 'lucide-react';
import React from 'react';
import PerformanceChart from '../components/dashboard/PerformanceChart';
import QueueChart from '../components/dashboard/QueueChart';
import QueuesSummary from '../components/dashboard/QueuesSummary';
import QuickActions from '../components/dashboard/QuickActions';
import RecentJobs from '../components/dashboard/RecentJobs';
import StatCard from '../components/dashboard/StatCard';
import { useOverview } from '../hooks/useOverview';

const Overview: React.FC = () => {
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

export default Overview;

