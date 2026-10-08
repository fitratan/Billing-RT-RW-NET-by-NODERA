@extends('layouts.admin')
@section('title', 'Pembayaran Kolektor')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-cash text-success me-2"></i>Pembayaran Kolektor</h1>
    </div>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="fw-semibold ps-3">#</th>
                        <th class="fw-semibold">Kolektor</th>
                        <th class="fw-semibold">Pelanggan</th>
                        <th class="fw-semibold">Nominal</th>
                        <th class="fw-semibold">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments ?? [] as $p)
                    <tr>
                        <td class="ps-3">{{ $loop->iteration }}</td>
                        <td>{{ $p->collector_name ?? '-' }}</td>
                        <td>{{ $p->customer_name ?? '-' }}</td>
                        <td class="fw-semibold text-success">Rp{{ number_format($p->amount ?? 0, 0, ',', '.') }}</td>
                        <td class="text-muted">{{ $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada pembayaran</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
