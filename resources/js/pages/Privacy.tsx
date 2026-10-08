import { Head, Link } from "@inertiajs/react"
import {
  ShieldCheck,
  ArrowLeft,
  Database,
  KeyRound,
  Eye,
  HelpCircle,
  HardDrive,
  Lock,
} from "lucide-react"
import { Button } from "@/components/ui/button"

interface PrivacyProps {
  company?: {
    name?: string
    phone_wa?: string
    email?: string
    address?: string
  }
  app_domain?: string
}

export default function Privacy({ company }: PrivacyProps) {
  const companyName = company?.name || "NODERA INDONESIA"
  const supportEmail = company?.email || "support@nodera.id"
  const supportPhone = company?.phone_wa || "6285155173547"

  return (
    <div className="min-h-screen bg-[#060A0E] text-slate-100 selection:bg-[#00C2FF] selection:text-black">
      <Head title="Kebijakan Privasi & Perlindungan Data - NODERA" />

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
          <div className="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-1 text-xs font-semibold text-emerald-400">
            <ShieldCheck className="h-3.5 w-3.5" />
            <span>Perlindungan Privasi &amp; UU PDP</span>
          </div>
          <h1 className="font-display text-3xl sm:text-4xl font-black tracking-tight text-white">
            Kebijakan Privasi
          </h1>
          <p className="text-sm sm:text-base text-slate-400 max-w-2xl leading-relaxed">
            Komitmen kami dalam menjaga kerahasiaan data pribadi, kredensial router, dan privasi pelanggan Anda di platform <strong className="text-slate-200">{companyName}</strong>.
          </p>
          <div className="flex flex-wrap items-center gap-4 pt-2 text-xs text-slate-500">
            <span>Berlaku efektif sejak: {new Date().toLocaleDateString("id-ID", { year: "numeric", month: "long", day: "numeric" })}</span>
            <span>•</span>
            <span>Kepatuhan UU No. 27 Tahun 2022 (UU PDP)</span>
          </div>
        </div>

        {/* Content Sections */}
        <div className="space-y-8 text-sm leading-relaxed text-slate-300">
          {/* Section 1 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Eye className="h-5 w-5 text-[#00C2FF]" />
              <h2>1. Informasi yang Kami Kumpulkan</h2>
            </div>
            <p>Untuk menyediakan layanan penagihan ISP dan otomatisasi jaringan secara optimal, kami mengumpulkan jenis data berikut:</p>
            <ul className="list-disc pl-5 space-y-2 text-slate-300">
              <li>
                <strong className="text-slate-100">Data Identitas Akun Instansi/ISP:</strong> Nama lengkap, nama instansi/brand ISP, alamat email, nomor telepon/WhatsApp, dan subdomain registrasi.
              </li>
              <li>
                <strong className="text-slate-100">Data Pembayaran &amp; Transaksi:</strong> Nomor tagihan, nominal pembayaran, metode bayar (QRIS/VA), status verifikasi bank, dan riwayat mutasi saldo deposit panel. (Catatan: Kami tidak pernah menyimpan data PIN atau kredensial rahasia perbankan Anda).
              </li>
              <li>
                <strong className="text-slate-100">Data Konfigurasi Jaringan &amp; Router:</strong> Alamat IP, port API router, hostname VPN Remote, serta nama profile paket yang Anda daftarkan di dashboard sistem.
              </li>
              <li>
                <strong className="text-slate-100">Log Teknis &amp; Audit Sistem:</strong> Riwayat akses login admin, alamat IP, timestamp aktivitas sistem, dan status pengiriman pesan notifikasi WhatsApp/Telegram.
              </li>
            </ul>
          </section>

          {/* Section 2 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Database className="h-5 w-5 text-[#00C2FF]" />
              <h2>2. Penggunaan Informasi</h2>
            </div>
            <p>Data yang dikumpulkan hanya digunakan untuk tujuan:</p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>Mengaktifkan dan memelihara akun instansi ISP Anda.</li>
              <li>Memproses penerbitan faktur tagihan langganan, verifikasi pembayaran otomatis QRIS/VA, dan pengelolaan saldo.</li>
              <li>Menghubungkan layanan automasi ke router MikroTik/OLT serta server Mikhmon &amp; GenieACS Anda.</li>
              <li>Mengirimkan laporan penagihan, pemberitahuan pemeliharaan, serta tiket bantuan teknis.</li>
              <li>Mendeteksi, mencegah, dan menangani insiden keamanan serta upaya penyalahgunaan sistem.</li>
            </ul>
          </section>

          {/* Section 3 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <Lock className="h-5 w-5 text-[#00C2FF]" />
              <h2>3. Keamanan Data &amp; Partisi Terisolasi</h2>
            </div>
            <p>
              Kami menerapkan standar keamanan informasi dan arsitektur database terisolasi untuk memastikan data Anda terlindungi:
            </p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>
                <strong className="text-slate-100">Enkripsi Data:</strong> Komunikasi web diproteksi enkripsi TLS/SSL (HTTPS) 256-bit dan password disimpan menggunakan algoritma hashing standar industri (Bcrypt/Argon2).
              </li>
              <li>
                <strong className="text-slate-100">Partisi Data Mandiri:</strong> Setiap instansi ISP memiliki ruang lingkup basis data terisolasi secara independen, memastikan data pelanggan dan konfigurasi router Anda sepenuhnya privat dan tidak dapat diakses oleh pihak manapun.
              </li>
              <li>
                <strong className="text-slate-100">Kerahasiaan Bot &amp; WhatsApp:</strong> Kunci sesi Bot Telegram dan WhatsApp Gateway disimpan secara privat di direktori aman instansi masing-masing dan tidak dibagikan ke entitas luar.
              </li>
            </ul>
          </section>

          {/* Section 4 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <KeyRound className="h-5 w-5 text-[#00C2FF]" />
              <h2>4. Pembagian Data kepada Pihak Ketiga</h2>
            </div>
            <p>
              <strong className="text-white">Kami tidak pernah menjual, menyewakan, atau memperdagangkan data pribadi Anda kepada pihak manapun untuk tujuan periklanan atau pemasaran.</strong>
            </p>
            <p>Data hanya dibagikan kepada mitra pihak ketiga terbatas yang sangat esensial untuk beroperasinya Layanan, seperti:</p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>Penyedia Payment Gateway berizin resmi Bank Indonesia (untuk memproses pembuatan invoice QRIS dan Virtual Account).</li>
              <li>Penyedia Infrastruktur Cloud Server VPS (untuk penempatan server komputasi berkeandalan tinggi).</li>
              <li>Otoritas Penegak Hukum yang berwenang, apabila diwajibkan secara sah oleh perintah pengadilan atau hukum Republik Indonesia.</li>
            </ul>
          </section>

          {/* Section 5 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <HardDrive className="h-5 w-5 text-[#00C2FF]" />
              <h2>5. Hak Pengguna &amp; Retensi Data</h2>
            </div>
            <p>Sesuai dengan Undang-Undang Pelindungan Data Pribadi (UU PDP), Anda berhak untuk:</p>
            <ul className="list-disc pl-5 space-y-1.5 text-slate-300">
              <li>Mengakses dan memperbarui data profil akun serta kontak instansi Anda kapan saja melalui dashboard.</li>
              <li>Meminta ekspor cadangan data (backup database) pelanggan dan transaksi Anda.</li>
              <li>Mengajukan permohonan penghapusan data akun (data deletion) jika Anda memutuskan berhenti berlangganan Layanan kami secara permanen.</li>
            </ul>
          </section>

          {/* Section 6 */}
          <section className="rounded-2xl border border-[#1A222E] bg-[#0E131B] p-6 sm:p-8 space-y-3">
            <div className="flex items-center gap-2.5 text-base font-bold text-white border-b border-[#1A222E] pb-3">
              <HelpCircle className="h-5 w-5 text-[#00C2FF]" />
              <h2>6. Hubungi Petugas Perlindungan Data</h2>
            </div>
            <p>
              Jika Anda memiliki pertanyaan tentang Kebijakan Privasi ini atau ingin mengajukan hak privasi data Anda, hubungi kami melalui:
            </p>
            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div className="rounded-xl border border-[#1A222E] bg-[#141A24] p-3.5 space-y-1">
                <span className="text-xs text-slate-400">Dukungan Privasi (WhatsApp):</span>
                <p className="text-sm font-bold text-white">
                  <a href={`https://wa.me/${supportPhone}`} target="_blank" rel="noreferrer" className="text-[#00C2FF] hover:underline">
                    +{supportPhone}
                  </a>
                </p>
              </div>
              <div className="rounded-xl border border-[#1A222E] bg-[#141A24] p-3.5 space-y-1">
                <span className="text-xs text-slate-400">Email Perlindungan Data:</span>
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
            <Link href="/terms" className="text-[#00C2FF] hover:underline">
              Syarat &amp; Ketentuan
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
