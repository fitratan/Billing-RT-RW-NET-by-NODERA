@extends('arisan.admin.layout')

@section('title', isset($isEdit) && $isEdit ? 'Edit Kloter Arisan' : 'Buat Kloter Arisan Baru')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header Navigation -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                class="p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                    {{ isset($isEdit) && $isEdit ? 'Edit Kloter Arisan' : 'Buat Kloter Arisan Baru' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ isset($isEdit) && $isEdit ? 'Perbarui informasi pengaturan kloter arisan.' : 'Tentukan aturan iuran, jumlah peserta, dan tanggal putaran.' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm">
        <form action="{{ isset($isEdit) && $isEdit ? route('arisan.admin.groups.update', ['subdomain' => $subdomain, 'group' => $group->id]) : route('arisan.admin.groups.store', ['subdomain' => $subdomain]) }}" method="POST" class="space-y-5">
            @csrf
            @if (isset($isEdit) && $isEdit)
                @method('PUT')
            @endif

            <!-- Nama Kloter -->
            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Nama Kloter Arisan <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="name" id="name" required
                    value="{{ old('name', $group->name ?? '') }}"
                    class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all"
                    placeholder="Contoh: Kloter Emas 5 Juta, Kloter Ibu-Ibu RT 04">
            </div>

            <!-- Tipe Periode & Total Slot -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="period_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Tipe Periode <span class="text-rose-500">*</span>
                    </label>
                    <select name="period_type" id="period_type" {{ isset($isEdit) && $isEdit ? 'disabled' : 'required' }}
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                        <option value="MONTHLY" {{ old('period_type', $group->period_type ?? 'MONTHLY') === 'MONTHLY' ? 'selected' : '' }}>Bulanan (Tiap Bulan)</option>
                        <option value="WEEKLY" {{ old('period_type', $group->period_type ?? '') === 'WEEKLY' ? 'selected' : '' }}>Mingguan (Tiap Minggu)</option>
                        <option value="DAILY" {{ old('period_type', $group->period_type ?? '') === 'DAILY' ? 'selected' : '' }}>Harian (Tiap Hari)</option>
                    </select>
                </div>

                <div>
                    <label for="total_slots" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Total Slot / Anggota <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" name="total_slots" id="total_slots" min="2" max="100" required {{ isset($isEdit) && $isEdit ? 'disabled' : '' }}
                        value="{{ old('total_slots', $group->total_slots ?? 10) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Menentukan jumlah putaran arisan yang otomatis dibuat.</p>
                </div>
            </div>

            <!-- Nominal Iuran & Biaya Admin -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="dues_amount" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Nominal Iuran per Slot (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" name="dues_amount" id="dues_amount" min="1000" step="500" required {{ isset($isEdit) && $isEdit ? 'readonly' : '' }}
                        value="{{ old('dues_amount', $group->dues_amount ?? 100000) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                </div>

                <div>
                    <label for="admin_fee_per_period" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Fee Admin Pengelola per Putaran (Rp)
                    </label>
                    <input type="number" name="admin_fee_per_period" id="admin_fee_per_period" min="0" step="500"
                        value="{{ old('admin_fee_per_period', $group->admin_fee_per_period ?? 0) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Potongan untuk kas pengelola saat undian.</p>
                </div>
            </div>

            <!-- Tanggal Mulai & Jadwal Hari -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="start_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Tanggal Mulai
                    </label>
                    <input type="date" name="start_date" id="start_date" {{ isset($isEdit) && $isEdit ? 'disabled' : '' }}
                        value="{{ old('start_date', isset($group->start_date) ? \Carbon\Carbon::parse($group->start_date)->format('Y-m-d') : date('Y-m-d')) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                </div>

                <div>
                    <label for="due_day" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Jatuh Tempo (Tgl)
                    </label>
                    <input type="number" name="due_day" id="due_day" min="1" max="31"
                        value="{{ old('due_day', $group->due_day ?? 10) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Batas akhir bayar iuran.</p>
                </div>

                <div>
                    <label for="draw_day" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Hari Undian (Tgl)
                    </label>
                    <input type="number" name="draw_day" id="draw_day" min="1" max="31"
                        value="{{ old('draw_day', $group->draw_day ?? 15) }}"
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Hari pengundian pemenang.</p>
                </div>
            </div>

            @if (isset($isEdit) && $isEdit)
                <!-- Status Kloter -->
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Status Kloter
                    </label>
                    <select name="status" id="status" required
                        class="block w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition-all">
                        <option value="ACTIVE" {{ old('status', $group->status) === 'ACTIVE' ? 'selected' : '' }}>Aktif (Sedang Berjalan)</option>
                        <option value="COMPLETED" {{ old('status', $group->status) === 'COMPLETED' ? 'selected' : '' }}>Selesai (Semua Putaran Berakhir)</option>
                        <option value="CANCELLED" {{ old('status', $group->status) === 'CANCELLED' ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>
            @endif

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end space-x-3">
                <a href="{{ route('arisan.admin.groups.index', ['subdomain' => $subdomain]) }}"
                    class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex items-center px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ isset($isEdit) && $isEdit ? 'Simpan Perubahan' : 'Buat Kloter & Generate Periode' }}</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
