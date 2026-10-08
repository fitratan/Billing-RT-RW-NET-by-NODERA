@extends('arisan.member.layout')

@section('title', 'Profil & Ganti PIN')

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <div>
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-white">
            Profil & Keamanan Akun
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Kelola data akun member dan perbarui PIN akses Anda.
        </p>
    </div>

    <!-- Member Profile Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center space-x-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 text-white flex items-center justify-center font-black text-lg shadow-sm">
                {{ strtoupper(substr($member->name, 0, 1)) }}
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ $member->name }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $member->phone_number }}
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between">
                <span class="text-slate-500 dark:text-slate-400">Layanan Arisan:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ $arisan_subscription->business_name ?? 'Pembukuan Arisan' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500 dark:text-slate-400">Status Akun:</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    Aktif
                </span>
            </div>
            @if ($member->address_notes)
                <div class="pt-1">
                    <span class="text-slate-500 dark:text-slate-400 block">Alamat / Catatan:</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium">{{ $member->address_notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Active Slots Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
            Daftar Slot Terdaftar
        </h3>

        @if ($slots->isEmpty())
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Anda belum terdaftar dalam slot kloter manapun.
            </p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($slots as $slot)
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $slot->group->name }}</span>
                            <span class="text-slate-400 block text-[11px]">Slot #{{ $slot->slot_number }} &bull; Rp {{ number_format($slot->group->dues_amount, 0, ',', '.') }}/putaran</span>
                        </div>
                        <div>
                            @if ($slot->has_won)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    Pemenang Putaran #{{ $slot->wonPeriod?->period_number ?? $slot->won_period_id }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                    Aktif Ikut Undian
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Magic Link Auto-Login Box -->
    @if ($member->magic_token)
        <div class="bg-slate-900 text-white rounded-2xl p-5 border border-slate-800 shadow-sm space-y-3">
            <div>
                <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-widest block">
                    LINK MASUK OTOMATIS
                </span>
                <h4 class="text-sm font-bold text-white mt-0.5">
                    Link 1-Click Login Anda
                </h4>
                <p class="text-xs text-slate-400 mt-0.5">
                    Gunakan tautan ini untuk langsung masuk ke portal arisan tanpa perlu memasukkan PIN.
                </p>
            </div>

            @php
                $magicLinkUrl = url("/arisan-app/{$subdomain}/member/autologin?token={$member->magic_token}");
            @endphp

            <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between gap-2">
                <input type="text" readonly value="{{ $magicLinkUrl }}" id="magicLinkInput"
                    class="bg-transparent text-xs text-emerald-300 w-full focus:outline-none truncate">
                <button type="button"
                    onclick="copyMagicLink(this)"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-950 bg-amber-400 hover:bg-amber-300 transition-colors shrink-0 shadow-xs">
                    Salin Link
                </button>
            </div>
        </div>
    @endif

    <!-- Change PIN Form -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Ganti PIN Akses
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Pastikan menggunakan PIN yang mudah diingat (minimal 4 digit angka).
            </p>
        </div>

        <form action="{{ route('arisan.member.profile.pin', ['subdomain' => $subdomain]) }}" method="POST" class="space-y-4">
            @csrf

            <!-- PIN Lama -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    PIN Lama <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="old_pin" required maxlength="10" placeholder="Masukkan PIN lama"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- PIN Baru -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    PIN Baru (Min 4 Karakter) <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="new_pin" required minlength="4" maxlength="10" placeholder="Masukkan PIN baru"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- Konfirmasi PIN Baru -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Konfirmasi PIN Baru <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="new_pin_confirmation" required minlength="4" maxlength="10" placeholder="Ulangi PIN baru"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <button type="submit"
                class="w-full py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-md shadow-emerald-600/20 flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>Simpan PIN Baru</span>
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function copyMagicLink(btn) {
        const input = document.getElementById('magicLinkInput');
        navigator.clipboard.writeText(input.value).then(function() {
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Tersalin!';
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }).catch(function(err) {
            console.error('Gagal menyalin link:', err);
        });
    }
</script>
@endpush
@endsection
