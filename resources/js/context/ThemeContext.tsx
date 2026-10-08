"use client";

import type React from "react";
import { createContext, useState, useContext, useEffect } from "react";

type Theme = "light" | "dark";

type ThemeContextType = {
  theme: Theme;
  toggleTheme: () => void;
  setTheme: (theme: Theme) => void;
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [theme, setThemeState] = useState<Theme>("light");
  const [isInitialized, setIsInitialized] = useState(false);

  useEffect(() => {
    // Read persisted theme from localStorage or document class
    if (typeof window !== "undefined") {
      const savedTheme = localStorage.getItem("theme") as Theme | null;
      if (savedTheme === "light" || savedTheme === "dark") {
        setThemeState(savedTheme);
        if (savedTheme === "dark") {
          document.documentElement.classList.add("dark");
          document.documentElement.setAttribute("data-color-scheme", "dark");
        } else {
          document.documentElement.classList.remove("dark");
          document.documentElement.setAttribute("data-color-scheme", "light");
        }
      } else {
        const isDocDark = document.documentElement.classList.contains("dark");
        const defaultTheme: Theme = isDocDark ? "dark" : "light";
        setThemeState(defaultTheme);
      }
    }
    setIsInitialized(true);
  }, []);

  useEffect(() => {
    if (isInitialized && typeof window !== "undefined") {
      localStorage.setItem("theme", theme);
      if (theme === "dark") {
        document.documentElement.classList.add("dark");
        document.documentElement.setAttribute("data-color-scheme", "dark");
      } else {
        document.documentElement.classList.remove("dark");
        document.documentElement.setAttribute("data-color-scheme", "light");
      }
    }
  }, [theme, isInitialized]);

  const toggleTheme = () => {
    setThemeState((prevTheme) => {
      const nextTheme = prevTheme === "light" ? "dark" : "light";
      if (typeof window !== "undefined") {
        localStorage.setItem("theme", nextTheme);
        if (nextTheme === "dark") {
          document.documentElement.classList.add("dark");
          document.documentElement.setAttribute("data-color-scheme", "dark");
        } else {
          document.documentElement.classList.remove("dark");
          document.documentElement.setAttribute("data-color-scheme", "light");
        }
      }
      return nextTheme;
    });
  };

  const setTheme = (newTheme: Theme) => {
    setThemeState(newTheme);
    if (typeof window !== "undefined") {
      localStorage.setItem("theme", newTheme);
      if (newTheme === "dark") {
        document.documentElement.classList.add("dark");
        document.documentElement.setAttribute("data-color-scheme", "dark");
      } else {
        document.documentElement.classList.remove("dark");
        document.documentElement.setAttribute("data-color-scheme", "light");
      }
    }
  };

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme, setTheme }}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    const isClient = typeof window !== "undefined";
    const isDark = isClient && document.documentElement.classList.contains("dark");
    return {
      theme: (isDark ? "dark" : "light") as Theme,
      toggleTheme: () => {
        if (isClient) {
          const nextIsDark = !document.documentElement.classList.contains("dark");
          if (nextIsDark) {
            document.documentElement.classList.add("dark");
            document.documentElement.setAttribute("data-color-scheme", "dark");
            localStorage.setItem("theme", "dark");
          } else {
            document.documentElement.classList.remove("dark");
            document.documentElement.setAttribute("data-color-scheme", "light");
            localStorage.setItem("theme", "light");
          }
        }
      },
    };
  }
  return context;
};
