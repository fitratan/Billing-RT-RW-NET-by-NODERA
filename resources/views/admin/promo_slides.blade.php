@extends('layouts.admin')
@section('title', 'Promo Slides')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-images text-warning me-2"></i>Promo Slides</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#slideModal"><i class="bi bi-plus me-1"></i> Slide Baru</button>
    </div>
    <div class="row g-3">
        @forelse($slides ?? [] as $s)
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3">
                <img src="/storage/{{ $s->image }}" class="card-img-top rounded-top-3" style="height:150px;object-fit:cover;">
                <div class="card-body p-2">
                    <p class="small fw-semibold mb-0 text-truncate">{{ $s->title }}</p>
                    <div class="d-flex gap-1 mt-1">
                        <span class="badge bg-{{ $s->is_active ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $s->is_active ? 'success' : 'secondary' }}" style="font-size:9px;">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        <form method="POST" action="/admin/promo-slides/delete/{{ $s->id }}" class="d-inline" onsubmit="return confirm('Hapus slide?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger px-1 py-0"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5 text-muted"><p class="mb-0">Belum ada promo slide</p></div>
        @endforelse
    </div>
</div>
{{-- Modal --}}
<div class="modal fade" id="slideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Slide Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/promo-slides/store" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Judul</label>
                        <input type="text" name="title" required maxlength="255" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Gambar</label>
                        <input type="file" name="image" required accept="image/*" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Link (opsional)</label>
                        <input type="url" name="link" class="form-control" placeholder="https://...">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
