@extends('layouts.admin')
@section('title', 'Laporan Kasir')
@section('content')
<div class="container-fluid px-0">
    <h1 class="fs-4 fw-bold mb-4"><i class="bi bi-cash-stack text-primary me-2"></i>Laporan Kasir</h1>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th class="fw-semibold ps-3">Kasir</th><th class="fw-semibold">Buka</th><th class="fw-semibold">Tutup</th><th class="fw-semibold text-end">Saldo Awal</th><th class="fw-semibold text-end">Total</th><th class="fw-semibold text-end pe-3">Status</th></tr>
                </thead>
                <tbody>
                    @forelse($sessions as $s)
                    <tr>
                        <td class="ps-3 fw-medium">{{ $s->cashier_name }}</td>
                        <td>{{ \Carbon\Carbon::parse($s->opened_at)->format('d M H:i') }}</td>
                        <td>{{ $s->closed_at ? \Carbon\Carbon::parse($s->closed_at)->format('d M H:i') : '-' }}</td>
                        <td class="text-end">Rp{{ number_format($s->opening_balance,0,',','.') }}</td>
                        <td class="text-end fw-semibold">Rp{{ number_format($s->total_cash_in,0,',','.') }}</td>
                        <td class="text-end pe-3"><span class="badge bg-{{ $s->status === 'open' ? 'warning' : 'success' }} bg-opacity-10 text-{{ $s->status === 'open' ? 'warning' : 'success' }}">{{ $s->status }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada sesi kasir</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
