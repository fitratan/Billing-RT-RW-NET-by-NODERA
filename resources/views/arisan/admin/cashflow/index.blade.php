@extends('arisan.admin.layout')

@section('title', 'Buku Kas & Arus Keuangan')

@section('content')
<div class="space-y-6">

    <!-- Top Header & Add Transaction Action -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300 uppercase tracking-wider">
                        Buku Kas Utama
                    </span>
                    <span class="text-xs text-slate-400">Pencatatan Keuangan Transparan</span>
                </div>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white mt-1">
                    Buku Kas & Arus Kas
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Rekap mutasi pemasukan iuran, pencairan hadiah pemenang, dan biaya operasional.
                </p>
            </div>

            <button type="button" onclick="openCashflowModal()"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm shrink-0">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Catat Transaksi Kas</span>
            </button>
        </div>
    </div>

    <!-- Summary Stats Ledger Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Saldo Kas Saat Ini -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Saldo Kas Bersih</span>
                <div class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold {{ $netBalance >= 0 ? 'text-slate-900 dark:text-white' : 'text-rose-600' }} truncate">
                Rp {{ number_format($netBalance, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Total kas tersedia saat ini
            </div>
        </div>

        <!-- Card 2: Total Kas Masuk -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Pemasukan</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 truncate">
                Rp {{ number_format($totalIn, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Iuran & kas masuk terverifikasi
            </div>
        </div>

        <!-- Card 3: Total Kas Keluar -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Pengeluaran</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                    </svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 truncate">
                Rp {{ number_format($totalOut, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Pencairan hadiah & beban kas
            </div>
        </div>

        <!-- Card 4: Total Mutasi -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Transaksi</span>
                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">
                {{ $cashflows->total() }}
            </div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Mutasi tercatat dalam buku
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form action="{{ route('arisan.admin.cashflow.index', ['subdomain' => $subdomain]) }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Filter Kloter -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Kloter:</label>
                <select name="group_id" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Semua Kloter</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}" {{ $filters['group_id'] == $g->id ? 'selected' : '' }}>
                            {{ $g->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Jenis Transaksi -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Jenis Mutasi:</label>
                <select name="type" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                    <option value="ALL" {{ $filters['type'] === 'ALL' ? 'selected' : '' }}>Semua Jenis</option>
                    <option value="IN" {{ $filters['type'] === 'IN' ? 'selected' : '' }}>Pemasukan (Masuk)</option>
                    <option value="OUT" {{ $filters['type'] === 'OUT' ? 'selected' : '' }}>Pengeluaran (Keluar)</option>
                </select>
            </div>

            <!-- Filter Kategori -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Kategori:</label>
                <select name="category" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Semua Kategori</option>
                    <option value="IURAN" {{ $filters['category'] === 'IURAN' ? 'selected' : '' }}>IURAN</option>
                    <option value="PENCAIRAN_PEMENANG" {{ $filters['category'] === 'PENCAIRAN_PEMENANG' ? 'selected' : '' }}>PENCAIRAN_PEMENANG</option>
                    <option value="BIAYA_ADMIN" {{ $filters['category'] === 'BIAYA_ADMIN' ? 'selected' : '' }}>BIAYA_ADMIN</option>
                    <option value="KAS_DARURAT" {{ $filters['category'] === 'KAS_DARURAT' ? 'selected' : '' }}>KAS_DARURAT</option>
                    <option value="TALANGAN" {{ $filters['category'] === 'TALANGAN' ? 'selected' : '' }}>TALANGAN</option>
                    <option value="LAINNYA" {{ $filters['category'] === 'LAINNYA' ? 'selected' : '' }}>LAINNYA</option>
                </select>
            </div>

            <!-- Filter Tanggal Mulai -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Dari Tanggal:</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                    class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- Filter Tanggal Selesai & Actions -->
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Sampai Tanggal:</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                        class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit"
                    class="px-3 py-2 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-700 transition-colors shrink-0">
                    Filter
                </button>
                @if (request()->hasAny(['group_id', 'type', 'category', 'start_date', 'end_date']))
                    <a href="{{ route('arisan.admin.cashflow.index', ['subdomain' => $subdomain]) }}"
                        class="px-2.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition-colors shrink-0" title="Reset Filter">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Ledger Transaction Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Daftar Mutasi Kas
            </h3>
            <span class="text-xs text-slate-400 font-medium">
                Menampilkan {{ $cashflows->count() }} dari {{ $cashflows->total() }} entri
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kloter</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-center">Jenis</th>
                        <th class="py-3 px-4 text-right">Nominal (Rp)</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($cashflows as $item)
                        @php
                            $isIncome = $item->type === 'IN';
                            $categoryBadgeClass = match($item->category) {
                                'IURAN' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                'PENCAIRAN_PEMENANG' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                'BIAYA_ADMIN' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
                                'KAS_DARURAT' => 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300',
                                'TALANGAN' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                                default => 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="py-3 px-4 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($item->transaction_date)->translatedFormat('d M Y') }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ $item->group ? $item->group->name : 'Umum (Non-Kloter)' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $categoryBadgeClass }}">
                                    {{ $item->category }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300 min-w-[200px] max-w-sm">
                                {{ $item->description ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isIncome ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' }}">
                                    {{ $isIncome ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold whitespace-nowrap {{ $isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $isIncome ? '+' : '-' }} {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <form action="{{ route('arisan.admin.cashflow.destroy', ['subdomain' => $subdomain, 'cashflow' => $item->id]) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan kas ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors" title="Hapus Transaksi">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 text-xs">
                                Belum ada riwayat mutasi kas yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cashflows->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $cashflows->links() }}
            </div>
        @endif
    </div>

</div>

<!-- ==================== ADD CASHFLOW MODAL ==================== -->
<div id="cashflowModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Catat Transaksi Buku Kas
            </h3>
            <button type="button" onclick="closeCashflowModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('arisan.admin.cashflow.store', ['subdomain' => $subdomain]) }}" method="POST" class="space-y-3.5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jenis Transaksi:</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative flex items-center justify-center p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 dark:has-[:checked]:bg-emerald-950/40">
                        <input type="radio" name="type" value="IN" checked class="sr-only">
                        <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300">+ Kas Masuk</span>
                    </label>
                    <label class="relative flex items-center justify-center p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 has-[:checked]:border-rose-600 has-[:checked]:bg-rose-50 dark:has-[:checked]:bg-rose-950/40">
                        <input type="radio" name="type" value="OUT" class="sr-only">
                        <span class="text-xs font-bold text-rose-700 dark:text-rose-300">- Kas Keluar</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kategori:</label>
                <select name="category" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                    <option value="IURAN">IURAN</option>
                    <option value="PENCAIRAN_PEMENANG">PENCAIRAN_PEMENANG</option>
                    <option value="BIAYA_ADMIN">BIAYA_ADMIN</option>
                    <option value="KAS_DARURAT">KAS_DARURAT</option>
                    <option value="TALANGAN">TALANGAN</option>
                    <option value="LAINNYA">LAINNYA</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Kloter (Opsional):</label>
                <select name="group_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Umum / Tidak Terikat Kloter</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nominal (Rp):</label>
                <input type="number" name="amount" min="1" required placeholder="Contoh: 100000"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tanggal Transaksi:</label>
                <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                    class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Keterangan (Opsional):</label>
                <textarea name="description" rows="2" placeholder="Catatan atau berita transaksi..."
                    class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-normal text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="flex items-center gap-3 pt-3">
                <button type="button" onclick="closeCashflowModal()"
                    class="w-1/3 py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition-colors">
                    Batal
                </button>
                <button type="submit"
                    class="w-2/3 py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-colors">
                    Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openCashflowModal() {
        document.getElementById('cashflowModal').classList.remove('hidden');
    }
    function closeCashflowModal() {
        document.getElementById('cashflowModal').classList.add('hidden');
    }
</script>
@endpush
