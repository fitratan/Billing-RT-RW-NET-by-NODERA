@extends('layouts.admin')
@section('title', 'Paket Voucher')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-box-seam text-primary me-2"></i>Paket Voucher</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vpModal"><i class="bi bi-plus me-1"></i></button>
    </div>
    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th class="fw-semibold ps-3">Nama</th><th class="fw-semibold">Harga</th><th class="fw-semibold">Durasi</th><th class="fw-semibold">BW</th><th class="fw-semibold">Status</th><th class="fw-semibold text-end pe-3">Aksi</th>
                </tr></thead>
                <tbody>
                    @forelse($packages as $p)
                    <tr>
                        <td class="ps-3 fw-medium">{{ $p->name }}</td>
                        <td>Rp{{ number_format($p->price, 0, ',', '.') }}</td>
                        <td>{{ $p->duration_days }} hari</td>
                        <td>{{ $p->bandwidth_down ?? '-' }} Mbps</td>
                        <td><span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary px-2" onclick="alert('Edit: {{ $p->id }}')"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="/admin/voucher-packages/delete/{{ $p->id }}" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf<button class="btn btn-sm btn-outline-danger px-2"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada paket voucher</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{-- Add Modal --}}
<div class="modal fade" id="vpModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-sm border-0">
<form method="POST" action="/admin/voucher-packages/add">@csrf
<div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Paket Voucher Baru</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-2"><label class="form-label small">Nama</label><input name="name" required class="form-control"></div>
<div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Harga</label><input type="number" name="price" required class="form-control"></div><div class="col-6"><label class="form-label small">Durasi (hari)</label><input type="number" name="duration_days" value="30" class="form-control"></div></div>
<div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">BW Download (Mbps)</label><input type="number" name="bandwidth_down" class="form-control"></div><div class="col-6"><label class="form-label small">BW Upload (Mbps)</label><input type="number" name="bandwidth_up" class="form-control"></div></div>
<div class="mb-2"><label class="form-label small">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
</div>
<div class="modal-footer border-0 pt-0"><button type="submit" class="btn btn-primary w-100 rounded-3">Simpan</button></div>
</form></div></div></div>
@endsection
