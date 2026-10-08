@extends('layouts.technician')
@section('title', 'Daftarkan Pelanggan Baru')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/teknisi/dashboard" class="btn btn-sm btn-outline-secondary rounded-3"><i class="bi bi-arrow-left"></i></a>
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-person-plus text-primary me-2"></i>Daftarkan Pelanggan</h1>
    </div>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <form method="POST" action="/teknisi/create-customer/store">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nama Pelanggan</label>
                    <input type="text" name="name" required class="form-control" placeholder="Nama lengkap">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-medium">No. Telepon</label>
                        <input type="text" name="phone" required class="form-control" placeholder="08xxx">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">Username PPPoE</label>
                        <input type="text" name="pppoe_username" required class="form-control" placeholder="username">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-medium">Paket</label>
                        <select name="package_id" required class="form-select">
                            <option value="">Pilih paket...</option>
                            @foreach($packages as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">Router</label>
                        <select name="router_id" class="form-select">
                            <option value="">Pilih router...</option>
                            @foreach($routers as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Alamat</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Alamat lengkap"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-semibold">
                    <i class="bi bi-check-lg me-1"></i> Daftarkan
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
