@extends('layouts.admin')
@section('title', 'Broadcast WhatsApp')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-megaphone text-success me-2"></i>Broadcast WhatsApp</h1>
        <a href="/admin/whatsapp-templates" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-text me-1"></i> Template</a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body">
                    <form method="POST" action="/admin/broadcast/send">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Target</label>
                            <select name="target" id="targetSelect" class="form-select" onchange="toggleTarget()">
                                <option value="all">Semua Pelanggan (dengan no. HP)</option>
                                <option value="customers">Pelanggan Aktif</option>
                                <option value="selected">Pilih Manual</option>
                            </select>
                        </div>
                        <div class="mb-3" id="customerSelectWrap" style="display:none;">
                            <label class="form-label small fw-medium">Pilih Pelanggan</label>
                            <select name="customer_ids" id="customerSelect" class="form-select" multiple style="height:200px;">
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->phone ?? '-' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Pesan <span class="text-danger">*</span></label>
                            <textarea name="message" id="messageText" rows="8" class="form-control" required placeholder="Tulis pesan..."></textarea>
                            <div class="small text-muted mt-1">
                                <span id="charCount">0</span> karakter |
                                <span class="dropdown d-inline">
                                    <a href="#" class="text-decoration-none" data-bs-toggle="dropdown">Insert variable</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="insertVar('{nama}')">{nama}</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="insertVar('{tagihan}')">{tagihan}</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="insertVar('{jatuh_tempo}')">{jatuh_tempo}</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="insertVar('{perusahaan}')">{perusahaan}</a></li>
                                    </ul>
                                </span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2 fw-semibold" onclick="return confirm('Kirim broadcast ke pelanggan terpilih?')">
                            <i class="bi bi-send me-1"></i> Kirim Broadcast
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-text text-primary me-1"></i>Template Cepat</h6>
                    @if($templates->count() > 0)
                    <div class="list-group">
                        @foreach($templates as $t)
                        <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('{{ addslashes($t->message) }}')">
                            <div class="fw-semibold small">{{ $t->name }}</div>
                            <div class="small text-muted text-truncate">{{ $t->message }}</div>
                        </button>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted small mb-0">Belum ada template. Buat di menu Template.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTarget() {
    document.getElementById('customerSelectWrap').style.display = 
        document.getElementById('targetSelect').value === 'selected' ? '' : 'none';
}

document.getElementById('messageText')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

function insertVar(v) {
    const ta = document.getElementById('messageText');
    const start = ta.selectionStart;
    ta.value = ta.value.substring(0, start) + v + ta.value.substring(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
    document.getElementById('charCount').textContent = ta.value.length;
}

function useTemplate(msg) {
    document.getElementById('messageText').value = msg;
    document.getElementById('charCount').textContent = msg.length;
}
</script>
@endsection
