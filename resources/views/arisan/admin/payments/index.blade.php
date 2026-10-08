@extends('arisan.admin.layout')

@section('title', 'Iuran & Pembayaran')

@section('content')
<div class="space-y-6">

    <!-- Header & Group Selector & Quick Settings Link -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 flex-wrap">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Pembukuan Iuran & Putaran</h2>
                @if ($currentGroup)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ $currentGroup->name }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Navigasi putaran periode arisan, verifikasi setoran, dan kirim pengingat WhatsApp japri.</p>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            @if ($currentGroup)
                <button type="button" onclick="openAdvanceModal()"
                    class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Bayar di Muka</span>
                </button>
            @endif
            <a href="{{ route('arisan.admin.settings.payment', ['subdomain' => $subdomain]) }}"
                class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Rekening & QRIS</span>
            </a>
        </div>
    </div>

    @if ($groups->isEmpty())
        <div class="p-12 text-center bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-sm">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Kloter Arisan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Silakan buat kloter arisan terlebih dahulu untuk mulai mengelola iuran dan periode undian.
            </p>
            <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                class="mt-4 inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700">
                + Buat Kloter Pertama
            </a>
        </div>
    @else
        <!-- Group Switcher Tabs/Dropdown -->
        @if ($groups->count() > 1)
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                @foreach ($groups as $g)
                    <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $g->id]) }}"
                        class="px-3.5 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-colors border {{ $currentGroup && $currentGroup->id === $g->id ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-semibold' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                        {{ $g->name }} ({{ $g->group_members_count }}/{{ $g->total_slots }} Slot)
                    </a>
                @endforeach
            </div>
        @endif

        @if ($currentPeriod)
            <!-- Mobile-First Period Stepper Navigator -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <!-- Prev Button -->
                    <div class="flex items-center justify-between sm:justify-start gap-2">
                        @if ($prevPeriod)
                            <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $prevPeriod->id, 'status' => $statusFilter]) }}"
                                class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Putaran Lalu</span>
                            </a>
                        @else
                            <button disabled class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 bg-slate-50 dark:bg-slate-800/40 cursor-not-allowed">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Putaran Lalu</span>
                            </button>
                        @endif

                        <!-- Next Button (Mobile View) -->
                        <div class="sm:hidden">
                            @if ($nextPeriod)
                                <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $nextPeriod->id, 'status' => $statusFilter]) }}"
                                    class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    <span>Putaran Depan</span>
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @else
                                <button disabled class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 bg-slate-50 dark:bg-slate-800/40 cursor-not-allowed">
                                    <span>Putaran Depan</span>
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Center Selector & Current Period Info -->
                    <div class="flex-1 flex flex-col items-center text-center">
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                Putaran {{ $currentPeriod->period_number }} / {{ $allPeriods->count() }}
                            </span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($currentPeriod->period_date)->translatedFormat('F Y') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex-wrap justify-center">
                            <span>Jatuh Tempo: <strong class="text-slate-700 dark:text-slate-300">{{ $currentPeriod->due_date ? \Carbon\Carbon::parse($currentPeriod->due_date)->translatedFormat('d M Y') : '-' }}</strong></span>
                            <span>&bull;</span>
                            <span>Jadwal Undian: <strong class="text-slate-700 dark:text-slate-300">{{ $currentPeriod->draw_date ? \Carbon\Carbon::parse($currentPeriod->draw_date)->translatedFormat('d M Y') : '-' }}</strong></span>
                        </div>
                    </div>

                    <!-- Next Button (Desktop View) & Dropdown jump -->
                    <div class="hidden sm:flex items-center gap-2">
                        <select onchange="location = this.value;"
                            class="py-1.5 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                            @foreach ($allPeriods as $p)
                                <option value="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $p->id, 'status' => $statusFilter]) }}"
                                    {{ $p->id === $currentPeriod->id ? 'selected' : '' }}>
                                    Putaran #{{ $p->period_number }} ({{ \Carbon\Carbon::parse($p->period_date)->translatedFormat('M Y') }})
                                </option>
                            @endforeach
                        </select>

                        @if ($nextPeriod)
                            <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $nextPeriod->id, 'status' => $statusFilter]) }}"
                                class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                <span>Putaran Depan</span>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @else
                            <button disabled class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 bg-slate-50 dark:bg-slate-800/40 cursor-not-allowed">
                                <span>Putaran Depan</span>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Summary Stats for Selected Period -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Slot</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white mt-0.5">{{ $stats['total_slots'] }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Sudah Lunas</p>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $stats['paid_count'] }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-rose-600 dark:text-rose-400">Belum Bayar</p>
                    <p class="text-lg font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $stats['unpaid_count'] }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Menunggu Verifikasi</p>
                    <p class="text-lg font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $stats['pending_verification_count'] }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Terkumpul</p>
                    <p class="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 truncate">Rp {{ number_format($stats['total_collected_amount'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Target Iuran</p>
                    <p class="text-base font-bold text-slate-900 dark:text-white mt-0.5 truncate">Rp {{ number_format($stats['expected_amount'], 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- Filter Status Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200/80 dark:border-slate-800 pb-2 overflow-x-auto">
                <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $currentPeriod->id, 'status' => 'ALL']) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'ALL' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    Semua Status ({{ $payments->count() }})
                </a>
                <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $currentPeriod->id, 'status' => 'UNPAID']) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'UNPAID' ? 'bg-rose-600 text-white' : 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40' }}">
                    Belum Bayar ({{ $stats['unpaid_count'] }})
                </a>
                <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $currentPeriod->id, 'status' => 'PENDING_VERIFICATION']) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'PENDING_VERIFICATION' ? 'bg-amber-500 text-white' : 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40' }}">
                    Menunggu Verifikasi ({{ $stats['pending_verification_count'] }})
                </a>
                <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $currentPeriod->id, 'status' => 'PAID']) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'PAID' ? 'bg-emerald-600 text-white' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40' }}">
                    Lunas ({{ $stats['paid_count'] }})
                </a>
            </div>

            <!-- Payments Table / Mobile Cards -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                @if ($payments->isEmpty())
                    <div class="p-10 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tidak Ada Data Pembayaran</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ $statusFilter !== 'ALL' ? 'Tidak ada data untuk status filter yang dipilih.' : 'Belum ada anggota yang terdaftar di kloter ini.' }}
                        </p>
                    </div>
                @else
                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200/80 dark:border-slate-800">
                                <tr>
                                    <th class="py-3.5 px-4 w-16">Slot</th>
                                    <th class="py-3.5 px-4">Nama Anggota</th>
                                    <th class="py-3.5 px-4">Nominal</th>
                                    <th class="py-3.5 px-4">Status & Waktu</th>
                                    <th class="py-3.5 px-4">Bukti Bayar</th>
                                    <th class="py-3.5 px-4 text-right">Aksi & WhatsApp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($payments as $payment)
                                    @php
                                        $member = $payment->groupMember->member ?? null;
                                        $slotNumber = $payment->groupMember->slot_number ?? '-';
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                            #{{ $slotNumber }}
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900 dark:text-white">
                                                {{ $member ? $member->name : 'Slot Belum Terisi' }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">
                                                {{ $member ? $member->phone_number : '-' }}
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                            Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4">
                                            @if ($payment->status === 'PAID')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Lunas ({{ $payment->payment_method ?: 'BCA' }})
                                                </span>
                                                <div class="text-[10px] text-slate-400 mt-0.5">
                                                    {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->translatedFormat('d M Y H:i') : '' }}
                                                </div>
                                            @elseif ($payment->status === 'PENDING_VERIFICATION')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Menunggu Verifikasi
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Belum Bayar
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4">
                                            @if ($payment->proof_image)
                                                <button type="button" onclick="previewProof('{{ asset('storage/' . $payment->proof_image) }}')"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <span>Lihat Bukti</span>
                                                </button>
                                            @else
                                                <span class="text-slate-400 text-[11px]">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if ($payment->status !== 'PAID')
                                                    <!-- Verifikasi Button -->
                                                    <button type="button" onclick="openVerifyModal({{ $payment->id }}, '{{ addslashes($member ? $member->name : '') }}', {{ $payment->amount }})"
                                                        class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-colors">
                                                        Verifikasi
                                                    </button>

                                                    @if ($payment->status === 'PENDING_VERIFICATION')
                                                        <!-- Tolak Bukti Button -->
                                                        <button type="button" onclick="openRejectModal({{ $payment->id }}, '{{ addslashes($member ? $member->name : '') }}')"
                                                            class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300 transition-colors">
                                                            Tolak
                                                        </button>
                                                    @endif

                                                    <!-- 1-Click WhatsApp Reminder -->
                                                    @if ($member)
                                                        <a href="{{ $payment->wa_reminder_url }}" target="_blank" title="Kirim Pengingat WhatsApp Japri"
                                                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-teal-800 dark:text-teal-200 bg-teal-50 dark:bg-teal-950/60 hover:bg-teal-100 dark:hover:bg-teal-900/60 border border-teal-200/60 dark:border-teal-800/60 transition-colors">
                                                            <svg class="w-3.5 h-3.5 mr-1 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                                            </svg>
                                                            <span>Ingatkan WA</span>
                                                        </a>
                                                    @endif
                                                @else
                                                    <!-- 1-Click WhatsApp Receipt -->
                                                    @if ($member)
                                                        <a href="{{ $payment->wa_receipt_url }}" target="_blank" title="Kirim Kuitansi WhatsApp"
                                                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-emerald-800 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200/60 dark:border-emerald-800/60 transition-colors">
                                                            <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span>Kuitansi WA</span>
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card List -->
                    <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($payments as $payment)
                            @php
                                $member = $payment->groupMember->member ?? null;
                                $slotNumber = $payment->groupMember->slot_number ?? '-';
                            @endphp
                            <div class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-bold">
                                                #{{ $slotNumber }}
                                            </span>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                                {{ $member ? $member->name : 'Slot Belum Terisi' }}
                                            </h4>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1 pl-8">
                                            {{ $member ? $member->phone_number : '-' }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-slate-900 dark:text-white">
                                            Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                        </div>
                                        <div class="mt-1">
                                            @if ($payment->status === 'PAID')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Lunas ({{ $payment->payment_method ?: 'BCA' }})
                                                </span>
                                            @elseif ($payment->status === 'PENDING_VERIFICATION')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    Menunggu Verifikasi
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                    Belum Bayar
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Proof & Actions -->
                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 gap-2 flex-wrap">
                                    <div>
                                        @if ($payment->proof_image)
                                            <button type="button" onclick="previewProof('{{ asset('storage/' . $payment->proof_image) }}')"
                                                class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Bukti Transfer</span>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if ($payment->status !== 'PAID')
                                            <button type="button" onclick="openVerifyModal({{ $payment->id }}, '{{ addslashes($member ? $member->name : '') }}', {{ $payment->amount }})"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                                                Verifikasi
                                            </button>

                                            @if ($payment->status === 'PENDING_VERIFICATION')
                                                <button type="button" onclick="openRejectModal({{ $payment->id }}, '{{ addslashes($member ? $member->name : '') }}')"
                                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300">
                                                    Tolak
                                                </button>
                                            @endif

                                            @if ($member)
                                                <a href="{{ $payment->wa_reminder_url }}" target="_blank"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-teal-800 dark:text-teal-200 bg-teal-50 dark:bg-teal-950/60 border border-teal-200/60">
                                                    <span>Ingatkan WA</span>
                                                </a>
                                            @endif
                                        @else
                                            @if ($member)
                                                <a href="{{ $payment->wa_receipt_url }}" target="_blank"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-800 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60">
                                                    <span>Kuitansi WA</span>
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endif

</div>

<!-- Modal Verifikasi Pembayaran -->
<div id="verifyModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Verifikasi Pembayaran Iuran</h3>
            <button type="button" onclick="closeVerifyModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="verifyForm" method="POST" action="">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Anggota & Nominal</label>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <p id="verifyMemberName" class="text-sm font-bold text-slate-900 dark:text-white"></p>
                        <p id="verifyAmount" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Metode Pembayaran</label>
                    <select name="payment_method"
                        class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        <option value="BCA">Transfer Bank BCA</option>
                        <option value="BRI">Transfer Bank BRI</option>
                        <option value="Mandiri">Transfer Bank Mandiri</option>
                        <option value="BNI">Transfer Bank BNI</option>
                        <option value="BSI">Transfer Bank BSI</option>
                        <option value="QRIS">QRIS / E-Wallet</option>
                        <option value="TUNAI">Tunai / Cash</option>
                        <option value="LAINNYA">Lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Dititipkan saat pertemuan warga"
                        class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeVerifyModal()"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                        Konfirmasi Lunas
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak Bukti Transfer -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Tolak Bukti Pembayaran</h3>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="space-y-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Bukti transfer untuk <strong id="rejectMemberName" class="text-slate-900 dark:text-white"></strong> akan dihapus dan status dikembalikan menjadi Belum Bayar.
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Alasan Penolakan</label>
                    <textarea name="note" rows="3" required placeholder="Contoh: Bukti buram, nominal tidak sesuai, atau rekening tujuan salah..."
                        class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-sm">
                        Tolak Bukti Transfer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Preview Bukti Transfer -->
<div id="previewProofModal" class="fixed inset-0 z-50 hidden bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-4 shadow-xl space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">Foto Bukti Transfer</h4>
            <button type="button" onclick="closePreviewProof()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 flex items-center justify-center max-h-[75vh]">
            <img id="previewProofImg" src="" alt="Bukti Transfer" class="object-contain max-h-[70vh] w-auto">
        </div>
    </div>
</div>

<!-- Modal Bayar di Muka (Advance Payment) -->
@if ($currentGroup)
<div id="advanceModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Bayar Iuran di Muka (Multi-Putaran)</h3>
            <button type="button" onclick="closeAdvanceModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('arisan.admin.payments.advance', ['subdomain' => $subdomain]) }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Pilih Anggota & Slot</label>
                    <select name="group_member_id" required
                        class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        <option value="">-- Pilih Anggota --</option>
                        @foreach ($availableGroupMembers as $gm)
                            <option value="{{ $gm->id }}">
                                Slot #{{ $gm->slot_number }} - {{ $gm->member ? $gm->member->name : 'Tanpa Nama' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Pilih Putaran yang Dibayar Sekaligus</label>
                    <div class="max-h-48 overflow-y-auto space-y-1.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40">
                        @foreach ($allPeriods as $p)
                            <label class="flex items-center space-x-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" name="period_ids[]" value="{{ $p->id }}" class="rounded text-emerald-600 focus:ring-emerald-500">
                                <span>Putaran #{{ $p->period_number }} ({{ \Carbon\Carbon::parse($p->period_date)->translatedFormat('d F Y') }}) - Rp {{ number_format($currentGroup->dues_amount, 0, ',', '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Metode Pembayaran</label>
                    <select name="payment_method"
                        class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        <option value="BCA">Transfer Bank BCA</option>
                        <option value="BRI">Transfer Bank BRI</option>
                        <option value="Mandiri">Transfer Bank Mandiri</option>
                        <option value="BNI">Transfer Bank BNI</option>
                        <option value="QRIS">QRIS / E-Wallet</option>
                        <option value="TUNAI">Tunai / Cash</option>
                    </select>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeAdvanceModal()"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                        Simpan Pembayaran di Muka
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
    function openVerifyModal(paymentId, memberName, amount) {
        document.getElementById('verifyForm').action = '/arisan-app/{{ $subdomain }}/admin/payments/' + paymentId + '/verify';
        document.getElementById('verifyMemberName').innerText = memberName;
        document.getElementById('verifyAmount').innerText = 'Rp ' + Number(amount).toLocaleString('id-ID');
        document.getElementById('verifyModal').classList.remove('hidden');
    }
    function closeVerifyModal() {
        document.getElementById('verifyModal').classList.add('hidden');
    }

    function openRejectModal(paymentId, memberName) {
        document.getElementById('rejectForm').action = '/arisan-app/{{ $subdomain }}/admin/payments/' + paymentId + '/reject';
        document.getElementById('rejectMemberName').innerText = memberName;
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function previewProof(url) {
        document.getElementById('previewProofImg').src = url;
        document.getElementById('previewProofModal').classList.remove('hidden');
    }
    function closePreviewProof() {
        document.getElementById('previewProofModal').classList.add('hidden');
    }

    function openAdvanceModal() {
        var modal = document.getElementById('advanceModal');
        if (modal) modal.classList.remove('hidden');
    }
    function closeAdvanceModal() {
        var modal = document.getElementById('advanceModal');
        if (modal) modal.classList.add('hidden');
    }
</script>
@endpush
@endsection
