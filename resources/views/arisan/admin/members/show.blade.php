@extends('arisan.admin.layout')

@section('title', 'Detail Anggota — ' . $member->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Breadcrumb & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                class="p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $member->name }}</h2>
                <p class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $member->phone_number }}</p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <a href="{{ $waShareUrl }}" target="_blank"
                class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 border border-emerald-200/60 dark:border-emerald-900/60 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                <span>Kirim Akun ke WA</span>
            </a>
        </div>
    </div>

    <!-- Member Details & Kloter Information -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Informasi Anggota</h3>
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-slate-400">Nama Lengkap:</span>
                    <p class="font-semibold text-slate-900 dark:text-white text-sm mt-0.5">{{ $member->name }}</p>
                </div>
                <div>
                    <span class="text-slate-400">Nomor WhatsApp:</span>
                    <p class="font-mono font-semibold text-slate-900 dark:text-white mt-0.5">{{ $member->phone_number }}</p>
                </div>
                <div>
                    <span class="text-slate-400">Catatan / Alamat:</span>
                    <p class="text-slate-700 dark:text-slate-300 mt-0.5">{{ $member->address_notes ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-slate-400">Status Keaktifan:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $member->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }} ml-2">
                        {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>

            <!-- Regenerate Magic Token Form -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                <form action="{{ route('arisan.admin.members.regenerate-token', ['subdomain' => $subdomain, 'member' => $member->id]) }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Generate Link Login Cepat</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="md:col-span-2 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Kloter & Slot yang Diikuti</h3>
            @if ($member->groupMembers->isEmpty())
                <div class="py-6 text-center text-xs text-slate-400">
                    Anggota ini belum dimasukkan ke slot kloter manapun.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($member->groupMembers as $gm)
                        <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $gm->group->name ?? 'Kloter' }}</h4>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        Slot #{{ $gm->slot_number }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Iuran: Rp {{ number_format($gm->group->dues_amount ?? 0, 0, ',', '.') }} &bull;
                                    Status: {{ $gm->has_won ? 'Sudah Menang' : 'Belum Menang' }}
                                </p>
                            </div>
                            <a href="{{ route('arisan.admin.groups.show', ['subdomain' => $subdomain, 'group' => $gm->group_id]) }}"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 transition-colors">
                                Lihat Kloter &rarr;
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
