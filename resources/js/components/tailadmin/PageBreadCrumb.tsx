import React from "react";
import { Link } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export interface PageBreadCrumbProps {
    pageTitle: string;
    description?: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    items?: BreadcrumbItem[];
    actions?: React.ReactNode;
    action?: React.ReactNode;
}

export const PageBreadCrumb: React.FC<PageBreadCrumbProps> = ({
    pageTitle,
    description,
    subtitle,
    breadcrumbs,
    items,
    actions,
    action,
}) => {
    const list = breadcrumbs || items || [];
    const rightActions = actions || action;
    const desc = description || subtitle;

    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 className="text-xl font-bold text-gray-900 sm:text-2xl dark:text-white">
                    {pageTitle}
                </h1>
                {desc && (
                    <p className="mt-1 text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                        {desc}
                    </p>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {rightActions}
                {list.length > 0 && (
                    <nav aria-label="Breadcrumb">
                        <ol className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            {list.map((item, idx) => (
                                <li key={idx} className="flex items-center gap-1.5">
                                    {idx > 0 && (
                                        <ChevronRight className="h-3.5 w-3.5 text-gray-400" />
                                    )}
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="hover:text-brand-500 transition-colors"
                                        >
                                            {item.label}
                                        </Link>
                                    ) : (
                                        <span className="font-medium text-gray-800 dark:text-white/80">
                                            {item.label}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>
                )}
            </div>
        </div>
    );
};

export const PageHeader = PageBreadCrumb;
export default PageBreadCrumb;
