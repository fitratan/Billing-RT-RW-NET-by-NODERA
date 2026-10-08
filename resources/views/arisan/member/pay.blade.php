@extends('arisan.member.layout')

@section('title', 'Bayar Iuran Arisan')

@section('content')
<div class="space-y-6">

    <!-- Header & Back Button -->
    <div class="flex items-center space-x-3">
        <a href="{{ route('arisan.member.history', ['subdomain' => $subdomain]) }}"
            class="w-9 h-9 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white">
                Pembayaran Iuran
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $payment->period->group->name }} &bull; Putaran #{{ $payment->period->period_number }}
            </p>
        </div>
    </div>

    <!-- Bill Detail Card -->
    <div class="bg-gradient-to-br from-emerald-800 via-emerald-900 to-slate-950 text-white rounded-3xl p-6 sm:p-7 shadow-xl border border-emerald-700/50 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <span class="text-[11px] font-bold text-emerald-300 uppercase tracking-widest block">
                    TOTAL TAGIHAN
                </span>
                <div class="text-2xl sm:text-3xl font-black text-white mt-1">
                    Rp {{ number_format($payment->amount, 0, ',', '.') }}
                </div>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-bold bg-amber-400 text-slate-950">
                SLOT #{{ $payment->groupMember->slot_number }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-emerald-700/60 text-xs">
            <div>
                <span class="text-emerald-300 block">Jatuh Tempo</span>
                <span class="font-bold text-white">
                    {{ $payment->period->due_date ? \Carbon\Carbon::parse($payment->period->due_date)->translatedFormat('d F Y') : '-' }}
                </span>
            </div>
            <div>
                <span class="text-emerald-300 block">Status Saat Ini</span>
                @if ($payment->status === 'PAID')
                    <span class="font-bold text-emerald-300">Lunas</span>
                @elseif ($payment->status === 'PENDING_VERIFICATION')
                    <span class="font-bold text-amber-300">Menunggu Verifikasi</span>
                @else
                    <span class="font-bold text-rose-300">Belum Bayar</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Admin Payment Accounts & QRIS Section -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Rekening Tujuan Pembayaran
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Silakan transfer nominal pas ke salah satu rekening atau QRIS resmi berikut:
            </p>
        </div>

        @if ($paymentSettings->isEmpty())
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-center">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Pengelola belum menambahkan rincian nomor rekening. Silakan hubungi pengelola secara langsung.
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($paymentSettings as $setting)
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">
                                    {{ $setting->bank_name }}
                                </span>
                                <div class="text-sm font-black text-slate-900 dark:text-white tracking-wider mt-0.5" id="acc-{{ $setting->id }}">
                                    {{ $setting->account_number }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    a.n {{ $setting->account_holder }}
                                </div>
                            </div>

                            @if (!empty($setting->account_number))
                                <button type="button"
                                    onclick="copyToClipboard('{{ $setting->account_number }}', this)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 transition-colors shadow-xs flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                    <span>Salin</span>
                                </button>
                            @endif
                        </div>

                        @if ($setting->qris_image_path)
                            <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 block mb-2">QRIS Pembayaran:</span>
                                <div class="w-44 h-44 bg-white p-2 rounded-xl border border-slate-200 shadow-sm mx-auto flex items-center justify-center">
                                    <img src="{{ asset('storage/' . $setting->qris_image_path) }}" alt="QRIS" class="max-w-full max-h-full object-contain">
                                </div>
                            </div>
                        @endif

                        @if ($setting->instructions)
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic pt-1">
                                Catatan: {{ $setting->instructions }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Upload Form / Status Box -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        @if ($payment->status === 'PAID')
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-start space-x-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-xs sm:text-sm text-emerald-900 dark:text-emerald-200">
                    <span class="font-bold block">Tagihan Ini Telah Lunas</span>
                    Pembayaran telah diverifikasi oleh pengelola pada {{ $payment->verified_by_admin_at ? \Carbon\Carbon::parse($payment->verified_by_admin_at)->translatedFormat('d F Y H:i') : '-' }}.
                </div>
            </div>
        @else
            @if ($payment->status === 'PENDING_VERIFICATION')
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-start space-x-3 mb-2">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs sm:text-sm text-amber-900 dark:text-amber-200">
                        <span class="font-bold block">Bukti Sedang Diverifikasi</span>
                        Bukti transfer Anda telah diterima dan sedang diperiksa pengelola. Anda dapat mengunggah ulang jika terdapat koreksi.
                    </div>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    Unggah Bukti Transfer
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Kirimkan foto atau screenshot bukti transfer bank/QRIS Anda.
                </p>
            </div>

            <form action="{{ route('arisan.member.pay.upload', ['subdomain' => $subdomain, 'payment' => $payment->id]) }}"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-4">
                @csrf

                <!-- Metode Pembayaran -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Metode Pembayaran <span class="text-rose-500">*</span>
                    </label>
                    <select name="payment_method" required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="BCA" {{ old('payment_method', $payment->payment_method) === 'BCA' ? 'selected' : '' }}>Bank BCA</option>
                        <option value="BRI" {{ old('payment_method', $payment->payment_method) === 'BRI' ? 'selected' : '' }}>Bank BRI</option>
                        <option value="Mandiri" {{ old('payment_method', $payment->payment_method) === 'Mandiri' ? 'selected' : '' }}>Bank Mandiri</option>
                        <option value="BNI" {{ old('payment_method', $payment->payment_method) === 'BNI' ? 'selected' : '' }}>Bank BNI</option>
                        <option value="BSI" {{ old('payment_method', $payment->payment_method) === 'BSI' ? 'selected' : '' }}>Bank BSI</option>
                        <option value="QRIS" {{ old('payment_method', $payment->payment_method) === 'QRIS' ? 'selected' : '' }}>QRIS</option>
                        <option value="DANA" {{ old('payment_method', $payment->payment_method) === 'DANA' ? 'selected' : '' }}>DANA</option>
                        <option value="GOPAY" {{ old('payment_method', $payment->payment_method) === 'GOPAY' ? 'selected' : '' }}>GoPay</option>
                        <option value="OVO" {{ old('payment_method', $payment->payment_method) === 'OVO' ? 'selected' : '' }}>OVO</option>
                        <option value="ShopeePay" {{ old('payment_method', $payment->payment_method) === 'ShopeePay' ? 'selected' : '' }}>ShopeePay</option>
                        <option value="Tunai" {{ old('payment_method', $payment->payment_method) === 'Tunai' ? 'selected' : '' }}>Tunai / Cash</option>
                        <option value="Lainnya" {{ old('payment_method', $payment->payment_method) === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>

                <!-- File Bukti Transfer -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Foto Bukti Transfer (JPG, PNG, WEBP, Maks 5MB) <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/jpg,image/webp" required
                        id="proofFileInput"
                        onchange="previewProofImage(event)"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300">

                    <!-- Preview Container -->
                    <div id="imagePreviewContainer" class="hidden mt-3">
                        <span class="text-[11px] text-slate-400 block mb-1">Preview Bukti:</span>
                        <div class="relative w-40 h-40 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                            <img id="imagePreview" src="" alt="Preview" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>

                <!-- Catatan Tambahan -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Catatan Tambahan (Opsional)
                    </label>
                    <textarea name="notes" rows="2" placeholder="Contoh: Transfer via rekening a.n Suami..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('notes', $payment->notes) }}</textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-3 rounded-xl text-xs sm:text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-md shadow-emerald-600/20 flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Unggah Bukti Pembayaran</span>
                </button>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span>Tersalin!</span>';
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }).catch(function(err) {
            console.error('Gagal menyalin:', err);
        });
    }

    function previewProofImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('imagePreview');
                const container = document.getElementById('imagePreviewContainer');
                preview.src = e.target.result;
                container.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    }
</script>
@endpush
@endsection
