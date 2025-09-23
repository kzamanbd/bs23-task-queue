import type { ScheduledJob } from '@/services/api';
import { deleteScheduledJob, getScheduledJobs, runScheduledJob } from '@/services/api';
import {
    AlertCircle,
    Calendar,
    Clock,
    Eye,
    Play,
    Plus,
    RefreshCw,
    Tag,
    Trash2,
    Zap
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import ConfirmModal from '../components/dashboard/confirm-modal';
import CreateScheduledJobModal from '../components/scheduled/create-modal';
import ScheduledJobDetailsModal from '../components/scheduled/details-modal';
import Card from '../components/shared/card-ui';

interface ScheduledJobsListProps {
    onRefresh?: () => void;
    onJobCreated?: () => void;
    onJobDeleted?: () => void;
    onJobExecuted?: () => void;
}

const ScheduledJobsList: React.FC<ScheduledJobsListProps> = ({
    onRefresh,
    onJobCreated,
    onJobDeleted,
    onJobExecuted
}) => {
    const [scheduledJobs, setScheduledJobs] = useState<ScheduledJob[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [runningJobs, setRunningJobs] = useState<Set<string>>(new Set());
    const [deletingJobs, setDeletingJobs] = useState<Set<string>>(new Set());
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [selectedJob, setSelectedJob] = useState<ScheduledJob | null>(null);
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState<{ isOpen: boolean; jobId: string }>({
        isOpen: false,
        jobId: ''
    });

    useEffect(() => {
        fetchScheduledJobs();
    }, []);

    const fetchScheduledJobs = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await getScheduledJobs();
            setScheduledJobs(response.scheduled_jobs);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch scheduled jobs');
        } finally {
            setLoading(false);
        }
    };

    const handleRunJob = async (jobId: string) => {
        setRunningJobs((prev) => new Set(prev).add(jobId));
        try {
            await runScheduledJob(jobId);
            onJobExecuted?.();
            onRefresh?.();
        } catch (error) {
            console.error('Failed to run scheduled job:', error);
        } finally {
            setRunningJobs((prev) => {
                const newSet = new Set(prev);
                newSet.delete(jobId);
                return newSet;
            });
        }
    };

    const handleViewDetails = (job: ScheduledJob) => {
        setSelectedJob(job);
        setShowDetailsModal(true);
    };

    const handleDeleteClick = (jobId: string) => {
        setConfirmDelete({ isOpen: true, jobId });
    };

    const handleConfirmDelete = async () => {
        const jobId = confirmDelete.jobId;
        setDeletingJobs((prev) => new Set(prev).add(jobId));
        try {
            await deleteScheduledJob(jobId);
            setScheduledJobs((prev) => prev.filter((job) => job.id !== jobId));
            onJobDeleted?.();
            setConfirmDelete({ isOpen: false, jobId: '' });
        } catch (error) {
            console.error('Failed to delete scheduled job:', error);
        } finally {
            setDeletingJobs((prev) => {
                const newSet = new Set(prev);
                newSet.delete(jobId);
                return newSet;
            });
        }
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const getTimeUntilNext = (nextRunAt: string | null) => {
        if (!nextRunAt) return 'Never';

        const now = new Date();
        const nextRun = new Date(nextRunAt);
        const diffInSeconds = Math.floor((nextRun.getTime() - now.getTime()) / 1000);

        if (diffInSeconds <= 0) return 'Due now';
        if (diffInSeconds < 60) return `${diffInSeconds}s`;
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`;
        return `${Math.floor(diffInSeconds / 86400)}d`;
    };

    const getStatusColor = (job: ScheduledJob) => {
        if (!job.is_active) return 'bg-gray-100 text-gray-800';
        if (job.expires_at && new Date(job.expires_at) < new Date())
            return 'bg-red-100 text-red-800';
        return 'bg-green-100 text-green-800';
    };

    const getStatusText = (job: ScheduledJob) => {
        if (!job.is_active) return 'Inactive';
        if (job.expires_at && new Date(job.expires_at) < new Date()) return 'Expired';
        return 'Active';
    };

    if (error) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600 shadow">
                        <Calendar className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Scheduled Jobs</h3>
                        <p className="text-sm text-gray-500">Jobs scheduled for future execution</p>
                    </div>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-sm">
                    <div className="flex items-center gap-3">
                        <AlertCircle className="h-6 w-6" />
                        <div>
                            <div className="font-semibold">Error loading scheduled jobs</div>
                            <div className="mt-1 text-sm">{error}</div>
                        </div>
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600 shadow">
                        <Calendar className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Scheduled Jobs</h3>
                        <p className="text-sm text-gray-500">
                            {loading ? 'Loading...' : `${scheduledJobs.length} scheduled jobs`}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={fetchScheduledJobs}
                        className="flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                    <button
                        onClick={() => setShowCreateForm(true)}
                        className="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                        <Plus className="h-4 w-4" />
                        Schedule Job
                    </button>
                </div>
            </div>

            {loading ? (
                <div className="space-y-4">
                    {[...Array(3)].map((_, i) => (
                        <div key={i} className="rounded-xl border border-gray-200 p-4">
                            <div className="flex items-center gap-4">
                                <div className="skeleton h-12 w-12 rounded-full"></div>
                                <div className="flex-1 space-y-2">
                                    <div className="skeleton h-4 w-3/4 rounded"></div>
                                    <div className="skeleton h-3 w-1/2 rounded"></div>
                                </div>
                                <div className="flex gap-2">
                                    <div className="skeleton h-8 w-16 rounded"></div>
                                    <div className="skeleton h-8 w-16 rounded"></div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : scheduledJobs.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
                        <Calendar className="h-8 w-8 text-blue-500" />
                    </div>
                    <p className="text-lg font-medium">No scheduled jobs</p>
                    <p className="text-sm">Create your first scheduled job to get started</p>
                </div>
            ) : (
                <div className="max-h-[600px] overflow-y-auto">
                    <div className="space-y-3 divide-y divide-gray-200">
                        {scheduledJobs.map((job) => {
                            const isRunning = runningJobs.has(job.id);
                            const isDeleting = deletingJobs.has(job.id);

                            return (
                                <div
                                    key={job.id}
                                    className="group p-4 transition-colors hover:bg-gray-50">
                                    <div className="flex items-start gap-4">
                                        <div className="flex-shrink-0">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 shadow-sm">
                                                <Calendar className="h-6 w-6 text-blue-500" />
                                            </div>
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="mb-2 flex items-center gap-2">
                                                <div className="truncate font-mono text-sm font-semibold text-gray-800">
                                                    {job.id}
                                                </div>
                                                <div
                                                    className={`rounded-full border px-2 py-1 text-xs font-bold ${getStatusColor(job)}`}>
                                                    {getStatusText(job)}
                                                </div>
                                                {job.recurring && (
                                                    <div className="rounded-full border border-purple-200 bg-purple-100 px-2 py-1 text-xs font-bold text-purple-800">
                                                        Recurring
                                                    </div>
                                                )}
                                            </div>

                                            <div className="space-y-2 text-sm text-gray-600">
                                                <div className="flex items-center gap-4">
                                                    <span className="flex items-center gap-1">
                                                        <span className="text-xs">⏰</span>
                                                        Cron:{' '}
                                                        <span className="font-mono font-medium text-gray-700">
                                                            {job.cron_expression}
                                                        </span>
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3 text-blue-500" />
                                                        Next:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {getTimeUntilNext(job.next_run_at)}
                                                        </span>
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-4">
                                                    <span className="flex items-center gap-1">
                                                        <span className="text-xs">🏷️</span>
                                                        Queue:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {job.queue}
                                                        </span>
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Zap className="h-3 w-3 text-amber-500" />
                                                        Priority:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {job.priority}
                                                        </span>
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <span className="text-xs">📅</span>
                                                        Created:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {formatDateTime(job.created_at)}
                                                        </span>
                                                    </span>
                                                </div>

                                                {job.expires_at && (
                                                    <div className="flex items-center gap-1">
                                                        <span className="text-xs">⏳</span>
                                                        Expires:{' '}
                                                        <span className="font-medium text-gray-700">
                                                            {formatDateTime(job.expires_at)}
                                                        </span>
                                                    </div>
                                                )}

                                                {job.tags.length > 0 && (
                                                    <div className="flex items-center gap-2">
                                                        <Tag className="h-3 w-3 text-blue-400" />
                                                        <div className="flex flex-wrap gap-1">
                                                            {job.tags.map((tag, index) => (
                                                                <span
                                                                    key={index}
                                                                    className="rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">
                                                                    {tag}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="mt-2 rounded-lg bg-gray-50 p-3">
                                                    <div className="mb-1 text-xs font-medium text-gray-600">
                                                        Payload:
                                                    </div>
                                                    <pre className="overflow-hidden font-mono text-xs text-gray-700">
                                                        {JSON.stringify(
                                                            job.payload,
                                                            null,
                                                            2
                                                        ).substring(0, 200)}
                                                        {JSON.stringify(job.payload, null, 2)
                                                            .length > 200
                                                            ? '...'
                                                            : ''}
                                                    </pre>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => handleViewDetails(job)}
                                                className="flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-200"
                                                title="View job details">
                                                <Eye className="h-4 w-4" />
                                                Details
                                            </button>
                                            <button
                                                onClick={() => handleRunJob(job.id)}
                                                disabled={isRunning || isDeleting || !job.is_active}
                                                className="flex items-center gap-1 rounded-lg bg-green-100 px-3 py-2 text-sm font-medium text-green-700 transition-colors hover:bg-green-200 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="Run job manually">
                                                <Play
                                                    className={`h-4 w-4 ${isRunning ? 'animate-pulse' : ''}`}
                                                />
                                                {isRunning ? 'Running...' : 'Run Now'}
                                            </button>
                                            <button
                                                onClick={() => handleDeleteClick(job.id)}
                                                disabled={isRunning || isDeleting}
                                                className="flex items-center gap-1 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-200 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="Delete scheduled job">
                                                <Trash2
                                                    className={`h-4 w-4 ${isDeleting ? 'animate-pulse' : ''}`}
                                                />
                                                {isDeleting ? 'Deleting...' : 'Delete'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Create Job Modal */}
            <CreateScheduledJobModal
                isOpen={showCreateForm}
                onClose={() => setShowCreateForm(false)}
                onJobCreated={() => {
                    fetchScheduledJobs();
                    onJobCreated?.();
                }}
            />

            {/* Scheduled Job Details Modal */}
            <ScheduledJobDetailsModal
                isOpen={showDetailsModal}
                onClose={() => {
                    setShowDetailsModal(false);
                    setSelectedJob(null);
                }}
                job={selectedJob}
                onJobUpdated={() => {
                    fetchScheduledJobs();
                }}
                onJobDeleted={() => {
                    fetchScheduledJobs();
                    onJobDeleted?.();
                }}
                onJobExecuted={() => {
                    onJobExecuted?.();
                }}
            />

            {/* Confirm Delete Modal */}
            <ConfirmModal
                isOpen={confirmDelete.isOpen}
                onClose={() => setConfirmDelete({ isOpen: false, jobId: '' })}
                onConfirm={handleConfirmDelete}
                title="Delete Scheduled Job"
                message="Are you sure you want to permanently delete this scheduled job? This action cannot be undone."
                confirmText="Delete Job"
                variant="danger"
                isLoading={deletingJobs.has(confirmDelete.jobId)}
            />
        </Card>
    );
};

export default ScheduledJobsList;

