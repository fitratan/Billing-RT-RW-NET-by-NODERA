import * as React from "react"
import { Link } from "@inertiajs/react"
import { ExternalLink, ChevronLeft } from "lucide-react"

interface WaGatewayHeaderProps {
  title: string
  description?: string
  actionButton?: React.ReactNode
  children?: React.ReactNode
}

export function WaGatewayHeader({
  title,
  description,
  actionButton,
  children,
}: WaGatewayHeaderProps) {
  return (
    <section className="relative bg-gradient-to-br from-[#0073C6] via-[#0056A0] to-[#003E78] px-4 pt-5 pb-10 sm:px-8 sm:pt-7 sm:pb-12 text-white shadow-sm rounded-b-[2rem] sm:rounded-b-[2.5rem] border-b border-white/15 overflow-hidden">
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-12 -right-12 h-56 w-56 rounded-full border border-white/15 pointer-events-none" />
        <div className="absolute -top-6 -right-6 h-44 w-44 rounded-full border border-dashed border-white/10 pointer-events-none" />
      </div>

      <div className="relative max-w-7xl 2xl:max-w-[1700px] mx-auto space-y-4 sm:space-y-5">
        <div className="flex items-center justify-between gap-2.5 sm:gap-4 flex-wrap">
          <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
            <Link
              href="/superadmin"
              className="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/15 backdrop-blur-md text-white hover:bg-white/25 active:scale-95 transition-all lg:hidden"
              title="Kembali ke Dashboard Superadmin"
            >
              <ChevronLeft className="h-5 w-5 shrink-0" />
            </Link>
            <div className="leading-tight min-w-0">
              <h1 className="text-base sm:text-lg lg:text-xl font-bold lg:font-extrabold tracking-wider text-white truncate whitespace-nowrap block">
                {title}
              </h1>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <a
              href="https://wa.dgtlnetsolution.com"
              target="_blank"
              rel="noreferrer"
              className="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-white/25 bg-white/20 hover:bg-white/30 text-white backdrop-blur-md transition-all active:scale-95 shadow-sm"
              title="Buka Portal WhatsApp Gateway"
            >
              <ExternalLink className="h-4 w-4" />
            </a>
            {actionButton}
          </div>
        </div>

        {description && (
          <div className="pt-0.5 sm:pt-1">
            <p className="text-xs sm:text-sm text-white/80 font-medium max-w-2xl leading-relaxed">
              {description}
            </p>
          </div>
        )}

        {children}
      </div>
    </section>
  )
}