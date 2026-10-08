export type Theme = "light" | "dark"

const STORAGE_KEY = "theme"

export function getInitialTheme(): Theme {
  if (typeof window !== "undefined") {
    try {
      const saved = window.localStorage.getItem(STORAGE_KEY) as Theme | null
      if (saved === "light" || saved === "dark") return saved
      if (document.documentElement.classList.contains("dark")) return "dark"
    } catch (e) {}
  }
  return "light"
}

export function applyTheme(theme: Theme) {
  if (typeof document === "undefined") return
  const root = document.documentElement
  if (theme === "dark") {
    root.classList.add("dark")
    root.classList.remove("light")
    root.style.colorScheme = "dark"
    const meta = document.querySelector('meta[name="theme-color"]')
    if (meta) meta.setAttribute("content", "#090B0E")
  } else {
    root.classList.remove("dark")
    root.classList.add("light")
    root.style.colorScheme = "light"
    const meta = document.querySelector('meta[name="theme-color"]')
    if (meta) meta.setAttribute("content", "#FFFFFF")
  }
}

export function persistTheme(theme: Theme) {
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(STORAGE_KEY, theme)
    } catch (e) {}
  }
}

export function syncAccentFromStorage() {
  const theme = getInitialTheme()
  applyTheme(theme)
}
