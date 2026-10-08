import React from "react";

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    size?: "xs" | "sm" | "md" | "lg";
    variant?: "primary" | "outline" | "secondary" | "danger" | "ghost";
    startIcon?: React.ReactNode;
    endIcon?: React.ReactNode;
    isLoading?: boolean;
}

export const Button: React.FC<ButtonProps> = ({
    children,
    size = "md",
    variant = "primary",
    startIcon,
    endIcon,
    isLoading = false,
    className = "",
    disabled,
    ...props
}) => {
    const sizeClasses = {
        xs: "px-2.5 py-1.5 text-xs rounded-md",
        sm: "px-3.5 py-2 text-xs font-medium rounded-lg",
        md: "px-4 py-2.5 text-sm font-medium rounded-lg",
        lg: "px-5 py-3 text-base font-medium rounded-xl",
    };

    const variantClasses = {
        primary:
            "bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 active:bg-brand-700 disabled:bg-brand-300 disabled:cursor-not-allowed",
        outline:
            "bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 active:bg-gray-100 shadow-theme-xs dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-white/5",
        secondary:
            "bg-gray-100 text-gray-700 hover:bg-gray-200 active:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700",
        danger:
            "bg-error-500 text-white shadow-theme-xs hover:bg-error-600 active:bg-error-700 disabled:bg-error-300",
        ghost:
            "text-gray-600 hover:bg-gray-100 hover:text-gray-900 active:bg-gray-200 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white",
    };

    return (
        <button
            className={`inline-flex items-center justify-center gap-2 font-medium transition active:scale-[0.98] ${
                sizeClasses[size]
            } ${variantClasses[variant]} ${
                disabled || isLoading ? "cursor-not-allowed opacity-60" : ""
            } ${className}`}
            disabled={disabled || isLoading}
            {...props}
        >
            {isLoading ? (
                <svg
                    className="h-4 w-4 animate-spin text-current"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                    />
                    <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v8H4z"
                    />
                </svg>
            ) : (
                startIcon && <span className="flex items-center">{startIcon}</span>
            )}
            {children}
            {!isLoading && endIcon && (
                <span className="flex items-center">{endIcon}</span>
            )}
        </button>
    );
};

export default Button;
