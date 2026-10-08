@extends('layouts.admin')

@section('title', 'Payment Gateway')

@push('styles')
<style>
    .gateway-card { transition: all 0.2s; }
    .gateway-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
    .gateway-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
</style>
@endpush

@section('content')
<div class="container-fluid px-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold text-dark mb-0">Payment Gateway</h1>
            <p class="small text-muted mb-0">Konfigurasi payment gateway</p>
        </div>
    </div>

    

    {{-- QRIS Card --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="gateway-icon" style="background:rgba(16,185,129,0.1);color:#10b981;">
                    <i class="bi bi-qr-code"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0">QRIS</h5>
                    <p class="small text-muted mb-0">Pembayaran via QRIS Statis</p>
                </div>
            </div>
            <p class="small text-muted mb-0">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Kelola QRIS di halaman <a href="/admin/payments/qris" class="text-primary fw-medium">Pengaturan QRIS</a>.
            </p>
        </div>
    </div>

    {{-- Tripay Card --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:rgba(251,146,60,0.1);color:#fb923c;font-size:1.3rem;">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Tripay</h5>
                        <p class="small text-muted mb-0">Virtual Account, E-Wallet, Convenience Store</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="/admin/payments/gateway/tripay/save">
                @csrf
                <div class="row g-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">Merchant Code</label>
                        <input type="text" name="TRIPAY_MERCHANT_CODE" value="{{ old('TRIPAY_MERCHANT_CODE', $tripayConfig['TRIPAY_MERCHANT_CODE'] ?? '') }}" class="form-control" placeholder="Tripay Merchant Code">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">API Key</label>
                        <input type="password" name="TRIPAY_API_KEY" value="{{ old('TRIPAY_API_KEY', $tripayConfig['TRIPAY_API_KEY'] ?? '') }}" class="form-control" placeholder="Tripay API Key">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">Private Key</label>
                        <input type="password" name="TRIPAY_PRIVATE_KEY" value="{{ old('TRIPAY_PRIVATE_KEY', $tripayConfig['TRIPAY_PRIVATE_KEY'] ?? '') }}" class="form-control" placeholder="Tripay Private Key">
                    </div>
                </div>
                <div class="mt-3 d-flex align-items-center gap-3">
                    <div class="form-check">
                        <input type="checkbox" name="TRIPAY_MODE" value="1" class="form-check-input" id="tripayMode" {{ ($tripayConfig['TRIPAY_MODE'] ?? '') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label small" for="tripayMode">Production Mode</label>
                    </div>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-floppy me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Midtrans Card --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:rgba(59,130,246,0.1);color:#3b82f6;font-size:1.3rem;">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Midtrans</h5>
                        <p class="small text-muted mb-0">Payment Gateway Indonesia</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="/admin/payments/gateway/midtrans/save">
                @csrf
                <div class="row g-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">Server Key</label>
                        <input type="password" name="MIDTRANS_SERVER_KEY" value="{{ old('MIDTRANS_SERVER_KEY', $midtransConfig['MIDTRANS_SERVER_KEY'] ?? '') }}" class="form-control" placeholder="Midtrans Server Key">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">Client Key</label>
                        <input type="text" name="MIDTRANS_CLIENT_KEY" value="{{ old('MIDTRANS_CLIENT_KEY', $midtransConfig['MIDTRANS_CLIENT_KEY'] ?? '') }}" class="form-control" placeholder="Midtrans Client Key">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-medium text-secondary">Mode</label>
                        <select name="MIDTRANS_MODE" class="form-select">
                            <option value="sandbox" {{ ($midtransConfig['MIDTRANS_MODE'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                            <option value="production" {{ ($midtransConfig['MIDTRANS_MODE'] ?? '') === 'production' ? 'selected' : '' }}>Production</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-floppy me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection