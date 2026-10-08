@extends('arisan.admin.layout')

@section('title', 'Daftar Kloter Arisan')

@section('content')
<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Kloter Arisan</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola grup arisan, kuota slot peserta, dan jadwal putaran.</p>
        </div>
        <div>
            <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Buat Kloter Baru</span>
            </a>
        </div>
    </div>

    <!-- Group List Grid -->
    @if ($groups->isEmpty())
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-10 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Kloter Arisan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Mulai buat kloter arisan pertama Anda untuk menentukan nominal iuran, jumlah slot, dan jadwal penarikan.
            </p>
            <div class="mt-4">
                <a href="{{ route('arisan.admin.groups.create', ['subdomain' => $subdomain]) }}"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
                    + Buat Kloter Baru
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($groups as $group)
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm hover:border-emerald-500/40 transition-all flex flex-col justify-between">
                    <div>
                        <!-- Title & Status Badge -->
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white truncate">
                                {{ $group->name }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 {{ $group->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300' }}">
                                {{ $group->status === 'ACTIVE' ? 'Aktif' : ($group->status === 'COMPLETED' ? 'Selesai' : 'Dibatalkan') }}
                            </span>
                        </div>

                        <!-- Stats & Info -->
                        <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300 py-2 border-y border-slate-100 dark:border-slate-800/80 my-3">
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Tipe Periode:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $group->period_type === 'MONTHLY' ? 'Bulanan' : ($group->period_type === 'WEEKLY' ? 'Mingguan' : 'Harian') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Iuran per Slot:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format($group->dues_amount, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Total Hadiah per Putaran:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    Rp {{ number_format($group->dues_amount * $group->total_slots, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-slate-400">Keterisian Slot:</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                                        <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min(100, round(($group->group_members_count / max(1, $group->total_slots)) * 100)) }}%"></div>
                                    </div>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $group->group_members_count }} / {{ $group->total_slots }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Jadwal Undian:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    Tiap tgl {{ $group->draw_day }} (Tempo: tgl {{ $group->due_day }})
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="pt-3 flex items-center justify-between gap-2">
                        <a href="{{ route('arisan.admin.groups.show', ['subdomain' => $subdomain, 'group' => $group->id]) }}"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Kelola Slot & Detail</span>
                        </a>

                        <a href="{{ route('arisan.admin.groups.edit', ['subdomain' => $subdomain, 'group' => $group->id]) }}"
                            class="p-2 rounded-xl text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                            title="Edit Kloter">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </a>

                        <form action="{{ route('arisan.admin.groups.destroy', ['subdomain' => $subdomain, 'group' => $group->id]) }}" method="POST"
                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus kloter {{ $group->name }}? Semua data putaran dan slot akan terhapus.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="p-2 rounded-xl text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors"
                                title="Hapus Kloter">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
