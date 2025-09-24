import JobDetailsModal from '@/components/jobs/job-details-modal';
import Card from '@/components/shared/card-ui';
import { useJobs } from '@/hooks/useJobs';
import {
    AlertTriangle,
    CheckCircle,
    Clock,
    Eye,
    Filter,
    RefreshCw,
    Search,
    XCircle,
    Zap
} from 'lucide-react';
import React, { useMemo, useState } from 'react';

type JobState = 'all' | 'pending' | 'processing' | 'completed' | 'failed';
type SortField = 'id' | 'created_at' | 'updated_at' | 'priority' | 'state' | 'queue';
type SortDirection = 'asc' | 'desc';

const JobsTable: React.FC = () => {
    const { allJobs: jobs, isLoading, error, refreshData: onRefresh } = useJobs({ limit: 100 });

    const [selectedJobId, setSelectedJobId] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [stateFilter, setStateFilter] = useState<JobState>('all');
    const [queueFilter, setQueueFilter] = useState<string>('all');
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [sortField, setSortField] = useState<SortField>('updated_at');
    const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

    const stateColors = {
        pending: 'bg-amber-100 text-amber-800 border-amber-200',
        processing: 'bg-blue-100 text-blue-800 border-blue-200',
        completed: 'bg-green-100 text-green-800 border-green-200',
        failed: 'bg-red-100 text-red-800 border-red-200'
    };

    const stateIcons = {
        pending: <Clock className="h-4 w-4" />,
        processing: <Zap className="h-4 w-4" />,
        completed: <CheckCircle className="h-4 w-4" />,
        failed: <XCircle className="h-4 w-4" />
    };

    // Get unique queues from jobs
    const availableQueues = useMemo(() => {
        const queues = Array.from(new Set(jobs.map((job) => job.queue)));
        return queues.sort();
    }, [jobs]);

    // Filter and sort jobs
    const filteredAndSortedJobs = useMemo(() => {
        let filtered = jobs;

        // Filter by state
        if (stateFilter !== 'all') {
            filtered = filtered.filter((job) => job.state === stateFilter);
        }

        // Filter by queue
        if (queueFilter !== 'all') {
            filtered = filtered.filter((job) => job.queue === queueFilter);
        }

        // Filter by search term
        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            filtered = filtered.filter(
                (job) =>
                    job.id.toLowerCase().includes(term) ||
                    job.queue.toLowerCase().includes(term) ||
                    job.tags.some((tag) => tag.toLowerCase().includes(term))
            );
        }

        // Sort jobs
        filtered.sort((a, b) => {
            let aValue: any;
            let bValue: any;

            switch (sortField) {
                case 'created_at':
                    aValue = new Date(a.created_at);
                    bValue = new Date(b.created_at);
                    break;
                case 'updated_at':
                    aValue = new Date(a.updated_at);
                    bValue = new Date(b.updated_at);
                    break;
                case 'priority':
                    aValue = a.priority;
                    bValue = b.priority;
                    break;
                case 'state':
                    aValue = a.state;
                    bValue = b.state;
                    break;
                case 'queue':
                    aValue = a.queue;
                    bValue = b.queue;
                    break;
                default:
                    return 0;
            }

            if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        return filtered;
    }, [jobs, stateFilter, queueFilter, searchTerm, sortField, sortDirection]);

    const handleViewDetails = (jobId: string) => {
        setSelectedJobId(jobId);
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelectedJobId(null);
    };

    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const getTimeSince = (dateString: string) => {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        return `${Math.floor(diffInSeconds / 86400)}d ago`;
    };

    const getSortIcon = (field: SortField) => {
        if (sortField !== field) return null;
        return sortDirection === 'asc' ? '↑' : '↓';
    };

    if (error) {
        return (
            <Card>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 text-red-600 shadow">
                        <AlertTriangle className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Jobs</h3>
                        <p className="text-sm text-gray-500">All job states and activities</p>
                    </div>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-sm">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-6 w-6" />
                        <div>
                            <div className="font-semibold">Error loading jobs</div>
                            <div className="mt-1 text-sm">{error}</div>
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
                        <Filter className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Jobs</h3>
                        <p className="text-sm text-gray-500">All job states and activities</p>
                    </div>
                </div>
                <div className="space-y-4">
                    {/* Loading skeleton for filters */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="skeleton h-10 w-full rounded-lg"></div>
                        <div className="skeleton h-10 w-full rounded-lg"></div>
                        <div className="skeleton h-10 w-full rounded-lg"></div>
                    </div>
                    {/* Loading skeleton for table */}
                    <div className="space-y-3">
                        {[...Array(10)].map((_, i) => (
                            <div
                                key={i}
                                className="flex items-center gap-4 rounded-xl border border-gray-200 p-4">
                                <div className="skeleton h-10 w-10 rounded-full"></div>
                                <div className="flex-1 space-y-2">
                                    <div className="skeleton h-4 w-1/4 rounded"></div>
                                    <div className="skeleton h-3 w-1/2 rounded"></div>
                                </div>
                                <div className="skeleton h-6 w-20 rounded-full"></div>
                                <div className="skeleton h-8 w-16 rounded"></div>
                            </div>
                        ))}
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 shadow">
                        <Filter className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-800">Jobs</h3>
                        <p className="text-sm text-gray-500">
                            {filteredAndSortedJobs.length} of {jobs.length} jobs
                        </p>
                    </div>
                </div>
                {onRefresh && (
                    <button
                        onClick={onRefresh}
                        className="flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                {/* Search */}
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search jobs..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 bg-white py-2 pr-4 pl-10 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                    />
                </div>

                {/* State Filter */}
                <select
                    value={stateFilter}
                    onChange={(e) => setStateFilter(e.target.value as JobState)}
                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                    <option value="all">All States</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>

                {/* Queue Filter */}
                <select
                    value={queueFilter}
                    onChange={(e) => setQueueFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                    <option value="all">All Queues</option>
                    {availableQueues.map((queue) => (
                        <option key={queue} value={queue}>
                            {queue}
                        </option>
                    ))}
                </select>
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-xl border border-gray-200">
                <div className="max-h:[600px] max-h-[600px] overflow-y-auto">
                    {filteredAndSortedJobs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                <Filter className="h-8 w-8" />
                            </div>
                            <p className="text-lg font-medium">No jobs found</p>
                            <p className="text-sm">Try adjusting your filters</p>
                        </div>
                    ) : (
                        <table className="min-w-full table-auto">
                            <thead className="sticky top-0 z-10 bg-gray-50">
                                <tr className="border-b border-gray-200 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('id')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            Job ID {getSortIcon('id')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('state')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            State {getSortIcon('state')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('queue')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            Queue {getSortIcon('queue')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('priority')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            Priority {getSortIcon('priority')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('updated_at')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            Updated {getSortIcon('updated_at')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left">
                                        <button
                                            onClick={() => handleSort('created_at')}
                                            className="flex items-center gap-1 hover:text-gray-700">
                                            Created {getSortIcon('created_at')}
                                        </button>
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-center">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {filteredAndSortedJobs.map((job) => {
                                    const stateClass =
                                        stateColors[job.state] ||
                                        'bg-gray-100 text-gray-800 border-gray-200';
                                    const stateIcon = stateIcons[job.state] || (
                                        <XCircle className="h-4 w-4" />
                                    );

                                    return (
                                        <tr key={job.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 align-top">
                                                <div className="font-mono text-sm font-semibold text-gray-800">
                                                    {job.id}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {job.attempts}/{job.max_attempts} attempts
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 align-top">
                                                <div
                                                    className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-bold uppercase ${stateClass}`}>
                                                    {stateIcon}
                                                    {job.state}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 align-top">
                                                <div className="text-sm font-medium text-gray-700">
                                                    {job.queue}
                                                </div>
                                                {job.tags.length > 0 && (
                                                    <div className="text-xs text-gray-500">
                                                        {job.tags.slice(0, 2).join(', ')}
                                                        {job.tags.length > 2 &&
                                                            ` +${job.tags.length - 2}`}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 align-top">
                                                <div className="flex items-center gap-1 text-sm text-gray-700">
                                                    <Zap className="h-3 w-3 text-amber-500" />
                                                    {job.priority}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 align-top">
                                                <div className="text-sm text-gray-700">
                                                    {getTimeSince(job.updated_at)}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {new Date(job.updated_at).toLocaleString()}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 align-top">
                                                <div className="text-sm text-gray-700">
                                                    {getTimeSince(job.created_at)}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {new Date(job.created_at).toLocaleString()}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center align-middle">
                                                <button
                                                    onClick={() => handleViewDetails(job.id)}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 transition-colors hover:bg-indigo-200"
                                                    title="View job details">
                                                    <Eye className="h-3 w-3" />
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Job Details Modal */}
            {selectedJobId && (
                <JobDetailsModal
                    jobId={selectedJobId}
                    isOpen={isModalOpen}
                    onClose={handleCloseModal}
                />
            )}
        </Card>
    );
};

export default JobsTable;

