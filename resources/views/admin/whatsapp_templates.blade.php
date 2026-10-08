@extends('layouts.admin')
@section('title', 'Template WhatsApp')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-file-text text-primary me-2"></i>Template WhatsApp</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus me-1"></i> Template Baru</button>
    </div>

    @if($templates->count() > 0)
    <div class="row g-3">
        @foreach($templates as $t)
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0">{{ $t->name }}</h6>
                        <form method="POST" action="/admin/whatsapp-templates/delete/{{ $t->id }}" onsubmit="return confirm('Hapus template?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger px-1 py-0"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <p class="small text-muted mb-2" style="white-space:pre-wrap;">{{ $t->message }}</p>
                    <button class="btn btn-sm btn-outline-success" onclick="copyTemplate('{{ addslashes($t->message) }}')"><i class="bi bi-clipboard me-1"></i> Salin</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5">
        <i class="bi bi-file-text" style="font-size:3rem;color:var(--text3);"></i>
        <p class="text-muted small mt-2 mb-0">Belum ada template</p>
    </div>
    @endif
</div>

{{-- Modal --}}
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Template Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/whatsapp-templates/save">
                    @csrf
                    <input type="hidden" name="id" value="">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nama Template</label>
                        <input type="text" name="name" required maxlength="100" placeholder="Contoh: Tagihan Bulanan" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Pesan</label>
                        <textarea name="message" required rows="5" class="form-control" placeholder="Tulis template pesan...">{{ old('message') }}</textarea>
                        <div class="small text-muted mt-1">Variable: <code>{nama}</code> <code>{tagihan}</code> <code>{jatuh_tempo}</code> <code>{perusahaan}</code></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function copyTemplate(msg) {
    navigator.clipboard.writeText(msg).then(() => alert('Template disalin!'));
}
</script>
@endsection
