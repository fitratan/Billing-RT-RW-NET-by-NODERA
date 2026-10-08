import React from "react";
import { Link } from "@inertiajs/react";
import { LucideIcon, TrendingUp, TrendingDown } from "lucide-react";
import { Badge, BadgeColor } from "./Badge";

export interface MetricCardProps {
    title: string;
    value: string | number;
    icon?: LucideIcon | React.ReactNode;
    trend?: {
        value: string;
        isPositive?: boolean;
    };
    badge?: {
        text: string;
        color?: BadgeColor;
    };
    description?: string;
    sub?: string;
    iconBgColor?: string;
    iconColor?: string;
    className?: string;
    href?: string;
    onClick?: () => void;
    isActive?: boolean;
}

export const MetricCard: React.FC<MetricCardProps> = ({
    title,
    value,
    icon: Icon,
    trend,
    badge,
    description,
    sub,
    iconBgColor = "bg-brand-50 dark:bg-brand-500/10",
    iconColor = "text-brand-500 dark:text-brand-400",
    className = "",
    href,
    onClick,
    isActive = false,
}) => {
    const cardContent = (
        <>
            <div className="flex items-center justify-between">
                <div
                    className={`flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl transition ${iconBgColor}`}
                >
                    {React.isValidElement(Icon) ? (
                        Icon
                    ) : Icon ? (
                        // @ts-ignore
                        <Icon className={`h-5 w-5 sm:h-6 sm:w-6 ${iconColor}`} />
                    ) : null}
                </div>
                {trend && (
                    <span
                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] sm:text-xs font-semibold ${
                            trend.isPositive !== false
                                ? "bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500"
                                : "bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500"
                        }`}
                    >
                        {trend.isPositive !== false ? (
                            <TrendingUp className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                        ) : (
                            <TrendingDown className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                        )}
                        {trend.value}
                    </span>
                )}
                {badge && (
                    <Badge color={badge.color || "primary"} size="sm">
                        {badge.text}
                    </Badge>
                )}
            </div>

            <div className="mt-3 sm:mt-4 flex items-end justify-between">
                <div className="min-w-0 flex-1">
                    <span className="text-[11px] sm:text-xs md:text-sm font-medium text-gray-500 dark:text-gray-400 block truncate">
                        {title}
                    </span>
                    <h4 className="mt-0.5 sm:mt-1 text-lg sm:text-2xl lg:text-3xl font-bold tracking-tight text-gray-800 dark:text-white/90 truncate">
                        {value}
                    </h4>
                    {(description || sub) && (
                        <p className="mt-0.5 sm:mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 truncate">
                            {description || sub}
                        </p>
                    )}
                </div>
            </div>
        </>
    );

    const isInteractive = Boolean(href || onClick);
    const activeClass = isActive
        ? "border-brand-500 ring-2 ring-brand-500/30 bg-brand-50/50 dark:bg-brand-500/10 shadow-sm"
        : "border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]";
    const interactiveClass = isInteractive
        ? "cursor-pointer hover:border-brand-500/60 hover:shadow-xs transition active:scale-[0.98]"
        : "";

    if (href) {
        return (
            <Link
                href={href}
                className={`block rounded-2xl border p-3.5 sm:p-5 lg:p-6 shadow-xs ${activeClass} ${interactiveClass} ${className}`}
            >
                {cardContent}
            </Link>
        );
    }

    return (
        <div
            onClick={onClick}
            className={`rounded-2xl border p-3.5 sm:p-5 lg:p-6 shadow-xs ${activeClass} ${interactiveClass} ${className}`}
        >
            {cardContent}
        </div>
    );
};

export default MetricCard;
