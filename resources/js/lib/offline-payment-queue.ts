/**
 * Offline Payment Queue helper (IndexedDB / LocalStorage)
 * Menyimpan draft transaksi pembayaran Kolektor saat offline / area blank spot,
 * lalu otomatis mengunggah (auto-sync) ke server Nodera saat HP online kembali.
 */

export interface OfflinePayment {
  id: string
  customerId: number
  customerName: string
  periods: string[]
  amount: number
  createdAt: string
}

const STORAGE_KEY = "nodera_offline_payments"

export function getOfflinePayments(): OfflinePayment[] {
  if (typeof window === "undefined") return []
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch (e) {
    return []
  }
}

export function saveOfflinePayment(payment: Omit<OfflinePayment, "id" | "createdAt">): OfflinePayment {
  const list = getOfflinePayments()
  const newRecord: OfflinePayment = {
    ...payment,
    id: "off_" + Date.now() + "_" + Math.random().toString(36).substring(2, 6),
    createdAt: new Date().toISOString(),
  }
  list.push(newRecord)
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list))
  } catch (e) {}
  return newRecord
}

export function removeOfflinePayment(id: string) {
  const list = getOfflinePayments().filter((p) => p.id !== id)
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list))
  } catch (e) {}
}

export async function syncOfflinePayments(
  sendFn: (payment: OfflinePayment) => Promise<boolean>
): Promise<number> {
  const list = getOfflinePayments()
  if (list.length === 0) return 0

  let syncedCount = 0
  for (const item of list) {
    try {
      const ok = await sendFn(item)
      if (ok) {
        removeOfflinePayment(item.id)
        syncedCount++
      }
    } catch (e) {
      console.error("Auto-sync error for item:", item, e)
    }
  }
  return syncedCount
}
