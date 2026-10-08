import React from "react";

export const Table: React.FC<React.TableHTMLAttributes<HTMLTableElement>> = ({
    children,
    className = "",
    ...props
}) => (
    <div className="w-full overflow-x-auto custom-scrollbar">
        <table
            className={`w-full min-w-[640px] text-left text-sm ${className}`}
            {...props}
        >
            {children}
        </table>
    </div>
);

export const TableHeader: React.FC<
    React.HTMLAttributes<HTMLTableSectionElement>
> = ({ children, className = "", ...props }) => (
    <thead
        className={`border-b border-gray-100 bg-gray-50/75 dark:border-gray-800 dark:bg-white/[0.02] ${className}`}
        {...props}
    >
        {children}
    </thead>
);

export const TableBody: React.FC<
    React.HTMLAttributes<HTMLTableSectionElement>
> = ({ children, className = "", ...props }) => (
    <tbody
        className={`divide-y divide-gray-100 dark:divide-gray-800 ${className}`}
        {...props}
    >
        {children}
    </tbody>
);

export const TableRow: React.FC<React.HTMLAttributes<HTMLTableRowElement>> = ({
    children,
    className = "",
    ...props
}) => (
    <tr
        className={`transition hover:bg-gray-50/50 dark:hover:bg-white/[0.02] ${className}`}
        {...props}
    >
        {children}
    </tr>
);

export const TableHead: React.FC<
    React.ThHTMLAttributes<HTMLTableCellElement>
> = ({ children, className = "", ...props }) => (
    <th
        className={`px-4 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400 ${className}`}
        {...props}
    >
        {children}
    </th>
);

export const TableCell: React.FC<
    React.TdHTMLAttributes<HTMLTableCellElement>
> = ({ children, className = "", ...props }) => (
    <td
        className={`px-4 py-3.5 text-sm text-gray-800 dark:text-gray-200 ${className}`}
        {...props}
    >
        {children}
    </td>
);

export default Table;
