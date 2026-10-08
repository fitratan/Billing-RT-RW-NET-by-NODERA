import * as React from "react"
import { ChevronDown } from "lucide-react"
import { cn } from "@/lib/utils"

const Card = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn(
        "rounded-2xl border border-border bg-card text-card-foreground shadow-none",
        className,
      )}
      {...props}
    />
  ),
)
Card.displayName = "Card"

const CardHeader = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn("flex flex-col space-y-1.5 p-4 sm:p-5", className)} {...props} />
  ),
)
CardHeader.displayName = "CardHeader"

const CardTitle = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn("font-display text-base font-semibold leading-none tracking-tight", className)}
      {...props}
    />
  ),
)
CardTitle.displayName = "CardTitle"

const CardDescription = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn("text-sm text-muted-foreground", className)} {...props} />
  ),
)
CardDescription.displayName = "CardDescription"

const CardContent = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn("p-4 sm:p-5 pt-0", className)} {...props} />
  ),
)
CardContent.displayName = "CardContent"

const CardFooter = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn("flex items-center p-4 sm:p-5 pt-0", className)} {...props} />
  ),
)
CardFooter.displayName = "CardFooter"

export interface CollapsibleCardProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: React.ReactNode
  description?: React.ReactNode
  icon?: React.ReactNode
  action?: React.ReactNode
  defaultOpen?: boolean
  children?: React.ReactNode
  headerClassName?: string
}

export function CollapsibleCard({
  title,
  description,
  icon,
  action,
  defaultOpen = true,
  children,
  className,
  headerClassName,
  ...props
}: CollapsibleCardProps) {
  const [isOpen, setIsOpen] = React.useState(defaultOpen)

  return (
    <div
      className={cn(
        "rounded-2xl border border-[#212B3B] bg-[#121720] text-card-foreground overflow-hidden transition-all duration-200 h-fit self-start w-full",
        className
      )}
      {...props}
    >
      <div
        onClick={() => setIsOpen((prev) => !prev)}
        className={cn(
          "flex items-center justify-between p-4 sm:p-5 cursor-pointer select-none hover:bg-white/[0.02] transition-colors",
          isOpen && "border-b border-[#1E2633]",
          headerClassName
        )}
      >
        <div className="flex items-center gap-3 min-w-0 flex-1">
          {icon && <div className="shrink-0">{icon}</div>}
          <div className="min-w-0 flex-1">
            {title && <div className="font-semibold text-sm sm:text-base text-white leading-tight truncate">{title}</div>}
            {description && <div className="text-xs text-slate-400 mt-0.5 truncate">{description}</div>}
          </div>
        </div>
        <div className="flex items-center gap-2 shrink-0 ml-2">
          {action && <div onClick={(e) => e.stopPropagation()}>{action}</div>}
          <div
            className="flex h-8 w-8 items-center justify-center rounded-lg border border-[#2B3544] bg-[#161B22] text-slate-400 hover:text-white hover:bg-[#1D242E] transition-all pointer-events-none"
            aria-label={isOpen ? "Tutup Card" : "Buka Card"}
          >
            <ChevronDown className={cn("h-4 w-4 transition-transform duration-200", isOpen && "rotate-180")} />
          </div>
        </div>
      </div>
      {isOpen && (
        <div className="p-4 sm:p-5 space-y-4 animate-in fade-in-50 duration-150">
          {children}
        </div>
      )}
    </div>
  )
}

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent }
