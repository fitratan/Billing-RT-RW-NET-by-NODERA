@extends('arisan.admin.layout')

@section('title', 'Undian Putaran — ' . ($currentGroup ? $currentGroup->name : 'Digital Draw'))

@section('content')
<div class="space-y-6">

    <!-- Top Header & Group Selector -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 uppercase tracking-wider">
                        Sistem Undian Fair-Play
                    </span>
                    <span class="text-xs text-slate-400">Pengacakan Kriptografis</span>
                </div>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white mt-1">
                    Undian Putaran & Pemenang
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Kocok pemenang arisan secara acak, transparan, dan anti-duplikasi.
                </p>
            </div>

            <!-- Group Selector Form -->
            @if ($groups->isNotEmpty())
                <form action="{{ route('arisan.admin.draws.index', ['subdomain' => $subdomain]) }}" method="GET" class="flex items-center gap-2">
                    <label for="group_select" class="text-xs font-semibold text-slate-600 dark:text-slate-300 shrink-0">Kloter:</label>
                    <select id="group_select" name="group_id" onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}" {{ $currentGroup && $currentGroup->id === $g->id ? 'selected' : '' }}>
                                {{ $g->name }} ({{ $g->group_members_count }} Peserta)
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($currentGroup && $allPeriods->isNotEmpty())
            <!-- Horizontal Period Selector Pills -->
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center space-x-2 overflow-x-auto pb-1 scrollbar-thin">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0 mr-1">Putaran:</span>
                @foreach ($allPeriods as $p)
                    @php
                        $isSelected = $currentPeriod && $currentPeriod->id === $p->id;
                        $isPeriodDrawn = ($p->status === 'DRAWN') || ($p->draw !== null);
                    @endphp
                    <a href="{{ route('arisan.admin.draws.index', ['subdomain' => $subdomain, 'group_id' => $currentGroup->id, 'period_id' => $p->id, 'paid_only' => request('paid_only')]) }}"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold shrink-0 transition-all flex items-center space-x-1.5 border {{ $isSelected ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        <span>Putaran {{ $p->period_number }}</span>
                        @if ($isPeriodDrawn)
                            <span class="inline-block w-2 h-2 rounded-full {{ $isSelected ? 'bg-amber-300' : 'bg-amber-500' }}" title="Sudah Diundi"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if (!$currentGroup)
        <!-- Empty State No Groups -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-8 text-center shadow-sm">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Kloter Arisan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Silakan buat kloter arisan terlebih dahulu untuk mulai menjadwalkan dan mengundi pemenang.
            </p>
            <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                class="mt-4 inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                + Buat Kloter Baru
            </a>
        </div>
    @elseif ($isDrawn && $draw)
        <!-- ==================== RESULT SHOWCASE: ALREADY DRAWN ==================== -->
        <div class="space-y-6">
            <!-- Winner Showcase Banner -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-emerald-900/20 border border-emerald-400/30">
                <!-- Background decorative shapes -->
                <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-emerald-400/10 blur-2xl"></div>
                <div class="absolute -left-10 -bottom-10 w-48 h-48 rounded-full bg-amber-400/10 blur-2xl"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-3">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-400/20 text-amber-200 border border-amber-400/30 backdrop-blur-sm">
                            <svg class="w-4 h-4 text-amber-300 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            <span>HASIL RESMI UNDIAN PUTARAN #{{ $currentPeriod->period_number }}</span>
                        </div>

                        <div>
                            <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                                {{ $draw->winningGroupMember->member->name ?? 'Pemenang' }}
                            </h3>
                            <p class="text-sm font-semibold text-emerald-200 mt-1">
                                Kloter {{ $currentGroup->name }} &bull; Slot Nomor #{{ $draw->winningGroupMember->slot_number }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-baseline gap-2 pt-2">
                            <span class="text-xs text-slate-200 uppercase tracking-wider font-semibold">Total Hadiah:</span>
                            <span class="text-2xl sm:text-3xl font-black text-amber-300">
                                Rp {{ number_format($draw->prize_amount, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Right Share & Status Actions -->
                    <div class="flex flex-col sm:flex-row md:flex-col gap-3 shrink-0">
                        <a href="{{ $waGroupShareUrl }}" target="_blank"
                            class="inline-flex items-center justify-center px-5 py-3 rounded-2xl text-xs font-bold text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition-transform active:scale-95 shadow-lg shadow-emerald-950/40">
                            <svg class="w-4 h-4 mr-2 text-slate-900" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                            <span>Share Hasil ke Grup WA</span>
                        </a>

                        <button type="button" onclick="copyWaText()"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl text-xs font-semibold text-white bg-white/10 hover:bg-white/20 border border-white/20 transition-colors">
                            <svg class="w-4 h-4 mr-1.5 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            <span id="copyBtnText">Salin Teks Pengumuman</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Audit & Disbursement Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Audit Log & Verifikasi Undian -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Audit & Bukti Kriptografis
                    </h4>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Waktu Pengundian</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ \Carbon\Carbon::parse($draw->draw_timestamp)->translatedFormat('l, d F Y - H:i:s') }} WIB
                            </span>
                        </div>

                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">Nomor Telepon</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">
                                {{ $draw->winningGroupMember->member->phone_number ?? '-' }}
                            </span>
                        </div>

                        <div class="py-2 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-slate-500 dark:text-slate-400">Seed Hash Verifikasi (SHA-256)</span>
                                <button type="button" onclick="copySeedHash('{{ $draw->draw_seed_hash }}')" class="text-[10px] text-emerald-600 dark:text-emerald-400 hover:underline">
                                    Salin Hash
                                </button>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 font-mono text-[11px] text-slate-700 dark:text-slate-300 break-all select-all">
                                {{ $draw->draw_seed_hash ?? 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <!-- Text Preview of WhatsApp Announcement -->
                    <div class="mt-4">
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Pratinjau Pesan WA Grup:</label>
                        <textarea id="waShareTextarea" readonly rows="5"
                            class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[11px] text-slate-700 dark:text-slate-300 font-sans focus:outline-none">{{ $waGroupShareText }}</textarea>
                    </div>
                </div>

                <!-- Right: Status Pencairan Hadiah (Disbursement) -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Pencairan Hadiah Pemenang
                        </h4>
                        @if ($draw->disbursement_status === 'TRANSFERRED')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                Sudah Ditransfer
                            </span>
                        @elseif ($draw->disbursement_status === 'HANDED_CASH')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300">
                                Diserahkan Tunai
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                Menunggu Pencairan
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Perbarui status transfer atau penyerahan dana hadiah kepada pemenang undian.
                    </p>

                    <form action="{{ route('arisan.admin.draws.disbursement', ['subdomain' => $subdomain, 'draw' => $draw->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Status Pencairan:</label>
                            <select name="disbursement_status"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                                <option value="PENDING" {{ $draw->disbursement_status === 'PENDING' ? 'selected' : '' }}>PENDING (Belum Ditransfer)</option>
                                <option value="TRANSFERRED" {{ $draw->disbursement_status === 'TRANSFERRED' ? 'selected' : '' }}>TRANSFERRED (Transfer Bank / E-Wallet)</option>
                                <option value="HANDED_CASH" {{ $draw->disbursement_status === 'HANDED_CASH' ? 'selected' : '' }}>HANDED_CASH (Diserahkan Tunai Langsung)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Upload Bukti Transfer / Penyerahan (Opsional):</label>
                            <input type="file" name="disbursement_proof" accept="image/*,.pdf"
                                class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        </div>

                        @if ($draw->disbursement_proof)
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <div class="flex items-center space-x-2 truncate">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-xs text-slate-700 dark:text-slate-300 truncate">Bukti Pencairan Tersimpan</span>
                                </div>
                                <a href="{{ Storage::url($draw->disbursement_proof) }}" target="_blank"
                                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline shrink-0">
                                    Lihat File &rarr;
                                </a>
                            </div>
                        @endif

                        <button type="submit"
                            class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-700 transition-colors">
                            Simpan Perubahan Status
                        </button>
                    </form>
                </div>
            </div>
        </div>

    @else
        <!-- ==================== PERIOD NOT DRAWN YET: INTERACTIVE ROULETTE ==================== -->
        <div class="space-y-6">

            <!-- Filter & Mode Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-sm">
                        {{ $currentPeriod ? $currentPeriod->period_number : 1 }}
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Undian Putaran {{ $currentPeriod ? $currentPeriod->period_number : 1 }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Total Kandidat Tersedia: <span class="font-bold text-slate-800 dark:text-slate-200">{{ $candidates->count() }} Peserta</span>
                        </p>
                    </div>
                </div>

                <!-- Toggle: Hanya yang Lunas -->
                <form id="filterForm" action="{{ route('arisan.admin.draws.index', ['subdomain' => $subdomain]) }}" method="GET" class="flex items-center space-x-2">
                    <input type="hidden" name="group_id" value="{{ $currentGroup->id }}">
                    <input type="hidden" name="period_id" value="{{ $currentPeriod ? $currentPeriod->id : '' }}">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="paid_only" value="1" {{ $paidOnly ? 'checked' : '' }} onchange="this.form.submit()" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                        <span class="ml-2 text-xs font-medium text-slate-700 dark:text-slate-300 select-none">
                            Hanya sertakan peserta yang sudah lunas iuran
                        </span>
                    </label>
                </form>
            </div>

            <!-- Digital Draw Stage (Roulette Machine) -->
            <div class="bg-gradient-to-b from-slate-900 via-slate-950 to-slate-900 rounded-3xl p-6 sm:p-10 text-white shadow-2xl border border-slate-800 relative overflow-hidden text-center">
                <!-- Canvas for confetti animation -->
                <canvas id="confettiCanvas" class="absolute inset-0 pointer-events-none z-20 w-full h-full"></canvas>

                <div class="relative z-10 max-w-lg mx-auto space-y-6">
                    <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span>MESIN PENGUNDI DIGITAL</span>
                    </div>

                    <!-- Display Showcase Box -->
                    <div id="drawDisplayBox" class="bg-slate-800/80 border-2 border-emerald-500/40 rounded-3xl p-6 sm:p-8 backdrop-blur-md shadow-inner transition-all duration-150 transform">
                        <div id="displaySlotBadge" class="inline-block px-4 py-1 rounded-xl text-xs font-black bg-amber-400 text-slate-950 uppercase tracking-wider mb-3">
                            PUTARAN #{{ $currentPeriod ? $currentPeriod->period_number : 1 }}
                        </div>
                        <div id="displayCandidateName" class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight truncate">
                            Siap Mengundi
                        </div>
                        <div id="displayCandidateSub" class="text-xs text-slate-400 mt-2 font-mono">
                            {{ $candidates->count() }} Peserta terdaftar dalam putaran ini
                        </div>
                    </div>

                    <!-- Prize Pool info -->
                    <div class="p-3 rounded-2xl bg-slate-800/40 border border-slate-700/60 flex items-center justify-between text-xs">
                        <span class="text-slate-400">Total Hadiah Putaran:</span>
                        <span class="font-bold text-amber-300 text-sm">Rp {{ number_format($prizeAmount, 0, ',', '.') }}</span>
                    </div>

                    <!-- Action Trigger Button -->
                    @if ($candidates->isEmpty())
                        <div class="p-4 rounded-2xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-xs font-medium">
                            @if ($paidOnly)
                                Tidak ada peserta yang telah melunasi iuran pada putaran ini. Nonaktifkan filter lunas atau lakukan verifikasi pembayaran terlebih dahulu.
                            @else
                                Semua peserta dalam kloter ini telah memenangkan undian pada putaran sebelumnya.
                            @endif
                        </div>
                    @else
                        <button type="button" id="startDrawBtn" onclick="startDigitalDraw()"
                            class="w-full py-4 px-6 rounded-2xl text-sm font-black text-slate-950 bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-400 hover:from-amber-300 hover:to-yellow-300 transition-all transform active:scale-95 shadow-xl shadow-amber-500/20 uppercase tracking-wider flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5 text-slate-950 animate-spin" id="btnSpinIcon" style="display:none;" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="btnText">Putar Undian Sekarang</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Candidate Grid List -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                            Daftar Peserta Layak Undi
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Peserta yang belum pernah menang di kloter ini
                        </p>
                    </div>
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ $candidates->count() }} Slot Layak
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse ($candidates as $candidate)
                        @php
                            $payment = $candidate->payments->firstWhere('period_id', $currentPeriod->id);
                            $isPaid = $payment && $payment->status === 'PAID';
                        @endphp
                        <div class="p-3.5 rounded-xl border {{ $isPaid ? 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/30 dark:bg-emerald-950/20' : 'border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30' }} flex items-center justify-between">
                            <div class="min-w-0 pr-2">
                                <div class="flex items-center space-x-2">
                                    <span class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-center shrink-0">
                                        {{ $candidate->slot_number }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                        {{ $candidate->member->name ?? 'Peserta #' . $candidate->slot_number }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 pl-8 font-mono">
                                    {{ $candidate->member->phone_number ?? '-' }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold shrink-0 {{ $isPaid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ $isPaid ? 'Lunas' : 'Belum Lunas' }}
                            </span>
                        </div>
                    @empty
                        <div class="col-span-full py-6 text-center text-xs text-slate-400">
                            Tidak ada peserta dalam daftar ini.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- ==================== WINNER CONFIRMATION MODAL ==================== -->
        <div id="winnerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm hidden">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 transform transition-all text-center">
                <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>

                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Pemenang Terpilih</span>
                    <h3 id="modalWinnerName" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                        -
                    </h3>
                    <p id="modalWinnerSlot" class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-0.5">
                        Slot #-
                    </p>
                </div>

                <form id="executeDrawForm" action="{{ route('arisan.admin.draws.execute', ['subdomain' => $subdomain, 'period' => $currentPeriod ? $currentPeriod->id : 0]) }}" method="POST" class="space-y-4 text-left">
                    @csrf
                    <input type="hidden" name="winning_group_member_id" id="modalWinningGroupMemberId" value="">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nominal Hadiah Pemenang (Rp):</label>
                        <input type="number" name="prize_amount" id="modalPrizeAmount" value="{{ $prizeAmount }}" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">Otomatis mencatat pengeluaran di Buku Kas saat dikunci.</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="button" onclick="closeWinnerModal()"
                            class="w-1/3 py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                            class="w-2/3 py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/30 transition-colors">
                            Kunci & Simpan Pemenang
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @endif

</div>
@endsection

@push('scripts')
<script>
    const candidates = @json($candidatesJson);
    let isDrawing = false;
    let audioCtx = null;

    function playTick() {
        try {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(600, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(120, audioCtx.currentTime + 0.04);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.04);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.05);
        } catch (e) {}
    }

    function startDigitalDraw() {
        if (isDrawing || candidates.length === 0) return;

        isDrawing = true;
        const btn = document.getElementById('startDrawBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinIcon = document.getElementById('btnSpinIcon');
        const displayBox = document.getElementById('drawDisplayBox');
        const displayName = document.getElementById('displayCandidateName');
        const displaySlot = document.getElementById('displaySlotBadge');
        const displaySub = document.getElementById('displayCandidateSub');

        if (btnText) btnText.innerText = 'Mengacak Pemenang...';
        if (btnSpinIcon) btnSpinIcon.style.display = 'inline-block';
        if (btn) btn.disabled = true;

        let index = 0;
        let speed = 50;
        let elapsed = 0;
        const totalDuration = 3500; // 3.5 seconds

        function shuffle() {
            const current = candidates[index % candidates.length];
            index++;
            elapsed += speed;

            displayName.innerText = current.member_name;
            displaySlot.innerText = 'Slot #' + current.slot_number;
            displaySub.innerText = current.phone_number || '-';

            playTick();

            if (elapsed < totalDuration) {
                if (elapsed > totalDuration * 0.6) {
                    speed += 25; // Slow down gradually
                }
                setTimeout(shuffle, speed);
            } else {
                // Final winner selection
                const winner = candidates[Math.floor(Math.random() * candidates.length)];
                displayName.innerText = winner.member_name;
                displaySlot.innerText = 'Slot #' + winner.slot_number;
                displaySub.innerText = winner.phone_number || '-';

                displayBox.classList.add('ring-4', 'ring-amber-400', 'scale-105');

                launchConfetti();

                setTimeout(() => {
                    openWinnerModal(winner);
                    isDrawing = false;
                    if (btnText) btnText.innerText = 'Putar Undian Sekarang';
                    if (btnSpinIcon) btnSpinIcon.style.display = 'none';
                    if (btn) btn.disabled = false;
                }, 800);
            }
        }

        shuffle();
    }

    function openWinnerModal(winner) {
        document.getElementById('modalWinnerName').innerText = winner.member_name;
        document.getElementById('modalWinnerSlot').innerText = 'Kloter {{ $currentGroup ? $currentGroup->name : "" }} &bull; Slot Nomor #' + winner.slot_number;
        document.getElementById('modalWinningGroupMemberId').value = winner.id;
        document.getElementById('winnerModal').classList.remove('hidden');
    }

    function closeWinnerModal() {
        document.getElementById('winnerModal').classList.add('hidden');
    }

    function copyWaText() {
        const textarea = document.getElementById('waShareTextarea');
        if (textarea) {
            textarea.select();
            document.execCommand('copy');
            const btnText = document.getElementById('copyBtnText');
            if (btnText) {
                btnText.innerText = 'Tersalin!';
                setTimeout(() => {
                    btnText.innerText = 'Salin Teks Pengumuman';
                }, 2000);
            }
        }
    }

    function copySeedHash(hash) {
        navigator.clipboard.writeText(hash).then(() => {
            alert('Seed Hash verifikasi berhasil disalin.');
        });
    }

    // Lightweight Confetti Animation on HTML5 Canvas
    function launchConfetti() {
        const canvas = document.getElementById('confettiCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        canvas.width = canvas.parentElement.offsetWidth;
        canvas.height = canvas.parentElement.offsetHeight;

        const pieces = [];
        const colors = ['#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#8b5cf6'];

        for (let i = 0; i < 80; i++) {
            pieces.push({
                x: canvas.width / 2,
                y: canvas.height / 2,
                w: Math.random() * 8 + 4,
                h: Math.random() * 8 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                vx: (Math.random() - 0.5) * 12,
                vy: (Math.random() - 0.7) * 12,
                g: 0.25,
                rot: Math.random() * 360,
                vrot: (Math.random() - 0.5) * 10
            });
        }

        let frames = 0;
        function render() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            frames++;

            pieces.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += p.g;
                p.rot += p.vrot;

                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate((p.rot * Math.PI) / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });

            if (frames < 90) {
                requestAnimationFrame(render);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        render();
    }
</script>
@endpush
