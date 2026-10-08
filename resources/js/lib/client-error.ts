// Kirim error client (React error boundary / uncaught exception / unhandled
// rejection) ke server untuk dilihat superadmin di Superadmin > Log Error.
// Didesain ringan & tidak pernah mengganggu pengalaman user.

let lastSent = 0
let sending = false

type Payload = {
  level: string
  message: string
  stack?: string | null
  source?: string | null
  url?: string
}

function send(payload: Payload) {
  const now = Date.now()
  // Throttle: maksimal 1 request per 2 detik supaya error loop tidak banjir.
  if (now - lastSent < 2000 || sending) return
  lastSent = now
  sending = true

  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""

  fetch("/client-error", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": meta,
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({ ...payload, url: window.location.href }),
  })
    .catch(() => {})
    .finally(() => {
      sending = false
    })
}

export function reportClientError(message: string, stack?: string | null, source?: string | null) {
  const msg = String(message || "").trim()
  const fullContext = (msg + " " + (stack || "") + " " + (source || "")).toLowerCase()

  // Filter out non-actionable browser cross-origin script error, transient network drops, chunk load retries, and layout observer noise
  if (!msg || (msg === "Script error." && !stack)) return
  if (msg.includes("ResizeObserver loop")) return
  if (
    msg === "pa" ||
    msg === "Error: pa" ||
    fullContext.includes("evaluating 'a.i'") ||
    fullContext.includes("evaluating \"a.i\"") ||
    fullContext.includes("evaluating 'a.i')") ||
    fullContext.includes("challenges.cloudflare.com") ||
    fullContext.includes("turnstile") ||
    fullContext.includes("maximum call stack size exceeded") ||
    fullContext.includes("chrome-extension://") ||
    fullContext.includes("safari-extension://") ||
    fullContext.includes("moz-extension://") ||
    msg.includes("HttpNetworkError") ||
    msg.includes("Network error") ||
    msg.includes("NetworkError") ||
    msg.includes("Failed to fetch") ||
    msg.includes("AbortError") ||
    msg.includes("Load failed") ||
    msg.includes("user cancelled") ||
    msg.includes("canceled") ||
    msg.includes("Network request failed") ||
    msg.includes("dynamically imported module") ||
    msg.includes("Loading chunk") ||
    msg.includes("Module chunk returned empty") ||
    msg.includes("stale_chunk")
  ) {
    return
  }

  send({
    level: "error",
    message: msg.slice(0, 1000),
    stack: stack ? String(stack).slice(0, 20000) : null,
    source: source ? String(source).slice(0, 255) : null,
  })
}

export function reportClientWarning(message: string, stack?: string | null, source?: string | null) {
  const msg = String(message || "").trim()
  const fullContext = (msg + " " + (stack || "") + " " + (source || "")).toLowerCase()

  if (!msg || (msg === "Script error." && !stack)) return
  if (msg.includes("ResizeObserver loop")) return
  if (
    msg === "pa" ||
    msg === "Error: pa" ||
    fullContext.includes("challenges.cloudflare.com") ||
    fullContext.includes("turnstile") ||
    fullContext.includes("maximum call stack size exceeded") ||
    fullContext.includes("chrome-extension://") ||
    fullContext.includes("safari-extension://") ||
    fullContext.includes("moz-extension://") ||
    msg.includes("HttpNetworkError") ||
    msg.includes("Network error") ||
    msg.includes("NetworkError") ||
    msg.includes("Failed to fetch") ||
    msg.includes("AbortError") ||
    msg.includes("Load failed") ||
    msg.includes("user cancelled") ||
    msg.includes("canceled") ||
    msg.includes("Network request failed") ||
    msg.includes("dynamically imported module") ||
    msg.includes("Loading chunk") ||
    msg.includes("Module chunk returned empty") ||
    msg.includes("stale_chunk")
  ) {
    return
  }

  send({
    level: "warning",
    message: msg.slice(0, 1000),
    stack: stack ? String(stack).slice(0, 20000) : null,
    source: source ? String(source).slice(0, 255) : null,
  })
}

// Pasang global handler: uncaught exception & unhandled promise rejection.
export function installClientErrorReporting() {
  if (typeof window === "undefined") return
  if ((window as any).__noderaErrorReportingInstalled) return
  ;(window as any).__noderaErrorReportingInstalled = true

  window.addEventListener("error", (event) => {
    reportClientError(
      event.message || "Uncaught error",
      event.error?.stack || null,
      event.filename || null
    )
  })

  window.addEventListener("unhandledrejection", (event) => {
    const reason: any = event.reason
    reportClientError(
      reason?.message || String(reason) || "Unhandled rejection",
      reason?.stack || null,
      null
    )
  })
}
