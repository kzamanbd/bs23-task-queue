import React from 'react';

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

    return (
        <div className="bg-white rounded-xl p-6 shadow-lg mb-8">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
            <div className="flex flex-wrap gap-2">
                <button 
                    className="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={onRefresh}
                    disabled={isLoading}
                >
                    🔄 Refresh Data
                </button>
                <button 
                    className="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={onCreateTestJobs}
                    disabled={isLoading}
                >
                    ➕ Create Test Jobs
                </button>
                <button 
                    className="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={handlePurgeDefault}
                    disabled={isLoading}
                >
                    🗑️ Purge Default Queue
                </button>
                <button 
                    className="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={handlePurgeHighPriority}
                    disabled={isLoading}
                >
                    🗑️ Purge High Priority
                </button>
            </div>
        </div>
    );
};

export default QuickActions;
