import React from "react";
import { Check } from "lucide-react";

export interface CheckboxProps {
    checked: boolean;
    onChange?: ((checked: boolean) => void) | ((e: any) => void);
    label?: string;
    description?: string;
    disabled?: boolean;
    className?: string;
    "aria-label"?: string;
}

export const Checkbox: React.FC<CheckboxProps> = ({
    checked,
    onChange,
    label,
    description,
    disabled = false,
    className = "",
    "aria-label": ariaLabel,
}) => {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (disabled || typeof onChange !== "function") return;
        const isChecked = e.target.checked;
        const hybridEvent: any = Object.assign(
            (hint?: string) => isChecked,
            {
                target: { checked: isChecked, value: e.target.value },
                currentTarget: { checked: isChecked },
                nativeEvent: e.nativeEvent,
                bubbles: e.bubbles,
                cancelable: e.cancelable,
                defaultPrevented: e.defaultPrevented,
                checked: isChecked,
                valueOf: () => isChecked,
                toString: () => String(isChecked),
                [Symbol.toPrimitive]: (hint: string) => (hint === "number" ? (isChecked ? 1 : 0) : isChecked),
            }
        );
        try {
            onChange(hybridEvent);
        } catch {
            onChange(isChecked);
        }
    };

    return (
        <label
            className={`flex items-start gap-2.5 cursor-pointer select-none ${
                disabled ? "cursor-not-allowed opacity-50" : ""
            } ${className}`}
        >
            <div className="relative flex items-center justify-center mt-0.5">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={handleChange}
                    disabled={disabled}
                    aria-label={ariaLabel}
                    className="sr-only"
                />
                <div
                    className={`flex h-4.5 w-4.5 items-center justify-center rounded-md border transition-colors ${
                        checked
                            ? "border-brand-500 bg-brand-500 text-white"
                            : "border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800"
                    }`}
                >
                    {checked && <Check className="h-3 w-3 stroke-[3]" />}
                </div>
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

export default Checkbox;
