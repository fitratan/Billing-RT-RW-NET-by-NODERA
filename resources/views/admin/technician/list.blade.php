@extends('layouts.admin')

@section('title', 'Teknisi')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Teknisi <small class="fw-normal text-muted fs-6">({{ $technicians->count() }})</small></h1>
        <button type="button" class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#techModal">
            <i class="bi bi-plus-lg me-1"></i> Baru
        </button>
    </div>

    <div class="row g-3">
        @forelse($technicians as $t)
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow-sm rounded-3 border-0">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle {{ $t->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-muted' }}" style="width:40px;height:40px">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <div>
                            <p class="fw-semibold text-dark mb-0">{{ $t->name }}</p>
                            <small class="text-muted">{{ $t->username }} · {{ $t->phone ?? '-' }}</small>
                        </div>
                    </div>
                    <span class="badge rounded-pill {{ $t->is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                        {{ $t->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded-circle mb-3" style="width:64px;height:64px">
                    <i class="bi bi-person-gear text-muted fs-2"></i>
                </div>
                <p class="text-muted small mb-0">Belum ada teknisi</p>
            </div>
        </div>
        @endforelse
    </div>
</div>

{{-- Add Technician Modal --}}
<div class="modal fade" id="techModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Teknisi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/technicians/add">
                    @csrf
                    <div class="mb-3">
                        <input type="text" name="username" required placeholder="Username" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" required placeholder="Password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="name" required placeholder="Nama" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="phone" placeholder="No. HP" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
