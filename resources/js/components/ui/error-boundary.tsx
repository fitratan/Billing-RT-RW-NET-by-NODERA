import * as React from "react"
import { reportClientError } from "@/lib/client-error"
import { AlertTriangle, RefreshCw, Home } from "lucide-react"

interface Props {
  children: React.ReactNode
}

interface State {
  hasError: boolean
}

/** Tangkap error render React → kirim ke /client-error tanpa menampilkan raw stack trace ke pengguna. */
export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { hasError: false }

  static getDerivedStateFromError(): State {
    return { hasError: true }
  }

  componentDidCatch(error: Error, info: React.ErrorInfo): void {
    reportClientError(error.message || "React render exception", error.stack, info.componentStack ?? null)
  }

  handleReload = (): void => {
    window.location.reload()
  }

  handleGoHome = (): void => {
    window.location.href = "/dashboard"
  }

  render(): React.ReactNode {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-5 p-6 text-center bg-[#090B0E] text-slate-100 select-none">
          <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 shadow-xl shadow-rose-950/30">
            <AlertTriangle className="h-8 w-8" />
          </div>
          <div className="max-w-md space-y-2">
            <span className="inline-block px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-rose-500/10 border border-rose-500/20 text-rose-400 font-mono">
              Kendala Tampilan
            </span>
            <h1 className="text-lg font-bold text-white tracking-tight">Terjadi Kendala Sistem</h1>
            <p className="text-xs text-slate-400 leading-relaxed">
              Sistem mengalami kendala sementara saat memuat tampilan halaman ini. Laporan error telah otomatis dikirimkan ke tim teknis untuk segera ditangani.
            </p>
          </div>

          <div className="flex items-center gap-3 pt-2">
            <button
              onClick={this.handleReload}
              className="inline-flex items-center gap-2 h-10 rounded-xl bg-[#0073C6] hover:bg-[#0084E3] px-5 text-xs font-bold text-white transition-all active:scale-95"
            >
              <RefreshCw className="h-3.5 w-3.5" />
              <span>Muat Ulang Halaman</span>
            </button>
            <button
              onClick={this.handleGoHome}
              className="inline-flex items-center gap-2 h-10 rounded-xl border border-[#212B3B] bg-[#121720] hover:bg-[#18202C] hover:text-white px-4 text-xs font-semibold text-slate-300 transition-all active:scale-95"
            >
              <Home className="h-3.5 w-3.5" />
              <span>Kembali ke Beranda</span>
            </button>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}