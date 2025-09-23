import { AlertTriangle, CheckCircle, Clock, FileText, XCircle, Zap } from 'lucide-react';
import React from 'react';
import type { Job } from '../../services/api';
import Card from '../Card';

interface RecentJobsProps {
    jobs: Job[];
    isLoading?: boolean;
    error?: string | null;
}

const RecentJobs: React.FC<RecentJobsProps> = ({ jobs, isLoading = false, error = null }) => {
    const stateColors = {
        pending: 'bg-amber-100 text-amber-800 border-amber-200',
        processing: 'bg-blue-100 text-blue-800 border-blue-200',
        completed: 'bg-green-100 text-green-800 border-green-200',
        failed: 'bg-red-100 text-red-800 border-red-200'
    };

    const stateIcons = {
        pending: <Clock className="h-5 w-5" />,
        processing: <Zap className="h-5 w-5" />,
        completed: <CheckCircle className="h-5 w-5" />,
        failed: <XCircle className="h-5 w-5" />
    };

    if (error) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 text-red-600 shadow">
                        <FileText className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Recent Jobs</h3>
                        <p className="text-sm text-gray-500">Latest job activities</p>
                    </div>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-sm">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-6 w-6" />
                        <div>
                            <div className="font-semibold">Error loading data</div>
                            <div className="mt-1 text-sm">{error}. Please refresh the page.</div>
                        </div>
                    </div>
                </div>
            </Card>
        );
    }

    if (isLoading) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 shadow">
                        <FileText className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Recent Jobs</h3>
                        <p className="text-sm text-gray-500">Latest job activities</p>
                    </div>
                </div>
                <div className="max-h-[400px] overflow-y-auto">
                    <div className="space-y-3">
                        {[...Array(5)].map((_, i) => (
                            <div
                                key={i}
                                className="flex items-center gap-4 rounded-xl border border-gray-200 p-4">
                                <div className="skeleton h-12 w-12 rounded-full"></div>
                                <div className="flex-1 space-y-2">
                                    <div className="skeleton h-4 w-3/4 rounded"></div>
                                    <div className="skeleton h-3 w-1/2 rounded"></div>
                                </div>
                                <div className="skeleton h-6 w-16 rounded-full"></div>
                            </div>
                        ))}
                    </div>
                </div>
            </Card>
        );
    }

    if (!jobs || jobs.length === 0) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 shadow">
                        <FileText className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Recent Jobs</h3>
                        <p className="text-sm text-gray-500">Latest job activities</p>
                    </div>
                </div>
                <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <FileText className="h-8 w-8" />
                    </div>
                    <p className="text-lg font-medium">No recent jobs</p>
                    <p className="text-sm">Jobs will appear here as they are processed</p>
                </div>
            </Card>
        );
    }

    return (
        <Card>
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 shadow">
                    <FileText className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-xl font-bold text-gray-800">Recent Jobs</h3>
                    <p className="text-sm text-gray-500">Latest {jobs.length} job activities</p>
                </div>
            </div>
            <div className="max-h-[500px] overflow-y-auto">
                <div className="space-y-3 divide-y divide-gray-200">
                    {jobs.map((job) => {
                        const stateClass =
                            stateColors[job.state] || 'bg-gray-100 text-gray-800 border-gray-200';
                        const stateIcon = stateIcons[job.state] || <XCircle className="h-5 w-5" />;

                        return (
                            <div key={job.id} className="group flex items-center gap-4 p-4">
                                <div className="flex-shrink-0">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 shadow-sm transition-transform duration-200 group-hover:scale-110">
                                        {stateIcon}
                                    </div>
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="mb-1 flex items-center gap-2">
                                        <div className="truncate font-mono text-sm font-semibold text-gray-800">
                                            {job.id}
                                        </div>
                                        <div
                                            className={`rounded-full border px-2 py-1 text-xs font-bold uppercase ${stateClass}`}>
                                            {job.state}
                                        </div>
                                    </div>
                                    <div className="space-y-1 text-sm text-gray-500">
                                        <div className="flex items-center gap-4">
                                            <span className="flex items-center gap-1">
                                                <span className="text-xs">🏷️</span>
                                                Queue:{' '}
                                                <span className="font-medium text-gray-700">
                                                    {job.queue}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <span className="text-xs">⚡</span>
                                                Priority:{' '}
                                                <span className="font-medium text-gray-700">
                                                    {job.priority}
                                                </span>
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <span className="text-xs">🔄</span>
                                            Attempts:{' '}
                                            <span className="font-medium text-gray-700">
                                                {job.attempts}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </Card>
    );
};

export default RecentJobs;

