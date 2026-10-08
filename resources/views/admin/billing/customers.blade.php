@extends('layouts.admin')

@section('title', 'Pelanggan')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h1 class="h4 fw-bold text-dark mb-0">Pelanggan <span class="fw-normal text-muted fs-6">({{ $customers->count() }})</span></h1>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="POST" action="/admin/billing/customers/sync" class="d-flex align-items-center gap-1 m-0" style="display:inline-flex;margin:0!important;">
                @csrf
                <select name="router_id" required class="form-select form-select-sm" style="min-width:140px;max-width:220px;">
                    <option value="">Router...</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm" title="Sync"><i class="bi bi-arrow-repeat"></i></button>
            </form>
            <a href="/admin/billing/customers/template" class="btn btn-outline-secondary btn-sm" title="Template"><i class="bi bi-download"></i></a>
            <button data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetCustomerModal()" class="btn btn-primary btn-sm" title="Baru">
                <i class="bi bi-plus"></i>
            </button>
        </div>
    </div>

    {{-- Search + Filter --}}
    <div class="mb-3">
        <div class="input-group" style="background:var(--card);border-radius:12px;overflow:hidden;box-shadow:var(--shadow);">
            <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="customerSearch" placeholder="Cari nama atau kode pelanggan..." class="form-control border-0 shadow-none" onkeyup="filterCustomers()" style="font-size:14px;padding:10px 0;">
            <button class="btn btn-light border-0 text-muted shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#customerFilterCollapse" style="font-size:16px;"><i class="bi bi-funnel"></i></button>
        </div>
        <div class="collapse mt-2" id="customerFilterCollapse">
            <div class="d-flex gap-2 align-items-center rounded-3 p-2" style="background:var(--card);box-shadow:var(--shadow);">
                <select id="packageFilter" class="form-select form-select-sm border-0" style="font-size:13px;" onchange="filterCustomers()">
                    <option value="">Semua Paket</option>
                    @foreach($packages as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Batch bar --}}
    <div class="d-flex align-items-center justify-content-between mb-3" id="batchBar">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAll(this)">
            <label class="form-check-label small text-muted" for="selectAll">Pilih semua</label>
        </div>
        <button type="button" id="batchDeleteBtn" class="btn btn-sm btn-danger d-none" onclick="batchDelete()">
            <i class="bi bi-trash"></i> Hapus Terpilih
        </button>
    </div>

    {{-- Customer Cards Grid --}}
    <div class="row g-3">
        @forelse($customers as $c)
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100" data-package="{{ $c->package_id }}">
                <div class="card-body customer-card">
                    <div class="d-flex gap-2">
                        <div class="pt-1">
                            <input class="form-check-input customer-checkbox" type="checkbox" value="{{ $c->id }}">
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="fw-semibold text-dark mb-0 text-truncate small customer-name">{{ $c->name }}</p>
                                <span class="badge bg-secondary-subtle text-secondary small customer-code" style="font-size: 10px;">{{ $c->code }}</span>
                            </div>
                            <p class="text-muted mb-0 small text-truncate">{{ $c->pppoe_username }} · {{ $c->package?->name ?? '-' }}</p>
                            @if($c->phone)
                            <p class="text-muted mb-0 small"><i class="bi bi-telephone"></i> {{ $c->phone }}</p>
                            @endif
                            @if($c->router?->name)
                            <p class="text-muted mb-0 small text-truncate"><i class="bi bi-router"></i> {{ $c->router->name }}</p>
                            @endif
                            <p class="text-muted mb-0 small"><i class="bi bi-calendar"></i> Jatuh tempo: tgl {{ $c->isolation_date ?? '20' }}</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                        <div class="d-flex gap-1">
                            <span class="badge rounded-pill
                                {{ $c->status === 'active' ? 'bg-success bg-opacity-10 text-success' : '' }}
                                {{ $c->status === 'isolated' ? 'bg-danger bg-opacity-10 text-danger' : '' }}
                                {{ $c->status === 'inactive' ? 'bg-light text-muted' : '' }}">
                                {{ ucfirst($c->status) }}
                            </span>
                            @if($c->package?->profile_normal)
                            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary">{{ $c->package->profile_normal }}</span>
                            @endif
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary px-2" onclick="openEditCustomer({{ $c->id }})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning px-2" title="Reset PIN" onclick="if(confirm('Reset PIN {{ $c->name }} ke 6 digit terakhir nomor HP?')){ document.getElementById('resetPin{{ $c->id }}').submit(); }">
                                <i class="bi bi-key"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger px-2"
                                onclick="openDeleteCustomer({{ $c->id }}, '{{ str_replace("'", "\\'", $c->name) }}', '{{ $c->pppoe_username }}')" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form method="POST" action="/admin/billing/customers/reset-pin/{{ $c->id }}" id="resetPin{{ $c->id }}" style="display:none;">@csrf</form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width:64px;height:64px;">
                <i class="bi bi-people text-muted fs-2"></i>
            </div>
            <p class="text-muted small">Belum ada pelanggan. <a href="#" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetCustomerModal()" class="text-primary fw-medium text-decoration-none">Tambah sekarang?</a></p>
        </div>
        @endforelse
    </div>
</div>

{{-- Create / Edit Customer Modal --}}
<div id="customerModal" class="modal fade" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="customerModalLabel">Pelanggan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="customerForm" action="/admin/billing/customers/add">
                    @csrf
                    <input type="hidden" name="id" id="editCustomerId">

                    <div class="row g-3">
                        <div class="col-6">
                            <input type="text" name="name" id="editName" required placeholder="Nama Lengkap" class="form-control bg-light border-0">
                        </div>
                        <div class="col-6">
                            <input type="text" name="phone" id="editPhone" placeholder="No. HP" class="form-control bg-light border-0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <input type="text" name="pppoe_username" id="editPppoe" required placeholder="Username PPPoE" class="form-control bg-light border-0">
                        <small id="pppoeHelp" class="text-muted d-none">Username tidak dapat diubah setelah dibuat</small>
                    </div>
                    <div class="mt-3">
                        <select name="router_id" id="editRouter" class="form-select bg-light border-0">
                            <option value="">Pilih Router MikroTik (opsional)</option>
                            @foreach(\App\Models\Mikrotik::where('is_active', true)->get() as $r)
                            <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->host }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-6">
                            <select name="package_id" id="editPackage" required class="form-select bg-light border-0">
                                <option value="">Pilih Paket...</option>
                                @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}">{{ $pkg->name }} — Rp{{ number_format($pkg->price, 0, ',', '.') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" name="isolation_date" id="editIsolationDate" required value="20" min="1" max="28" class="form-control bg-light border-0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <input type="text" name="email" id="editEmail" placeholder="Email" class="form-control bg-light border-0">
                    </div>
                    <div class="mt-3">
                        <textarea name="address" id="editAddress" rows="2" placeholder="Alamat" class="form-control bg-light border-0 resize-none"></textarea>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-6">
                            <input type="text" name="lat" id="editLat" placeholder="Latitude" class="form-control bg-light border-0">
                        </div>
                        <div class="col-6">
                            <input type="text" name="lng" id="editLng" placeholder="Longitude" class="form-control bg-light border-0">
                        </div>
                    </div>
                    <div class="mt-3 form-check" id="createPppoeWrapper">
                        <input type="checkbox" name="create_pppoe" value="1" checked class="form-check-input" id="createPppoe">
                        <label class="form-check-label small text-secondary" for="createPppoe">Buat user PPPoE di MikroTik (password = username)</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3 shadow-sm d-flex align-items-center justify-content-center gap-2" id="customerSubmitBtn">
                        <i class="bi bi-floppy"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Delete Customer Modal --}}
<div id="deleteCustomerModal" class="modal fade" tabindex="-1" aria-labelledby="deleteCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="deleteCustomerModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Hapus Pelanggan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Yakin ingin menghapus pelanggan berikut?</p>
                <div class="bg-light rounded-3 p-3 mb-3">
                    <p class="fw-semibold text-dark mb-0" id="deleteCustomerName">-</p>
                    <p class="text-muted small mb-0" id="deleteCustomerPppoe">-</p>
                </div>
                <form method="POST" id="deleteCustomerForm">
                    @csrf
                    <input type="hidden" name="delete_pppoe" id="deletePppoeValue" value="0">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger d-flex align-items-center justify-content-center gap-2"
                            onclick="document.getElementById('deletePppoeValue').value='1'">
                            <i class="bi bi-router"></i> Hapus dari MikroTik Juga
                        </button>
                        <button type="submit" class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2"
                            onclick="document.getElementById('deletePppoeValue').value='0'">
                            <i class="bi bi-trash"></i> Hapus dari Aplikasi Saja
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Batch Delete Customer Modal --}}
{{-- Delete All Modal --}}  
{{-- Batch Delete Modal --}}
<div class="modal fade" id="batchDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Hapus Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Yakin ingin menghapus <strong id="batchCountLabel">0</strong> pelanggan terpilih?</p>
                <p class="text-muted small mb-3">Pelanggan dengan invoice belum lunas akan dilewati.</p>
                <form method="POST" action="/admin/billing/customers/delete-batch" id="batchDeleteForm">
                    @csrf
                    <input type="hidden" name="ids" id="batchIds" value="">
                    <input type="hidden" name="delete_pppoe" id="batchDeletePppoe" value="0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger d-flex align-items-center justify-content-center gap-2"
                            onclick="document.getElementById('batchDeletePppoe').value='1'">
                            <i class="bi bi-router"></i> Hapus dari MikroTik Juga
                        </button>
                        <button type="submit" class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2"
                            onclick="document.getElementById('batchDeletePppoe').value='0'">
                            <i class="bi bi-trash"></i> Hapus dari Aplikasi Saja
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ===== Reset modal untuk Create baru =====
function resetCustomerModal() {
    document.getElementById('customerModalLabel').textContent = 'Pelanggan Baru';
    document.getElementById('customerForm').action = '/admin/billing/customers/add';
    document.getElementById('editCustomerId').value = '';
    document.getElementById('editName').value = '';
    document.getElementById('editPhone').value = '';
    document.getElementById('editPppoe').value = '';
    document.getElementById('editPppoe').readOnly = false;
    document.getElementById('editPppoe').required = true;
    document.getElementById('editRouter').value = '';
    document.getElementById('editPackage').value = '';
    document.getElementById('editIsolationDate').value = '20';
    document.getElementById('editEmail').value = '';
    document.getElementById('editAddress').value = '';
    document.getElementById('editLat').value = '';
    document.getElementById('editLng').value = '';
    document.getElementById('createPppoeWrapper').classList.remove('d-none');
    document.getElementById('createPppoe').checked = true;
    document.getElementById('pppoeHelp').classList.add('d-none');
    document.getElementById('customerSubmitBtn').innerHTML = '<i class="bi bi-floppy"></i> Simpan';
}

// ===== Open Edit Modal =====
function openEditCustomer(id) {
    // Reset form dulu
    resetCustomerModal();

    // Ubah title & action untuk edit
    document.getElementById('customerModalLabel').textContent = 'Edit Pelanggan';
    document.getElementById('customerForm').action = '/admin/billing/customers/edit/' + id;
    document.getElementById('editCustomerId').value = id;
    document.getElementById('pppoeHelp').classList.remove('d-none');
    document.getElementById('createPppoeWrapper').classList.add('d-none');

    // Fetch data dari API
    fetch('/admin/billing/customers/get/' + id)
        .then(res => res.json())
        .then(response => {
            if (!response.success) {
                alert('Gagal mengambil data pelanggan: ' + (response.message || 'Unknown error'));
                return;
            }
            const c = response.data;
            document.getElementById('editName').value = c.name || '';
            document.getElementById('editPhone').value = c.phone || '';
            document.getElementById('editPppoe').value = c.pppoe_username || '';
            document.getElementById('editPppoe').readOnly = true;
            document.getElementById('editPppoe').required = false;
            document.getElementById('editRouter').value = c.router_id || '';
            document.getElementById('editPackage').value = c.package_id || '';
            document.getElementById('editIsolationDate').value = c.isolation_date || '20';
            document.getElementById('editEmail').value = c.email || '';
            document.getElementById('editAddress').value = c.address || '';
            document.getElementById('editLat').value = c.lat || '';
            document.getElementById('editLng').value = c.lng || '';
            document.getElementById('customerSubmitBtn').innerHTML = '<i class="bi bi-pencil"></i> Update';

            // Buka modal
            var modal = new bootstrap.Modal(document.getElementById('customerModal'));
            modal.show();
        })
        .catch(err => {
            alert('Gagal terhubung ke server: ' + err.message);
        });
}

// ===== Open Delete Modal =====
function openDeleteCustomer(id, name, pppoe) {
    document.getElementById('deleteCustomerName').textContent = name;
    document.getElementById('deleteCustomerPppoe').textContent = pppoe;
    document.getElementById('deleteCustomerForm').action = '/admin/billing/customers/delete/' + id;
    document.getElementById('deletePppoeValue').value = '0';

    var modal = new bootstrap.Modal(document.getElementById('deleteCustomerModal'));
    modal.show();
}

// ===== Batch Delete =====
let selectedIds = [];
function toggleAll(source) {
    document.querySelectorAll('.customer-checkbox').forEach(cb => {
        cb.checked = source.checked;
    });
    updateBatchBtn();
}
function updateBatchBtn() {
    const checked = document.querySelectorAll('.customer-checkbox:checked').length;
    const btn = document.getElementById('batchDeleteBtn');
    btn.classList.toggle('d-none', checked === 0);
    if (checked > 0) btn.innerHTML = '<i class="bi bi-trash"></i> Hapus ' + checked;
}
function batchDelete() {
    const ids = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return;
    document.getElementById('batchIds').value = ids.join(',');
    document.getElementById('batchCountLabel').textContent = ids.length;
    new bootstrap.Modal(document.getElementById('batchDeleteModal')).show();
}
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('customer-checkbox')) updateBatchBtn();
});
</script>
<script>
// ===== Filter customers by name or code or package =====
function filterCustomers() {
    const q = document.getElementById('customerSearch').value.toLowerCase();
    const pkg = document.getElementById('packageFilter').value;
    document.querySelectorAll('.customer-card').forEach(card => {
        const name = card.querySelector('.customer-name').textContent.toLowerCase();
        const code = card.querySelector('.customer-code')?.textContent.toLowerCase() || '';
        const matchText = name.includes(q) || code.includes(q);
        const matchPkg = !pkg || card.closest('.card').dataset.package === pkg;
        card.closest('.col-12').style.display = (matchText && matchPkg) ? '' : 'none';
    });
}
</script>
@endpush
