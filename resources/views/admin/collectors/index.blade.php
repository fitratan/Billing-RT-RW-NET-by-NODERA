@extends('layouts.admin')

@section('title', 'Kolektor')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Kolektor <span class="text-muted fw-normal fs-6">({{ $collectors->count() }})</span></h1>
        <button onclick="document.getElementById('collectorModal').classList.remove('d-none')" class="btn btn-primary px-4 py-2 rounded-3 text-white fw-semibold shadow">
            <i class="fas fa-plus me-1"></i> Baru
        </button>
    </div>

    

    <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3">
        @forelse($collectors as $c)
        <div class="col">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle {{ $c->is_active ? 'bg-warning bg-opacity-10 text-warning' : 'bg-light text-muted' }}" style="width:40px;height:40px;">
                                <i class="fas fa-walking small"></i>
                            </div>
                            <div>
                                <p class="fw-semibold text-dark mb-0">{{ $c->name }}</p>
                                <p class="small text-muted mb-0">@<span class="text-lowercase">{{ $c->username }}</span></p>
                            </div>
                        </div>
                        <span class="badge rounded-pill {{ $c->is_active ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted' }}">
                            {{ $c->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    @if($c->collection_area)
                    <div class="bg-light rounded-3 p-2 small mb-3">
                        <span class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>Area:</span>
                        <span class="fw-medium text-secondary">{{ $c->collection_area }}</span>
                    </div>
                    @endif

                    <div class="row g-2 small mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <span class="text-muted d-block">Saldo</span>
                                <span class="fw-semibold text-dark">Rp {{ number_format($c->balance, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <span class="text-muted d-block">{{ $c->commission_type === 'percent' ? 'Komisi (%)' : 'Komisi (Rp)' }}</span>
                                <span class="fw-semibold text-dark">
                                    @if($c->commission_type === 'percent')
                                        {{ $c->commission_value }}%
                                    @else
                                        Rp {{ number_format($c->commission_value, 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mb-3">
                        @if($c->phone)<i class="fas fa-phone me-1"></i>{{ $c->phone }}@endif
                        @if($c->email) · <i class="fas fa-envelope me-1"></i>{{ $c->email }} @endif
                    </div>

                    <div class="d-flex gap-2 pt-3 border-top border-light">
                        <button onclick="editCollector({{ $c->id }}, '{{ addslashes($c->name) }}', '{{ $c->username }}', '{{ $c->phone }}', '{{ $c->email }}', '{{ addslashes($c->collection_area) }}', '{{ $c->commission_type }}', '{{ $c->commission_value }}', {{ $c->is_active ? 'true' : 'false' }})" class="btn btn-outline-primary btn-sm flex-fill rounded-3 fw-semibold">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <form method="POST" action="/admin/collectors/delete/{{ $c->id }}" onsubmit="return confirm('Hapus kolektor {{ $c->name }}?')" class="flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 rounded-3 fw-semibold">
                                <i class="fas fa-trash me-1"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:56px;height:56px;">
                <i class="fas fa-walking text-muted" style="font-size:1.25rem;"></i>
            </div>
            <p class="text-muted small">Belum ada kolektor</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Add Collector Modal --}}
<div id="collectorModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Kolektor Baru</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('collectorModal').classList.remove('show'); document.getElementById('collectorModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/collectors/add">
                    @csrf
                    <div class="mb-3">
                        <input type="text" name="name" required placeholder="Nama Lengkap" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="username" required placeholder="Username" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" required placeholder="Password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="phone" placeholder="No. HP" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email" placeholder="Email (opsional)" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="collection_area" placeholder="Area Penagihan (contoh: RW 01, RW 02)" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <select name="commission_type" class="form-select">
                                <option value="fixed">Komisi Tetap (Rp)</option>
                                <option value="percent">Komisi Persen (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.01" name="commission_value" required placeholder="Nilai Komisi" class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Edit Collector Modal --}}
<div id="editModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Kolektor</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('editModal').classList.remove('show'); document.getElementById('editModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    @csrf
                    <div class="mb-3">
                        <input type="text" name="name" id="edit_name" required placeholder="Nama Lengkap" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="username" id="edit_username" required placeholder="Username" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" placeholder="Password (kosongkan jika tidak diubah)" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="phone" id="edit_phone" placeholder="No. HP" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email" id="edit_email" placeholder="Email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="collection_area" id="edit_area" placeholder="Area Penagihan" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <select name="commission_type" id="edit_commission_type" class="form-select">
                                <option value="fixed">Komisi Tetap (Rp)</option>
                                <option value="percent">Komisi Persen (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.01" name="commission_value" id="edit_commission_value" required placeholder="Nilai Komisi" class="form-control">
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <label class="small text-secondary">Status:</label>
                        <div class="form-check form-check-inline">
                            <input type="radio" name="is_active" value="1" id="edit_active" class="form-check-input">
                            <label class="form-check-label small" for="edit_active">Aktif</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="radio" name="is_active" value="0" id="edit_inactive" class="form-check-input">
                            <label class="form-check-label small" for="edit_inactive">Nonaktif</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editCollector(id, name, username, phone, email, area, comType, comValue, isActive) {
    document.getElementById('editForm').action = '/admin/collectors/edit/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_area').value = area;
    document.getElementById('edit_commission_type').value = comType;
    document.getElementById('edit_commission_value').value = comValue;
    if (isActive) {
        document.getElementById('edit_active').checked = true;
    } else {
        document.getElementById('edit_inactive').checked = true;
    }
    document.getElementById('editModal').classList.remove('d-none');
}
</script>
@endpush