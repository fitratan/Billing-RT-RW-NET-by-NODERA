<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}">
    <title>@yield('title', 'Admin Panel') — {{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}</title>
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
        /* Safe bottom spacing for mobile fixed nav bar */
        .pb-safe-nav { padding-bottom: 5.5rem; }
    </style>
    @stack('styles')
</head>
<body class="min-h-full flex flex-col text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 antialiased pb-safe-nav md:pb-8">

    <!-- Top Header -->
    <header class="sticky top-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Business Info -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('arisan.admin.dashboard', ['subdomain' => $subdomain]) }}" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:bg-emerald-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div class="leading-tight">
                            <h1 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors truncate max-w-[180px] sm:max-w-xs">
                                {{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}
                            </h1>
                            <div class="flex items-center space-x-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    ADMIN
                                </span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $subdomain }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('arisan.admin.dashboard', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('arisan.admin.dashboard') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Beranda
                    </a>
                    <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('arisan.admin.groups.*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Kloter
                    </a>
                    <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('arisan.admin.members.*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Anggota
                    </a>
                    <a href="/arisan-app/{{ $subdomain }}/admin/payments"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->is('*/admin/payments*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Iuran
                    </a>
                    <a href="/arisan-app/{{ $subdomain }}/admin/draws"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->is('*/admin/draws*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Undian
                    </a>
                    <a href="/arisan-app/{{ $subdomain }}/admin/cashflow"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->is('*/admin/cashflow*') ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        Buku Kas
                    </a>
                </nav>

                <!-- Right Actions: Portal Member & Logout -->
                <div class="flex items-center space-x-2">
                    <a href="{{ route('arisan.member.login', ['subdomain' => $subdomain]) }}" target="_blank"
                        class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <span>Portal Member</span>
                    </a>

                    <form action="{{ route('arisan.admin.logout', ['subdomain' => $subdomain]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                            title="Keluar dari panel admin"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors border border-rose-200/60 dark:border-rose-900/60">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Notification Alerts Area -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-sm text-emerald-800 dark:text-emerald-200 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-sm text-rose-800 dark:text-rose-200 font-medium">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-sm text-amber-800 dark:text-amber-200 font-medium">
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
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex-1 w-full">
        @yield('content')
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shadow-lg">
        <div class="grid grid-cols-5 h-16">
            <!-- Beranda -->
            <a href="{{ route('arisan.admin.dashboard', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.admin.dashboard') ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-[10px] mt-1">Beranda</span>
            </a>

            <!-- Kloter -->
            <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.admin.groups.*') ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span class="text-[10px] mt-1">Kloter</span>
            </a>

            <!-- Iuran -->
            <a href="/arisan-app/{{ $subdomain }}/admin/payments"
                class="flex flex-col items-center justify-center transition-colors {{ request()->is('*/admin/payments*') ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-[10px] mt-1">Iuran</span>
            </a>

            <!-- Undian -->
            <a href="/arisan-app/{{ $subdomain }}/admin/draws"
                class="flex flex-col items-center justify-center transition-colors {{ request()->is('*/admin/draws*') ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                </svg>
                <span class="text-[10px] mt-1">Undian</span>
            </a>

            <!-- Kas / Menu -->
            <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center transition-colors {{ request()->routeIs('arisan.admin.members.*') ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-[10px] mt-1">Anggota</span>
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
