import { CheckCircle, Clock, XCircle, Zap } from 'lucide-react';
import PerformanceChart from './components/dashboard/PerformanceChart';
import QueueChart from './components/dashboard/QueueChart';
import QueuesSummary from './components/dashboard/QueuesSummary';
import QuickActions from './components/dashboard/QuickActions';
import RecentJobs from './components/dashboard/RecentJobs';
import RefreshIndicator from './components/dashboard/RefreshIndicator';
import StatCard from './components/dashboard/StatCard';
import Header from './components/Header';
import { useDashboard } from './hooks/useDashboard';

function App() {
    const {
        totals,
        recentJobs,
        performanceData,
        isLoading,
        error,
        showRefreshIndicator,
        refreshData,
        createTestJobs,
        purgeQueue,
        queues
    } = useDashboard();

    return (
        <div className="relative min-h-screen overflow-hidden">
            {/* Enhanced Background with Gradient and Pattern */}
            <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(120,119,198,0.1),transparent_50%)]"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(120,119,198,0.05),transparent_50%)]"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(120,119,198,0.05),transparent_50%)]"></div>

            {/* Content */}
            <div className="relative z-10">
                <Header />

                <RefreshIndicator show={showRefreshIndicator} />

                <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <div>
                        <QuickActions
                            onRefresh={refreshData}
                            onCreateTestJobs={createTestJobs}
                            onPurgeQueue={purgeQueue}
                            isLoading={isLoading}
                        />
                    </div>

                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                    <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
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
                        <RecentJobs jobs={recentJobs} isLoading={isLoading} error={error} />
                        <QueuesSummary queues={queues} isLoading={isLoading} />
                    </div>
                </div>
            </div>
        </div>
    );
}

export default App;
