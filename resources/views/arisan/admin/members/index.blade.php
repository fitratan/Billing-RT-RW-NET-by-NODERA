@extends('arisan.admin.layout')

@section('title', 'Kelola Anggota Arisan')

@section('content')
<div class="space-y-6">

    <!-- Top Action Banner if WhatsApp link was just generated -->
    @if (session('wa_share_url'))
        <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-100">Rincian Akun Berhasil Dibuat</h4>
                    <p class="text-xs text-white/90">Kirimkan rincian akun dan link login cepat langsung ke WhatsApp anggota.</p>
                </div>
            </div>
            <a href="{{ session('wa_share_url') }}" target="_blank"
                class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-emerald-900 bg-white hover:bg-emerald-50 transition-all shadow-sm shrink-0">
                <svg class="w-4 h-4 mr-1.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                <span>Buka WhatsApp Sekarang</span>
            </a>
        </div>
    @endif

    <!-- Header & Search & Add Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Anggota Arisan</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Daftar anggota, manajemen PIN, link akses cepat, dan integrasi WhatsApp.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openAddMemberModal()"
                class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>Tambah Anggota</span>
            </button>
        </div>
    </div>

    <!-- Search Form -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form action="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}" method="GET" class="flex items-center gap-3">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="{{ $search }}"
                    placeholder="Cari nama anggota, nomor WhatsApp, atau catatan..."
                    class="block w-full pl-10 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <button type="submit"
                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                Cari
            </button>
            @if ($search)
                <a href="{{ route('arisan.admin.members.index', ['subdomain' => $subdomain]) }}"
                    class="px-3 py-2 rounded-xl text-xs font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Member List Table / Cards -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        @if ($members->isEmpty())
            <div class="p-10 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tidak Ada Data Anggota</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ $search ? 'Tidak ada anggota yang sesuai dengan kata kunci pencarian.' : 'Belum ada anggota arisan yang ditambahkan.' }}
                </p>
                <button type="button" onclick="openAddMemberModal()"
                    class="mt-4 inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700">
                    + Tambah Anggota Pertama
                </button>
            </div>
        @else
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4">Nama Anggota</th>
                            <th class="py-3.5 px-4">Nomor WhatsApp</th>
                            <th class="py-3.5 px-4">Kloter & Slot Diikuti</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Aksi WhatsApp & Akun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                        @foreach ($members as $member)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $member->name }}</div>
                                    @if ($member->address_notes)
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-xs">{{ $member->address_notes }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300">
                                    {{ $member->phone_number }}
                                </td>
                                <td class="py-3.5 px-4">
                                    @if ($member->groupMembers->isEmpty())
                                        <span class="text-slate-400 dark:text-slate-500 text-[11px]">Belum masuk kloter</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($member->groupMembers as $gm)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    {{ $gm->group->name ?? 'Kloter' }} (Slot #{{ $gm->slot_number }})
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $member->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end space-x-1.5">
                                        <!-- 1-Click WhatsApp Button -->
                                        <a href="{{ $member->wa_share_url }}" target="_blank"
                                            title="Kirim Akun & Link Akses via WhatsApp"
                                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200/60 dark:border-emerald-900/60 transition-colors">
                                            <svg class="w-3.5 h-3.5 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                            <span>Kirim WA</span>
                                        </a>

                                        <!-- Reset PIN Button -->
                                        <button type="button" onclick="openResetPinModal({{ $member->id }}, '{{ addslashes($member->name) }}')"
                                            title="Reset PIN"
                                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 hover:bg-amber-100 dark:hover:bg-amber-900/60 border border-amber-200/60 dark:border-amber-900/60 transition-colors">
                                            <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                            <span>Reset PIN</span>
                                        </button>

                                        <!-- Regenerate Magic Token Form -->
                                        <form action="{{ route('arisan.admin.members.regenerate-token', ['subdomain' => $subdomain, 'member' => $member->id]) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" title="Generate Ulang Link Akses Cepat"
                                                class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- Delete Member -->
                                        <form action="{{ route('arisan.admin.members.destroy', ['subdomain' => $subdomain, 'member' => $member->id]) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Hapus anggota {{ addslashes($member->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Hapus Anggota"
                                                class="p-1.5 rounded-lg text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($members as $member)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $member->name }}</h3>
                                <p class="text-xs font-mono text-slate-500 dark:text-slate-400 mt-0.5">{{ $member->phone_number }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $member->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <!-- Kloter list -->
                        <div class="text-xs">
                            <span class="text-slate-400">Kloter:</span>
                            @if ($member->groupMembers->isEmpty())
                                <span class="text-slate-500">Belum masuk kloter</span>
                            @else
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach ($member->groupMembers as $gm)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            {{ $gm->group->name ?? 'Kloter' }} (Slot #{{ $gm->slot_number }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Action buttons -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2">
                            <a href="{{ $member->wa_share_url }}" target="_blank"
                                class="flex-1 inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-900/60">
                                <svg class="w-3.5 h-3.5 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                <span>Kirim WA</span>
                            </a>

                            <button type="button" onclick="openResetPinModal({{ $member->id }}, '{{ addslashes($member->name) }}')"
                                class="flex-1 inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-900/60">
                                <span>Reset PIN</span>
                            </button>

                            <form action="{{ route('arisan.admin.members.destroy', ['subdomain' => $subdomain, 'member' => $member->id]) }}" method="POST"
                                onsubmit="return confirm('Hapus anggota {{ addslashes($member->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if ($members->hasPages())
                <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                    {{ $members->links() }}
                </div>
            @endif
        @endif
    </div>

</div>

<!-- Modal: Tambah Anggota Baru -->
<div id="addMemberModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tambah Anggota Arisan</h3>
            <button type="button" onclick="closeAddMemberModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('arisan.admin.members.store', ['subdomain' => $subdomain]) }}" method="POST" class="mt-4 space-y-4">
            @csrf

            <!-- Nama Anggota -->
            <div>
                <label for="new_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="name" id="new_name" required
                    class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="Contoh: Siti Rahmawati">
            </div>

            <!-- Nomor WhatsApp -->
            <div>
                <label for="new_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Nomor WhatsApp <span class="text-rose-500">*</span>
                </label>
                <input type="tel" name="phone_number" id="new_phone" required
                    class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="Contoh: 081234567890">
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Digunakan untuk login member dan link WhatsApp.</p>
            </div>

            <!-- PIN Awal (Opsional) -->
            <div>
                <label for="new_pin" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    PIN Awal (4 Digit)
                </label>
                <input type="password" name="pin" id="new_pin" maxlength="10"
                    class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="Kosongkan untuk otomatis (4 digit akhir No HP)">
            </div>

            <!-- Catatan / Alamat -->
            <div>
                <label for="new_notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Catatan / Alamat
                </label>
                <textarea name="address_notes" id="new_notes" rows="2"
                    class="block w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="Alamat rumah atau catatan khusus..."></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeAddMemberModal()"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm">
                    Simpan & Buat Akun
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset PIN -->
<div id="resetPinModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reset PIN Anggota</h3>
            <button type="button" onclick="closeResetPinModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="resetPinForm" method="POST" class="mt-4 space-y-4">
            @csrf

            <p class="text-xs text-slate-600 dark:text-slate-300">
                Reset PIN untuk anggota: <span id="resetMemberName" class="font-bold text-slate-900 dark:text-white"></span>
            </p>

            <div>
                <label for="modal_new_pin" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    PIN Baru (Minimal 4 Karakter/Digit) <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="new_pin" id="modal_new_pin" minlength="4" maxlength="10" required
                    class="block w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono tracking-widest text-center"
                    placeholder="Contoh: 1234 atau 8899">
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeResetPinModal()"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 shadow-sm">
                    Simpan PIN Baru
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddMemberModal() {
        document.getElementById('addMemberModal').classList.remove('hidden');
    }

    function closeAddMemberModal() {
        document.getElementById('addMemberModal').classList.add('hidden');
    }

    function openResetPinModal(memberId, memberName) {
        const modal = document.getElementById('resetPinModal');
        const form = document.getElementById('resetPinForm');
        const nameSpan = document.getElementById('resetMemberName');

        nameSpan.textContent = memberName;
        form.action = `/arisan-app/{{ $subdomain }}/admin/members/${memberId}/reset-pin`;

        modal.classList.remove('hidden');
    }

    function closeResetPinModal() {
        document.getElementById('resetPinModal').classList.add('hidden');
    }
</script>
@endsection
