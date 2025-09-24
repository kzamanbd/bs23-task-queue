import { Calendar, Clock, Play, Settings, Tag, Trash2, Zap } from 'lucide-react';
import React, { useState } from 'react';
import type { ScheduledJob } from '../../services/api';
import { deleteScheduledJob, runScheduledJob } from '../../services/api';
import Modal from '../shared/modal-ui';

interface ScheduledJobDetailsModalProps {
    isOpen: boolean;
    onClose: () => void;
    job: ScheduledJob | null;
    onJobUpdated?: () => void;
    onJobDeleted?: () => void;
    onJobExecuted?: () => void;
}

const ScheduledJobDetailsModal: React.FC<ScheduledJobDetailsModalProps> = ({
    isOpen,
    onClose,
    job,
    onJobDeleted,
    onJobExecuted
}) => {
    const [isRunning, setIsRunning] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    if (!job) return null;

    const handleRunJob = async () => {
        setIsRunning(true);
        try {
            await runScheduledJob(job.id);
            onJobExecuted?.();
        } catch (error) {
            console.error('Failed to run scheduled job:', error);
        } finally {
            setIsRunning(false);
        }
    };

    const handleDeleteJob = async () => {
        setIsDeleting(true);
        try {
            await deleteScheduledJob(job.id);
            onJobDeleted?.();
            onClose();
        } catch (error) {
            console.error('Failed to delete scheduled job:', error);
        } finally {
            setIsDeleting(false);
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

    return (
        <Modal open={isOpen} onClose={onClose} className="w-full max-w-4xl">
            <Modal.Title>
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 shadow">
                        <Calendar className="h-6 w-6" />
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Scheduled Job Details</h2>
                        <p className="text-sm text-gray-500">Job ID: {job.id}</p>
                    </div>
                </div>
            </Modal.Title>

            <Modal.Content className="max-h-[70vh] overflow-y-auto">
                <div className="space-y-6">
                    {/* Basic Information */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                            <Settings className="h-5 w-5" />
                            Basic Information
                        </h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-gray-600">Job ID</label>
                                <div className="mt-1 font-mono text-sm text-gray-800">{job.id}</div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">Status</label>
                                <div className="mt-1 flex items-center gap-2">
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
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">Queue</label>
                                <div className="mt-1 text-sm text-gray-800">{job.queue}</div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">
                                    Priority
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Zap className="h-4 w-4 text-amber-500" />
                                    <span className="text-sm text-gray-800">{job.priority}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Schedule Information */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                            <Clock className="h-5 w-5" />
                            Schedule Information
                        </h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-gray-600">
                                    Cron Expression
                                </label>
                                <div className="mt-1 rounded-lg bg-gray-100 p-3 font-mono text-sm text-gray-800">
                                    {job.cron_expression}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">
                                    Next Run
                                </label>
                                <div className="mt-1 text-sm text-gray-800">
                                    {job.next_run_at ? formatDateTime(job.next_run_at) : 'Never'}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">
                                    Time Until Next
                                </label>
                                <div className="mt-1 text-sm text-gray-800">
                                    {getTimeUntilNext(job.next_run_at)}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">Created</label>
                                <div className="mt-1 text-sm text-gray-800">
                                    {formatDateTime(job.created_at)}
                                </div>
                            </div>
                            {job.expires_at && (
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Expires
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {formatDateTime(job.expires_at)}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Tags */}
                    {job.tags.length > 0 && (
                        <div className="rounded-xl border border-gray-200 p-6">
                            <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                                <Tag className="h-5 w-5" />
                                Tags
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {job.tags.map((tag, index) => (
                                    <span
                                        key={index}
                                        className="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Payload */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-800">Payload</h3>
                        <pre className="max-h-64 overflow-auto rounded-lg bg-gray-50 p-4 text-sm text-gray-800">
                            {JSON.stringify(job.payload, null, 2)}
                        </pre>
                    </div>
                </div>
            </Modal.Content>

            <Modal.Footer>
                <div className="flex items-center justify-between">
                    <div className="text-sm text-gray-500">
                        Last updated: {formatDateTime(job.created_at)}
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleRunJob}
                            disabled={isRunning || isDeleting || !job.is_active}
                            className="flex items-center gap-2 rounded-xl bg-green-100 px-4 py-2 text-sm font-medium text-green-700 transition-colors hover:bg-green-200 disabled:cursor-not-allowed disabled:opacity-50">
                            <Play className={`h-4 w-4 ${isRunning ? 'animate-pulse' : ''}`} />
                            {isRunning ? 'Running...' : 'Run Now'}
                        </button>
                        <button
                            onClick={handleDeleteJob}
                            disabled={isRunning || isDeleting}
                            className="flex items-center gap-2 rounded-xl bg-red-100 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-200 disabled:cursor-not-allowed disabled:opacity-50">
                            <Trash2 className={`h-4 w-4 ${isDeleting ? 'animate-pulse' : ''}`} />
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </button>
                        <button
                            onClick={onClose}
                            className="rounded-xl bg-gray-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-700">
                            Close
                        </button>
                    </div>
                </div>
            </Modal.Footer>
        </Modal>
    );
};

export default ScheduledJobDetailsModal;

