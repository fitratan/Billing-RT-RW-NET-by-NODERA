import React from "react";

interface ComponentCardProps {
    title: string;
    children: React.ReactNode;
    className?: string;
    desc?: string;
    headerRight?: React.ReactNode;
    noPadding?: boolean;
}

export const ComponentCard: React.FC<ComponentCardProps> = ({
    title,
    children,
    className = "",
    desc = "",
    headerRight,
    noPadding = false,
}) => {
    return (
        <div
            className={`rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
        >
            {/* Card Header */}
            {(title || headerRight) && (
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                    <div>
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                            {title}
                        </h3>
                        {desc && (
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {desc}
                            </p>
                        )}
                    </div>
                    {headerRight && (
                        <div className="flex items-center gap-2">
                            {headerRight}
                        </div>
                    )}
                </div>
            )}

            {/* Card Body */}
            <div
                className={`border-t border-gray-100 dark:border-gray-800 ${
                    noPadding ? "" : "p-4 sm:p-6"
                }`}
            >
                {children}
            </div>
        </div>
    );
};

export default ComponentCard;
