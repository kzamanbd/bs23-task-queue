import {
    AlertTriangle,
    BarChart3,
    CheckCircle,
    Clock,
    Eye,
    Filter,
    RefreshCw,
    Search,
    Settings,
    Trash2,
    Zap
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import ConfirmModal from '../components/dashboard/confirm-modal';
import QueueSettingsModal from '../components/queues/queue-settings-modal';
import Card from '../components/shared/card-ui';
import type { Job } from '../services/api';
import { getOverview, purgeQueue } from '../services/api';

interface QueueManagementProps {
    onRefresh?: () => void;
    onQueuePurged?: (queueName: string) => void;
}

const QueueManagement: React.FC<QueueManagementProps> = ({ onQueuePurged }) => {
    const [queues, setQueues] = useState<
        Array<{
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
        }>
    >([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedQueue, setSelectedQueue] = useState<string | null>(null);
    const [queueJobs, setQueueJobs] = useState<Job[]>([]);
    const [jobsLoading, setJobsLoading] = useState(false);
    const [purgingQueues, setPurgingQueues] = useState<Set<string>>(new Set());
    const [searchTerm, setSearchTerm] = useState('');
    const [stateFilter, setStateFilter] = useState<string>('all');
    const [confirmPurge, setConfirmPurge] = useState<{ isOpen: boolean; queueName: string }>({
        isOpen: false,
        queueName: ''
    });
    const [settingsModal, setSettingsModal] = useState<{ isOpen: boolean; queueName: string }>({
        isOpen: false,
        queueName: ''
    });

    useEffect(() => {
        fetchQueues();
    }, []);

    useEffect(() => {
        if (selectedQueue) {
            fetchQueueJobs(selectedQueue);
        }
    }, [selectedQueue]);

    const fetchQueues = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await getOverview(100);
            setQueues(response.queues);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch queues');
        } finally {
            setLoading(false);
        }
    };

    const fetchQueueJobs = async (queueName: string) => {
        setJobsLoading(true);
        try {
            // For now, we'll use the overview endpoint to get recent jobs
            // In a real implementation, you'd have a specific endpoint for queue jobs
            const response = await getOverview(100);
            const filteredJobs = response.recent.filter((job) => job.queue === queueName);
            setQueueJobs(filteredJobs);
        } catch (err) {
            console.error('Failed to fetch queue jobs:', err);
        } finally {
            setJobsLoading(false);
        }
    };

    const handlePurgeClick = (queueName: string) => {
        setConfirmPurge({ isOpen: true, queueName });
    };

    const handleConfirmPurge = async () => {
        const queueName = confirmPurge.queueName;
        setPurgingQueues((prev) => new Set(prev).add(queueName));
        try {
            await purgeQueue(queueName);
            setQueues((prev) =>
                prev.map((queue) =>
                    queue.name === queueName
                        ? {
                              ...queue,
                              total_jobs: 0,
                              by_state: { pending: 0, processing: 0, completed: 0, failed: 0 }
                          }
                        : queue
                )
            );
            onQueuePurged?.(queueName);
            setConfirmPurge({ isOpen: false, queueName: '' });
        } catch (error) {
            console.error('Failed to purge queue:', error);
        } finally {
            setPurgingQueues((prev) => {
                const newSet = new Set(prev);
                newSet.delete(queueName);
                return newSet;
            });
        }
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const getStateIcon = (state: string) => {
        switch (state) {
            case 'pending':
                return <Clock className="h-4 w-4 text-amber-500" />;
            case 'processing':
                return <Zap className="h-4 w-4 text-blue-500" />;
            case 'completed':
                return <CheckCircle className="h-4 w-4 text-green-500" />;
            case 'failed':
                return <AlertTriangle className="h-4 w-4 text-red-500" />;
            default:
                return <Clock className="h-4 w-4 text-gray-500" />;
        }
    };

    const getStateColor = (state: string) => {
        switch (state) {
            case 'pending':
                return 'bg-amber-100 text-amber-800 border-amber-200';
            case 'processing':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'completed':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'failed':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const filteredJobs = queueJobs.filter((job) => {
        const matchesSearch =
            searchTerm === '' ||
            job.id.toLowerCase().includes(searchTerm.toLowerCase()) ||
            JSON.stringify(job.payload).toLowerCase().includes(searchTerm.toLowerCase());
        const matchesState = stateFilter === 'all' || job.state === stateFilter;
        return matchesSearch && matchesState;
    });

    if (error) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600 shadow">
                        <BarChart3 className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Queue Management</h3>
                        <p className="text-sm text-gray-500">
                            Detailed queue operations and monitoring
                        </p>
                    </div>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-sm">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-6 w-6" />
                        <div>
                            <div className="font-semibold">Error loading queue data</div>
                            <div className="mt-1 text-sm">{error}</div>
                        </div>
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Queue Overview */}
            <Card>
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600 shadow">
                            <BarChart3 className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="text-xl font-bold text-gray-800">Queue Management</h3>
                            <p className="text-sm text-gray-500">
                                Detailed queue operations and monitoring
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={fetchQueues}
                        className="flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                </div>

                {loading ? (
                    <div className="space-y-4">
                        {[...Array(3)].map((_, i) => (
                            <div key={i} className="rounded-xl border border-gray-200 p-4">
                                <div className="skeleton mb-3 h-6 w-32 rounded"></div>
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
                ) : (
                    <div className="space-y-4">
                        {queues.map((queue) => (
                            <div
                                key={queue.name}
                                className="rounded-xl border border-gray-200 p-4 transition-colors hover:border-gray-300">
                                <div className="mb-4 flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <h4 className="text-lg font-semibold text-gray-800">
                                            {queue.name}
                                        </h4>
                                        <div className="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                            {queue.total_jobs} jobs
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => setSelectedQueue(queue.name)}
                                            className="flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-200">
                                            <Eye className="h-4 w-4" />
                                            View Jobs
                                        </button>
                                        <button
                                            onClick={() =>
                                                setSettingsModal({
                                                    isOpen: true,
                                                    queueName: queue.name
                                                })
                                            }
                                            className="flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                                            <Settings className="h-4 w-4" />
                                            Settings
                                        </button>
                                        <button
                                            onClick={() => handlePurgeClick(queue.name)}
                                            disabled={
                                                purgingQueues.has(queue.name) ||
                                                queue.total_jobs === 0
                                            }
                                            className="flex items-center gap-1 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-200 disabled:cursor-not-allowed disabled:opacity-50">
                                            <Trash2 className="h-4 w-4" />
                                            {purgingQueues.has(queue.name) ? 'Purging...' : 'Purge'}
                                        </button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-4 gap-4">
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-amber-600">
                                            {queue.by_state.pending}
                                        </div>
                                        <div className="text-xs text-gray-500">Pending</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-blue-600">
                                            {queue.by_state.processing}
                                        </div>
                                        <div className="text-xs text-gray-500">Processing</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-green-600">
                                            {queue.by_state.completed}
                                        </div>
                                        <div className="text-xs text-gray-500">Completed</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-red-600">
                                            {queue.by_state.failed}
                                        </div>
                                        <div className="text-xs text-gray-500">Failed</div>
                                    </div>
                                </div>

                                <div className="mt-3 flex items-center justify-between text-sm text-gray-500">
                                    <span>Avg Priority: {queue.avg_priority.toFixed(1)}</span>
                                    <span>
                                        Oldest:{' '}
                                        {queue.oldest_job
                                            ? formatDateTime(queue.oldest_job)
                                            : 'N/A'}
                                    </span>
                                    <span>
                                        Newest:{' '}
                                        {queue.newest_job
                                            ? formatDateTime(queue.newest_job)
                                            : 'N/A'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            {/* Queue Jobs Detail */}
            {selectedQueue && (
                <Card>
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h3 className="text-xl font-bold text-gray-800">
                                Jobs in "{selectedQueue}"
                            </h3>
                            <p className="text-sm text-gray-500">
                                {filteredJobs.length} jobs found
                            </p>
                        </div>
                        <button
                            onClick={() => setSelectedQueue(null)}
                            className="rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                            Close
                        </button>
                    </div>

                    {/* Filters */}
                    <div className="mb-6 flex items-center gap-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search jobs by ID or payload..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pr-4 pl-10 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                        <select
                            value={stateFilter}
                            onChange={(e) => setStateFilter(e.target.value)}
                            className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                            <option value="all">All States</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>

                    {jobsLoading ? (
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
                    ) : filteredJobs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                            <Filter className="mb-4 h-12 w-12" />
                            <p className="text-lg font-medium">No jobs found</p>
                            <p className="text-sm">Try adjusting your search or filter criteria</p>
                        </div>
                    ) : (
                        <div className="max-h-[600px] overflow-y-auto">
                            <div className="space-y-3 divide-y divide-gray-200">
                                {filteredJobs.map((job) => {
                                    const stateClass = getStateColor(job.state);
                                    const stateIcon = getStateIcon(job.state);

                                    return (
                                        <div
                                            key={job.id}
                                            className="group flex items-center gap-4 p-4 transition-colors hover:bg-gray-50">
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
                                                            <Zap className="h-3 w-3 text-amber-500" />
                                                            Priority:{' '}
                                                            <span className="font-medium text-gray-700">
                                                                {job.priority}
                                                            </span>
                                                        </span>
                                                        <span className="flex items-center gap-1">
                                                            <span className="text-xs">🔄</span>
                                                            Attempts:{' '}
                                                            <span className="font-medium text-gray-700">
                                                                {job.attempts}/{job.max_attempts}
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <span className="text-xs">📅</span>
                                                        Created:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {formatDateTime(job.created_at)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </Card>
            )}

            {/* Confirm Purge Modal */}
            <ConfirmModal
                isOpen={confirmPurge.isOpen}
                onClose={() => setConfirmPurge({ isOpen: false, queueName: '' })}
                onConfirm={handleConfirmPurge}
                title="Purge Queue"
                message={`Are you sure you want to purge all jobs from the "${confirmPurge.queueName}" queue? This action cannot be undone and will permanently delete all jobs in this queue.`}
                confirmText="Purge Queue"
                variant="danger"
                isLoading={purgingQueues.has(confirmPurge.queueName)}
            />

            {/* Queue Settings Modal */}
            <QueueSettingsModal
                isOpen={settingsModal.isOpen}
                onClose={() => setSettingsModal({ isOpen: false, queueName: '' })}
                queueName={settingsModal.queueName}
                onSave={(settings) => {
                    console.log('Save queue settings:', settings);
                    // Implement save functionality
                }}
            />
        </div>
    );
};

export default QueueManagement;

