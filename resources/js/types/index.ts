import type { PageProps as InertiaPageProps } from "@inertiajs/core"

export interface AuthUser {
  id: number
  name: string
  email: string
  role?: string | null
}

export interface SharedFlash {
  msg?: string | null
  error?: string | null
  info?: string | null
  warning?: string | null
}

export interface SharedData {
  auth: { user: AuthUser | null }
  flash: SharedFlash
  [key: string]: unknown
}

export type PageProps<T = Record<string, unknown>> = InertiaPageProps & SharedData & T
