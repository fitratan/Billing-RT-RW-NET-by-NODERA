"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import {
  Home,
  LineChart,
  CreditCard,
  MessageCircle,
  Trophy,
  User,
  type LucideIcon,
} from "lucide-react"
import { cn } from "@/lib/utils";

export interface NavItemConfig {
  label: string;
  icon: LucideIcon;
  href?: string;
  onClick?: () => void;
}

const defaultNavItems: NavItemConfig[] = [
  { label: "Home", icon: Home },
  { label: "Portfolio", icon: LineChart },
  { label: "Transactions", icon: CreditCard },
  { label: "Messages", icon: MessageCircle },
  { label: "Rewards", icon: Trophy },
  { label: "Profile", icon: User },
];

const MOBILE_LABEL_WIDTH = 72;

export type BottomNavBarProps = {
  className?: string;
  defaultIndex?: number;
  stickyBottom?: boolean;
  items?: NavItemConfig[];
  activeIndex?: number;
  onItemSelect?: (index: number, item: NavItemConfig) => void;
};

export function BottomNavBar({
  className,
  defaultIndex = 0,
  stickyBottom = false,
  items = defaultNavItems,
  activeIndex: controlledIndex,
  onItemSelect,
}: BottomNavBarProps) {
  const [internalIndex, setInternalIndex] = useState(defaultIndex);
  const activeIndex = controlledIndex !== undefined ? controlledIndex : internalIndex;

  const handleSelect = (idx: number, item: NavItemConfig) => {
    if (controlledIndex === undefined) {
      setInternalIndex(idx);
    }
    item.onClick?.();
    onItemSelect?.(idx, item);
  };

  return (
    <motion.nav
      initial={{ scale: 0.9, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      transition={{ type: "spring", stiffness: 300, damping: 26 }}
      role="navigation"
      aria-label="Bottom Navigation"
      className={cn(
        "bg-card dark:bg-card border border-border dark:border-sidebar-border rounded-full flex items-center p-2 shadow-xl space-x-1 min-w-[320px] max-w-[95vw] h-[52px]",
        stickyBottom && "fixed inset-x-0 bottom-4 mx-auto z-20 w-fit",
        className,
      )}
    >
      {items.map((item, idx) => {
        const Icon = item.icon;
        const isActive = activeIndex === idx;

        return (
          <motion.button
            key={item.label}
            whileTap={{ scale: 0.97 }}
            className={cn(
              "flex items-center gap-0 px-3 py-2 rounded-full transition-colors duration-200 relative h-10 min-w-[44px] min-h-[40px] max-h-[44px]",
              isActive
                ? "bg-primary/10 dark:bg-primary/15 text-primary dark:text-primary gap-2"
                : "bg-transparent text-muted-foreground dark:text-muted-foreground hover:bg-muted dark:hover:bg-muted",
              "focus:outline-none focus-visible:ring-0",
            )}
            onClick={() => handleSelect(idx, item)}
            aria-label={item.label}
            type="button"
          >
            <Icon
              size={20}
              strokeWidth={2}
              aria-hidden
              className="transition-colors duration-200"
            />

            <motion.div
              initial={false}
              animate={{
                scaleX: isActive ? 1 : 0,
                opacity: isActive ? 1 : 0,
                x: isActive ? 8 : 0,
              }}
              transition={{
                scaleX: { type: "spring", stiffness: 350, damping: 32 },
                opacity: { duration: 0.19 },
                x: { duration: 0.19 },
              }}
              style={{ transformOrigin: "left", width: isActive ? `${MOBILE_LABEL_WIDTH}px` : "0px" }}
              className={cn("overflow-hidden flex items-center max-w-[72px]")}
            >
              <span
                className={cn(
                  "font-medium text-xs whitespace-nowrap select-none transition-opacity duration-200 overflow-hidden text-ellipsis text-[clamp(0.625rem,0.5263rem+0.5263vw,1rem)] leading-[1.9]",
                  isActive ? "text-primary dark:text-primary" : "opacity-0",
                )}
                title={item.label}
              >
                {item.label}
              </span>
            </motion.div>
          </motion.button>
        );
      })}
    </motion.nav>
  );
}

export default BottomNavBar;
