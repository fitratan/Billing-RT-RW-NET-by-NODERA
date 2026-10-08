import { Head, Link } from "@inertiajs/react"
import {
  FileText,
  Shield,
  ArrowLeft,
  AlertTriangle,
  Server,
  CreditCard,
  Lock,
  Scale,
  HelpCircle,
} from "lucide-react"
import { Button } from "@/components/ui/button"

interface TermsProps {
  company?: {
    name?: string
    phone_wa?: string
    email?: string
    address?: string
  }
  app_domain?: string
}

export default function Terms({ company }: TermsProps) {
  const companyName = company?.name || "NODERA INDONESIA"
  const supportEmail = company?.email || "support@nodera.id"
  const supportPhone = company?.phone_wa || "6285155173547"

  return (
    <div className="min-h-screen bg-[#060A0E] text-slate-100 selection:bg-[#00C2FF] selection:text-black">
      <Head title="Syarat dan Ketentuan Layanan - NODERA" />

      {/* Header */}
      <header className="sticky top-0 z-50 border-b border-[#1A222E]/80 bg-[#060A0E]/80 backdrop-blur-md">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
          <div className="flex items-center gap-3">
            <Link href="/" className="flex items-center gap-2.5 group">
              <img
                src="/images/logo-white.png?v=36"
                alt={companyName}
                className="h-8 w-auto object-contain transition-transform group-hover:scale-105"
                onError={(e) => {
                  ;(e.target as HTMLElement).style.display = "none"
                }}
              />
              <span className="font-display text-base font-black tracking-tight text-white group-hover:text-[#00C2FF] transition-colors">
                {companyName}
              </span>
            </Link>
          </div>

          <div className="flex items-center gap-2">
            <Link href="/">
              <Button variant="ghost" size="sm" className="text-xs text-slate-300 hover:text-white hover:bg-[#141A24]">
                <ArrowLeft className="mr-1.5 h-3.5 w-3.5" />
                Beranda
              </Button>
            </Link>
            <Link href="/register">
              <Button size="sm" className="bg-[#0073C6] hover:bg-[#0060A8] text-white text-xs font-bold shadow-sm">
                Daftar ISP
              </Button>
            </Link>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-16 space-y-10">
        {/* Title Hero */}
        <div className="space-y-4 text-center sm:text-left">
          <div className="inline-flex items-center gap-2 rounded-full border border-[#00C2FF]/30 bg-[#0073C6]/10 px-3.5 py-1 text-xs font-semibold text-[#00C2FF]">
            <Scale className="h-3.5 w-3.5" />
            <span>Dokumen Perjanjian Layanan</span>
          </div>
          <h1 className="font-display text-3xl sm:text-4xl font-black tracking-tight text-white">
            Syarat &amp; Ketentuan Layanan
          </h1>
          <p className="text-sm sm:text-base text-slate-400 max-w-2xl leading-relaxed">
            Harap membaca Syarat dan Ketentuan ini secara seksama sebelum menggunakan ekosistem platform manajemen ISP, billing hotspot &amp; PPPoE, layanan cloud, dan infrastruktur <strong className="text-slate-200">{companyName}</strong>.
          </p>
          <div className="flex flex-wrap items-center gap-4 pt-2 text-xs text-slate-500">
            <span>Terakhir diperbarui: {new Date().toLocaleDateString("id-ID", { year: "numeric", month: "long", day: "numeric" })}</span>
            <span>•</span>
            <span>Versi: 2.4 (Enterprise Production)</span>
          </div>
        </div>

        {/* Content Sections */}
        <div className="space-y-8 text-sm leading-relaxed text-slate-300">
          {/* Section 1 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <FileText className="h-5 w-5 text-[#00C2FF]" />
              <h2>1. Ketentuan Umum &amp; Penerimaan Perjanjian</h2>
            </div>
            <p>
              Dengan mendaftar, mengakses, atau menggunakan platform <strong className="text-white">{companyName}</strong> (selanjutnya disebut "Layanan"), Anda menyatakan bahwa Anda telah membaca, memahami, dan menyetujui untuk terikat dengan seluruh syarat dan ketentuan ini. Jika Anda tidak menyetujui salah satu poin dalam ketentuan ini, Anda tidak diperkenankan menggunakan Layanan kami.
            </p>
            <p>
              Layanan ini disediakan sebagai Software-as-a-Service (SaaS) dan alat bantu otomatisasi manajemen penagihan (billing), voucher hotspot, koneksi PPPoE, monitoring OLT/ONT, server Mikhmon Online, serta VPN Remote untuk pengusaha dan instansi penyedia jasa internet (ISP/RT-RW Net).
            </p>
          </section>

          {/* Section 2 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Lock className="h-5 w-5 text-[#00C2FF]" />
              <h2>2. Akun Pengguna &amp; Keamanan Kredensial</h2>
            </div>
            <ul className="list-disc pl-5 space-y-2 text-slate-300">
              <li>
                <strong className="text-slate-100">Kewajiban Pengguna:</strong> Anda bertanggung jawab penuh untuk menjaga kerahasiaan username, password, token API, dan akses panel administratif Anda.
              </li>
              <li>
                <strong className="text-slate-100">Akses Router MikroTik &amp; OLT:</strong> Seluruh konfigurasi router, IP public, domain VPN, serta port API yang dimasukkan ke dalam sistem adalah tanggung jawab eksklusif pengelola instansi/ISP. Anda wajib menerapkan praktik keamanan jaringan yang baik (seperti tidak menggunakan password default admin/kosong).
              </li>
              <li>
                <strong className="text-slate-100">Penyalahgunaan Akun:</strong> Setiap aktivitas yang terjadi di bawah akun Anda dianggap sebagai tindakan sah dari Anda. {companyName} tidak bertanggung jawab atas kehilangan atau kerusakan yang diakibatkan oleh kelalaian dalam menjaga keamanan kredensial.
              </li>
            </ul>
          </section>

          {/* Section 3 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <CreditCard className="h-5 w-5 text-[#00C2FF]" />
              <h2>3. Biaya Layanan, Topup Saldo &amp; Pembayaran</h2>
            </div>
            <ul className="list-disc pl-5 space-y-2 text-slate-300">
              <li>
                <strong className="text-slate-100">Aktivasi Awal:</strong> Pendaftaran instansi/ISP baru dikenakan biaya aktivasi/paket langganan awal sesuai paket yang dipilih, diproses melalui gerbang pembayaran resmi platform (QRIS Realtime, Virtual Account, atau Bank).
              </li>
              <li>
                <strong className="text-slate-100">Sistem Saldo Panel (Deposit Wallet):</strong> Seluruh perpanjangan sewa rutin, pembelian add-on modul, sewa cloud Mikhmon/GenieACS, dan server VPN dieksekusi melalui pemotongan saldo deposit panel masing-masing admin.
              </li>
              <li>
                <strong className="text-slate-100">Kebijakan Pengembalian Dana (No Refund):</strong> Seluruh pembayaran registrasi dan deposit saldo yang telah berhasil diverifikasi bersifat final dan tidak dapat dikembalikan (non-refundable), kecuali terdapat kendala sistemik fatal yang tidak dapat diselesaikan oleh tim teknis kami dalam batas waktu wajar.
              </li>
              <li>
                <strong className="text-slate-100">Keterlambatan Pembayaran:</strong> Kegagalan memperpanjang langganan setelah tanggal jatuh tempo dapat mengakibatkan pembekuan otomatis (suspension) sementara terhadap akses panel dan sinkronisasi router hingga tagihan diselesaikan.
              </li>
            </ul>
          </section>

          {/* Section 4 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Server className="h-5 w-5 text-[#00C2FF]" />
              <h2>4. Ketersediaan Layanan (Uptime &amp; SLA)</h2>
            </div>
            <p>
              Kami berkomitmen untuk menjaga ketersediaan infrastruktur cloud dan server platform dengan target Uptime 99.5% per bulan. Namun demikian, ketersediaan dapat terpengaruh oleh:
            </p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>Jadwal pemeliharaan berkala (maintenance window) yang akan diinformasikan sebelumnya.</li>
              <li>Gangguan jaringan upstream global, kabel laut, atau bencana di luar kendali wajar (Force Majeure).</li>
              <li>Gangguan konektivitas pada ISP lokal atau perangkat fisik di lokasi router/pelanggan Anda.</li>
            </ul>
          </section>

          {/* Section 5 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <AlertTriangle className="h-5 w-5 text-amber-400" />
              <h2>5. Larangan Penggunaan (Acceptable Use Policy)</h2>
            </div>
            <p>Anda dilarang keras menggunakan Layanan {companyName} untuk:</p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>Menjalankan aktivitas ilegal yang melanggar hukum dan peraturan perundang-undangan Republik Indonesia.</li>
              <li>Melakukan penipuan pembayaran, manipulasi saldo, atau eksploitasi celah keamanan sistem.</li>
              <li>Melakukan serangan Denial-of-Service (DoS/DDoS), port scanning tanpa izin, atau penyebaran malware melalui VPN Remote.</li>
              <li>Mengirimkan pesan spam massal tanpa izin melalui integrasi WhatsApp Gateway atau Bot Telegram.</li>
              <li>Melakukan pembajakan, penggandaan tidak sah, atau reverse engineering terhadap source code platform.</li>
            </ul>
          </section>

          {/* Section 6 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Shield className="h-5 w-5 text-[#00C2FF]" />
              <h2>6. Batasan Tanggung Jawab</h2>
            </div>
            <p>
              Platform disediakan atas dasar "sebagaimana adanya" (as is) dan "sebagaimana tersedia" (as available). {companyName} tidak bertanggung jawab atas segala kerugian tidak langsung, kehilangan keuntungan bisnis, gangguan transaksi pelanggan akhir Anda, atau kerusakan data router yang diakibatkan oleh salah konfigurasi perintah skrip di luar panduan resmi kami.
            </p>
          </section>

          {/* Section 7 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <HelpCircle className="h-5 w-5 text-[#00C2FF]" />
              <h2>7. Kontak &amp; Informasi Bantuan</h2>
            </div>
            <p>
              Apabila Anda memiliki pertanyaan, keluhan, atau memerlukan klarifikasi terkait Syarat dan Ketentuan Layanan ini, silakan hubungi tim kami melalui:
            </p>
            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div className="rounded-xl border border-[#1A222E] bg-[#141A24] p-3.5 space-y-1">
                <span className="text-xs text-slate-400">WhatsApp Support:</span>
                <p className="text-sm font-bold text-white">
                  <a href={`https://wa.me/${supportPhone}`} target="_blank" rel="noreferrer" className="text-[#00C2FF] hover:underline">
                    +{supportPhone}
                  </a>
                </p>
              </div>
              <div className="rounded-xl border border-[#1A222E] bg-[#141A24] p-3.5 space-y-1">
                <span className="text-xs text-slate-400">Email Korespondensi:</span>
                <p className="text-sm font-bold text-white">
                  <a href={`mailto:${supportEmail}`} className="text-[#00C2FF] hover:underline">
                    {supportEmail}
                  </a>
                </p>
              </div>
            </div>
          </section>
        </div>

        {/* Footer Navigation */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-[#1A222E] pt-8 text-xs text-slate-400">
          <p>&copy; {new Date().getFullYear()} {companyName}. Hak Cipta Dilindungi.</p>
          <div className="flex items-center gap-4">
            <Link href="/privacy" className="text-[#00C2FF] hover:underline">
              Kebijakan Privasi
            </Link>
            <span>•</span>
            <Link href="/register" className="text-[#00C2FF] hover:underline">
              Pendaftaran ISP
            </Link>
            <span>•</span>
            <Link href="/login" className="text-[#00C2FF] hover:underline">
              Masuk Akun
            </Link>
          </div>
        </div>
      </main>
    </div>
  )
}
