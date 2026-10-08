<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $subscription->business_name }}">
    <title>Login Pengelola — {{ $subscription->business_name }}</title>
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
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        amber: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
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
<body class="min-h-full flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8 text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 antialiased">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <!-- Logo & Header -->
        <div class="text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/20 mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{{ $subscription->business_name }}</h1>
            <p class="text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-semibold mt-1">Panel Pengelola Arisan</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">Masukkan kata sandi admin untuk mengelola arisan</p>
        </div>

        <!-- Notification Alerts -->
        @if (session('success'))
            <div class="mt-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-sm text-emerald-800 dark:text-emerald-200 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mt-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-sm text-rose-800 dark:text-rose-200 font-medium">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <!-- Card Form -->
        <div class="mt-6 bg-white dark:bg-slate-900 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8">
            <form action="{{ route('arisan.admin.login.submit', ['subdomain' => $subdomain]) }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="admin_password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Password Admin
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" name="admin_password" id="admin_password" required
                            class="block w-full pl-10 pr-12 py-3 rounded-xl border {{ $errors->has('admin_password') ? 'border-rose-300 dark:border-rose-700 bg-rose-50/30' : 'border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50' }} text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all"
                            placeholder="Masukkan kata sandi pengelola">
                        <button type="button" onclick="togglePasswordVisibility('admin_password')"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                            <svg id="admin_password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('admin_password')
                        <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center items-center py-3 px-4 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all shadow-md shadow-emerald-600/20">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <span>Masuk ke Panel Pengelola</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-800 text-center">
                <a href="{{ route('arisan.member.login', ['subdomain' => $subdomain]) }}"
                    class="inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Masuk sebagai Anggota / Peserta Arisan</span>
                </a>
            </div>
        </div>

        <!-- Smart PWA Install Helper Banner -->
        <x-arisan.pwa-install-banner />

        <div class="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">
            &copy; {{ date('Y') }} {{ $subscription->business_name }} &bull; Powered by NODERA
        </div>
    </div>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ route('arisan.pwa.sw', ['subdomain' => $subdomain]) }}')
                    .catch((err) => console.error('Service Worker registration error:', err));
            });
        }

        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
    </script>
</body>
</html>
