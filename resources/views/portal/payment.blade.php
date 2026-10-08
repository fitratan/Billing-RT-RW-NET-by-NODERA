@extends('portal.layout')

@section('title', 'Pembayaran')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-8 px-4 pt-5">
    <div class="flex items-center gap-3 mb-6">
        <a href="/portal" class="w-10 h-10 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-500">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <h1 class="text-xl font-bold text-gray-900">Pembayaran</h1>
    </div>

    <div class="card mb-4">
        <p class="text-xs text-gray-400 font-medium">Tagihan</p>
        <p class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
        <p class="text-2xl font-bold text-gray-900 mt-2">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-400 mt-1">Jatuh tempo: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</p>
    </div>

    {{-- 1. QRIS Static --}}
    <div class="card mb-3">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center text-green-600"><i class="fas fa-qrcode text-sm"></i></div>
            <div>
                <p class="text-sm font-semibold text-gray-900">QRIS Static</p>
                <p class="text-xs text-gray-400">Bayar via QRIS — konfirmasi ke admin</p>
            </div>
        </div>
        <div class="bg-gray-50 rounded-2xl p-4 text-center mb-3">
            @if($qrisImageUrl)
            <img src="{{ $qrisImageUrl }}" alt="QRIS" class="w-48 h-48 mx-auto rounded-xl object-contain bg-white border border-gray-200">
            @else
            <div class="w-40 h-40 mx-auto bg-white rounded-xl flex items-center justify-center border border-gray-200">
                <i class="fas fa-qrcode text-5xl text-gray-300"></i>
            </div>
            @endif
            <p class="text-xs text-gray-400 mt-2">Scan QRIS di atas atau transfer ke nomor berikut</p>
        </div>
        @if(!empty($qrisText))
        <div class="text-xs text-gray-500 mb-3 p-3 bg-gray-50 rounded-xl">
            {{ $qrisText }}
        </div>
        @endif
        <div class="text-xs text-gray-500 space-y-1 mb-3">
            <p><span class="font-medium text-gray-700">Bank:</span> BCA — PT Gembok Teknologi</p>
            <p><span class="font-medium text-gray-700">No. Rek:</span> 1234567890</p>
            @if($adminWa)
            <p><span class="font-medium text-gray-700">Konfirmasi:</span> <a href="https://wa.me/{{ $adminWa }}" class="text-green-600 font-medium" target="_blank">WA {{ $adminWa }}</a></p>
            @endif
        </div>
        <form method="POST" action="/portal/paymentManual">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <input type="hidden" name="method" value="qris">
            <button type="submit" class="w-full py-3 rounded-2xl text-white font-semibold text-sm shadow-sm" style="background:#16a34a;">
                <i class="fas fa-check-circle mr-2"></i> Saya Sudah Bayar
            </button>
        </form>
    </div>

    {{-- 2. Bayar Online (Tripay) --}}
    @if(!empty($groupedChannels))
    <div class="card mb-3">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600"><i class="fas fa-credit-card text-sm"></i></div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Bayar Online</p>
                <p class="text-xs text-gray-400">Transfer via berbagai channel</p>
            </div>
        </div>
        <form method="POST" action="/portal/processPayment">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            @foreach($groupedChannels as $group => $channels)
            <div class="mb-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ $group }}</h3>
                <div class="space-y-2">
                    @foreach($channels as $ch)
                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" name="method" value="{{ $ch['code'] }}" class="w-4 h-4 text-blue-600" required>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $ch['name'] }}</p>
                            @if(!empty($ch['total_fee']['flat']))
                            <p class="text-xs text-gray-400">Biaya: Rp{{ number_format($ch['total_fee']['flat'], 0, ',', '.') }}</p>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
            <button type="submit" class="w-full py-3 rounded-2xl text-white font-semibold text-sm shadow-sm shadow-blue-200" style="background:#2563eb;">
                <i class="fas fa-credit-card mr-2"></i> Lanjutkan Pembayaran
            </button>
        </form>
    </div>
    @endif

    {{-- 3. Bayar ke Collector --}}
    @if($collector)
    <div class="card">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600"><i class="fas fa-user-tie text-sm"></i></div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Bayar ke Kolektor</p>
                <p class="text-xs text-gray-400">Bayar langsung ke petugas tagihan</p>
            </div>
        </div>
        <div class="bg-amber-50 rounded-2xl p-4 text-sm text-gray-700 mb-3">
            <p class="font-semibold">{{ $collector->name }}</p>
            @if($collector->phone)
            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-phone mr-1"></i> {{ $collector->phone }}</p>
            @endif
            @if($collector->collection_area)
            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-map-marker-alt mr-1"></i> {{ $collector->collection_area }}</p>
            @endif
        </div>
        <form method="POST" action="/portal/paymentManual">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <input type="hidden" name="method" value="collector">
            <button type="submit" class="w-full py-3 rounded-2xl text-white font-semibold text-sm shadow-sm" style="background:#d97706;">
                <i class="fas fa-hand-holding-usd mr-2"></i> Saya Akan Bayar ke Kolektor
            </button>
        </form>
    </div>
    @endif
</div>
@endsection
