@extends('layouts.admin')
@section('title', 'Laporan Kolektor')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Laporan Kolektor</h1>
        <a href="/admin/agent-reports?print=1" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-pdf me-1"></i> Cetak</a>
    </div>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th class="fw-semibold ps-3">Kolektor</th><th class="fw-semibold">Tagihan Dibayar</th><th class="fw-semibold text-end">Total Tagihan</th><th class="fw-semibold text-end pe-3">Total Dibayar</th></tr>
                </thead>
                <tbody>
                    @php
                        $collectors = \App\Models\Collector::where('tenant_id', session('tenant_id'))->get();
                    @endphp
                    @forelse($collectors as $c)
                    @php
                        $paidCount = \App\Models\Invoice::where('collector_id', $c->id)->where('paid', true)->count() ?? 0;
                        $totalInvoices = \App\Models\Invoice::where('collector_id', $c->id)->count() ?? 0;
                        $totalPaid = \App\Models\Invoice::where('collector_id', $c->id)->where('paid', true)->sum('amount') ?? 0;
                    @endphp
                    <tr>
                        <td class="ps-3 fw-medium">{{ $c->name }}</td>
                        <td>{{ $paidCount }} / {{ $totalInvoices ?: '-' }}</td>
                        <td class="text-end">Rp{{ number_format($totalInvoices ? \App\Models\Invoice::where('collector_id', $c->id)->sum('amount') : 0, 0, ',', '.') }}</td>
                        <td class="text-end pe-3 fw-semibold text-success">Rp{{ number_format($totalPaid, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada kolektor</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
