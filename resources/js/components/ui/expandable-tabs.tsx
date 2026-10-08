"use client";

import { useState, useCallback, type ReactNode } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { cn } from "@/lib/utils";

// ─── Types ────────────────────────────────────────────────────────────────────

export interface TabItem {
  id: string;
  label: string;
  icon: ReactNode;
  content?: ReactNode;
}

interface ExpandableTabsProps {
  items: TabItem[];
  defaultActive?: string;
  onChange?: (id: string) => void;
  className?: string;
  /** Render content inline (not in a separate panel) */
  inline?: boolean;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ExpandableTabs({
  items,
  defaultActive,
  onChange,
  className,
  inline = true,
}: ExpandableTabsProps) {
  const [activeId, setActiveId] = useState<string | null>(
    defaultActive ?? items[0]?.id ?? null,
  );

  const activeItem = items.find((i) => i.id === activeId);

  const handleSelect = useCallback(
    (id: string) => {
      setActiveId((prev) => (prev === id ? prev : id));
      onChange?.(id);
    },
    [onChange],
  );

  return (
    <div className={cn("flex flex-col", className)}>
      {/* ── Tab Bar ─────────────────────────────────────────────── */}
      <nav
        className="flex items-center gap-1 rounded-2xl border border-zinc-200 bg-white p-1.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        role="tablist"
        aria-label="Navigation tabs"
      >
        {items.map((item) => {
          const isActive = item.id === activeId;

          return (
            <button
              key={item.id}
              role="tab"
              aria-selected={isActive}
              aria-controls={`panel-${item.id}`}
              tabIndex={isActive ? 0 : -1}
              onClick={() => handleSelect(item.id)}
              onKeyDown={(e) => {
                const idx = items.findIndex((i) => i.id === item.id);
                if (e.key === "ArrowRight") {
                  const next = items[(idx + 1) % items.length];
                  handleSelect(next.id);
                  (e.currentTarget
                    .parentElement?.children[(idx + 1) % items.length] as HTMLButtonElement)?.focus();
                }
                if (e.key === "ArrowLeft") {
                  const prev = items[(idx - 1 + items.length) % items.length];
                  handleSelect(prev.id);
                  (e.currentTarget
                    .parentElement?.children[(idx - 1 + items.length) % items.length] as HTMLButtonElement)?.focus();
                }
              }}
              className={cn(
                "relative flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-medium transition-colors outline-none",
                "focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1",
                isActive
                  ? "text-blue-600 dark:text-blue-400"
                  : "text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200",
              )}
            >
              {/* Active background pill */}
              {isActive && (
                <motion.div
                  layoutId="active-tab-bg"
                  className="absolute inset-0 rounded-xl bg-blue-50 dark:bg-blue-950/40"
                  transition={{ type: "spring", stiffness: 400, damping: 30 }}
                />
              )}

              {/* Icon */}
              <span className="relative z-10 flex h-5 w-5 items-center justify-center shrink-0">
                {item.icon}
              </span>

              {/* Label — animated width */}
              <AnimatePresence mode="wait" initial={false}>
                {isActive && (
                  <motion.span
                    key={`label-${item.id}`}
                    initial={{ width: 0, opacity: 0 }}
                    animate={{ width: "auto", opacity: 1 }}
                    exit={{ width: 0, opacity: 0 }}
                    transition={{ type: "spring", stiffness: 350, damping: 28 }}
                    className="relative z-10 origin-left overflow-hidden whitespace-nowrap"
                  >
                    <span className="px-1">{item.label}</span>
                  </motion.span>
                )}
              </AnimatePresence>
            </button>
          );
        })}
      </nav>

      {/* ── Content Panel ──────────────────────────────────────── */}
      {inline && activeItem?.content && (
        <div className="mt-4">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeItem.id}
              id={`panel-${activeItem.id}`}
              role="tabpanel"
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -4 }}
              transition={{ type: "spring", stiffness: 300, damping: 28 }}
            >
              {activeItem.content}
            </motion.div>
          </AnimatePresence>
        </div>
      )}
    </div>
  );
}
