import React from "react";

export interface SelectOption {
    label: string;
    value: string | number;
}

export interface SelectProps
    extends React.SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    hint?: string;
    options?: SelectOption[];
}

export const Select: React.FC<SelectProps> = ({
    label,
    error,
    hint,
    options = [],
    children,
    className = "",
    id,
    disabled,
    ...props
}) => {
    const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, "-") : undefined);

    return (
        <div className="w-full">
            {label && (
                <label
                    htmlFor={selectId}
                    className="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300"
                >
                    {label}
                </label>
            )}
            <div className="relative">
                <select
                    id={selectId}
                    disabled={disabled}
                    className={`w-full appearance-none rounded-lg border bg-white px-3.5 py-2 pr-10 text-sm text-gray-900 transition focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-500/10 disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-brand-400 ${
                        error
                            ? "border-error-500 focus:border-error-500 focus:ring-error-500/10"
                            : "border-gray-300"
                    } ${className}`}
                    {...props}
                >
                    {options.length > 0
                        ? options.map((opt) => (
                              <option key={opt.value} value={opt.value}>
                                  {opt.label}
                              </option>
                          ))
                        : children}
                </select>
                <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                    <svg
                        className="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </div>
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

export default Select;
