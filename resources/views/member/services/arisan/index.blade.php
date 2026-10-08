<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembukuan Arisan PWA — NODERA Member</title>
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
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        amber: {
                            500: '#f59e0b',
                            600: '#d97706',
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
<body class="min-h-full flex flex-col text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 antialiased">
    <!-- Header Navigation -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="/dashboard" class="flex items-center space-x-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="text-sm font-medium hidden sm:inline">Kembali</span>
                </a>
                <div class="h-5 w-px bg-slate-200 dark:bg-slate-800"></div>
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Pembukuan Arisan</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Kelola Aplikasi & Langganan Arisan PWA</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Saldo Akun</span>
                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($user->total_saldo ?? $user->saldo, 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('member.arisan.order') }}" class="inline-flex items-center justify-center px-3.5 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Pesan Arisan Baru</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-sm text-emerald-800 dark:text-emerald-200 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-sm text-rose-800 dark:text-rose-200 font-medium">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Aplikasi</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $subscriptions->count() }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">Instansi Pembukuan Arisan terdaftar</p>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status Aktif</p>
                        <h3 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {{ $subscriptions->where('status', 'ACTIVE')->count() }}
                        </h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">Aplikasi siap diakses kapan saja</p>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Biaya Langganan</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                            Rp {{ number_format($monthlyPrice, 0, ',', '.') }}
                        </h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-500 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">Tarif flat per bulan / subdomain</p>
            </div>
        </div>

        <!-- Subscriptions List Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Aplikasi Arisan Anda</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $subscriptions->count() }} Terdaftar</span>
            </div>

            @forelse ($subscriptions as $sub)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $sub->business_name }}</h3>
                                @if ($sub->status === 'ACTIVE' && !$sub->isExpired())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        Aktif
                                    </span>
                                @elseif ($sub->isExpired() || $sub->status === 'EXPIRED')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        Kedaluwarsa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        {{ $sub->status }}
                                    </span>
                                @endif

                                @if ($sub->auto_renew)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        Auto Renew Aktif
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-500 dark:text-slate-400">
                                <div class="flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                    </svg>
                                    <span class="font-mono text-slate-700 dark:text-slate-300 font-medium">{{ $sub->subdomain }}</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Berlaku hingga: <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ $sub->expires_at ? $sub->expires_at->format('d M Y H:i') : '-' }}</strong></span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>{{ $sub->groups_count ?? 0 }} Kloter &bull; {{ $sub->members_count ?? 0 }} Anggota</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center gap-2.5 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100 dark:border-slate-800">
                            <!-- Toggle Auto Renew Form -->
                            <form action="{{ route('member.arisan.toggle-auto-renew', $sub) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition-colors" title="Nyalakan/Matikan auto-debit saldo bulanan">
                                    <svg class="w-3.5 h-3.5 mr-1.5 {{ $sub->auto_renew ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Auto Renew: <strong>{{ $sub->auto_renew ? 'ON' : 'OFF' }}</strong></span>
                                </button>
                            </form>

                            <!-- Manual Renew Form -->
                            <form action="{{ route('member.arisan.renew', $sub) }}" method="POST" onsubmit="return confirm('Perpanjang langganan 30 hari untuk subdomain {{ $sub->subdomain }} seharga Rp {{ number_format($sub->price ?: $monthlyPrice, 0, ',', '.') }}?');">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center px-3.5 py-2 text-xs font-medium rounded-xl text-amber-900 dark:text-amber-200 bg-amber-100 dark:bg-amber-950/60 hover:bg-amber-200 dark:hover:bg-amber-900 border border-amber-300 dark:border-amber-800 transition-colors">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Perpanjang (+30 Hari)</span>
                                </button>
                            </form>

                            <!-- Open App Button -->
                            <a href="{{ $sub->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-4 py-2 text-xs font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                                <span>Buka Aplikasi</span>
                                <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-10 text-center shadow-sm">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Belum Ada Aplikasi Pembukuan Arisan</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto mt-1.5 mb-6">
                        Buat portal arisan mandiri berbasis PWA untuk mengelola kloter, kartu iuran anggota, buku kas, dan undian fair-play.
                    </p>
                    <a href="{{ route('member.arisan.order') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Pesan Aplikasi Arisan Sekarang</span>
                    </a>
                </div>
            @endforelse
        </div>
    </main>
</body>
</html>
