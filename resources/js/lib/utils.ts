import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatIDR(n: number | string | null | undefined): string {
  const num = Number(n ?? 0)
  return "Rp" + num.toLocaleString("id-ID", { maximumFractionDigits: 0 })
}

export function formatRupiah(n: number | string | null | undefined): string {
  return formatIDR(n)
}

export function formatCurrency(n: number | string | null | undefined): string {
  return formatIDR(n)
}

export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return "-"
  let p = String(phone).replace(/\D/g, "")
  if (p.startsWith("0")) p = "62" + p.slice(1)
  if (p.startsWith("62") && p.length >= 9) {
    return `+62 ${p.slice(2, 5)}-${p.slice(5, 9)}${p.length > 9 ? '-' + p.slice(9) : ''}`
  }
  return phone
}

export function periodLabel(p: string | null | undefined): string {
  if (!p) return "-"
  const str = String(p).trim()
  const m = str.match(/^(\d{4})-(\d{1,2})/)
  if (!m) return str
  const monthNames = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember"
  ]
  const monthIdx = parseInt(m[2], 10) - 1
  return `${monthNames[monthIdx] ?? m[2]} ${m[1]}`
}

/**
 * Format angka mentah → tampilan ribuan saat mengetik (10000 → "10.000").
 * Nilai yang disimpan tetap digit mentah (tanpa pemisah).
 */
export function formatPriceInput(v: string | number | null | undefined): string {
  const raw = String(v ?? "").replace(/\D/g, "").replace(/^0+(?=\d)/, "")
  if (!raw) return ""
  return Number(raw).toLocaleString("id-ID")
}

/** Balikkan nilai input harga → digit mentah (hapus pemisah ribuan & non-digit). */
export function stripPriceInput(v: string): string {
  return v.replace(/[^\d]/g, "").replace(/^0+(?=\d)/, "")
}

export function formatDate(date: string | null | undefined, withTime = false): string {
  if (!date) return "-"
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
    ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
  })
}

export function timeAgo(date: string | null | undefined): string {
  if (!date) return "-"
  const d = new Date(date)
  const diff = Date.now() - d.getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return "baru saja"
  if (mins < 60) return `${mins} menit lalu`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs} jam lalu`
  const days = Math.floor(hrs / 24)
  if (days < 30) return `${days} hari lalu`
  return formatDate(date)
}

/**
 * Uptime MikroTik → teks ramah awam.
 * Terima format RouterOS ("1w2d3h4m5s", "2h15m", "45s") ATAU detik mentah.
 * Contoh: "3d4h" → "3 hari 4 jam"; "1w2d" → "1 minggu 2 hari".
 */
export function formatUptime(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") return "-"
  let total = 0
  const s = String(value).trim()
  if (typeof value === "number" || /^\d+$/.test(s)) {
    total = typeof value === "number" ? value : Number(s)
  } else {
    const re = /(\d+)\s*([wdhms])/g
    const units: Record<string, number> = { w: 604800, d: 86400, h: 3600, m: 60, s: 1 }
    let m: RegExpExecArray | null
    while ((m = re.exec(s)) !== null) total += Number(m[1]) * (units[m[2]] ?? 0)
    if (total === 0) return s // format tidak dikenal → tampilkan mentah
  }
  const w = Math.floor(total / 604800)
  const d = Math.floor((total % 604800) / 86400)
  const h = Math.floor((total % 86400) / 3600)
  const mi = Math.floor((total % 3600) / 60)
  const sec = Math.floor(total % 60)
  const parts: string[] = []
  if (w > 0) parts.push(`${w} minggu`)
  if (d > 0) parts.push(`${d} hari`)
  if (h > 0 && parts.length < 2) parts.push(`${h} jam`)
  if (mi > 0 && parts.length < 2) parts.push(`${mi} mnt`)
  if (parts.length === 0) parts.push(`${sec} dtk`)
  return parts.join(" ")
}
