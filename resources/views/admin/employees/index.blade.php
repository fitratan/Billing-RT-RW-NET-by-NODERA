@php
    $tenantSlug = session('tenant_slug');
@endphp

@extends('layouts.admin')

@section('title', 'Manajemen Karyawan')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Manajemen Karyawan <span class="text-muted fw-normal fs-6">({{ $employees->count() }})</span></h1>
        <button onclick="openAddModal()" class="btn btn-primary px-4 py-2 rounded-3 text-white fw-semibold shadow">
            <i class="bi bi-plus-lg me-1"></i> Tambah Karyawan
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle"></i> {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Table --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3" style="width:60px;">No</th>
                            <th class="py-3">Nama</th>
                            <th class="py-3">Username</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Link Login</th>
                            <th class="py-3">Telepon</th>
                            <th class="py-3">Status</th>
                            <th class="pe-4 py-3 text-center" style="width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $i => $e)
                        <tr>
                            <td class="ps-4 text-secondary small">{{ $i + 1 }}</td>
                            <td class="fw-medium">{{ $e->name }}</td>
                            <td class="text-secondary small">
                                <span class="font-monospace">@<span class="text-lowercase">{{ $e->username }}</span></span>
                            </td>
                            <td>
                                @php
                                    $roleLabels = [
                                        'admin' => ['label' => 'Admin', 'class' => 'bg-danger bg-opacity-10 text-danger'],
                                        'technician' => ['label' => 'Teknisi', 'class' => 'bg-info bg-opacity-10 text-info'],
                                        'collector' => ['label' => 'Kolektor', 'class' => 'bg-warning bg-opacity-10 text-warning'],
                                    ];
                                    $r = $roleLabels[$e->role] ?? ['label' => ucfirst($e->role), 'class' => 'bg-secondary bg-opacity-10 text-secondary'];
                                @endphp
                                <span class="badge rounded-pill {{ $r['class'] }}">{{ $r['label'] }}</span>
                            </td>
                            <td class="small">
                                @php
                                    $roleUrl = $e->role === 'technician'
                                        ? url('/teknisi/login')
                                        : ($tenantSlug ? url('/panel/' . $tenantSlug . '/login') : url('/login'));
                                @endphp
                                <div class="input-group input-group-sm flex-nowrap" style="min-width:280px;">
                                    <input type="text" class="form-control form-control-sm bg-light font-monospace" value="{{ $roleUrl }}" id="loginUrl-{{ $i }}" readonly onclick="this.select()">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyCredentials({{ $i }}, '{{ $e->username }}')" title="Salin link + username">
                                        <i class="bi bi-files"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="small text-secondary">{{ $e->phone ?: '-' }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $e->is_active ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted' }}">
                                    {{ $e->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="pe-4 text-center">
                                <button onclick="openEditModal('{{ $e->id }}', '{{ addslashes($e->name) }}', '{{ $e->username }}', '{{ $e->phone }}', '{{ $e->email }}', '{{ $e->role }}', {{ $e->is_active ? 'true' : 'false' }})" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold me-1">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form method="POST" action="/admin/employees/delete/{{ $e->id }}" onsubmit="return confirm('Hapus karyawan {{ addslashes($e->name) }}?')" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-3 fw-semibold">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:56px;height:56px;">
                                    <i class="bi bi-people-fill text-muted" style="font-size:1.25rem;"></i>
                                </div>
                                <p class="text-muted small mb-0">Belum ada karyawan</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Employee Modal --}}
<div id="addModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/employees/add">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nama Lengkap</label>
                        <input type="text" name="name" required placeholder="Nama Lengkap" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Username</label>
                            <input type="text" name="username" required pattern="[^\s]+" title="Username tidak boleh mengandung spasi" placeholder="Username" class="form-control">
                            <div class="invalid-feedback">Username tidak boleh mengandung spasi</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Password</label>
                            <input type="password" name="password" required placeholder="Password" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">No. Telepon</label>
                            <input type="text" name="phone" placeholder="No. Telepon" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Email</label>
                            <input type="email" name="email" placeholder="Email" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Role</label>
                            <select name="role" required class="form-select">
                                <option value="admin">Admin</option>
                                <option value="technician">Teknisi</option>
                                <option value="collector">Kolektor</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Status</label>
                            <select name="is_active" required class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Edit Employee Modal --}}
<div id="editModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Edit Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nama Lengkap</label>
                        <input type="text" name="name" id="edit_name" required placeholder="Nama Lengkap" class="form-control">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Username</label>
                            <input type="text" name="username" id="edit_username" required pattern="[^\s]+" title="Username tidak boleh mengandung spasi" placeholder="Username" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Password</label>
                            <input type="password" name="password" placeholder="Kosongkan jika tidak diubah" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">No. Telepon</label>
                            <input type="text" name="phone" id="edit_phone" placeholder="No. Telepon" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Email</label>
                            <input type="email" name="email" id="edit_email" placeholder="Email" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Role</label>
                            <select name="role" id="edit_role" required class="form-select">
                                <option value="admin">Admin</option>
                                <option value="technician">Teknisi</option>
                                <option value="collector">Kolektor</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Status</label>
                            <select name="is_active" id="edit_is_active" required class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAddModal() {
    var modal = new bootstrap.Modal(document.getElementById('addModal'));
    modal.show();
}

function copyCredentials(index, username) {
    var input = document.getElementById('loginUrl-' + index);
    var text = 'URL: ' + input.value + '\nUsername: ' + username;
    navigator.clipboard.writeText(text).then(function() {
        var btn = input.nextElementSibling;
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    });
}

function openEditModal(id, name, username, phone, email, role, isActive) {
    document.getElementById('editForm').action = '/admin/employees/edit/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_is_active').value = isActive ? '1' : '0';
    var modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>
@endpush
