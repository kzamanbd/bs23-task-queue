import React from 'react';
import Card from './Card';

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
        <Card title="Queues Summary">
            {isLoading ? (
                <div className="text-gray-500">Loading queues...</div>
            ) : queues.length === 0 ? (
                <div className="text-gray-500">No queues found</div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-gray-600 border-b">
                                <th className="py-2 pr-4">Queue</th>
                                <th className="py-2 pr-4">Total</th>
                                <th className="py-2 pr-4">Pending</th>
                                <th className="py-2 pr-4">Processing</th>
                                <th className="py-2 pr-4">Completed</th>
                                <th className="py-2 pr-4">Failed</th>
                                <th className="py-2 pr-4">Avg Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            {queues.map(q => (
                                <tr key={q.name} className="border-b last:border-none">
                                    <td className="py-2 pr-4 font-medium text-gray-800">{q.name}</td>
                                    <td className="py-2 pr-4">{q.total_jobs}</td>
                                    <td className="py-2 pr-4 text-amber-600">{q.by_state.pending || 0}</td>
                                    <td className="py-2 pr-4 text-blue-600">{q.by_state.processing || 0}</td>
                                    <td className="py-2 pr-4 text-green-600">{q.by_state.completed || 0}</td>
                                    <td className="py-2 pr-4 text-red-600">{q.by_state.failed || 0}</td>
                                    <td className="py-2 pr-4">{q.avg_priority?.toFixed?.(2) ?? q.avg_priority}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
};

export default QueuesSummary;
