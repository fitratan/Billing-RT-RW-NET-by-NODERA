/**
 * Lightweight Haptic Feedback utility for native mobile touch feedback.
 */
export function triggerHaptic(style: "light" | "medium" | "heavy" | "selection" = "light"): void {
  if (typeof window !== "undefined" && "navigator" in window && "vibrate" in navigator) {
    try {
      if (style === "light") {
        navigator.vibrate(8)
      } else if (style === "selection") {
        navigator.vibrate(5)
      } else if (style === "medium") {
        navigator.vibrate(15)
      } else if (style === "heavy") {
        navigator.vibrate([20, 10, 20])
      }
    } catch {
      // Ignore browsers that block or don't support vibration
    }
  }
}
