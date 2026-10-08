@extends('layouts.admin')
@section('title', 'MikroTik Display')
@section('content')
<style>
.display-card{background:var(--card);border-radius:16px;padding:24px;text-align:center;box-shadow:var(--shadow);}
.display-card .big{font-size:2.5rem;font-weight:800;}
.display-card .label{font-size:12px;color:var(--text2);margin-top:4px;}
.status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;}
</style>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-hdd-network text-primary me-2"></i>MikroTik Display</h1>
        <button class="btn btn-outline-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
    </div>
    <div class="row g-3 mb-4">
        @foreach($routers ?? [] as $r)
        <div class="col-12 col-md-6 col-lg-3">
            <div class="display-card">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                    <span class="status-dot bg-{{ $r->is_active ? 'success' : 'danger' }}"></span>
                    <span class="fw-bold">{{ $r->name }}</span>
                </div>
                <div class="big">{{ $r->customers_count ?? 0 }}</div>
                <div class="label">Pelanggan</div>
                <div class="small text-muted mt-1">{{ $r->host }}:{{ $r->port }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
