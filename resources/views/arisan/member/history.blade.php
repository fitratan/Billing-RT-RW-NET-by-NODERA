@extends('arisan.member.layout')

@section('title', 'Riwayat Pembayaran Iuran')

@section('content')
<div class="space-y-5">

    <!-- Page Title -->
    <div>
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white">
            Riwayat Pembayaran Iuran
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Daftar mutasi setoran iuran pada setiap putaran arisan Anda.
        </p>
    </div>

    <!-- Summary Stats Grid -->
    <div class="grid grid-cols-3 gap-2.5 sm:gap-4">
        <!-- Lunas -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <span class="text-[10px] sm:text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">
                Total Lunas
            </span>
            <div class="text-sm sm:text-lg font-black text-slate-900 dark:text-white mt-1">
                Rp {{ number_format($totalPaid, 0, ',', '.') }}
            </div>
        </div>

        <!-- Menunggu Verifikasi -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <span class="text-[10px] sm:text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">
                Verifikasi
            </span>
            <div class="text-sm sm:text-lg font-black text-slate-900 dark:text-white mt-1">
                Rp {{ number_format($totalPending, 0, ',', '.') }}
            </div>
        </div>

        <!-- Belum Bayar -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <span class="text-[10px] sm:text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider block">
                Belum Bayar
            </span>
            <div class="text-sm sm:text-lg font-black text-slate-900 dark:text-white mt-1">
                Rp {{ number_format($totalUnpaid, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
        <!-- Slot Selector if multiple slots -->
        @if ($slots->count() > 1)
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    Pilih Slot Arisan:
                </label>
                <div class="flex flex-wrap gap-1.5">
                    <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'status' => $statusFilter]) }}"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors {{ empty($selectedSlotId) ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                        Semua Slot
                    </a>
                    @foreach ($slots as $s)
                        <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $s->id, 'status' => $statusFilter]) }}"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors {{ (string) $selectedSlotId === (string) $s->id ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                            {{ $s->group->name }} (Slot #{{ $s->slot_number }})
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Status Filter Tabs -->
        <div class="flex items-center space-x-1 border-t border-slate-100 dark:border-slate-800 pt-2.5 overflow-x-auto">
            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $selectedSlotId, 'status' => 'ALL']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'ALL' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Semua Status
            </a>
            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $selectedSlotId, 'status' => 'PAID']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'PAID' ? 'bg-emerald-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Lunas
            </a>
            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $selectedSlotId, 'status' => 'PENDING_VERIFICATION']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'PENDING_VERIFICATION' ? 'bg-amber-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Menunggu Verifikasi
            </a>
            <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain, 'slot_id' => $selectedSlotId, 'status' => 'UNPAID']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $statusFilter === 'UNPAID' ? 'bg-rose-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Belum Bayar
            </a>
        </div>
    </div>

    <!-- Payments List -->
    <div class="space-y-3">
        @if ($payments->isEmpty())
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-slate-200/80 dark:border-slate-800 text-center shadow-sm">
                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400 mb-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Tidak Ada Catatan Pembayaran</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Tidak ditemukan data tagihan atau riwayat dengan filter yang dipilih.
                </p>
            </div>
        @else
            @foreach ($payments as $payment)
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3 transition-all hover:border-emerald-500/40">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    Putaran #{{ $payment->period->period_number }}
                                </span>
                                <span class="text-xs font-semibold text-slate-900 dark:text-white">
                                    {{ $payment->period->group->name }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Slot #{{ $payment->groupMember->slot_number }} &bull;
                                Tanggal: {{ $payment->period->period_date ? \Carbon\Carbon::parse($payment->period->period_date)->translatedFormat('d M Y') : '-' }}
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-sm sm:text-base font-black text-slate-900 dark:text-white block">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </span>
                            @if ($payment->status === 'PAID')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    <svg class="w-3 h-3 mr-1 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Lunas
                                </span>
                            @elseif ($payment->status === 'PENDING_VERIFICATION')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    <svg class="w-3 h-3 mr-1 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Menunggu Verifikasi
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                    Belum Bayar
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Payment Metadata & Actions -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between text-xs gap-2">
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 space-y-0.5">
                            @if ($payment->status === 'PAID')
                                <div>Metode: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $payment->payment_method ?? 'Transfer' }}</span></div>
                                @if ($payment->verified_by_admin_at)
                                    <div>Diverifikasi: {{ \Carbon\Carbon::parse($payment->verified_by_admin_at)->translatedFormat('d M Y H:i') }}</div>
                                @endif
                            @elseif ($payment->status === 'PENDING_VERIFICATION')
                                <div>Metode: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $payment->payment_method ?? 'Transfer' }}</span></div>
                                <div>Waktu Upload: {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->translatedFormat('d M Y H:i') : '-' }}</div>
                            @else
                                <div>Jatuh Tempo: {{ $payment->period->due_date ? \Carbon\Carbon::parse($payment->period->due_date)->translatedFormat('d F Y') : '-' }}</div>
                            @endif
                            @if ($payment->notes)
                                <div class="text-amber-600 dark:text-amber-400 italic">Catatan: {{ $payment->notes }}</div>
                            @endif
                        </div>

                        <div class="flex items-center space-x-2">
                            @if ($payment->proof_image)
                                <a href="{{ asset('storage/' . $payment->proof_image) }}" target="_blank"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Bukti</span>
                                </a>
                            @endif

                            @if ($payment->status !== 'PAID')
                                <a href="{{ route('arisan.member.pay', ['subdomain' => $subdomain, 'payment' => $payment->id]) }}"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    <span>{{ $payment->status === 'PENDING_VERIFICATION' ? 'Ganti Bukti' : 'Bayar' }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Pagination -->
            <div class="pt-2">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
