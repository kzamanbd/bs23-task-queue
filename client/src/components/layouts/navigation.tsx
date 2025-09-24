import { BarChart3, Calendar, Settings, Zap } from 'lucide-react';
import React from 'react';
import { Link, useLocation } from 'react-router-dom';

interface Tab {
    id: string;
    name: string;
    icon: React.ReactNode;
    description: string;
    path: string;
}

const Navigation: React.FC = () => {
    const location = useLocation();

    const tabs: Tab[] = [
        {
            id: 'overview',
            name: 'Overview',
            icon: <BarChart3 className="h-5 w-5" />,
            description: 'System overview and quick actions',
            path: '/'
        },
        {
            id: 'jobs',
            name: 'Jobs',
            icon: <Zap className="h-5 w-5" />,
            description: 'Recent and failed job management',
            path: '/jobs'
        },
        {
            id: 'scheduled',
            name: 'Scheduled',
            icon: <Calendar className="h-5 w-5" />,
            description: 'Scheduled job management',
            path: '/scheduled'
        },
        {
            id: 'queues',
            name: 'Queues',
            icon: <Settings className="h-5 w-5" />,
            description: 'Queue management and monitoring',
            path: '/queues'
        }
    ];

    return (
        <div className="mb-4 space-y-6">
            {/* Tab Navigation */}
            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                    {tabs.map((tab) => {
                        const isActive = location.pathname === tab.path;
                        return (
                            <Link
                                key={tab.id}
                                to={tab.path}
                                className={`group inline-flex cursor-pointer items-center gap-2 border-b-2 px-1 py-4 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}>
                                <div
                                    className={`transition-colors ${
                                        isActive
                                            ? 'text-indigo-600'
                                            : 'text-gray-400 group-hover:text-gray-500'
                                    }`}>
                                    {tab.icon}
                                </div>
                                <div className="text-left">
                                    <div className="font-semibold">{tab.name}</div>
                                    <div className="text-xs text-gray-400">{tab.description}</div>
                                </div>
                            </Link>
                        );
                    })}
                </nav>
            </div>
        </div>
    );
};

export default Navigation;

