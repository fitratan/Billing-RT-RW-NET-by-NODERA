@extends('layouts.admin')

@section('title', 'QRIS Payment')

@push('styles')
<style>
    .qris-box { background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 18px; padding: 16px; }
    .qrimg-preview { max-width: 280px; border-radius: 16px; background: #fff; padding: 10px; box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
</style>
@endpush

@section('content')
<div class="container-fluid p-3 pb-24">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold text-dark mb-0">QRIS Statis</h1>
            <p class="small text-muted mb-0">Upload & kelola QRIS pembayaran</p>
        </div>
    </div>

    {{-- Alerts --}}
    

    {{-- Current QRIS --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-dark mb-3" style="font-size:0.95rem;">
                <i class="fas fa-qrcode me-2 text-primary"></i> QRIS Saat Ini
            </h3>

            @if($qrisImage && \Illuminate\Support\Facades\Storage::disk('public')->exists($qrisImage))
            <div class="d-flex flex-column align-items-center gap-3">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($qrisImage) }}" alt="QRIS" class="qrimg-preview">
                <form method="POST" action="/admin/payments/qris/delete" onsubmit="return confirm('Hapus QRIS ini?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm px-4 rounded-3 fw-semibold">
                        <i class="fas fa-trash me-1"></i> Hapus QRIS
                    </button>
                </form>
            </div>
            @else
            <div class="text-center py-4">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:64px;height:64px;">
                    <i class="fas fa-qrcode text-muted" style="font-size:1.5rem;"></i>
                </div>
                <p class="small text-muted mb-0">Belum ada QRIS. Upload gambar QRIS di bawah.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Upload QRIS --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-dark mb-3" style="font-size:0.95rem;">
                <i class="fas fa-upload me-2 text-primary"></i> Upload QRIS Baru
            </h3>
            <form method="POST" action="/admin/payments/qris/upload" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">Gambar QRIS (PNG/JPG/WEBP, max 2MB)</label>
                    <input type="file" name="qris_image" accept="image/png,image/jpeg,image/webp" required class="form-control">
                </div>
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold shadow">
                    <i class="fas fa-cloud-upload-alt me-1"></i> Upload
                </button>
            </form>
        </div>
    </div>

    {{-- QRIS Text / Merchant Info --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-dark mb-3" style="font-size:0.95rem;">
                <i class="fas fa-info-circle me-2 text-primary"></i> Informasi Merchant
            </h3>
            <form method="POST" action="/admin/payments/qris/save-text">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">Nama Merchant / Keterangan</label>
                    <textarea name="qris_text" rows="3" class="form-control"
                        placeholder="Contoh: NODERA ISP - Pembayaran Tagihan Bulanan">{{ $qrisText ?? '' }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold shadow">
                    <i class="fas fa-save me-1"></i> Simpan Informasi
                </button>
            </form>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="card shadow-sm border-0 rounded-3 bg-primary bg-opacity-10">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <i class="fas fa-lightbulb text-warning mt-1"></i>
                <div>
                    <p class="fw-semibold text-dark">Cara Penggunaan</p>
                    <ul class="small text-secondary mt-1 mb-0 ps-3">
                        <li>Upload gambar QRIS statis dari merchant (DANA/OVO/GoPay/ShopeePay dll)</li>
                        <li>Bagikan link QRIS ke pelanggan untuk pembayaran mandiri</li>
                        <li>Pelanggan scan QRIS dan transfer sesuai nominal tagihan</li>
                        <li>Admin akan memverifikasi pembayaran secara manual</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection