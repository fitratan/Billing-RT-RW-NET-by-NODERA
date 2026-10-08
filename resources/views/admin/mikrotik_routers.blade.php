@extends('layouts.admin')
@section('title', 'Router MikroTik')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Router MikroTik</h1>
        <button type="button" class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#routerModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Router
        </button>
    </div>

    

    @if($routers->count() > 0)
    <div class="row g-3">
        @foreach($routers as $r)
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-3 {{ $r->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-muted' }}" style="width:40px;height:40px">
                                <i class="bi bi-diagram-3"></i>
                            </div>
                            <div>
                                <p class="fw-semibold text-dark mb-0">{{ $r->name }}</p>
                                <small class="text-muted">{{ $r->host }}:{{ $r->port }}</small>
                            </div>
                        </div>
                        <span class="badge rounded-pill {{ $r->is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                            {{ $r->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/admin/mikrotik/router/{{ $r->id }}" class="btn btn-sm btn-outline-primary rounded-3 flex-fill">
                            <i class="bi bi-eye me-1"></i> PPPoE
                        </a>
                        <a href="/admin/mikrotik/router/test/{{ $r->id }}" class="btn btn-sm btn-outline-success rounded-3 flex-fill">
                            <i class="bi bi-plug me-1"></i> Test
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-warning rounded-3 flex-fill" data-bs-toggle="modal" data-bs-target="#editRouterModal{{ $r->id }}">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 flex-fill" onclick="if(confirm('Hapus router {{ $r->name }}?')){ event.preventDefault(); document.getElementById('deleteRouter{{ $r->id }}').submit(); }">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </div>
                    <form method="POST" action="/admin/mikrotik/router/delete/{{ $r->id }}" id="deleteRouter{{ $r->id }}" style="display:none;">@csrf</form>
                </div>
            </div>
        </div>

        {{-- Edit Router Modal --}}
        <div class="modal fade" id="editRouterModal{{ $r->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-sm border-0">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit Router</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="/admin/mikrotik/router/edit/{{ $r->id }}">
                            @csrf
                            <div class="mb-3">
                                <input type="text" name="name" required value="{{ $r->name }}" placeholder="Nama Router (contoh: RT-RW-01)" class="form-control">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-8">
                                    <input type="text" name="host" required value="{{ $r->host }}" placeholder="Host/IP" class="form-control">
                                </div>
                                <div class="col-4">
                                    <input type="number" name="port" required value="{{ $r->port }}" class="form-control">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <input type="text" name="username" required value="{{ $r->username }}" placeholder="Username" class="form-control">
                                </div>
                                <div class="col-6">
                                    <input type="password" name="password" placeholder="Password (kosongkan jika tidak diubah)" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="default_profile" value="{{ $r->default_profile }}" placeholder="Default Profile (contoh: up-10Mbps)" class="form-control">
                            </div>
                            <div class="mb-3">
                                <input type="text" name="default_isolir_profile" value="{{ $r->default_isolir_profile }}" placeholder="Isolir Profile (contoh: isolir)" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-warning w-100 rounded-3 fw-semibold">
                                <i class="bi bi-pencil me-1"></i> Update
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5">
        <div class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded-circle mb-3" style="width:56px;height:56px">
            <i class="bi bi-diagram-3 text-muted fs-3"></i>
        </div>
        <p class="text-muted small mb-2">Belum ada router MikroTik</p>
        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal" data-bs-target="#routerModal">Tambah sekarang</button>
    </div>
    @endif
</div>

{{-- Add Router Modal --}}
<div class="modal fade" id="routerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Router</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/mikrotik/router/add">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nama Router <span class="text-danger">*</span></label>
                        <input type="text" name="name" required placeholder="Contoh: RT-RW-01 / MT-Office" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-medium">Host/IP <span class="text-danger">*</span></label>
                            <input type="text" name="host" required placeholder="192.168.1.1 atau domain" class="form-control">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-medium">Port <span class="text-danger">*</span></label>
                            <input type="number" name="port" required value="8728" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" required placeholder="Username MikroTik" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Password</label>
                            <input type="password" name="password" placeholder="Password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold py-2">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Router
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection