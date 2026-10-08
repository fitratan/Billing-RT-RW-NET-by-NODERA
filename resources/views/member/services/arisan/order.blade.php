<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Pembukuan Arisan PWA — NODERA Member</title>
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
                            50: '#fffbeb',
                            100: '#fef3c7',
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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('member.arisan.index') }}" class="flex items-center space-x-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="text-sm font-medium">Kembali</span>
                </a>
                <div class="h-5 w-px bg-slate-200 dark:bg-slate-800"></div>
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Order Pembukuan Arisan</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">PWA Micro-SaaS Arisan Dedicated</p>
                    </div>
                </div>
            </div>

            <div class="text-right">
                <span class="text-xs text-slate-500 dark:text-slate-400">Saldo Akun</span>
                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($user->total_saldo ?? $user->saldo, 0, ',', '.') }}</p>
            </div>
        </div>
    </header>

    <!-- Main Form Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60">
                <div class="flex items-center space-x-2 text-rose-800 dark:text-rose-200 font-semibold text-sm mb-1">
                    <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Terdapat kendala pada input:</span>
                </div>
                <ul class="list-disc list-inside text-xs text-rose-700 dark:text-rose-300 space-y-1 mt-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $userSaldo = (float) ($user->total_saldo ?? $user->saldo);
            $hasEnoughBalance = $userSaldo >= $monthlyPrice;
        @endphp

        @if (!$hasEnoughBalance)
            <div class="mb-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start space-x-3">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-xs text-amber-900 dark:text-amber-200">
                    <p class="font-semibold text-sm">Saldo Anda Tidak Mencukupi</p>
                    <p class="mt-0.5">Saldo akun Anda (Rp {{ number_format($userSaldo, 0, ',', '.') }}) kurang dari biaya langganan bulan pertama (Rp {{ number_format($monthlyPrice, 0, ',', '.') }}). Silakan lakukan topup saldo terlebih dahulu.</p>
                    <div class="mt-2.5">
                        <a href="/topup" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-amber-900 bg-amber-200 hover:bg-amber-300 dark:bg-amber-800 dark:text-amber-100 rounded-lg transition-colors">
                            Topup Saldo Sekarang &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Order Form -->
            <div class="lg:col-span-2">
                <form action="{{ route('member.arisan.order.submit') }}" method="POST" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
                    @csrf

                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Formulir Pendaftaran Arisan Baru</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi alamat subdomain dan akun pengelola arisan.</p>
                    </div>

                    <!-- Nama Arisan / Usaha -->
                    <div>
                        <label for="business_name" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Nama Arisan / Komunitas / Usaha <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}" required placeholder="Contoh: Arisan Berkah RT 05" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                        <p class="text-xs text-slate-400 mt-1">Nama ini akan tampil di kartu iuran digital dan portal member.</p>
                    </div>

                    <!-- Subdomain Input & Preview -->
                    <div>
                        <label for="subdomain" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Subdomain Aplikasi <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex rounded-xl shadow-sm">
                            <input type="text" id="subdomain" name="subdomain" value="{{ old('subdomain') }}" required minlength="3" maxlength="30" placeholder="arisan-berkah" pattern="[a-zA-Z0-9-]+" class="flex-1 px-3.5 py-2.5 text-sm rounded-l-xl border border-r-0 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all font-mono lowercase">
                            <span class="inline-flex items-center px-3.5 rounded-r-xl border border-l-0 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 text-xs font-mono select-none">
                                .{{ $domain }}
                            </span>
                        </div>
                        <div class="mt-2 p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-300 flex items-center space-x-2">
                            <span class="text-slate-400">URL Akses:</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-medium" id="url-preview">https://[subdomain].{{ $domain }}</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Hanya huruf, angka, dan tanda hubung (-). Minimal 3 karakter, maksimal 30 karakter.</p>
                    </div>

                    <!-- Password Admin -->
                    <div>
                        <label for="admin_password" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Password Admin / Pengelola <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="6" placeholder="Minimal 6 karakter" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                        <p class="text-xs text-slate-400 mt-1">Digunakan untuk login ke portal admin pengelolaan arisan.</p>
                    </div>

                    <!-- Auto Renew Checkbox -->
                    <div class="pt-2">
                        <label class="relative flex items-start space-x-3 cursor-pointer select-none">
                            <input type="checkbox" name="auto_renew" value="1" {{ old('auto_renew', true) ? 'checked' : '' }} class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4 border-slate-300 dark:border-slate-700">
                            <div>
                                <span class="text-xs font-semibold text-slate-900 dark:text-white">Perpanjang Otomatis (Auto-Renew)</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Potong saldo secara otomatis tiap 30 hari agar portal arisan tidak terputus.</p>
                            </div>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" @if(!$hasEnoughBalance) disabled @endif class="w-full inline-flex items-center justify-center px-5 py-3 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Aktifkan Aplikasi Arisan (Rp {{ number_format($monthlyPrice, 0, ',', '.') }})</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Summary & Highlights Sidebar -->
            <div class="space-y-4">
                <!-- Pricing Summary Box -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Ringkasan Biaya</h3>
                    <div class="flex items-baseline justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Biaya Langganan</span>
                        <div class="text-right">
                            <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($monthlyPrice, 0, ',', '.') }}</span>
                            <span class="text-xs text-slate-400 block">/ 30 hari</span>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <div class="flex justify-between">
                            <span>Masa Aktif Awal:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">30 Hari</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Domain:</span>
                            <span class="font-mono text-slate-800 dark:text-slate-200">{{ $domain }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Platform:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">PWA Mobile-First</span>
                        </div>
                    </div>
                </div>

                <!-- Feature Highlights -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fitur Termasuk</h3>
                    <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Portal Admin Pengelola & Papan Member Transparan</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Kartu Iuran Digital & 1-Click WhatsApp Reminders</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Undian Digital Fair-Play dengan Anti-Duplikasi</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Buku Kas Arisan & Verifikasi Pembayaran Transfer</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>PWA Standalone & Panduan Install Android / iOS</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        const subdomainInput = document.getElementById('subdomain');
        const urlPreview = document.getElementById('url-preview');
        const domain = "{{ $domain }}";

        if (subdomainInput && urlPreview) {
            subdomainInput.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
                this.value = val;
                if (val) {
                    urlPreview.textContent = 'https://' + val + '.' + domain;
                } else {
                    urlPreview.textContent = 'https://[subdomain].' + domain;
                }
            });
        }
    </script>
</body>
</html>
