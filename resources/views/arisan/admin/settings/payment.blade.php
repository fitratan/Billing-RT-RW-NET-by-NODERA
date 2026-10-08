@extends('arisan.admin.layout')

@section('title', 'Pengaturan Pembayaran & Rekening')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Pengaturan Rekening & QRIS</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola nomor rekening bank transfer, gambar QRIS, dan panduan pembayaran untuk anggota.</p>
        </div>
        <div>
            <a href="{{ route('arisan.admin.payments.index', ['subdomain' => $subdomain]) }}"
                class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Pembukuan Iuran</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Bank Accounts List & Add Form -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Add Bank Account Form -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tambah Rekening Bank</h3>
                        <p class="text-[11px] text-slate-400">Rekening ini akan dicantumkan di pesan pengingat WhatsApp & portal anggota.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('arisan.admin.settings.payment.store', ['subdomain' => $subdomain]) }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nama Bank</label>
                            <input type="text" name="bank_name" placeholder="Contoh: BCA / BRI / Mandiri" required
                                class="block w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nomor Rekening</label>
                            <input type="text" name="account_number" placeholder="Contoh: 1234567890" required
                                class="block w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Atas Nama</label>
                            <input type="text" name="account_holder" placeholder="Contoh: Budi Santoso" required
                                class="block w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-colors">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Simpan Rekening</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Bank Accounts List -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Rekening Terdaftar</h3>
                    <span class="text-xs font-semibold text-slate-500">{{ $settings->where('bank_name', '!=', 'QRIS Utama')->count() }} Rekening</span>
                </div>

                @php
                    $bankAccounts = $settings->where('account_number', '!=', '-');
                @endphp

                @if ($bankAccounts->isEmpty())
                    <div class="p-8 text-center text-slate-400">
                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <p class="text-xs">Belum ada rekening bank yang ditambahkan.</p>
                    </div>
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($bankAccounts as $setting)
                            <div class="p-4 flex items-center justify-between gap-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold flex items-center justify-center text-xs uppercase">
                                        {{ substr($setting->bank_name, 0, 4) }}
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $setting->bank_name }}</h4>
                                        <p class="text-xs text-slate-600 dark:text-slate-300 font-mono tracking-wide">{{ $setting->account_number }}</p>
                                        <p class="text-[11px] text-slate-400">a.n {{ $setting->account_holder }}</p>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('arisan.admin.settings.payment.destroy', ['subdomain' => $subdomain, 'setting' => $setting->id]) }}"
                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus rekening ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                        title="Hapus Rekening">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        <!-- Right Column: QRIS & Instructions -->
        <div class="lg:col-span-5 space-y-6">

            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">QRIS & Instruksi Bayar</h3>
                        <p class="text-[11px] text-slate-400">Upload kode barcode QRIS dan panduan transfer.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('arisan.admin.settings.payment.qris', ['subdomain' => $subdomain]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <!-- Current QRIS Preview -->
                        @if ($qrisSetting && $qrisSetting->qris_image_path)
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-center">
                                <p class="text-[11px] text-slate-500 mb-2">QRIS Aktif Saat Ini:</p>
                                <img src="{{ asset('storage/' . $qrisSetting->qris_image_path) }}" alt="QRIS" class="max-h-44 mx-auto rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                {{ $qrisSetting && $qrisSetting->qris_image_path ? 'Ganti Gambar QRIS' : 'Upload Gambar QRIS' }}
                            </label>
                            <input type="file" name="qris_image" accept="image/*"
                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300 cursor-pointer">
                            <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG, atau WebP (Maks. 3MB)</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Instruksi Pembayaran (Opsional)</label>
                            <textarea name="instructions" rows="4" placeholder="Contoh: Mohon cantumkan nama lengkap dan nomor slot pada berita transfer. Konfirmasi bukti pembayaran melalui portal member."
                                class="block w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500">{{ $qrisSetting ? $qrisSetting->instructions : '' }}</textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-colors">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Simpan Pengaturan QRIS</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>

    </div>

</div>
@endsection
