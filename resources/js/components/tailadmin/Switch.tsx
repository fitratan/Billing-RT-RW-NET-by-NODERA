import React from "react";

export interface SwitchProps {
    checked: boolean;
    onChange?: (checked: boolean) => void;
    onCheckedChange?: (checked: boolean) => void;
    label?: string;
    description?: string;
    disabled?: boolean;
    className?: string;
}

export const Switch: React.FC<SwitchProps> = ({
    checked,
    onChange,
    onCheckedChange,
    label,
    description,
    disabled = false,
    className = "",
}) => {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (disabled) return;
        const newChecked = e.target.checked;
        if (typeof onChange === "function") {
            onChange(newChecked);
        }
        if (typeof onCheckedChange === "function") {
            onCheckedChange(newChecked);
        }
    };

    return (
        <label
            className={`flex items-start gap-3 cursor-pointer select-none ${
                disabled ? "cursor-not-allowed opacity-50" : ""
            } ${className}`}
        >
            <div className="relative inline-flex items-center mt-0.5">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={handleChange}
                    disabled={disabled}
                    className="sr-only"
                />
                <div
                    className={`h-6 w-11 rounded-full transition-colors ${
                        checked
                            ? "bg-brand-500"
                            : "bg-gray-200 dark:bg-gray-700"
                    }`}
                />
                <div
                    className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-xs transition-transform ${
                        checked ? "translate-x-5" : "translate-x-0"
                    }`}
                />
            </div>
            {(label || description) && (
                <div>
                    {label && (
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {label}
                        </span>
                    )}
                    {description && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {description}
                        </p>
                    )}
                </div>
            )}
        </label>
    );
};

export default Switch;
