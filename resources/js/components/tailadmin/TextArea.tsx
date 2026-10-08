import React from "react";

export interface TextAreaProps
    extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    error?: string;
    hint?: string;
}

export const TextArea: React.FC<TextAreaProps> = ({
    label,
    error,
    hint,
    className = "",
    id,
    disabled,
    ...props
}) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, "-") : undefined);

    return (
        <div className="w-full">
            {label && (
                <label
                    htmlFor={inputId}
                    className="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                >
                    {label}
                </label>
            )}
            <textarea
                id={inputId}
                disabled={disabled}
                className={`w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-500/10 disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-400 ${
                    error
                        ? "border-error-500 focus:border-error-500 focus:ring-error-500/10"
                        : "border-gray-300"
                } ${className}`}
                {...props}
            />
            {error && (
                <p className="mt-1 text-xs text-error-600 dark:text-error-400">
                    {error}
                </p>
            )}
            {!error && hint && (
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {hint}
                </p>
            )}
        </div>
    );
};

export default TextArea;
