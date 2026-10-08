import { AppLayout } from "@/components/layout/app-layout"
import { ShieldCheck } from "lucide-react"
import { PageProps } from "@/types"
import { portalNavItems, portalBrand } from "@/lib/portal-nav"

export default function PortalTosPage({
  companyName = "NODERA",
  customer,
}: PageProps<{
  companyName?: string
  customer?: any
}>) {
  return (
    <AppLayout
      title="Syarat & Ketentuan"
      subtitle={companyName}
      brand={portalBrand}
      navItems={portalNavItems}
      hideSidebar={true}
    >
      <div className="space-y-4 max-w-4xl mx-auto pb-32 sm:pb-20">
        <div className="rounded-2xl border border-gray-200/80 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-sm font-bold text-gray-900 dark:text-white">
                Ketentuan Berlangganan {companyName}
              </h2>
              <p className="text-[10px] text-gray-400">
                Pembaruan: Oktober 2026
              </p>
            </div>
          </div>

          <div className="space-y-3 text-xs text-gray-600 dark:text-gray-300 leading-relaxed divide-y divide-gray-100 dark:divide-gray-800/80">
            <div className="space-y-1.5 pt-2 first:pt-0">
              <h3 className="font-bold text-gray-900 dark:text-white">1. Ketentuan Pembayaran</h3>
              <p className="text-[11px]">
                Pelanggan wajib melakukan pembayaran iuran bulanan sebelum tanggal jatuh tempo yang tertera pada invoice. Keterlambatan dapat mengakibatkan isolasi layanan otomatis.
              </p>
            </div>

            <div className="space-y-1.5 pt-3">
              <h3 className="font-bold text-gray-900 dark:text-white">2. Penggunaan Wajar (FUP)</h3>
              <p className="text-[11px]">
                Layanan diperuntukkan untuk kebutuhan rumah tangga/kantor sesuai paket. Dilarang menyebarluaskan atau menjual kembali tanpa izin tertulis.
              </p>
            </div>

            <div className="space-y-1.5 pt-3">
              <h3 className="font-bold text-gray-900 dark:text-white">3. Pemeliharaan Modem ONT</h3>
              <p className="text-[11px]">
                Perangkat modem (ONT) yang dipinjamkan wajib dirawat dengan baik. Kerusakan akibat kelalaian fisik atau petir menjadi tanggung jawab pelanggan.
              </p>
            </div>

            <div className="space-y-1.5 pt-3">
              <h3 className="font-bold text-gray-900 dark:text-white">4. Layanan Bantuan</h3>
              <p className="text-[11px]">
                Jika terjadi kendala koneksi atau gangguan jaringan, pelanggan dapat membuat tiket laporan melalui menu Bantuan di portal ini.
              </p>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}
