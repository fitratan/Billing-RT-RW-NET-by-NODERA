@extends('arisan.admin.layout')

@section('title', 'Ringkasan Dasbor')

@section('content')
<div class="space-y-6">

    <!-- Top Greeting Banner -->
    <div class="bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 rounded-2xl p-5 sm:p-6 text-white shadow-lg shadow-emerald-700/15">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/30 text-emerald-100 border border-emerald-400/20 mb-2">
                    Sistem Pembukuan Arisan Digital
                </div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight">
                    {{ $subscription->business_name }}
                </h2>
                <p class="text-xs sm:text-sm text-emerald-100/90 mt-1">
                    Kelola peserta, pembagian kloter, verifikasi iuran, dan undian putaran secara transparan.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                    class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold text-emerald-900 bg-white hover:bg-emerald-50 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Kloter Baru</span>
                </a>
                <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                    class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-800/80 hover:bg-emerald-800 transition-colors border border-emerald-400/30">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    <span>Tambah Anggota</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Statistics Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Kloter -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Kloter</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalGroups }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">({{ $activeGroups }} Aktif)</span>
            </div>
            <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}" class="hover:underline inline-flex items-center">
                    Lihat kloter &rarr;
                </a>
            </div>
        </div>

        <!-- Card 2: Total Anggota & Slot -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Anggota & Slot</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalMembers }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Orang</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Slot Terisi: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $filledSlots }} / {{ $totalSlots }}</span>
            </div>
        </div>

        <!-- Card 3: Iuran Terkumpul -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Iuran Terverifikasi</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="text-lg sm:text-xl font-bold text-emerald-600 dark:text-emerald-400 truncate">
                Rp {{ number_format($totalCollectedDues, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                @if ($totalPendingVerification > 0)
                    <span class="inline-flex items-center text-amber-600 dark:text-amber-400 font-medium">
                        {{ $totalPendingVerification }} menunggu verifikasi
                    </span>
                @else
                    <span>Semua pembayaran up to date</span>
                @endif
            </div>
        </div>

        <!-- Card 4: Saldo Kas -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Saldo Buku Kas</span>
                <div class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white truncate">
                Rp {{ number_format($cashBalance, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex justify-between">
                <span class="text-emerald-600">+{{ number_format($totalCashIn, 0, ',', '.') }}</span>
                <span class="text-rose-600">-{{ number_format($totalCashOut, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Quick Action Shortcut Grid -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
            Aksi Cepat
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all text-center group">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Buat Kloter</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Tambah grup arisan</span>
            </a>

            <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all text-center group">
                <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Kelola Anggota</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Reset PIN & Link WA</span>
            </a>

            <a href="/arisan-app/{{ $subdomain }}/admin/payments"
                class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all text-center group">
                <div class="w-9 h-9 rounded-lg bg-teal-100 dark:bg-teal-900/60 text-teal-600 dark:text-teal-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Iuran & Verifikasi</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Rekap tagihan putaran</span>
            </a>

            <a href="/arisan-app/{{ $subdomain }}/admin/draws"
                class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all text-center group">
                <div class="w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                    </svg>
                </div>
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Undian Digital</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Kocok pemenang putaran</span>
            </a>
        </div>
    </div>

    <!-- Group & Schedule Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Left: Daftar Kloter Aktif -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Kloter Arisan</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Daftar kloter yang sedang berjalan</p>
                </div>
                <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                    Lihat Semua
                </a>
            </div>

            @if ($recentGroups->isEmpty())
                <div class="text-center py-8 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                    <svg class="mx-auto w-10 h-10 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Belum ada kloter arisan yang dibuat.</p>
                    <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                        class="mt-3 inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-950/60">
                        + Buat Kloter Pertama
                    </a>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($recentGroups as $group)
                        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
                            <div class="min-w-0 pr-3">
                                <div class="flex items-center space-x-2">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $group->name }}</h4>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $group->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ $group->status === 'ACTIVE' ? 'Aktif' : 'Selesai' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Iuran: <span class="font-medium text-slate-700 dark:text-slate-300">Rp {{ number_format($group->dues_amount, 0, ',', '.') }}</span> &bull;
                                    Slot: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $group->group_members_count }} / {{ $group->total_slots }}</span>
                                </p>
                            </div>
                            <a href="{{ route('arisan.admin.groups.show', ['subdomain' => $subdomain, 'group' => $group->id]) }}"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 shrink-0">
                                Kelola Slot
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Jadwal Putaran & Undian Mendatang -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Jadwal Undian & Jatuh Tempo</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Putaran arisan terdekat</p>
                </div>
                <a href="/arisan-app/{{ $subdomain }}/admin/draws"
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                    Undian Putaran
                </a>
            </div>

            @if ($upcomingDraws->isEmpty())
                <div class="text-center py-8 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                    <svg class="mx-auto w-10 h-10 text-slate-300 dark:text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Belum ada jadwal putaran aktif.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($upcomingDraws as $period)
                        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $period->group->name ?? 'Kloter' }}</h4>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        Putaran {{ $period->period_number }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Tempo: {{ $period->due_date ? \Carbon\Carbon::parse($period->due_date)->format('d M Y') : '-' }} &bull;
                                    Undian: {{ $period->draw_date ? \Carbon\Carbon::parse($period->draw_date)->format('d M Y') : '-' }}
                                </p>
                            </div>
                            <a href="/arisan-app/{{ $subdomain }}/admin/draws"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200/60 dark:border-rose-900/60 shrink-0">
                                Undi Putaran
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
