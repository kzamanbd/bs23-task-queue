import { BarChart3, Tag } from 'lucide-react';
import React from 'react';
import Card from '../shared/card-ui';

interface QueueSummaryItem {
    name: string;
    total_jobs: number;
    by_state: {
        pending: number;
        processing: number;
        completed: number;
        failed: number;
    };
    avg_priority: number;
    oldest_job: string | null;
    newest_job: string | null;
}

interface QueuesSummaryProps {
    queues: QueueSummaryItem[];
    isLoading?: boolean;
}

const QueuesSummary: React.FC<QueuesSummaryProps> = ({ queues, isLoading = false }) => {
    return (
        <Card>
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600 shadow">
                    <BarChart3 className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-xl font-bold text-gray-800">Queues Summary</h3>
                    <p className="text-sm text-gray-500">Detailed breakdown by queue</p>
                </div>
            </div>

            {isLoading ? (
                <div className="space-y-4">
                    {[...Array(3)].map((_, i) => (
                        <div key={i} className="rounded-xl border border-gray-200 p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <div className="skeleton h-4 w-24 rounded"></div>
                                <div className="skeleton h-4 w-16 rounded"></div>
                            </div>
                            <div className="grid grid-cols-4 gap-4">
                                {[...Array(4)].map((_, j) => (
                                    <div key={j} className="text-center">
                                        <div className="skeleton mx-auto mb-1 h-6 w-8 rounded"></div>
                                        <div className="skeleton mx-auto h-3 w-12 rounded"></div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            ) : queues.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <BarChart3 className="h-8 w-8" />
                    </div>
                    <p className="text-lg font-medium">No queues found</p>
                    <p className="text-sm">Queues will appear here when jobs are created</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {queues.map((queue, index) => (
                        <div
                            key={queue.name}
                            className="group rounded-xl border border-gray-200 p-6 transition-all duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow"
                            style={{ animationDelay: `${index * 0.1}s` }}>
                            <div className="mb-4 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 shadow-sm transition-transform duration-200 group-hover:scale-110">
                                        <Tag className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 className="text-lg font-semibold text-gray-800">
                                            {queue.name}
                                        </h4>
                                        <p className="text-sm text-gray-500">Queue Details</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-2xl font-bold text-gray-800">
                                        {queue.total_jobs}
                                    </div>
                                    <div className="text-xs text-gray-500">Total Jobs</div>
                                </div>
                            </div>

                            <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-center shadow-sm">
                                    <div className="text-xl font-bold text-amber-600">
                                        {queue.by_state.pending || 0}
                                    </div>
                                    <div className="text-xs font-medium text-amber-700">
                                        Pending
                                    </div>
                                </div>
                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center shadow-sm">
                                    <div className="text-xl font-bold text-blue-600">
                                        {queue.by_state.processing || 0}
                                    </div>
                                    <div className="text-xs font-medium text-blue-700">
                                        Processing
                                    </div>
                                </div>
                                <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-center shadow-sm">
                                    <div className="text-xl font-bold text-green-600">
                                        {queue.by_state.completed || 0}
                                    </div>
                                    <div className="text-xs font-medium text-green-700">
                                        Completed
                                    </div>
                                </div>
                                <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-center shadow-sm">
                                    <div className="text-xl font-bold text-red-600">
                                        {queue.by_state.failed || 0}
                                    </div>
                                    <div className="text-xs font-medium text-red-700">Failed</div>
                                </div>
                            </div>

                            <div className="border-t border-gray-100 pt-4">
                                <div className="flex items-center justify-between text-sm">
                                    <div className="flex items-center gap-4">
                                        <span className="flex items-center gap-1 text-gray-600">
                                            <span>⚡</span>
                                            Avg Priority:{' '}
                                            <span className="font-semibold text-gray-800">
                                                {queue.avg_priority?.toFixed?.(2) ??
                                                    queue.avg_priority}
                                            </span>
                                        </span>
                                        {queue.oldest_job && (
                                            <span className="flex items-center gap-1 text-gray-600">
                                                <span>🕐</span>
                                                Oldest:{' '}
                                                <span className="font-semibold text-gray-800">
                                                    {new Date(
                                                        queue.oldest_job
                                                    ).toLocaleDateString()}
                                                </span>
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-2 animate-pulse rounded-full bg-green-400"></div>
                                        <span className="text-xs text-gray-500">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
};

export default QueuesSummary;

