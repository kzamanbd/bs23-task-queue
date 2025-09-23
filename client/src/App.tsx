import Header from './components/Header';
import PerformanceChart from './components/PerformanceChart';
import QueueChart from './components/QueueChart';
import QueuesSummary from './components/QueuesSummary';
import QuickActions from './components/QuickActions';
import RecentJobs from './components/RecentJobs';
import RefreshIndicator from './components/RefreshIndicator';
import StatCard from './components/StatCard';
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
        <div className="min-h-screen bg-gray-50 font-sans text-gray-800">
            <Header />

            <RefreshIndicator show={showRefreshIndicator} />

            <div className="mx-auto max-w-7xl px-8 py-8">
                <QuickActions
                    onRefresh={refreshData}
                    onCreateTestJobs={createTestJobs}
                    onPurgeQueue={purgeQueue}
                    isLoading={isLoading}
                />

                <div className="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Pending Jobs"
                        count={totals.pending}
                        color="text-amber-500"
                        isLoading={isLoading}
                    />
                    <StatCard
                        title="Processing Jobs"
                        count={totals.processing}
                        color="text-blue-500"
                        isLoading={isLoading}
                    />
                    <StatCard
                        title="Completed Jobs"
                        count={totals.completed}
                        color="text-green-500"
                        isLoading={isLoading}
                    />
                    <StatCard
                        title="Failed Jobs"
                        count={totals.failed}
                        color="text-red-500"
                        isLoading={isLoading}
                    />
                </div>

                <div className="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <QueueChart
                        pending={totals.pending}
                        processing={totals.processing}
                        completed={totals.completed}
                        failed={totals.failed}
                    />
                    <PerformanceChart data={performanceData} />
                </div>

                <div className="mt-8">
                    <RecentJobs jobs={recentJobs} isLoading={isLoading} error={error} />
                    <QueuesSummary queues={queues} isLoading={isLoading} />
                </div>
            </div>
        </div>
    );
}

export default App;
