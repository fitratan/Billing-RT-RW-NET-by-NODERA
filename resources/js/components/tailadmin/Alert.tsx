import React from "react";
import { CheckCircle2, AlertTriangle, XCircle, Info, X } from "lucide-react";

export type AlertVariant = "success" | "warning" | "error" | "info";

export interface AlertProps {
    variant?: AlertVariant;
    title?: string;
    children: React.ReactNode;
    onClose?: () => void;
    className?: string;
}

export const Alert: React.FC<AlertProps> = ({
    variant = "info",
    title,
    children,
    onClose,
    className = "",
}) => {
    const config = {
        success: {
            container:
                "bg-success-50/80 border-success-200 text-success-900 dark:bg-success-500/10 dark:border-success-500/20 dark:text-success-400",
            icon: <CheckCircle2 className="h-5 w-5 text-success-600 dark:text-success-400" />,
        },
        warning: {
            container:
                "bg-warning-50/80 border-warning-200 text-warning-900 dark:bg-warning-500/10 dark:border-warning-500/20 dark:text-warning-400",
            icon: <AlertTriangle className="h-5 w-5 text-warning-600 dark:text-warning-400" />,
        },
        error: {
            container:
                "bg-error-50/80 border-error-200 text-error-900 dark:bg-error-500/10 dark:border-error-500/20 dark:text-error-400",
            icon: <XCircle className="h-5 w-5 text-error-600 dark:text-error-400" />,
        },
        info: {
            container:
                "bg-brand-50/80 border-brand-200 text-brand-900 dark:bg-brand-500/10 dark:border-brand-500/20 dark:text-brand-400",
            icon: <Info className="h-5 w-5 text-brand-600 dark:text-brand-400" />,
        },
    };

    const current = config[variant];

    return (
        <div
            className={`flex items-start gap-3 rounded-xl border p-4 text-sm ${current.container} ${className}`}
        >
            <div className="flex-shrink-0 mt-0.5">{current.icon}</div>
            <div className="flex-1">
                {title && <h4 className="mb-0.5 font-semibold">{title}</h4>}
                <div className="text-xs sm:text-sm leading-relaxed">{children}</div>
            </div>
            {onClose && (
                <button
                    onClick={onClose}
                    className="flex-shrink-0 text-gray-400 hover:text-gray-700 transition dark:hover:text-white"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
};

export default Alert;
