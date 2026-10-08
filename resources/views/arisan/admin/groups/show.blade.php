@extends('arisan.admin.layout')

@section('title', 'Detail Kloter — ' . $group->name)

@section('content')
<div class="space-y-6">

    <!-- Top Breadcrumb & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                class="p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex items-center space-x-2">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $group->name }}</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $group->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300' }}">
                        {{ $group->status === 'ACTIVE' ? 'Aktif' : 'Selesai' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Periode: {{ $group->period_type === 'MONTHLY' ? 'Bulanan' : ($group->period_type === 'WEEKLY' ? 'Mingguan' : 'Harian') }} &bull;
                    Iuran: Rp {{ number_format($group->dues_amount, 0, ',', '.') }} / slot &bull;
                    Total Slot: {{ $group->total_slots }}
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <button type="button" onclick="openAssignModal()"
                class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>Isi Slot Anggota</span>
            </button>
            <a href="{{ route('arisan.admin.groups.edit', ['subdomain' => $subdomain, 'group' => $group->id]) }}"
                class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
                <span>Edit Kloter</span>
            </a>
        </div>
    </div>

    <!-- Group Information Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pot Hadiah per Putaran</span>
            <div class="text-base sm:text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                Rp {{ number_format($group->dues_amount * $group->total_slots, 0, ',', '.') }}
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">Iuran &times; Total {{ $group->total_slots }} slot</p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Keterisian Slot</span>
            <div class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mt-1">
                {{ $group->groupMembers->count() }} / {{ $group->total_slots }}
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">{{ $group->total_slots - $group->groupMembers->count() }} slot kosong tersisa</p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Jadwal Penarikan</span>
            <div class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mt-1">
                Tgl {{ $group->draw_day }}
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">Jatuh tempo tgl {{ $group->due_day }}</p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Fee Pengelola</span>
            <div class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mt-1">
                Rp {{ number_format($group->admin_fee_per_period, 0, ',', '.') }}
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">Dipotong saat undian</p>
        </div>
    </div>

    <!-- Section 1: Slot Matrix Grid -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Matriks Slot Peserta</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daftar pemegang nomor slot dalam kloter arisan ini.</p>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                {{ $group->groupMembers->count() }} dari {{ $group->total_slots }} Terisi
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
            @for ($slot = 1; $slot <= $group->total_slots; $slot++)
                @php
                    $groupMember = $groupMembersBySlot->get($slot);
                @endphp

                <div class="p-4 rounded-xl border {{ $groupMember ? 'border-emerald-200/90 dark:border-emerald-900/60 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-dashed border-slate-300 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40' }} flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="w-7 h-7 rounded-lg {{ $groupMember ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} flex items-center justify-center text-xs shadow-sm">
                            #{{ $slot }}
                        </div>

                        @if ($groupMember)
                            @if ($groupMember->has_won)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    Pemenang Putaran {{ $groupMember->won_period_id ? ($groupMember->wonPeriod->period_number ?? '') : '' }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-100/70 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                                    Belum Menang
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                Kosong
                            </span>
                        @endif
                    </div>

                    @if ($groupMember)
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                {{ $groupMember->member->name ?? 'Anggota' }}
                            </h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                {{ $groupMember->member->phone_number ?? '-' }}
                            </p>
                        </div>

                        <div class="pt-2 border-t border-emerald-100 dark:border-emerald-950 flex items-center justify-end">
                            @if (!$groupMember->has_won)
                                <form action="{{ route('arisan.admin.groups.remove-slot', ['subdomain' => $subdomain, 'group' => $group->id, 'groupMember' => $groupMember->id]) }}" method="POST"
                                    onsubmit="return confirm('Lepas anggota {{ $groupMember->member->name ?? '' }} dari Slot #{{ $slot }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-[11px] font-medium text-rose-600 dark:text-rose-400 hover:text-rose-700 hover:underline">
                                        Lepas Slot
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">Telah Menang</span>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-1">
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-2">Slot belum terisi</p>
                            <button type="button" onclick="openAssignModal({{ $slot }})"
                                class="w-full inline-flex items-center justify-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-950/60 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 transition-colors">
                                + Isi Slot Ini
                            </button>
                        </div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <!-- Section 2: Generated Periods List -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Periode Putaran</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Jadwal penagihan iuran dan pengundian pemenang tiap putaran.</p>
            </div>
            <a href="/arisan-app/{{ $subdomain }}/admin/payments"
                class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                Kelola Pembayaran &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-y border-slate-200/80 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-3">Putaran</th>
                        <th class="py-3 px-3">Tanggal Periode</th>
                        <th class="py-3 px-3">Jatuh Tempo</th>
                        <th class="py-3 px-3">Jadwal Undian</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Pemenang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                    @foreach ($group->periods as $period)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="py-3 px-3 font-bold text-slate-900 dark:text-white">
                                Putaran {{ $period->period_number }}
                            </td>
                            <td class="py-3 px-3">
                                {{ $period->period_date ? \Carbon\Carbon::parse($period->period_date)->format('M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                {{ $period->due_date ? \Carbon\Carbon::parse($period->due_date)->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                {{ $period->draw_date ? \Carbon\Carbon::parse($period->draw_date)->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $period->status === 'COMPLETED' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                    {{ $period->status === 'COMPLETED' ? 'Selesai' : 'Pengumpulan Iuran' }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @if ($period->draw)
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ $period->draw->winningGroupMember->member->name ?? 'Pemenang' }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">Belum diundi</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Assign Member to Slot -->
<div id="assignModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Isi Slot Anggota</h3>
            <button type="button" onclick="closeAssignModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('arisan.admin.groups.assign-slot', ['subdomain' => $subdomain, 'group' => $group->id]) }}" method="POST" class="mt-4 space-y-4">
            @csrf

            <!-- Nomor Slot -->
            <div>
                <label for="modal_slot_number" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Nomor Slot <span class="text-rose-500">*</span>
                </label>
                <select name="slot_number" id="modal_slot_number" required
                    class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    @for ($s = 1; $s <= $group->total_slots; $s++)
                        @if (!$groupMembersBySlot->has($s))
                            <option value="{{ $s }}">Slot #{{ $s }} (Kosong)</option>
                        @endif
                    @endfor
                </select>
            </div>

            <!-- Pilih Anggota -->
            <div>
                <label for="modal_member_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Pilih Anggota <span class="text-rose-500">*</span>
                </label>
                @if ($availableMembers->isEmpty())
                    <p class="text-xs text-amber-600 dark:text-amber-400 mb-2">Belum ada data anggota arisan.</p>
                    <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                        class="inline-block text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        + Tambah Anggota Terlebih Dahulu
                    </a>
                @else
                    <select name="member_id" id="modal_member_id" required
                        class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">-- Pilih Anggota --</option>
                        @foreach ($availableMembers as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->phone_number }})</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeAssignModal()"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                    Simpan ke Slot
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssignModal(slotNumber = null) {
        const modal = document.getElementById('assignModal');
        const select = document.getElementById('modal_slot_number');
        if (slotNumber && select) {
            select.value = slotNumber;
        }
        modal.classList.remove('hidden');
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.add('hidden');
    }
</script>
@endsection
