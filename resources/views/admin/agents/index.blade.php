@extends('layouts.admin')

@section('title', 'Agen')

@section('content')
<div class="container-fluid p-3 pb-24">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0">Agen <span class="text-muted fw-normal fs-6">({{ $agents->count() }})</span></h1>
        <button onclick="document.getElementById('agentModal').classList.remove('d-none')" class="btn btn-primary px-4 py-2 rounded-3 text-white fw-semibold shadow">
            <i class="fas fa-plus me-1"></i> Baru
        </button>
    </div>

    

    <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3">
        @forelse($agents as $a)
        <div class="col">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle {{ $a->is_active ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-muted' }}" style="width:40px;height:40px;">
                                <i class="fas fa-user-tie small"></i>
                            </div>
                            <div>
                                <p class="fw-semibold text-dark mb-0">{{ $a->name }}</p>
                                <p class="small text-muted mb-0">@<span class="text-lowercase">{{ $a->username }}</span></p>
                            </div>
                        </div>
                        <span class="badge rounded-pill {{ $a->is_active ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted' }}">
                            {{ $a->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <div class="row g-2 small mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <span class="text-muted d-block">Saldo</span>
                                <span class="fw-semibold text-dark">Rp {{ number_format($a->balance, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded-3 p-2">
                                <span class="text-muted d-block">{{ $a->commission_type === 'percent' ? 'Komisi (%)' : 'Komisi (Rp)' }}</span>
                                <span class="fw-semibold text-dark">
                                    @if($a->commission_type === 'percent')
                                        {{ $a->commission_value }}%
                                    @else
                                        Rp {{ number_format($a->commission_value, 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    @if($a->phone)
                    <div class="small text-muted mb-3">
                        <i class="fas fa-phone me-1"></i> {{ $a->phone }}
                        @if($a->email) · <i class="fas fa-envelope me-1"></i>{{ $a->email }} @endif
                    </div>
                    @endif

                    <div class="d-flex gap-2 pt-3 border-top border-light">
                        <button onclick="editAgent({{ $a->id }}, '{{ addslashes($a->name) }}', '{{ $a->username }}', '{{ $a->phone }}', '{{ $a->email }}', '{{ $a->commission_type }}', '{{ $a->commission_value }}', {{ $a->is_active ? 'true' : 'false' }})" class="btn btn-outline-primary btn-sm flex-fill rounded-3 fw-semibold">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button onclick="topupAgent({{ $a->id }}, '{{ addslashes($a->name) }}')" class="btn btn-outline-success btn-sm flex-fill rounded-3 fw-semibold">
                            <i class="fas fa-wallet me-1"></i> Topup
                        </button>
                        <form method="POST" action="/admin/agents/delete/{{ $a->id }}" onsubmit="return confirm('Hapus agen {{ $a->name }}?')" class="flex-fill">
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
                <i class="fas fa-user-tie text-muted" style="font-size:1.25rem;"></i>
            </div>
            <p class="text-muted small">Belum ada agen</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Add Agent Modal --}}
<div id="agentModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Agen Baru</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('agentModal').classList.remove('show'); document.getElementById('agentModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/agents/add">
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

{{-- Edit Agent Modal --}}
<div id="editModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Agen</h5>
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

{{-- Topup Modal --}}
<div id="topupModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Topup Saldo</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('topupModal').classList.remove('show'); document.getElementById('topupModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3" id="topupName">Agen: -</p>
                <form method="POST" action="" id="topupForm">
                    @csrf
                    <div class="mb-3">
                        <input type="number" name="amount" required placeholder="Jumlah Topup (Rp)" min="1" class="form-control">
                    </div>
                    <div class="mb-3">
                        <input type="text" name="note" placeholder="Catatan (opsional)" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">Topup</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editAgent(id, name, username, phone, email, comType, comValue, isActive) {
    document.getElementById('editForm').action = '/admin/agents/edit/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_commission_type').value = comType;
    document.getElementById('edit_commission_value').value = comValue;
    if (isActive) {
        document.getElementById('edit_active').checked = true;
    } else {
        document.getElementById('edit_inactive').checked = true;
    }
    document.getElementById('editModal').classList.remove('d-none');
}

function topupAgent(id, name) {
    document.getElementById('topupForm').action = '/admin/agents/topup/' + id;
    document.getElementById('topupName').textContent = 'Agen: ' + name;
    document.getElementById('topupModal').classList.remove('d-none');
}
</script>
@endpush