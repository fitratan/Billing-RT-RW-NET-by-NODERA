<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Koneksi Terputus — {{ $subscription->business_name }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        emerald: {
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 text-slate-100 bg-slate-950 antialiased">
    <div class="w-full max-w-md text-center">
        <!-- Brand Header -->
        <div class="mb-8">
            <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-xs text-slate-400">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span>Mode Offline</span>
            </div>
            <h1 class="text-xl font-bold text-white mt-3">{{ $subscription->business_name }}</h1>
        </div>

        <!-- Offline Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <!-- Signal Icon -->
            <div class="w-20 h-20 mx-auto rounded-2xl bg-gradient-to-br from-rose-500/20 to-amber-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center mb-6 shadow-inner">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 01-2.828-6.364m2.828 6.364L3 3m2.828 2.828A9 9 0 0112 3a9 9 0 016.364 2.636M9.172 9.172a5 5 0 015.656 0M12 18h.01" />
                </svg>
            </div>

            <h2 class="text-xl font-bold text-white mb-2">Koneksi Internet Terputus</h2>
            <p class="text-sm text-slate-400 leading-relaxed mb-8">
                Perangkat Anda tidak terhubung ke jaringan internet. Silakan periksa koneksi data seluler atau Wi-Fi Anda, lalu muat ulang halaman.
            </p>

            <div class="space-y-3">
                <button type="button" onclick="window.location.reload()"
                    class="w-full flex items-center justify-center py-3.5 px-4 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 transition-colors shadow-lg shadow-emerald-600/20">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Coba Muat Ulang</span>
                </button>

                <a href="/arisan-app/{{ $subdomain }}/member/login"
                    class="w-full flex items-center justify-center py-3.5 px-4 rounded-xl text-sm font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 active:bg-slate-600 transition-colors border border-slate-700">
                    <span>Kembali ke Halaman Utama</span>
                </a>
            </div>
        </div>

        <div class="mt-8 text-xs text-slate-500">
            Pembukuan Arisan Digital &bull; Tersimpan di Perangkat
        </div>
    </div>
</body>
</html>
