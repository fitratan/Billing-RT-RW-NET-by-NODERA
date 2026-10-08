import React, { useEffect } from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: React.ReactNode;
    footer?: React.ReactNode;
    maxWidth?: "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl";
    className?: string;
    isLoading?: boolean;
}

export const Modal: React.FC<ModalProps> = ({
    isOpen,
    onClose,
    title,
    description,
    children,
    footer,
    maxWidth = "lg",
    className = "",
    isLoading = false,
}) => {
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === "Escape" && isOpen && !isLoading) {
                onClose();
            }
        };
        if (isOpen) {
            document.body.style.overflow = "hidden";
            window.addEventListener("keydown", handleKeyDown);
        } else {
            document.body.style.overflow = "unset";
        }
        return () => {
            document.body.style.overflow = "unset";
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [isOpen, onClose, isLoading]);

    if (!isOpen) return null;

    const maxWidthClasses = {
        sm: "max-w-sm",
        md: "max-w-md",
        lg: "max-w-lg",
        xl: "max-w-xl",
        "2xl": "max-w-2xl",
        "3xl": "max-w-3xl",
        "4xl": "max-w-4xl",
    };

    return createPortal(
        <div className="fixed inset-0 z-[99999] flex items-center justify-center overflow-y-auto p-4 sm:p-6 animate-in fade-in duration-150">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity"
                onClick={() => {
                    if (!isLoading) onClose();
                }}
            />

            {/* Modal Dialog */}
            <div
                className={`relative w-full ${maxWidthClasses[maxWidth]} rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-lg transition-all dark:border-gray-800 dark:bg-[#121720] ${className}`}
            >
                {/* Header */}
                {(title || description) && (
                    <div className="mb-5 flex items-start justify-between">
                        <div>
                            {title && (
                                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                    {title}
                                </h3>
                            )}
                            {description && (
                                <p className="mt-1 text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                                    {description}
                                </p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={isLoading}
                            className={`rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition dark:hover:bg-gray-800 dark:hover:text-white ${
                                isLoading ? "opacity-40 cursor-not-allowed pointer-events-none" : "cursor-pointer"
                            }`}
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>
                )}

                {/* Body */}
                <div className="max-h-[75vh] overflow-y-auto custom-scrollbar">
                    {children}
                </div>

                {/* Footer */}
                {footer && (
                    <div className="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        {footer}
                    </div>
                )}
            </div>
        </div>,
        document.body
    );
};

export default Modal;
