import { BarChart3, Settings, Zap } from 'lucide-react';
import React, { useState } from 'react';
import Modal from '../shared/modal-ui';

interface QueueSettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    queueName: string;
    currentSettings?: {
        priority?: number;
        maxAttempts?: number;
        timeout?: number;
        delay?: number;
    };
    onSave?: (settings: any) => void;
}

const QueueSettingsModal: React.FC<QueueSettingsModalProps> = ({
    isOpen,
    onClose,
    queueName,
    currentSettings = {},
    onSave
}) => {
    const [settings, setSettings] = useState({
        priority: currentSettings.priority || 5,
        maxAttempts: currentSettings.maxAttempts || 3,
        timeout: currentSettings.timeout || 300,
        delay: currentSettings.delay || 0
    });
    const [isSaving, setIsSaving] = useState(false);

    const handleSave = async () => {
        setIsSaving(true);
        try {
            await onSave?.(settings);
            onClose();
        } catch (error) {
            console.error('Failed to save queue settings:', error);
        } finally {
            setIsSaving(false);
        }
    };

    const handleInputChange = (field: string, value: any) => {
        setSettings((prev) => ({ ...prev, [field]: value }));
    };

    const priorityOptions = [
        { value: 1, label: 'Low (1)', description: 'Background tasks, cleanup jobs' },
        { value: 5, label: 'Normal (5)', description: 'Regular business operations' },
        { value: 10, label: 'High (10)', description: 'Important notifications, user actions' },
        { value: 15, label: 'Urgent (15)', description: 'Critical system operations' }
    ];

    return (
        <Modal open={isOpen} onClose={onClose} className="w-full max-w-2xl">
            <Modal.Title>
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-100 text-orange-600 shadow">
                        <Settings className="h-6 w-6" />
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">Queue Settings</h2>
                        <p className="text-sm text-gray-500">
                            Configure settings for "{queueName}" queue
                        </p>
                    </div>
                </div>
            </Modal.Title>

            <Modal.Content>
                <div className="space-y-6">
                    {/* Priority Settings */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                            <Zap className="h-5 w-5" />
                            Priority Settings
                        </h3>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Default Priority Level
                            </label>
                            <select
                                value={settings.priority}
                                onChange={(e) =>
                                    handleInputChange('priority', parseInt(e.target.value))
                                }
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                                {priorityOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-gray-500">
                                {
                                    priorityOptions.find((opt) => opt.value === settings.priority)
                                        ?.description
                                }
                            </p>
                        </div>
                    </div>

                    {/* Retry Settings */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800">
                            <BarChart3 className="h-5 w-5" />
                            Retry Settings
                        </h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Max Attempts
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="10"
                                    value={settings.maxAttempts}
                                    onChange={(e) =>
                                        handleInputChange('maxAttempts', parseInt(e.target.value))
                                    }
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Number of times to retry failed jobs
                                </p>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Timeout (seconds)
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="3600"
                                    value={settings.timeout}
                                    onChange={(e) =>
                                        handleInputChange('timeout', parseInt(e.target.value))
                                    }
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Maximum execution time per job
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Delay Settings */}
                    <div className="rounded-xl border border-gray-200 p-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-800">Delay Settings</h3>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Default Delay (seconds)
                            </label>
                            <input
                                type="number"
                                min="0"
                                max="86400"
                                value={settings.delay}
                                onChange={(e) =>
                                    handleInputChange('delay', parseInt(e.target.value))
                                }
                                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Delay before job execution (0 = immediate)
                            </p>
                        </div>
                    </div>

                    {/* Settings Summary */}
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <h4 className="mb-2 text-sm font-medium text-blue-800">Settings Summary</h4>
                        <div className="grid grid-cols-2 gap-2 text-xs text-blue-700">
                            <div>Priority: {settings.priority}</div>
                            <div>Max Attempts: {settings.maxAttempts}</div>
                            <div>Timeout: {settings.timeout}s</div>
                            <div>Delay: {settings.delay}s</div>
                        </div>
                    </div>
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
                        type="button"
                        onClick={handleSave}
                        disabled={isSaving}
                        className="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <Settings className="h-4 w-4" />
                        {isSaving ? 'Saving...' : 'Save Settings'}
                    </button>
                </div>
            </Modal.Footer>
        </Modal>
    );
};

export default QueueSettingsModal;

