@extends('layouts.admin')

@section('title', 'Analytics')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Analytics</h1>
        <button onclick="location.reload()" class="btn btn-outline-secondary rounded-3 shadow-sm border">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>

    <div class="row g-2 g-lg-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Revenue Bulan Ini</p>
                    <p class="fs-4 fw-bold text-dark mb-0">Rp{{ number_format($revenueThisMonth, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Invoice Lunas</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $paidInvoices }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Belum Lunas</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $unpaidInvoices }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Total Pelanggan</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $totalCustomers }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body">
            <h6 class="fw-semibold text-secondary mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-clock text-primary"></i> Pembayaran Terbaru
            </h6>
            @if(count($recentPayments) > 0)
            <div class="list-group list-group-flush border-0">
                @foreach($recentPayments as $p)
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                        <p class="fw-medium text-dark mb-0">{{ $p->customer_name }}</p>
                        <small class="text-muted">{{ $p->invoice_number }} · {{ \Carbon\Carbon::parse($p->paid_at)->format('d M H:i') }}</small>
                    </div>
                    <span class="fw-bold text-success">Rp{{ number_format($p->amount, 0, ',', '.') }}</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="small text-muted text-center py-4 mb-0">Belum ada pembayaran</p>
            @endif
        </div>
    </div>
</div>
@endsection
