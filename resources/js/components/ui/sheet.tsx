import * as React from "react"
import * as DialogPrimitive from "@radix-ui/react-dialog"
import { motion, AnimatePresence } from "framer-motion"
import { X } from "lucide-react"
import { cn } from "@/lib/utils"

const Sheet = DialogPrimitive.Root
const SheetTrigger = DialogPrimitive.Trigger
const SheetClose = DialogPrimitive.Close

function SheetOverlay({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Overlay>) {
  return (
    <DialogPrimitive.Overlay asChild {...props}>
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.18 }}
        className={cn("fixed inset-0 z-[60] bg-black/60 backdrop-blur-[2px]", className)}
      />
    </DialogPrimitive.Overlay>
  )
}

function SheetContent({
  className,
  side = "bottom",
  children,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & { side?: "bottom" | "right" }) {
  const isBottom = side === "bottom"
  return (
    <DialogPrimitive.Portal>
      <AnimatePresence>
        <SheetOverlay />
        <DialogPrimitive.Content {...props}>
          <motion.div
            initial={isBottom ? { y: "100%" } : { x: "100%" }}
            animate={isBottom ? { y: 0 } : { x: 0 }}
            exit={isBottom ? { y: "100%" } : { x: "100%" }}
            transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
            className={cn(
              "fixed z-[61] flex flex-col bg-card shadow-2xl",
              isBottom
                ? "inset-x-0 bottom-0 max-h-[88dvh] rounded-t-2xl border-t border-border"
                : "inset-y-0 right-0 h-full w-full max-w-sm border-l border-border",
              className,
            )}
          >
            {isBottom ? (
              <div className="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-border" />
            ) : null}
            {children}
            <DialogPrimitive.Close className="absolute right-4 top-4 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
              <X className="h-4 w-4" />
              <span className="sr-only">Tutup</span>
            </DialogPrimitive.Close>
          </motion.div>
        </DialogPrimitive.Content>
      </AnimatePresence>
    </DialogPrimitive.Portal>
  )
}

function SheetHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("flex flex-col space-y-1.5 px-5 pt-6", className)} {...props} />
}

function SheetTitle({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Title>) {
  return (
    <DialogPrimitive.Title
      className={cn("font-display text-lg font-semibold leading-none tracking-tight", className)}
      {...props}
    />
  )
}

function SheetDescription({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Description>) {
  return <DialogPrimitive.Description className={cn("text-sm text-muted-foreground", className)} {...props} />
}

function SheetBody({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("flex-1 overflow-y-auto px-5 py-4", className)} {...props} />
}

function SheetFooter({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn("safe-bottom flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row", className)}
      {...props}
    />
  )
}

export {
  Sheet,
  SheetTrigger,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetBody,
  SheetFooter,
}
