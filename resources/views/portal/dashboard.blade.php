@extends('portal.layout')

@section('title', 'Dashboard - Portal')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-28 px-4 pt-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-[13px] text-gray-400 font-medium">Halo,</p>
            <h1 class="text-xl font-bold text-gray-900">{{ $customer->name }}</h1>
            @if($companyName)
            <div class="flex items-center gap-2 mt-1.5">
                <i class="fas fa-building text-blue-500 text-xs"></i>
                <span class="text-xs font-semibold text-blue-700 uppercase tracking-wide">{{ $companyName }}</span>
            </div>
            @endif
        </div>
        <a href="/portal/logout" class="w-10 h-10 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-400 hover:text-red-500">
            <i class="fas fa-sign-out-alt text-sm"></i>
        </a>
    </div>

    {{-- Status Card --}}
    @php
        $statusGradient = match($customer->status) {
            'active' => 'from-emerald-50 to-white',
            'isolated' => 'from-red-50 to-white',
            default => 'from-amber-50 to-white',
        };
        $statusColor = match($customer->status) {
            'active' => 'text-emerald-600',
            'isolated' => 'text-red-600',
            default => 'text-amber-600',
        };
        $statusBg = match($customer->status) {
            'active' => 'bg-emerald-100 text-emerald-600',
            'isolated' => 'bg-red-100 text-red-600',
            default => 'bg-amber-100 text-amber-600',
        };
        $statusIcon = match($customer->status) {
            'active' => 'fa-check-circle',
            'isolated' => 'fa-exclamation-triangle',
            default => 'fa-clock',
        };
        $statusText = match($customer->status) {
            'active' => 'AKTIF',
            'isolated' => 'ISOLIR',
            default => strtoupper($customer->status),
        };
    @endphp
    <div class="card mb-4 bg-gradient-to-br {{ $statusGradient }}">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">Status Layanan</p>
                <p class="text-lg font-bold {{ $statusColor }}">{{ $statusText }}</p>
            </div>
            <div class="w-12 h-12 rounded-full {{ $statusBg }} flex items-center justify-center">
                <i class="fas {{ $statusIcon }} text-xl"></i>
            </div>
        </div>
        @if($package)
        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
            <span class="text-gray-500">Paket</span>
            <span class="font-semibold text-gray-900">{{ $package->name }} — Rp{{ number_format($package->price, 0, ',', '.') }}</span>
        </div>
        @endif
    </div>

    {{-- Current Invoice --}}
    @if($currentInvoice)
    @php
        $isIsolated = $customer->status === 'isolated';
        $isPaid = $currentInvoice->status === 'paid';
        if ($isPaid) {
            $cardGradient = 'from-emerald-500 to-green-600';
            $cardShadow = 'shadow-emerald-200';
            $statusLabel = 'LUNAS';
            $statusClass = 'bg-white/20 text-white';
            $iconBg = 'bg-white/20';
        } elseif ($isIsolated) {
            $cardGradient = 'from-red-600 to-rose-700';
            $cardShadow = 'shadow-red-200';
            $statusLabel = 'ISOLIR';
            $statusClass = 'bg-white text-red-700';
            $iconBg = 'bg-white/20';
        } else {
            $cardGradient = 'from-amber-500 to-orange-600';
            $cardShadow = 'shadow-amber-200';
            $statusLabel = 'BELUM';
            $statusClass = 'bg-white text-amber-700';
            $iconBg = 'bg-white/20';
        }
    @endphp
    <div class="relative overflow-hidden rounded-3xl mb-4 bg-gradient-to-br {{ $cardGradient }} p-5 text-white shadow-lg {{ $cardShadow }}">
        {{-- Background decoration --}}
        <div class="absolute top-0 right-0 w-24 h-24 rounded-full bg-white/10 -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-16 h-16 rounded-full bg-white/5 translate-y-1/2 -translate-x-1/2"></div>

        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl {{ $iconBg }} flex items-center justify-center">
                        <i class="fas fa-file-invoice text-white text-sm"></i>
                    </div>
                    <p class="text-xs font-medium text-white/80">Tagihan Bulan Ini</p>
                </div>
                <span class="text-[12px] px-4 py-1.5 rounded-full font-bold {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <p class="text-3xl font-extrabold tracking-tight mb-1">Rp{{ number_format($currentInvoice->amount, 0, ',', '.') }}</p>
            <p class="text-xs text-white/70">Jatuh tempo {{ \Carbon\Carbon::parse($currentInvoice->due_date)->format('d M Y') }}</p>

            @if($isIsolated)
            <div class="mt-3 p-3 rounded-2xl bg-white/15 border border-white/20">
                <p class="text-xs font-semibold text-white">Layanan Anda sedang diisolir</p>
                <p class="text-[11px] text-white/70 mt-0.5">Segera lakukan pembayaran untuk memulihkan layanan internet Anda</p>
            </div>
            @endif

            @if(!$isPaid)
            <a href="/portal/payment/{{ $currentInvoice->id }}" class="mt-4 w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-white {{ $isIsolated ? 'text-red-700' : 'text-amber-700' }} font-bold text-sm shadow-sm">
                <i class="fas fa-credit-card"></i> Bayar Sekarang
            </a>
            @endif
        </div>
    </div>
    @endif

    {{-- ONU Status --}}
    @if($onuData)
    <div class="card mb-4">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fas fa-satellite-dish text-blue-500 text-sm"></i> Status ONU
        </h2>
        <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[11px] text-gray-400">Status</p>
                <p class="text-sm font-semibold {{ $onuData['online'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $onuData['online'] ? 'Online' : 'Offline' }}
                </p>
            </div>
            <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[11px] text-gray-400">RX Power</p>
                <p class="text-sm font-semibold text-gray-900">{{ $onuData['rxPower'] }}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[11px] text-gray-400">SSID</p>
                <p class="text-sm font-semibold text-gray-900 truncate">{{ $onuData['ssid'] ?: '-' }}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[11px] text-gray-400">Model</p>
                <p class="text-sm font-semibold text-gray-900 truncate">{{ $onuData['model'] }}</p>
            </div>
        </div>
        <a href="/portal/wifi" class="mt-3 flex items-center justify-center gap-2 text-sm text-blue-600 font-medium py-2">
            <i class="fas fa-wifi"></i> Atur WiFi
        </a>
    </div>
    @endif

    {{-- Invoices --}}
    <div class="card">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fas fa-file-invoice text-blue-500 text-sm"></i> Tagihan Terbaru
        </h2>
        @if($invoices->count() > 0)
        <div class="divide-y divide-gray-50">
            @foreach($invoices as $inv)
            <div class="py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $inv->invoice_number }}</p>
                    <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">Rp{{ number_format($inv->amount, 0, ',', '.') }}</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                        {{ $inv->status === 'paid' ? 'bg-green-100 text-green-700' : ($inv->status === 'cancelled' ? 'bg-gray-100 text-gray-500' : 'bg-yellow-100 text-yellow-700') }}">
                        {{ ucfirst($inv->status) }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        <a href="/portal/invoices" class="mt-3 text-center block text-sm text-blue-600 font-medium">Lihat Semua</a>
        @else
        <p class="text-sm text-gray-400 text-center py-4">Belum ada tagihan</p>
        @endif
    </div>

    {{-- Bottom Nav --}}
    <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-lg px-4 pb-3 pt-2 z-50">
        <div class="bottom-nav bg-white/85 shadow-[0_-4px_30px_-10px_rgba(0,0,0,0.12)] rounded-3xl px-4 py-2.5 border border-gray-100/60 mx-auto max-w-sm">
            <div class="flex items-center justify-around">
                <div class="nav-item active flex items-center gap-2 cursor-pointer px-5 py-2" onclick="location.href='/portal'">
                    <i class="fas fa-home text-white text-sm"></i>
                    <span class="text-xs font-semibold text-white">Home</span>
                </div>
                <div class="nav-item flex items-center gap-2 cursor-pointer px-4 py-2" onclick="location.href='/portal/invoices'">
                    <i class="fas fa-file-invoice text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-400 font-medium">Tagihan</span>
                </div>
                @if($onuData)
                <div class="nav-item flex items-center gap-2 cursor-pointer px-4 py-2" onclick="location.href='/portal/wifi'">
                    <i class="fas fa-wifi text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-400 font-medium">WiFi</span>
                </div>
                @endif
                <div class="nav-item flex items-center gap-2 cursor-pointer px-4 py-2" onclick="location.href='/portal/laporan'">
                    <i class="fas fa-exclamation-triangle text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-400 font-medium">Laporan</span>
                </div>
                <div class="nav-item flex items-center gap-2 cursor-pointer px-4 py-2" onclick="location.href='/portal/usage'">
                    <i class="fas fa-chart-bar text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-400 font-medium">Pemakaian</span>
                </div>
                <div class="nav-item flex items-center gap-2 cursor-pointer px-4 py-2" onclick="location.href='/portal/logout'">
                    <i class="fas fa-sign-out-alt text-gray-400 text-sm"></i>
                    <span class="text-xs text-gray-400 font-medium">Keluar</span>
                </div>
            </div>
        </div>
    </nav>
</div>
@endsection
