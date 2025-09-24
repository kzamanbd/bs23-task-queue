import {
    AlertTriangle,
    Calendar,
    CheckCircle,
    Clock,
    Hash,
    RefreshCw,
    Tag,
    XCircle,
    Zap
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { getJobDetails, retryFailedJob } from '../../services/api';
import Modal from '../shared/modal-ui';

interface JobDetailsModalProps {
    jobId: string;
    isOpen: boolean;
    onClose: () => void;
    onJobRetried?: () => void;
}

interface JobDetails {
    id: string;
    state: 'pending' | 'processing' | 'completed' | 'failed';
    queue: string;
    priority: number;
    attempts: number;
    max_attempts: number;
    timeout: number;
    delay: number;
    created_at: string;
    updated_at: string;
    completed_at?: string | null;
    failed_at?: string | null;
    exception?: {
        message: string;
        trace: string;
    } | null;
    payload: any;
    dependencies: string[];
    tags: string[];
}

const JobDetailsModal: React.FC<JobDetailsModalProps> = ({
    jobId,
    isOpen,
    onClose,
    onJobRetried
}) => {
    const [jobDetails, setJobDetails] = useState<JobDetails | null>(null);
    const [loading, setLoading] = useState(false);
    const [retrying, setRetrying] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && jobId) {
            fetchJobDetails();
        }
    }, [isOpen, jobId]);

    const fetchJobDetails = async () => {
        setLoading(true);
        setError(null);
        try {
            const details = await getJobDetails(jobId);
            setJobDetails(details);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch job details');
        } finally {
            setLoading(false);
        }
    };

    const handleRetry = async () => {
        if (!jobDetails) return;

        setRetrying(true);
        try {
            await retryFailedJob(jobId);
            await fetchJobDetails(); // Refresh details
            onJobRetried?.();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to retry job');
        } finally {
            setRetrying(false);
        }
    };

    const getStateIcon = (state: string) => {
        switch (state) {
            case 'pending':
                return <Clock className="h-5 w-5 text-amber-500" />;
            case 'processing':
                return <Zap className="h-5 w-5 text-blue-500" />;
            case 'completed':
                return <CheckCircle className="h-5 w-5 text-green-500" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-red-500" />;
            default:
                return <Clock className="h-5 w-5 text-gray-500" />;
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

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    return (
        <Modal open={isOpen} onClose={onClose} className="w-full max-w-4xl">
            <Modal.Title>
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 shadow">
                        <Hash className="h-6 w-6" />
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Job Details</h2>
                        <p className="text-sm text-gray-500">
                            Complete job information and management
                        </p>
                    </div>
                </div>
            </Modal.Title>

            <Modal.Content className="max-h-[70vh] overflow-y-auto">
                {loading ? (
                    <div className="space-y-4">
                        {[...Array(8)].map((_, i) => (
                            <div key={i} className="skeleton h-4 w-full rounded"></div>
                        ))}
                    </div>
                ) : error ? (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-6">
                        <div className="flex items-center gap-3">
                            <AlertTriangle className="h-6 w-6 text-red-500" />
                            <div>
                                <div className="font-semibold text-red-800">
                                    Error loading job details
                                </div>
                                <div className="mt-1 text-sm text-red-600">{error}</div>
                            </div>
                        </div>
                    </div>
                ) : jobDetails ? (
                    <div className="space-y-6">
                        {/* Basic Information */}
                        <div className="rounded-xl border border-gray-200 p-6">
                            <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                                <Hash className="h-5 w-5" />
                                Basic Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Job ID
                                    </label>
                                    <div className="mt-1 font-mono text-sm text-gray-800">
                                        {jobDetails.id}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        State
                                    </label>
                                    <div className="mt-1 flex items-center gap-2">
                                        {getStateIcon(jobDetails.state)}
                                        <span
                                            className={`rounded-full border px-3 py-1 text-xs font-bold uppercase ${getStateColor(jobDetails.state)}`}>
                                            {jobDetails.state}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Queue
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {jobDetails.queue}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Priority
                                    </label>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Zap className="h-4 w-4 text-amber-500" />
                                        <span className="text-sm text-gray-800">
                                            {jobDetails.priority}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Attempts
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {jobDetails.attempts} / {jobDetails.max_attempts}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Timeout
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {jobDetails.timeout}s
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Timing Information */}
                        <div className="rounded-xl border border-gray-200 p-6">
                            <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                                <Calendar className="h-5 w-5" />
                                Timing Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Created At
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {formatDateTime(jobDetails.created_at)}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-600">
                                        Updated At
                                    </label>
                                    <div className="mt-1 text-sm text-gray-800">
                                        {formatDateTime(jobDetails.updated_at)}
                                    </div>
                                </div>
                                {jobDetails.completed_at && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">
                                            Completed At
                                        </label>
                                        <div className="mt-1 text-sm text-gray-800">
                                            {formatDateTime(jobDetails.completed_at)}
                                        </div>
                                    </div>
                                )}
                                {jobDetails.failed_at && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">
                                            Failed At
                                        </label>
                                        <div className="mt-1 text-sm text-gray-800">
                                            {formatDateTime(jobDetails.failed_at)}
                                        </div>
                                    </div>
                                )}
                                {jobDetails.delay > 0 && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">
                                            Delay
                                        </label>
                                        <div className="mt-1 text-sm text-gray-800">
                                            {jobDetails.delay}s
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Tags */}
                        {jobDetails.tags.length > 0 && (
                            <div className="rounded-xl border border-gray-200 p-6">
                                <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                                    <Tag className="h-5 w-5" />
                                    Tags
                                </h3>
                                <div className="flex flex-wrap gap-2">
                                    {jobDetails.tags.map((tag, index) => (
                                        <span
                                            key={index}
                                            className="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Dependencies */}
                        {jobDetails.dependencies.length > 0 && (
                            <div className="rounded-xl border border-gray-200 p-6">
                                <h3 className="mb-4 text-lg font-semibold text-gray-800">
                                    Dependencies
                                </h3>
                                <div className="space-y-2">
                                    {jobDetails.dependencies.map((depId, index) => (
                                        <div
                                            key={index}
                                            className="font-mono text-sm text-gray-600">
                                            {depId}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Payload */}
                        <div className="rounded-xl border border-gray-200 p-6">
                            <h3 className="mb-4 text-lg font-semibold text-gray-800">Payload</h3>
                            <pre className="max-h-64 overflow-auto rounded-lg bg-gray-50 p-4 text-sm text-gray-800">
                                {JSON.stringify(jobDetails.payload, null, 2)}
                            </pre>
                        </div>

                        {/* Exception Details */}
                        {jobDetails.exception && (
                            <div className="rounded-xl border border-red-200 bg-red-50 p-6">
                                <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-red-800">
                                    <AlertTriangle className="h-5 w-5" />
                                    Exception Details
                                </h3>
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium text-red-600">
                                            Error Message
                                        </label>
                                        <div className="mt-1 rounded-lg bg-white p-3 text-sm text-red-800">
                                            {jobDetails.exception.message}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-red-600">
                                            Stack Trace
                                        </label>
                                        <pre className="mt-1 max-h-48 overflow-auto rounded-lg bg-white p-3 text-xs text-red-800">
                                            {jobDetails.exception.trace}
                                        </pre>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                ) : null}
            </Modal.Content>

            <Modal.Footer>
                <div className="flex items-center justify-between">
                    <div className="text-sm text-gray-500">
                        {jobDetails && `Last updated: ${formatDateTime(jobDetails.updated_at)}`}
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={fetchJobDetails}
                            className="flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                            <RefreshCw className="h-4 w-4" />
                            Refresh
                        </button>
                        {jobDetails?.state === 'failed' && (
                            <button
                                onClick={handleRetry}
                                disabled={retrying}
                                className="flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                                <RefreshCw
                                    className={`h-4 w-4 ${retrying ? 'animate-spin' : ''}`}
                                />
                                {retrying ? 'Retrying...' : 'Retry Job'}
                            </button>
                        )}
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

export default JobDetailsModal;

