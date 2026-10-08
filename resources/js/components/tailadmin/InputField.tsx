import React from "react";

export interface InputFieldProps
    extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    hint?: string;
    startIcon?: React.ReactNode;
    endIcon?: React.ReactNode;
}

export const InputField: React.FC<InputFieldProps> = ({
    label,
    error,
    hint,
    startIcon,
    endIcon,
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
            <div className="relative">
                {startIcon && (
                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        {startIcon}
                    </div>
                )}
                <input
                    id={inputId}
                    disabled={disabled}
                    className={`w-full rounded-lg border bg-white px-3.5 py-2 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-500/10 disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-400 ${
                        startIcon ? "pl-10" : ""
                    } ${endIcon ? "pr-10" : ""} ${
                        error
                            ? "border-error-500 focus:border-error-500 focus:ring-error-500/10"
                            : "border-gray-300"
                    } ${className}`}
                    {...props}
                />
                {endIcon && (
                    <div className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                        {endIcon}
                    </div>
                )}
            </div>
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

export default InputField;
