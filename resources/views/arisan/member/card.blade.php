@extends('arisan.member.layout')

@section('title', 'Kartu Arisan Digital')

@section('content')
<div class="space-y-6">

    <!-- Header Section -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                Kartu Anggota
            </span>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mt-0.5">
                {{ $member->name }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $member->phone_number }}
            </p>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                {{ $slots->count() }} Slot Aktif
            </span>
        </div>
    </div>

    @if ($slots->isEmpty())
        <!-- Empty State -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200/80 dark:border-slate-800 text-center shadow-sm">
            <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400 mb-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">
                Belum Terdaftar di Slot
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                Anda belum memiliki slot arisan aktif. Silakan hubungi pengelola arisan untuk dimasukkan ke dalam kloter.
            </p>
        </div>
    @else
        <!-- Cards List (Multi-slot) -->
        <div class="space-y-6">
            @foreach ($slots as $slot)
                <div class="space-y-3">
                    <!-- Digital Card Box -->
                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-800 via-emerald-900 to-slate-950 text-white p-6 sm:p-7 shadow-xl border border-emerald-700/50">
                        <!-- Background Pattern -->
                        <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full bg-emerald-500/10 blur-2xl pointer-events-none"></div>
                        <div class="absolute -left-12 -bottom-12 w-48 h-48 rounded-full bg-amber-500/10 blur-2xl pointer-events-none"></div>

                        <!-- Card Top: Kloter & Slot Number -->
                        <div class="relative z-10 flex items-start justify-between mb-6">
                            <div>
                                <span class="text-[11px] font-semibold text-emerald-300 uppercase tracking-widest block">
                                    {{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}
                                </span>
                                <h3 class="text-lg sm:text-xl font-extrabold text-white mt-0.5 tracking-tight">
                                    {{ $slot->group->name }}
                                </h3>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-bold bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20">
                                    SLOT #{{ $slot->slot_number }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Middle: Chip & Amount -->
                        <div class="relative z-10 flex items-center justify-between my-4">
                            <!-- Card Chip Graphic -->
                            <div class="w-11 h-8 rounded-md bg-gradient-to-tr from-amber-300 to-amber-200 border border-amber-400/80 flex items-center justify-center shadow-inner">
                                <div class="w-7 h-5 border border-amber-600/40 rounded-sm grid grid-cols-2 gap-0.5 p-0.5">
                                    <div class="bg-amber-400/60 rounded-xs"></div>
                                    <div class="bg-amber-400/60 rounded-xs"></div>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[11px] text-emerald-200 block">Iuran Per Putaran</span>
                                <span class="text-xl sm:text-2xl font-black text-white tracking-tight">
                                    Rp {{ number_format($slot->group->dues_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar & Status -->
                        <div class="relative z-10 mt-6 pt-4 border-t border-emerald-700/60">
                            <div class="flex items-center justify-between text-xs mb-2">
                                <span class="text-emerald-200 font-medium">
                                    Progress: {{ $slot->paid_count }} / {{ $slot->total_periods }} Putaran
                                </span>
                                <span class="font-bold text-amber-300">
                                    {{ $slot->progress_percent }}%
                                </span>
                            </div>
                            <div class="w-full bg-slate-900/80 rounded-full h-2.5 overflow-hidden p-0.5 border border-emerald-600/40">
                                <div class="bg-gradient-to-r from-amber-400 to-emerald-400 h-full rounded-full transition-all duration-500" style="width: {{ $slot->progress_percent }}%"></div>
                            </div>
                        </div>

                        <!-- Winner Status Badge inside Card -->
                        <div class="relative z-10 mt-4 flex items-center justify-between">
                            @if ($slot->has_won)
                                <div class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-500/20 text-emerald-200 border border-emerald-400/40">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Pemenang Putaran #{{ $slot->wonPeriod?->period_number ?? $slot->won_period_id }}</span>
                                </div>
                            @else
                                <div class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800/80 text-slate-300 border border-slate-700">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Belum Menang (Ikut Undian)</span>
                                </div>
                            @endif

                            <span class="text-[11px] text-slate-400">
                                Periode: {{ $slot->group->period_type ?? 'MONTHLY' }}
                            </span>
                        </div>
                    </div>

                    <!-- Bill / Action Panel for this Slot -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                        @if ($slot->current_payment)
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Tagihan Terkini</span>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                        Putaran #{{ $slot->next_period->period_number ?? '-' }}
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Jatuh Tempo: {{ $slot->next_period && $slot->next_period->due_date ? \Carbon\Carbon::parse($slot->next_period->due_date)->translatedFormat('d F Y') : '-' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white block">
                                        Rp {{ number_format($slot->current_payment->amount, 0, ',', '.') }}
                                    </span>
                                    @if ($slot->current_payment->status === 'PENDING_VERIFICATION')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            Menunggu Verifikasi
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                            Belum Bayar
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                <a href="{{ route('arisan.member.pay', ['subdomain' => $subdomain, 'payment' => $slot->current_payment->id]) }}"
                                    class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-600/20">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    <span>{{ $slot->current_payment->status === 'PENDING_VERIFICATION' ? 'Upload Ulang' : 'Bayar Sekarang' }}</span>
                                </a>

                                <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain, 'group_id' => $slot->group_id]) }}"
                                    class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>Transparansi</span>
                                </a>
                            </div>
                        @else
                            <!-- All Paid State -->
                            <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-900/60 flex items-center justify-between">
                                <div class="flex items-center space-x-2.5">
                                    <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h5 class="text-xs font-bold text-emerald-900 dark:text-emerald-200">Semua Putaran Telah Lunas</h5>
                                        <p class="text-[11px] text-emerald-700 dark:text-emerald-400">Tidak ada tagihan tertunda untuk slot ini.</p>
                                    </div>
                                </div>
                                <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain, 'group_id' => $slot->group_id]) }}"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/60 hover:bg-emerald-200 transition-colors">
                                    Papan Anggota
                                </a>
                            </div>
                        @endif

                        <!-- Slot Navigation Link -->
                        <div class="flex items-center justify-between text-xs pt-2 text-slate-500 dark:text-slate-400">
                            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $slot->id]) }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center">
                                <span>Lihat Riwayat Putaran Slot Ini</span>
                                <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
