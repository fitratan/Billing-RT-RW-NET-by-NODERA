import React from "react"
import { Link, usePage } from "@inertiajs/react"
import { ArrowLeft } from "lucide-react"
import { NotificationDropdown } from "./NotificationDropdown"
import { UserDropdown } from "./UserDropdown"
import { cn } from "@/lib/utils"

export interface AppHeaderProps {
  title?: string
  subtitle?: string
  onBack?: string
  hideBack?: boolean
  right?: React.ReactNode
  className?: string
}

export const AppHeader: React.FC<AppHeaderProps> = ({
  title,
  onBack,
  hideBack,
  right,
  className,
}) => {
  const { url } = usePage()
  const isPortal = url.startsWith("/portal")

  return (
    <header className={cn("sticky top-0 z-40 flex h-16 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xs dark:border-gray-800 dark:bg-gray-900/95 transition-colors shrink-0", className)}>
      <div className="flex grow items-center justify-between px-4 sm:px-6 lg:px-8 gap-3">
        {/* ── LEFT: BACK BUTTON / NODERA LOGO (MOBILE ONLY) + PAGE TITLE ── */}
        <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 pr-2">
          {onBack && !hideBack ? (
            <Link
              href={onBack}
              className="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition shrink-0"
              aria-label="Kembali"
              title="Kembali"
            >
              <ArrowLeft className="h-4 w-4" />
            </Link>
          ) : (
            <Link
              href={isPortal ? "/portal" : "/dashboard"}
              className="flex xl:hidden items-center shrink-0"
            >
              <img
                src="/images/logo-dark.png"
                alt="NODERA"
                className="h-6 sm:h-7 w-auto object-contain block dark:hidden select-none"
              />
              <img
                src="/images/logo-white.png"
                alt="NODERA"
                className="h-6 sm:h-7 w-auto object-contain hidden dark:block select-none"
              />
            </Link>
          )}

          {title && (
            <div className="flex items-center gap-2.5 min-w-0">
              <span className="h-4 w-px bg-gray-200 dark:bg-gray-800 shrink-0 xl:hidden" />
              <h1 className="text-sm sm:text-base font-bold text-gray-900 dark:text-white truncate">
                {title}
              </h1>
            </div>
          )}
        </div>

        {/* ── RIGHT: EXTRA ACTIONS + NOTIFICATIONS + USER PROFILE ── */}
        <div className="flex items-center gap-2 sm:gap-3 shrink-0">
          {right && (
            <div className="flex items-center gap-1.5 sm:gap-2">
              {right}
            </div>
          )}

          {/* Notification Bell Dropdown (Admin/Member only) */}
          {!isPortal && <NotificationDropdown />}

          {/* Vertical Divider */}
          {!isPortal && <div className="h-6 w-px bg-gray-200 dark:bg-gray-800 mx-0.5" />}

          {/* User Profile Dropdown */}
          <UserDropdown />
        </div>
      </div>
    </header>
  )
}

export default AppHeader
