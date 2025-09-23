import { classNames } from '@/utils';
import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    Transition,
    TransitionChild
} from '@headlessui/react';
import { X } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import React, { Fragment } from 'react';

export type ModalProps = {
    children: React.ReactNode;
    className?: string;
    open: boolean;
    fullScreen?: boolean;
    showXButton?: boolean;
    onClose: () => void;
} & HTMLAttributes<HTMLDivElement>;

const Modal = ({
    children,
    showXButton = true,
    className,
    open,
    fullScreen = false,
    onClose
}: ModalProps) => {
    return (
        <Transition appear show={open} as={Fragment}>
            <Dialog as="div" className="z-[9999]" onClose={onClose}>
                {/* Backdrop with fade transition */}
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0">
                    <DialogBackdrop className="fixed inset-0 z-[8888] bg-black/30 backdrop-blur-sm" />
                </TransitionChild>

                {/* Full-screen container to center the panel */}
                <div
                    className={classNames(
                        'fixed inset-0 z-[9999] flex w-screen',
                        fullScreen ? '' : 'items-center justify-center p-4'
                    )}>
                    {/* Panel with scale and fade transition */}
                    <TransitionChild
                        as={Fragment}
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 scale-95"
                        enterTo="opacity-100 scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 scale-100"
                        leaveTo="opacity-0 scale-95">
                        <DialogPanel
                            className={classNames(
                                fullScreen
                                    ? 'h-full w-full bg-white'
                                    : 'relative m-4 w-full max-w-md transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all',
                                className
                            )}>
                            {children}
                            {showXButton && (
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="absolute top-2 right-2 rounded-lg p-1.5 text-sm text-gray-500 transition-colors duration-150 outline-none hover:text-gray-700 focus:outline-none">
                                    <X size={20} />
                                </button>
                            )}
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
};

export type TitleProps = {
    className?: string;
} & HTMLAttributes<HTMLDivElement>;

const Title: React.FunctionComponent<TitleProps> = ({ children, className, ...rest }) => {
    return (
        <div
            className={classNames('rounded-t-lg border-b bg-gray-100 p-4 font-semibold', className)}
            {...rest}>
            {children}
        </div>
    );
};

export type ContentProps = {
    className?: string;
} & HTMLAttributes<HTMLDivElement>;

const Content: React.FunctionComponent<ContentProps> = ({ children, className, ...rest }) => {
    return (
        <div className={classNames('p-4', className)} {...rest}>
            {children}
        </div>
    );
};

export type FooterProps = {
    className?: string;
} & HTMLAttributes<HTMLDivElement>;

const Footer: React.FunctionComponent<FooterProps> = ({ children, className, ...rest }) => {
    return (
        <div className={classNames('rounded-b-lg border-t bg-gray-100 p-4', className)} {...rest}>
            {children}
        </div>
    );
};

Modal.Title = Title;
Modal.Content = Content;
Modal.Footer = Footer;

export default Modal;

