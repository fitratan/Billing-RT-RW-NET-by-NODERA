@extends('layouts.admin')
@section('title', 'Manajemen OLT')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Manajemen OLT</h1>
        <button type="button" class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#oltModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah OLT
        </button>
    </div>

    

    @if($olts->count() > 0)
    <div class="row g-3">
        @foreach($olts as $olt)
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm rounded-3 border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-3 {{ $olt->is_active ? 'bg-primary-subtle text-primary' : 'bg-body-secondary text-muted' }}" style="width:40px;height:40px">
                                <i class="bi bi-hdd-stack"></i>
                            </div>
                            <div>
                                <p class="fw-semibold text-dark mb-0">{{ $olt->name }}</p>
                                <small class="text-muted">{{ $olt->host }}:{{ $olt->port }}</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill {{ $olt->is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                                {{ $olt->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <span class="badge rounded-pill
                                @if($olt->model === 'huawei') bg-danger-subtle text-danger-emphasis
                                @elseif($olt->model === 'zte') bg-info-subtle text-info-emphasis
                                @else bg-secondary-subtle text-secondary-emphasis @endif
                            ">{{ $olt->model }}</span>
                        </div>
                    </div>
                    @if($olt->location)
                    <p class="small text-muted mb-3"><i class="bi bi-geo-alt me-1"></i> {{ $olt->location }}</p>
                    @endif
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="/admin/olt/onus/{{ $olt->id }}" class="btn btn-sm btn-outline-primary rounded-3 flex-fill">
                            <i class="bi bi-list me-1"></i> ONU ({{ $olt->onus_count ?? 0 }})
                        </a>
                        <a href="/admin/olt/test/{{ $olt->id }}" class="btn btn-sm btn-outline-success rounded-3 flex-fill">
                            <i class="bi bi-plug me-1"></i> Test
                        </a>
                        <button onclick="editOlt({{ $olt->id }}, '{{ $olt->name }}', '{{ $olt->host }}', {{ $olt->port }}, '{{ $olt->username }}', '{{ $olt->snmp_community }}', '{{ $olt->model }}', {{ $olt->is_active ? 'true' : 'false' }}, '{{ addslashes($olt->location ?? '') }}')" class="btn btn-sm btn-outline-warning rounded-3 flex-fill">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                        <form method="POST" action="/admin/olt/delete/{{ $olt->id }}" class="flex-fill" onsubmit="return confirm('Hapus OLT {{ $olt->name }}? Semua ONU terkait juga akan dihapus.')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 w-100">
                                <i class="bi bi-trash me-1"></i> Hapus
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
            <i class="bi bi-hdd-stack text-muted fs-3"></i>
        </div>
        <p class="text-muted small mb-2">Belum ada perangkat OLT</p>
        <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal" data-bs-target="#oltModal">Tambah sekarang</button>
    </div>
    @endif
</div>

{{-- Add/Edit Modal --}}
<div class="modal fade" id="oltModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Tambah OLT</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/olt/add" id="oltForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nama OLT</label>
                        <input type="text" name="name" id="inputName" required placeholder="Contoh: OLT Pusat" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-medium text-secondary">IP Address / Host</label>
                            <input type="text" name="host" id="inputHost" required placeholder="192.168.1.10" class="form-control">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium text-secondary">Port</label>
                            <input type="number" name="port" id="inputPort" required value="161" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">SNMP Community</label>
                            <input type="text" name="snmp_community" id="inputCommunity" required value="public" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Model</label>
                            <select name="model" id="inputModel" class="form-select">
                                <option value="huawei">Huawei</option>
                                <option value="zte">ZTE</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Username (opsional)</label>
                            <input type="text" name="username" id="inputUsername" placeholder="admin" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Password (opsional)</label>
                            <input type="password" name="password" id="inputPassword" placeholder="Kosongkan jika tidak diubah" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Lokasi</label>
                        <input type="text" name="location" id="inputLocation" placeholder="Contoh: Gedung Pusat Lt.3" class="form-control">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" id="inputActive" value="1" checked class="form-check-input">
                        <label class="form-check-label" for="inputActive">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold" id="submitBtn">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editOlt(id, name, host, port, username, community, model, active, location) {
    document.getElementById('modalTitle').textContent = 'Edit OLT';
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('oltForm').action = '/admin/olt/edit/' + id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputHost').value = host;
    document.getElementById('inputPort').value = port;
    document.getElementById('inputCommunity').value = community;
    document.getElementById('inputModel').value = model;
    document.getElementById('inputUsername').value = username;
    document.getElementById('inputPassword').value = '';
    document.getElementById('inputPassword').placeholder = 'Kosongkan jika tidak diubah';
    document.getElementById('inputLocation').value = location;
    document.getElementById('inputActive').checked = active;
    document.getElementById('submitBtn').textContent = 'Simpan Perubahan';
    var modal = new bootstrap.Modal(document.getElementById('oltModal'));
    modal.show();
}

// Reset form when modal is hidden
document.getElementById('oltModal').addEventListener('hidden.bs.modal', function () {
    resetForm();
});

function resetForm() {
    document.getElementById('modalTitle').textContent = 'Tambah OLT';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('oltForm').action = '/admin/olt/add';
    document.getElementById('inputName').value = '';
    document.getElementById('inputHost').value = '';
    document.getElementById('inputPort').value = '161';
    document.getElementById('inputCommunity').value = 'public';
    document.getElementById('inputModel').value = 'other';
    document.getElementById('inputUsername').value = '';
    document.getElementById('inputPassword').value = '';
    document.getElementById('inputPassword').placeholder = 'Kosongkan jika tidak diubah';
    document.getElementById('inputLocation').value = '';
    document.getElementById('inputActive').checked = true;
    document.getElementById('submitBtn').textContent = 'Simpan';
}
</script>
@endsection