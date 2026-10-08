@extends('portal.layout')

@section('title', 'Pemakaian Data - Portal')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-28 px-4 pt-5">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="/portal/dashboard" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors flex-shrink-0">
            <i class="fas fa-arrow-left text-xs"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-gray-900">Pemakaian Data</h1>
            <p class="text-xs text-gray-500">Penggunaan internet {{ $customer->name }}</p>
        </div>
    </div>

    {{-- Current Usage --}}
    @if($usageData)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Pemakaian Saat Ini</h2>
        <div class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <span class="text-sm text-gray-500">Status</span>
                <span class="text-sm font-semibold text-green-600"><i class="fas fa-circle text-[8px] me-1"></i>Online</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <span class="text-sm text-gray-500">IP Address</span>
                <span class="text-sm font-semibold text-gray-900 font-mono">{{ $usageData['address'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <span class="text-sm text-gray-500">Uptime</span>
                <span class="text-sm font-semibold text-gray-900">{{ $usageData['uptime'] }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <span class="text-sm text-gray-500">Download</span>
                <span class="text-sm font-semibold text-blue-600">{{ $usageData['rx'] >= 1073741824 ? number_format($usageData['rx'] / 1073741824, 2) . ' GB' : ($usageData['rx'] >= 1048576 ? number_format($usageData['rx'] / 1048576, 2) . ' MB' : number_format($usageData['rx'] / 1024, 2) . ' KB') }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-gray-500">Upload</span>
                <span class="text-sm font-semibold text-orange-600">{{ $usageData['tx'] >= 1073741824 ? number_format($usageData['tx'] / 1073741824, 2) . ' GB' : ($usageData['tx'] >= 1048576 ? number_format($usageData['tx'] / 1048576, 2) . ' MB' : number_format($usageData['tx'] / 1024, 2) . ' KB') }}</span>
            </div>
        </div>
    </div>
    @else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-4 text-center">
        <i class="fas fa-wifi text-3xl text-gray-300 mb-3"></i>
        <p class="text-sm text-gray-500">Pelanggan sedang offline atau tidak memiliki router</p>
    </div>
    @endif

    {{-- Usage History --}}
    @if($history->count() > 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Riwayat Pemakaian</h2>
        <div class="space-y-2">
            @foreach($history as $h)
            @php
                $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                $monthName = $monthNames[(int)$h->period_month] ?? $h->period_month;
                $totalGB = round(($h->bytes_in + $h->bytes_out) / 1073741824, 2);
            @endphp
            <div class="flex items-center justify-between py-2 px-3 rounded-xl hover:bg-gray-50 transition-colors">
                <span class="text-sm text-gray-700">{{ $monthName }} {{ $h->period_year }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ $totalGB }} GB</span>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
        <p class="text-sm text-gray-400">Belum ada riwayat pemakaian</p>
    </div>
    @endif
</div>
@endsection
