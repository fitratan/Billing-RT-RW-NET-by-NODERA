@extends('portal.layout')

@section('title', 'Tagihan - Portal')

@section('content')
<div class="max-w-lg mx-auto min-h-screen pb-8 px-4 pt-5">
    <div class="flex items-center gap-3 mb-6">
        <a href="/portal" class="w-10 h-10 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-500">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <h1 class="text-xl font-bold text-gray-900">Tagihan Saya</h1>
    </div>

    @if($invoices->count() > 0)
    <div class="space-y-3">
        @foreach($invoices as $inv)
        <div class="card flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-900">{{ $inv->invoice_number }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}</p>
                <p class="text-xs text-gray-400">{{ $inv->description }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-gray-900">Rp{{ number_format($inv->amount, 0, ',', '.') }}</p>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium mt-1
                    {{ $inv->status === 'paid' ? 'bg-green-100 text-green-700' : ($inv->status === 'cancelled' ? 'bg-gray-100 text-gray-500' : 'bg-yellow-100 text-yellow-700') }}">
                    {{ $inv->status === 'paid' ? 'LUNAS' : ($inv->status === 'cancelled' ? 'BATAL' : 'BELUM') }}
                </span>
                @if($inv->status !== 'paid' && $inv->status !== 'cancelled')
                <a href="/portal/payment/{{ $inv->id }}" class="block mt-2 text-xs text-blue-600 font-medium">Bayar →</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-12">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
        </div>
        <p class="text-gray-400 text-sm">Belum ada tagihan</p>
    </div>
    @endif
</div>
@endsection
