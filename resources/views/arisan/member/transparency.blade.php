@extends('arisan.member.layout')

@section('title', 'Papan Transparansi Kloter')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Group Selector -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white">
                    Papan Transparansi Kloter
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Keterbukaan status iuran seluruh anggota dan riwayat pemenang.
                </p>
            </div>
        </div>

        @if ($memberGroups->count() > 1)
            <div class="flex items-center space-x-1.5 overflow-x-auto pb-1">
                @foreach ($memberGroups as $mg)
                    <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain, 'group_id' => $mg->id]) }}"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors {{ $currentGroup && $currentGroup->id === $mg->id ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-100' }}">
                        {{ $mg->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if (!$currentGroup)
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200/80 dark:border-slate-800 text-center shadow-sm">
            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400 mb-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Tidak Ada Kloter Ditemukan</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400">Belum ada kloter yang aktif saat ini.</p>
        </div>
    @else
        <!-- Period Navigator -->
        @if ($allPeriods->isNotEmpty() && $currentPeriod)
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">PILIH PUTARAN</span>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                        {{ $currentGroup->name }}
                    </span>
                </div>

                <div class="flex items-center space-x-1.5 overflow-x-auto pb-1">
                    @foreach ($allPeriods as $p)
                        <a href="{{ route('arisan.member.transparency', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $p->id]) }}"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ $currentPeriod->id === $p->id ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                            Putaran #{{ $p->period_number }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Current Period Status Card -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-2xl p-5 border border-slate-800 shadow-sm space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[11px] font-semibold text-emerald-400 uppercase tracking-widest block">
                            Status Putaran Berjalan
                        </span>
                        <h3 class="text-base sm:text-lg font-black text-white mt-0.5">
                            Putaran #{{ $currentPeriod->period_number }} &bull; {{ $currentPeriod->period_date ? \Carbon\Carbon::parse($currentPeriod->period_date)->translatedFormat('F Y') : '-' }}
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Batas Bayar: {{ $currentPeriod->due_date ? \Carbon\Carbon::parse($currentPeriod->due_date)->translatedFormat('d F Y') : '-' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] text-slate-400 block">Iuran per Slot</span>
                        <span class="text-sm sm:text-base font-extrabold text-amber-300">
                            Rp {{ number_format($currentGroup->dues_amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Stats Badges -->
                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-800 text-center">
                    <div class="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700">
                        <span class="text-[10px] text-emerald-400 font-bold block">LUNAS</span>
                        <span class="text-base font-black text-white">{{ $stats['paid_count'] }}</span>
                        <span class="text-[10px] text-slate-400">/ {{ $stats['total_slots'] }} slot</span>
                    </div>
                    <div class="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700">
                        <span class="text-[10px] text-amber-400 font-bold block">VERIFIKASI</span>
                        <span class="text-base font-black text-white">{{ $stats['pending_count'] }}</span>
                        <span class="text-[10px] text-slate-400">slot</span>
                    </div>
                    <div class="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700">
                        <span class="text-[10px] text-rose-400 font-bold block">BELUM</span>
                        <span class="text-base font-black text-white">{{ $stats['unpaid_count'] }}</span>
                        <span class="text-[10px] text-slate-400">slot</span>
                    </div>
                </div>
            </div>

            <!-- Members Transparency Grid / Matrix -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                            Status Setoran Anggota
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Daftar seluruh peserta di Putaran #{{ $currentPeriod->period_number }}
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($slotsInGroup as $slot)
                        @php
                            $payment = $slot->payments->first();
                            $status = $payment ? $payment->status : 'UNPAID';
                            $isCurrentMember = ($slot->member_id === $member->id);
                        @endphp
                        <div class="py-3 flex items-center justify-between {{ $isCurrentMember ? 'bg-emerald-50/60 dark:bg-emerald-950/30 px-3 rounded-xl' : '' }}">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center text-xs font-bold shrink-0">
                                    #{{ $slot->slot_number }}
                                </div>
                                <div>
                                    <div class="flex items-center space-x-1.5">
                                        <h5 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $slot->member->name }}
                                        </h5>
                                        @if ($isCurrentMember)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-200">
                                                Anda
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        @if ($slot->has_won)
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold">Pemenang Putaran #{{ $slot->wonPeriod?->period_number ?? $slot->won_period_id }}</span>
                                        @else
                                            <span>Belum Menang</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                @if ($status === 'PAID')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Lunas
                                    </span>
                                @elseif ($status === 'PENDING_VERIFICATION')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <svg class="w-3.5 h-3.5 mr-1 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Verifikasi
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                        Belum Bayar
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Winner Hall of Fame / Riwayat Pemenang Kloter -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                        Daftar Pemenang Undian
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Riwayat pemenang kocokan pada kloter {{ $currentGroup->name }}
                    </p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    {{ $pastDraws->count() }} Pemenang
                </span>
            </div>

            @if ($pastDraws->isEmpty())
                <div class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 text-center">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Belum ada kocokan undian yang dilakukan pada kloter ini.
                    </p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pastDraws as $draw)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-amber-500 text-slate-950 flex items-center justify-center font-extrabold text-xs shadow-sm">
                                    #{{ $draw->period->period_number }}
                                </div>
                                <div>
                                    <h5 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $draw->winningGroupMember->member->name ?? 'Pemenang' }}
                                    </h5>
                                    <p class="text-[11px] text-slate-400">
                                        Slot #{{ $draw->winningGroupMember->slot_number ?? '-' }} &bull;
                                        {{ $draw->draw_timestamp ? \Carbon\Carbon::parse($draw->draw_timestamp)->translatedFormat('d M Y H:i') : '-' }}
                                    </p>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-xs sm:text-sm font-black text-emerald-600 dark:text-emerald-400 block">
                                    Rp {{ number_format($draw->prize_amount, 0, ',', '.') }}
                                </span>
                                <span class="text-[10px] font-semibold text-slate-400">
                                    Status: {{ $draw->disbursement_status ?? 'TRANSFERRED' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
