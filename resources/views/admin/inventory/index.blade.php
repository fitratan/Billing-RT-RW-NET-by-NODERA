@extends('layouts.admin')

@section('title', 'Inventaris')

@section('content')
<div class="container-fluid px-0 pb-5">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold text-dark mb-0">Inventaris</h4>
        <div class="d-flex gap-2">
            <button onclick="openAddItem()" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-plus"></i> Tambah Barang
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-3 small mb-4" role="alert">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 py-3 small mb-4" role="alert">
        <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-4 gap-1" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-items-btn" data-bs-toggle="pill" data-bs-target="#tab-items" type="button" role="tab">
                <i class="bi bi-boxes me-1"></i> Barang
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-categories-btn" data-bs-toggle="pill" data-bs-target="#tab-categories" type="button" role="tab">
                <i class="bi bi-tags me-1"></i> Kategori
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Tab: Items --}}
        <div class="tab-pane fade show active" id="tab-items" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <h6 class="fw-semibold text-dark mb-0"><i class="bi bi-boxes text-primary me-2"></i>Daftar Barang</h6>
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="itemSearch" placeholder="Cari barang..." onkeyup="filterTable('itemSearch', 'itemsTable')" class="form-control bg-light border-0">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold text-muted">Nama Barang</th>
                                <th class="small fw-semibold text-muted">Kategori</th>
                                <th class="text-center small fw-semibold text-muted">Stok</th>
                                <th class="text-end small fw-semibold text-muted">Harga</th>
                                <th class="text-end small fw-semibold text-muted">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr>
                                <td>
                                    <span class="fw-medium text-dark">{{ $item->name }}</span>
                                    @if($item->notes)
                                    <br><small class="text-muted">{{ Str::limit($item->notes, 60) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($item->category)
                                    <span class="badge bg-light text-purple fw-medium px-3 py-2" style="color:#6f42c1;">{{ $item->category->name }}</span>
                                    @else
                                    <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php $lowStock = $item->quantity <= 5; @endphp
                                    <span class="badge rounded-pill {{ $lowStock ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' }} px-3 py-2 fw-bold">
                                        <i class="bi bi-{{ $lowStock ? 'exclamation-triangle' : 'check-circle' }} me-1"></i>
                                        {{ $item->quantity }} {{ $item->unit }}
                                    </span>
                                </td>
                                <td class="text-end fw-medium text-dark">Rp{{ number_format($item->price, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <button onclick="openAdjustStock({{ $item->id }}, '{{ $item->name }}', {{ $item->quantity }})" class="btn btn-sm btn-warning" title="Sesuaikan Stok">
                                            <i class="bi bi-arrow-up-down"></i>
                                        </button>
                                        <button onclick="openEditItem({{ $item->id }}, '{{ $item->name }}', {{ $item->category_id ?? 'null' }}, {{ $item->quantity }}, '{{ $item->unit }}', {{ $item->price }}, '{{ addslashes($item->notes ?? '') }}')" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="/admin/inventory/item/delete/{{ $item->id }}" method="POST" onsubmit="return confirm('Hapus barang {{ $item->name }}?')" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-box d-block fs-1 text-secondary mb-2"></i>
                                    <small>Belum ada barang inventaris</small>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tab: Categories --}}
        <div class="tab-pane fade" id="tab-categories" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-semibold text-dark mb-0"><i class="bi bi-tags text-primary me-2"></i>Daftar Kategori</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small fw-semibold text-muted">Nama Kategori</th>
                                        <th class="small fw-semibold text-muted">Keterangan</th>
                                        <th class="text-end small fw-semibold text-muted">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($categories as $cat)
                                    <tr>
                                        <td class="fw-medium text-dark">{{ $cat->name }}</td>
                                        <td class="text-muted small">{{ $cat->description ?? '—' }}</td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <button onclick="openEditCategory({{ $cat->id }}, '{{ $cat->name }}', '{{ addslashes($cat->description ?? '') }}')" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form action="/admin/inventory/category/delete/{{ $cat->id }}" method="POST" onsubmit="return confirm('Hapus kategori {{ $cat->name }}?')" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-danger" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="bi bi-tag d-block fs-1 text-secondary mb-2"></i>
                                            <small>Belum ada kategori</small>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Kategori</h6>
                            <form action="/admin/inventory/category/add" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-secondary">Nama Kategori</label>
                                    <input type="text" name="name" required class="form-control" placeholder="Contoh: Kabel">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-medium text-secondary">Keterangan</label>
                                    <textarea name="description" rows="2" class="form-control" placeholder="Opsional"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Simpan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Add/Edit Item --}}
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="itemModalTitle">Tambah Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm" method="POST" action="/admin/inventory/item/add">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="itemId">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="itemName" required class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Kategori</label>
                        <select name="category_id" id="itemCategory" class="form-select">
                            <option value="">— Pilih Kategori —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Jumlah <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="itemQuantity" required min="0" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="unit" id="itemUnit" required class="form-control" placeholder="pcs">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Harga (Rp)</label>
                        <input type="number" name="price" id="itemPrice" min="0" step="100" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Catatan</label>
                        <textarea name="notes" id="itemNotes" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Adjust Stock --}}
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Sesuaikan Stok</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockForm" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="stockItemId">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <p class="fw-medium text-dark mb-0" id="stockItemName"></p>
                        <small class="text-muted">Stok saat ini: <strong id="stockCurrentQty">0</strong></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Penyesuaian <span class="text-danger">*</span></label>
                        <p class="text-muted small mb-1">Gunakan nilai positif (+) untuk menambah, negatif (-) untuk mengurangi</p>
                        <input type="number" name="adjustment" id="stockAdjustment" required class="form-control" placeholder="Contoh: 10 atau -5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Alasan</label>
                        <textarea name="reason" id="stockReason" rows="2" class="form-control" placeholder="Contoh: Pembelian dari supplier"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Sesuaikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Edit Category --}}
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="catForm" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="catId">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" required class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Keterangan</label>
                        <textarea name="description" id="catDesc" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAddItem() {
    document.getElementById('itemModalTitle').textContent = 'Tambah Barang';
    document.getElementById('itemForm').action = '/admin/inventory/item/add';
    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

function openEditItem(id, name, catId, qty, unit, price, notes) {
    document.getElementById('itemModalTitle').textContent = 'Edit Barang';
    document.getElementById('itemForm').action = '/admin/inventory/item/edit/' + id;
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
    document.getElementById('itemCategory').value = catId || '';
    document.getElementById('itemQuantity').value = qty;
    document.getElementById('itemUnit').value = unit;
    document.getElementById('itemPrice').value = price;
    document.getElementById('itemNotes').value = notes;
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

function openAdjustStock(id, name, currentQty) {
    document.getElementById('stockItemId').value = id;
    document.getElementById('stockItemName').textContent = name;
    document.getElementById('stockCurrentQty').textContent = currentQty;
    document.getElementById('stockForm').action = '/admin/inventory/item/adjust/' + id;
    document.getElementById('stockAdjustment').value = '';
    document.getElementById('stockReason').value = '';
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

function openEditCategory(id, name, desc) {
    document.getElementById('catId').value = id;
    document.getElementById('catName').value = name;
    document.getElementById('catDesc').value = desc;
    document.getElementById('catForm').action = '/admin/inventory/category/edit/' + id;
    new bootstrap.Modal(document.getElementById('catModal')).show();
}

function filterTable(inputId, tableId) {
    const q = document.getElementById(inputId).value.toLowerCase();
    const rows = document.getElementById(tableId).querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
@endpush
