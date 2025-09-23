import { AlertCircle, Calendar, Save } from 'lucide-react';
import React, { useState } from 'react';
import { createScheduledJob } from '../../services/api';
import Modal from '../shared/Modal';

interface CreateScheduledJobModalProps {
    isOpen: boolean;
    onClose: () => void;
    onJobCreated?: () => void;
}

const CreateScheduledJobModal: React.FC<CreateScheduledJobModalProps> = ({
    isOpen,
    onClose,
    onJobCreated
}) => {
    const [formData, setFormData] = useState({
        schedule: '',
        job_class: 'TaskQueue\\Jobs\\TestJob',
        payload: '{}',
        queue: 'default',
        priority: 5,
        recurring: false,
        expires_at: ''
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await createScheduledJob(formData);
            onJobCreated?.();
            onClose();
            // Reset form
            setFormData({
                schedule: '',
                job_class: 'TaskQueue\\Jobs\\TestJob',
                payload: '{}',
                queue: 'default',
                priority: 5,
                recurring: false,
                expires_at: ''
            });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to create scheduled job');
        } finally {
            setLoading(false);
        }
    };

    const handleInputChange = (field: string, value: any) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const exampleSchedules = [
        { label: 'Every 5 minutes', value: '*/5 * * * *' },
        { label: 'Every hour', value: '0 * * * *' },
        { label: 'Every day at 2 AM', value: '0 2 * * *' },
        { label: 'Every Monday at 9 AM', value: '0 9 * * 1' },
        { label: 'Every month on the 1st', value: '0 0 1 * *' }
    ];

    return (
        <Modal open={isOpen} onClose={onClose} className="w-full max-w-2xl">
            <Modal.Title>
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 shadow">
                        <Calendar className="h-6 w-6" />
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Schedule New Job</h2>
                        <p className="text-sm text-gray-500">Create a new scheduled job</p>
                    </div>
                </div>
            </Modal.Title>

            <form onSubmit={handleSubmit}>
                <Modal.Content>
                    <div className="space-y-6">
                        {/* Schedule Expression */}
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Schedule Expression (Cron)
                            </label>
                            <input
                                type="text"
                                value={formData.schedule}
                                onChange={(e) => handleInputChange('schedule', e.target.value)}
                                placeholder="e.g., */5 * * * * (every 5 minutes)"
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                required
                            />
                            <div className="mt-2">
                                <p className="mb-2 text-xs text-gray-500">Quick examples:</p>
                                <div className="flex flex-wrap gap-2">
                                    {exampleSchedules.map((example, index) => (
                                        <button
                                            key={index}
                                            type="button"
                                            onClick={() =>
                                                handleInputChange('schedule', example.value)
                                            }
                                            className="rounded-lg bg-gray-100 px-3 py-1 text-xs text-gray-700 transition-colors hover:bg-gray-200">
                                            {example.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Job Class */}
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Job Class
                            </label>
                            <select
                                value={formData.job_class}
                                onChange={(e) => handleInputChange('job_class', e.target.value)}
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                                <option value="TaskQueue\\Jobs\\TestJob">TestJob</option>
                                <option value="TaskQueue\\Jobs\\ScheduledJob">ScheduledJob</option>
                            </select>
                        </div>

                        {/* Payload */}
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Payload (JSON)
                            </label>
                            <textarea
                                value={formData.payload}
                                onChange={(e) => handleInputChange('payload', e.target.value)}
                                placeholder='{"message": "Hello World", "data": {}}'
                                rows={4}
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Enter valid JSON data for the job payload
                            </p>
                        </div>

                        {/* Queue and Priority */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Queue
                                </label>
                                <input
                                    type="text"
                                    value={formData.queue}
                                    onChange={(e) => handleInputChange('queue', e.target.value)}
                                    placeholder="default"
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Priority
                                </label>
                                <select
                                    value={formData.priority}
                                    onChange={(e) =>
                                        handleInputChange('priority', parseInt(e.target.value))
                                    }
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                                    <option value={1}>Low (1)</option>
                                    <option value={5}>Normal (5)</option>
                                    <option value={10}>High (10)</option>
                                    <option value={15}>Urgent (15)</option>
                                </select>
                            </div>
                        </div>

                        {/* Recurring and Expiration */}
                        <div className="grid grid-cols-2 gap-4">
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="recurring"
                                    checked={formData.recurring}
                                    onChange={(e) =>
                                        handleInputChange('recurring', e.target.checked)
                                    }
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label htmlFor="recurring" className="ml-2 text-sm text-gray-700">
                                    Recurring Job
                                </label>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Expires At (Optional)
                                </label>
                                <input
                                    type="datetime-local"
                                    value={formData.expires_at}
                                    onChange={(e) =>
                                        handleInputChange('expires_at', e.target.value)
                                    }
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        {/* Error Display */}
                        {error && (
                            <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                                <div className="flex items-center gap-3">
                                    <AlertCircle className="h-5 w-5 text-red-500" />
                                    <div className="text-sm text-red-700">{error}</div>
                                </div>
                            </div>
                        )}
                    </div>
                </Modal.Content>

                <Modal.Footer>
                    <div className="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl bg-gray-100 px-6 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={loading}
                            className="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <Save className="h-4 w-4" />
                            {loading ? 'Creating...' : 'Create Job'}
                        </button>
                    </div>
                </Modal.Footer>
            </form>
        </Modal>
    );
};

export default CreateScheduledJobModal;

