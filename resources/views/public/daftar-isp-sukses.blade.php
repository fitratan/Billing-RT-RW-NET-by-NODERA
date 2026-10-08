<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permohonan Terkirim — {{ $company['name'] ?? 'NODERA' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-grid {
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        @keyframes pulse-glow {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.15); opacity: 0.8; }
        }
        .animate-pulse-glow {
            animation: pulse-glow 3s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden bg-grid">
    <!-- Ambient Background Glows -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-emerald-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Main Card -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl shadow-black/50 text-center relative overflow-hidden">
            <!-- Top Gradient Bar -->
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-500 via-emerald-500 to-indigo-500"></div>

            <!-- Check Icon Container -->
            <div class="relative mx-auto w-20 h-20 mb-6 flex items-center justify-center">
                <div class="absolute inset-0 bg-emerald-500/20 rounded-full blur-xl animate-pulse-glow"></div>
                <div class="w-20 h-20 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 relative z-10 shadow-lg shadow-emerald-500/10">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>

            <!-- Header Text -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                Permohonan Berhasil Terkirim
            </span>

            <h1 class="text-2xl font-bold tracking-tight text-white mb-2.5">
                Pendaftaran Diterima!
            </h1>

            <p class="text-slate-400 text-sm leading-relaxed mb-6">
                Terima kasih telah mendaftar di <strong class="text-slate-200 font-semibold">{{ $company['name'] ?? 'NODERA' }}</strong>. Silakan lakukan pembayaran, lalu kirim <strong class="text-emerald-400">bukti transfer</strong> beserta <strong class="text-slate-200">nama & subdomain</strong> Anda ke WhatsApp kami untuk konfirmasi pengaktifan.
            </p>

            <!-- Subdomain Card -->
            @if(isset($slug))
            @php
                $domain = isset($app_domain) && !empty($app_domain) ? $app_domain : request()->getHost();
            @endphp
            <div class="bg-slate-950/70 border border-slate-800/80 rounded-2xl p-4 mb-6 text-left relative group hover:border-blue-500/40 transition-colors">
                <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase mb-1">
                    Subdomain Panel Anda
                </p>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-sm font-semibold text-blue-400 truncate">
                        https://{{ $slug }}.{{ $domain }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20 shrink-0">
                        Menunggu Aktif
                    </span>
                </div>
            </div>
            @endif

            <!-- WhatsApp Action Button -->
            @php $phone = \App\Models\Setting::getValue('COMPANY_PHONE', '6281234567890'); @endphp
            <div class="space-y-3">
                <a href="https://wa.me/{{ $phone }}?text=Halo%20Admin%2C%20saya%20sudah%20mendaftar%20di%20{{ urlencode($company['name'] ?? 'NODERA') }}%20dengan%20subdomain%20*{{ $slug ?? '' }}*.%0A%0ABerikut%20saya%20lampirkan%20bukti%20transfer%20pembayaran.%20Mohon%20bantu%20proses%20pengaktifan%20akun%20saya.%20Terima%20kasih%F0%9F%99%8F" 
                   target="_blank" 
                   class="w-full inline-flex items-center justify-center gap-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm py-3.5 px-6 rounded-xl transition-all duration-200 shadow-lg shadow-emerald-600/25 active:scale-[0.98]">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-1.156 4.22 4.103-1.077z"/>
                    </svg>
                    <span>Kirim Bukti Transfer via WhatsApp</span>
                </a>

                <a href="/login" class="w-full inline-flex items-center justify-center gap-2 bg-slate-800/80 hover:bg-slate-700/80 text-slate-300 hover:text-white font-medium text-sm py-3 px-6 rounded-xl transition-all duration-200 border border-slate-700/60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <span>Kembali ke Halaman Login</span>
                </a>
            </div>
        </div>

        <!-- Footer Info -->
        <p class="text-center text-xs text-slate-500 mt-6">
            &copy; {{ date('Y') }} {{ $company['name'] ?? 'NODERA' }}. All rights reserved.
        </p>
    </div>
</body>
</html>
