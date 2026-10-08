<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $arisan_subscription->business_name ?? 'Portal Member' }}">
    <title>@yield('title', 'Portal Member') — {{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="manifest" href="{{ route('arisan.pwa.manifest', ['subdomain' => $subdomain]) }}">
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
                            200: '#a7f3d0',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            950: '#022c22',
                        },
                        amber: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .pb-safe-nav { padding-bottom: 5.5rem; }
    </style>
    @stack('styles')
</head>
<body class="min-h-full flex flex-col text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 antialiased pb-safe-nav md:pb-8">

    <!-- Top Header -->
    <header class="sticky top-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800">
        <div class="max-w-xl md:max-w-4xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Member Info -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('arisan.member.card', ['subdomain' => $subdomain]) }}" class="flex items-center space-x-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-700 text-white flex items-center justify-center shadow-sm shadow-emerald-500/20 group-hover:from-emerald-700 group-hover:to-emerald-800 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div class="leading-tight">
                            <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors truncate max-w-[150px] sm:max-w-xs">
                                {{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}
                            </h1>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[150px] sm:max-w-xs">
                                {{ $current_member->name ?? 'Anggota' }}
                            </p>
                        </div>
                    </a>
                </div>

                <!-- Desktop Nav -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('arisan.member.card', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('arisan.member.card') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Kartu Arisan
                    </a>
                    <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('arisan.member.history') || request()->routeIs('arisan.member.pay') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Riwayat
                    </a>
                    <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('arisan.member.transparency') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Transparansi
                    </a>
                    <a href="{{ route('arisan.member.profile', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('arisan.member.profile*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Profil & PIN
                    </a>
                </nav>

                <!-- Right Actions: Logout -->
                <div class="flex items-center space-x-2">
                    <form action="{{ route('arisan.member.logout', ['subdomain' => $subdomain]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                            title="Keluar dari akun member"
                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors border border-rose-200/60 dark:border-rose-900/60">
                            <svg class="w-3.5 h-3.5 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Notification Alerts Area -->
    <div class="max-w-xl md:max-w-4xl mx-auto px-4 sm:px-6 pt-4 w-full">
        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-start space-x-3 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-xs sm:text-sm text-emerald-800 dark:text-emerald-200 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-start space-x-3 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-200 font-medium">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start space-x-3 shadow-sm">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-xs sm:text-sm text-amber-800 dark:text-amber-200 font-medium">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <!-- PWA Install Banner -->
        <x-arisan.pwa-install-banner />
    </div>

    <!-- Main Content -->
    <main class="max-w-xl md:max-w-4xl mx-auto px-4 sm:px-6 py-2 flex-1 w-full">
        @yield('content')
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 shadow-lg">
        <div class="grid grid-cols-4 h-16 max-w-xl mx-auto">
            <!-- Kartu -->
            <a href="{{ route('arisan.member.card', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.member.card') ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span class="text-[10px] mt-1">Kartu</span>
            </a>

            <!-- Riwayat -->
            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.member.history') || request()->routeIs('arisan.member.pay') ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span class="text-[10px] mt-1">Riwayat</span>
            </a>

            <!-- Transparansi -->
            <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.member.transparency') ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="text-[10px] mt-1">Transparansi</span>
            </a>

            <!-- Profil -->
            <a href="{{ route('arisan.member.profile', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.member.profile*') ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-[10px] mt-1">Profil</span>
            </a>
        </div>
    </nav>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ route('arisan.pwa.sw', ['subdomain' => $subdomain]) }}')
                    .catch((err) => console.error('Service Worker registration error:', err));
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
