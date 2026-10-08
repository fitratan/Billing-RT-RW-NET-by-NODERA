import React from "react";

export type BadgeVariant = "light" | "solid";
export type BadgeSize = "sm" | "md";
export type BadgeColor =
    | "primary"
    | "brand"
    | "success"
    | "error"
    | "warning"
    | "info"
    | "light"
    | "dark";

export interface BadgeProps {
    variant?: BadgeVariant;
    size?: BadgeSize;
    color?: BadgeColor;
    startIcon?: React.ReactNode;
    endIcon?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
}

export const Badge: React.FC<BadgeProps> = ({
    variant = "solid",
    color = "primary",
    size = "md",
    startIcon,
    endIcon,
    children,
    className = "",
}) => {
    const baseStyles =
        "inline-flex items-center justify-center gap-1 rounded-full font-semibold leading-none shadow-xs";

    const sizeStyles = {
        sm: "px-2.5 py-0.5 text-xs font-semibold",
        md: "px-3 py-1 text-xs font-semibold",
    };

    const variants = {
        light: {
            primary:
                "bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400",
            brand:
                "bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400",
            success:
                "bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500",
            error:
                "bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500",
            warning:
                "bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400",
            info:
                "bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400",
            light:
                "bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-white/80",
            dark: "bg-gray-500 text-white dark:bg-white/5 dark:text-white",
        },
        solid: {
            primary: "bg-brand-500 text-white dark:bg-brand-500",
            brand: "bg-brand-500 text-white dark:bg-brand-500",
            success: "bg-emerald-600 text-white dark:bg-emerald-500",
            error: "bg-rose-600 text-white dark:bg-rose-500",
            warning: "bg-amber-500 text-white dark:bg-amber-600",
            info: "bg-blue-600 text-white dark:bg-blue-500",
            light: "bg-gray-500 text-white dark:bg-gray-600",
            dark: "bg-gray-800 text-white dark:bg-gray-700",
        },
    };

    const sizeClass = sizeStyles[size];
    const colorStyles = variants[variant][color] || variants[variant].primary;

    return (
        <span className={`${baseStyles} ${sizeClass} ${colorStyles} ${className}`}>
            {startIcon && <span>{startIcon}</span>}
            {children}
            {endIcon && <span>{endIcon}</span>}
        </span>
    );
};

export default Badge;
