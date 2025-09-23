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
        queues,
    } = useDashboard();

    return (
        <div className="bg-gray-50 text-gray-800 font-sans min-h-screen">
            <Header />
            
            <RefreshIndicator show={showRefreshIndicator} />
            
            <div className="max-w-7xl mx-auto px-8 py-8">
                <QuickActions
                    onRefresh={refreshData}
                    onCreateTestJobs={createTestJobs}
                    onPurgeQueue={purgeQueue}
                    isLoading={isLoading}
                />
                
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
                
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <QueueChart
                        pending={totals.pending}
                        processing={totals.processing}
                        completed={totals.completed}
                        failed={totals.failed}
                    />
                    
                    <RecentJobs
                        jobs={recentJobs}
                        isLoading={isLoading}
                        error={error}
                    />
                </div>
                
                <PerformanceChart data={performanceData} />

                <div className="mt-8">
                    <QueuesSummary queues={queues} isLoading={isLoading} />
                </div>
            </div>
        </div>
    );
}

export default App;
