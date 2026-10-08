@extends('layouts.admin')
@section('title', 'Laporan')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-file-bar-graph text-primary me-2"></i>Laporan</h1>
        <a href="/admin/reports/print?type={{ request('type', 'revenue') }}&from={{ request('from') }}&to={{ request('to') }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-pdf me-1"></i> Cetak</a>
    </div>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-4"><label class="form-label small fw-medium">Dari</label><input type="date" name="from" value="{{ request('from', now()->startOfMonth()->format('Y-m-d')) }}" class="form-control"></div>
                <div class="col-4"><label class="form-label small fw-medium">Sampai</label><input type="date" name="to" value="{{ request('to', now()->format('Y-m-d')) }}" class="form-control"></div>
                <div class="col-4"><label class="form-label small fw-medium">Tipe</label><select name="type" class="form-select"><option value="revenue" {{ request('type')=='revenue'?'selected':'' }}>Pendapatan</option><option value="customers" {{ request('type')=='customers'?'selected':'' }}>Pelanggan</option><option value="collection" {{ request('type')=='collection'?'selected':'' }}>Kolektor</option></select></div>
                <div class="col-12"><button type="submit" class="btn btn-primary w-100 rounded-3"><i class="bi bi-search me-1"></i> Tampilkan</button></div>
            </form>
            <hr>
            <div id="reportContent">
                <p class="text-muted small text-center py-4">Pilih periode dan tipe laporan</p>
            </div>
        </div>
    </div>
</div>
@endsection
