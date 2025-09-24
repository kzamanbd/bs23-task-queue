import { Plus, RefreshCw, Trash2, Zap } from 'lucide-react';
import React from 'react';
import Card from '../shared/card-ui';

interface QuickActionsProps {
    onRefresh: () => void;
    onCreateTestJobs: () => void;
    onPurgeQueue: (queueName: string) => void;
    isLoading?: boolean;
}

const QuickActions: React.FC<QuickActionsProps> = ({
    onRefresh,
    onCreateTestJobs,
    onPurgeQueue,
    isLoading = false
}) => {
    const handlePurgeDefault = () => onPurgeQueue('default');
    const handlePurgeHighPriority = () => onPurgeQueue('high-priority');

    const actions = [
        {
            label: 'Refresh Data',
            icon: <RefreshCw className="h-5 w-5" />,
            onClick: onRefresh,
            variant: 'primary',
            description: 'Update dashboard data'
        },
        {
            label: 'Create Test Jobs',
            icon: <Plus className="h-5 w-5" />,
            onClick: onCreateTestJobs,
            variant: 'success',
            description: 'Generate 100 test jobs'
        },
        {
            label: 'Purge Default Queue',
            icon: <Trash2 className="h-5 w-5" />,
            onClick: handlePurgeDefault,
            variant: 'danger',
            description: 'Clear default queue'
        },
        {
            label: 'Purge High Priority',
            icon: <Trash2 className="h-5 w-5" />,
            onClick: handlePurgeHighPriority,
            variant: 'danger',
            description: 'Clear high priority queue'
        }
    ];

    const getVariantStyles = (variant: string) => {
        switch (variant) {
            case 'primary':
                return 'bg-indigo-600 hover:bg-indigo-700';
            case 'success':
                return 'bg-green-600 hover:bg-green-700';
            case 'danger':
                return 'bg-red-600 hover:bg-red-700';
            default:
                return 'bg-gray-600 hover:bg-gray-700';
        }
    };

    return (
        <Card className="mb-8">
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 shadow">
                    <Zap className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-xl font-bold text-gray-800">Quick Actions</h3>
                    <p className="text-sm text-gray-500">Manage your queue system</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {actions.map((action, index) => (
                    <button
                        key={action.label}
                        className={`group relative overflow-hidden ${getVariantStyles(action.variant)} rounded-xl px-6 py-4 text-sm font-semibold text-white shadow transition-all duration-300 hover:scale-105 hover:shadow-lg active:scale-95 disabled:cursor-not-allowed disabled:opacity-50`}
                        onClick={action.onClick}
                        disabled={isLoading}
                        style={{ animationDelay: `${index * 0.1}s` }}>
                        <div className="flex items-center gap-3">
                            <div className="transition-transform duration-300 group-hover:scale-110">
                                {action.icon}
                            </div>
                            <div className="text-left">
                                <div className="font-semibold">{action.label}</div>
                                <div className="text-xs opacity-80">{action.description}</div>
                            </div>
                        </div>

                        {/* Ripple effect overlay */}
                        <div className="absolute inset-0 bg-white/20 opacity-0 transition-opacity duration-150 group-active:opacity-100"></div>
                    </button>
                ))}
            </div>

            {isLoading && (
                <div className="mt-4 flex items-center justify-center gap-2 text-gray-500">
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-500"></div>
                    <span className="text-sm">Processing...</span>
                </div>
            )}
        </Card>
    );
};

export default QuickActions;

