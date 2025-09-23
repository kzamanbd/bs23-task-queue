import { AlertTriangle, Trash2 } from 'lucide-react';
import React from 'react';
import Modal from '../shared/Modal';

interface ConfirmModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'warning' | 'info';
    isLoading?: boolean;
}

const ConfirmModal: React.FC<ConfirmModalProps> = ({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'danger',
    isLoading = false
}) => {
    const getVariantStyles = () => {
        switch (variant) {
            case 'danger':
                return {
                    icon: <Trash2 className="h-6 w-6 text-red-500" />,
                    iconBg: 'bg-red-100',
                    confirmButton: 'bg-red-600 hover:bg-red-700 text-white',
                    border: 'border-red-200'
                };
            case 'warning':
                return {
                    icon: <AlertTriangle className="h-6 w-6 text-amber-500" />,
                    iconBg: 'bg-amber-100',
                    confirmButton: 'bg-amber-600 hover:bg-amber-700 text-white',
                    border: 'border-amber-200'
                };
            case 'info':
                return {
                    icon: <AlertTriangle className="h-6 w-6 text-blue-500" />,
                    iconBg: 'bg-blue-100',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white',
                    border: 'border-blue-200'
                };
            default:
                return {
                    icon: <AlertTriangle className="h-6 w-6 text-gray-500" />,
                    iconBg: 'bg-gray-100',
                    confirmButton: 'bg-gray-600 hover:bg-gray-700 text-white',
                    border: 'border-gray-200'
                };
        }
    };

    const styles = getVariantStyles();

    return (
        <Modal open={isOpen} onClose={onClose} className="w-full max-w-md">
            <Modal.Title>
                <div className="flex items-center gap-3">
                    <div
                        className={`flex h-12 w-12 items-center justify-center rounded-xl ${styles.iconBg} shadow`}>
                        {styles.icon}
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800">{title}</h2>
                        <p className="text-sm text-gray-500">Please confirm your action</p>
                    </div>
                </div>
            </Modal.Title>

            <Modal.Content>
                <div className="rounded-xl border bg-gray-50 p-4">
                    <p className="text-sm text-gray-700">{message}</p>
                </div>
            </Modal.Content>

            <Modal.Footer>
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={isLoading}
                        className="rounded-xl bg-gray-100 px-6 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50">
                        {cancelText}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={isLoading}
                        className={`rounded-xl px-6 py-3 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${styles.confirmButton}`}>
                        {isLoading ? 'Processing...' : confirmText}
                    </button>
                </div>
            </Modal.Footer>
        </Modal>
    );
};

export default ConfirmModal;

