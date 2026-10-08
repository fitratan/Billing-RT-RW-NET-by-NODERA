@extends('layouts.admin')
@section('title', 'ONU - ' . $olt->name)
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold text-dark mb-0">
                <a href="/admin/olt" class="text-muted text-decoration-none me-2"><i class="bi bi-arrow-left small"></i></a>
                {{ $olt->name }}
            </h1>
            <small class="text-muted">{{ $olt->host }}:{{ $olt->port }} · {{ ucfirst($olt->model) }}</small>
        </div>
        <div class="d-flex gap-2 mt-2 mt-lg-0">
            <a href="/admin/olt/test/{{ $olt->id }}" class="btn btn-sm btn-outline-success rounded-3">
                <i class="bi bi-plug me-1"></i> Test
            </a>
            <a href="/admin/olt" class="btn btn-outline-secondary rounded-3 border shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    

    {{-- Stats --}}
    @php
        $total = $olt->onus->count();
        $online = $olt->onus->where('status', 'online')->count();
        $offline = $olt->onus->where('status', 'offline')->count();
    @endphp
    <div class="row g-2 g-lg-4 mb-4">
        <div class="col-4">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Total ONU</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $total }}</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Online</p>
                    <p class="fs-4 fw-bold text-success mb-0">{{ $online }}</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Offline</p>
                    <p class="fs-4 fw-bold text-danger mb-0">{{ $offline }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($olt->onus->count() > 0)
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small fw-semibold text-secondary px-3 py-3">Serial Number</th>
                            <th class="small fw-semibold text-secondary px-3 py-3">Nama</th>
                            <th class="small fw-semibold text-secondary px-3 py-3">PON Port</th>
                            <th class="small fw-semibold text-secondary px-3 py-3">Index</th>
                            <th class="small fw-semibold text-secondary px-3 py-3">Status</th>
                            <th class="small fw-semibold text-secondary text-end px-3 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($olt->onus as $onu)
                        <tr>
                            <td class="font-monospace small fw-medium text-dark px-3 py-3">{{ $onu->serial_number }}</td>
                            <td class="px-3 py-3 text-secondary">{{ $onu->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-secondary">{{ $onu->pon_port ?? '-' }}</td>
                            <td class="px-3 py-3 text-secondary">{{ $onu->onu_index ?? '-' }}</td>
                            <td class="px-3 py-3">
                                <span class="badge rounded-pill {{ $onu->status === 'online' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                                    {{ $onu->status === 'online' ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-end">
                                <form method="POST" action="/admin/olt/onus/{{ $olt->id }}/delete/{{ $onu->id }}" class="d-inline" onsubmit="return confirm('Hapus ONU {{ $onu->serial_number }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="text-center py-5">
        <div class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded-circle mb-3" style="width:56px;height:56px">
            <i class="bi bi-wifi text-muted fs-3"></i>
        </div>
        <p class="text-muted small mb-0">Belum ada ONU terdaftar pada OLT ini</p>
    </div>
    @endif
</div>
@endsection