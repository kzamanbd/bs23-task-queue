import { lazy, Suspense } from 'react';
import { createBrowserRouter, Outlet, RouterProvider } from 'react-router-dom';
import Header from './components/layouts/header';
import Navigation from './components/layouts/navigation';

// Lazy load page components for code splitting
const Overview = lazy(() => import('./pages/overview-stats'));
const JobsTable = lazy(() => import('./pages/jobs-table'));
const QueueManagement = lazy(() => import('./pages/queue-management'));
const ScheduledJobsList = lazy(() => import('./pages/scheduled-jobs-list'));

// Loading component for Suspense fallback
function LoadingSpinner() {
    return (
        <div className="flex min-h-[400px] items-center justify-center">
            <div className="flex flex-col items-center space-y-4">
                <div className="h-12 w-12 animate-spin rounded-full border-b-2 border-indigo-600"></div>
                <p className="text-sm text-gray-600">Loading...</p>
            </div>
        </div>
    );
}

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
                    <Suspense fallback={<LoadingSpinner />}>
                        <Outlet />
                    </Suspense>
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
