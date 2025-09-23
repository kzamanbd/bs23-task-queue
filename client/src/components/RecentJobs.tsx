import React from 'react';
import type { Job } from '../services/api';

interface RecentJobsProps {
    jobs: Job[];
    isLoading?: boolean;
    error?: string | null;
}

const RecentJobs: React.FC<RecentJobsProps> = ({ jobs, isLoading = false, error = null }) => {
    const stateColors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'processing': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800'
    };

    if (error) {
        return (
            <div className="bg-white rounded-xl p-6 shadow-lg">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Recent Jobs</h3>
                <div className="bg-red-50 text-red-700 p-4 rounded-lg">
                    Error loading data: {error}. Please refresh the page.
                </div>
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="bg-white rounded-xl p-6 shadow-lg">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Recent Jobs</h3>
                <div className="max-h-[400px] overflow-y-auto">
                    <div className="text-center py-8 text-gray-500">Loading recent jobs...</div>
                </div>
            </div>
        );
    }

    if (!jobs || jobs.length === 0) {
        return (
            <div className="bg-white rounded-xl p-6 shadow-lg">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Recent Jobs</h3>
                <div className="max-h-[400px] overflow-y-auto">
                    <div className="text-center py-8 text-gray-500">No recent jobs found</div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-xl p-6 shadow-lg">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Recent Jobs</h3>
            <div className="max-h-[400px] overflow-y-auto">
                {jobs.map((job) => {
                    const stateClass = stateColors[job.state] || 'bg-gray-100 text-gray-800';
                    
                    return (
                        <div key={job.id} className="flex justify-between items-center p-3 border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200 last:border-b-0">
                            <div className="flex-1">
                                <div className="font-mono text-sm text-gray-600">{job.id}</div>
                                <div className="text-sm text-gray-500 mt-1">
                                    Queue: {job.queue} | Priority: {job.priority} | Attempts: {job.attempts}
                                </div>
                            </div>
                            <div className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${stateClass}`}>
                                {job.state}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default RecentJobs;
