import { createBrowserRouter, Outlet, RouterProvider } from 'react-router-dom';
import Header from './components/layout/header';
import Navigation from './components/layout/navigation';
import JobsTable from './pages/jobs-table';
import Overview from './pages/overview-stats';
import QueueManagement from './pages/queue-management';
import ScheduledJobsList from './pages/scheduled-jobs-list';

// Layout component that wraps all pages
function Layout() {
    return (
        <div className="relative min-h-screen overflow-hidden">
            {/* Enhanced Background with Gradient and Pattern */}
            <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(120,119,198,0.1),transparent_50%)]"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(120,119,198,0.05),transparent_50%)]"></div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(120,119,198,0.05),transparent_50%)]"></div>

            {/* Content */}
            <div className="relative">
                <Header />

                <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <Navigation />
                    <Outlet />
                </div>
            </div>
        </div>
    );
}

// create react router routes
const router = createBrowserRouter([
    {
        path: '/',
        element: <Layout />,
        children: [
            {
                index: true,
                element: <Overview />
            },
            {
                path: 'jobs',
                element: <JobsTable />
            },
            {
                path: 'scheduled',
                element: <ScheduledJobsList />
            },
            {
                path: 'queues',
                element: <QueueManagement />
            }
        ]
    }
]);

function App() {
    return <RouterProvider router={router} />;
}

export default App;

if (import.meta.hot) {
    import.meta.hot.dispose(() => router.dispose());
}
