@extends('layouts.admin')

@section('title', 'Paket')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 fw-bold text-dark mb-0">Paket <span class="fw-normal text-muted fs-6">({{ $packages->count() }})</span></h1>
        <div class="d-flex gap-2 align-items-center">
            @if($routers->count() > 0)
            <form method="POST" action="/admin/billing/packages/sync" class="d-flex align-items-center gap-2" style="margin:0!important;">
                @csrf
                <select name="router_id" required class="form-select form-select-sm" style="min-width:160px;max-width:260px;">
                    <option value="">Pilih Router...</option>
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->host }})</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="return confirm('Sync paket dari router yg dipilih?')">
                    <i class="bi bi-arrow-repeat"></i> Sync
                </button>
            </form>
            @endif
            <button data-bs-toggle="modal" data-bs-target="#packageModal" class="btn btn-primary shadow-sm" onclick="resetPackageForm()">
                <i class="bi bi-plus"></i>
            </button>
        </div>
    </div>

    {{-- Select All + Batch Delete Toolbar --}}
    <div class="d-flex align-items-center justify-content-between mb-3 px-1">
        <div class="d-flex align-items-center gap-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleAll(this)">
                <label class="form-check-label small text-secondary fw-medium" for="selectAll">Pilih Semua</label>
            </div>
            <span class="text-muted small" id="selectedCount">0 dipilih</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 d-none" id="batchDeleteBtn" onclick="openBatchDeleteModal()">
            <i class="bi bi-trash"></i> Hapus Terpilih
        </button>
    </div>

    {{-- Hidden form for batch delete --}}
    <form method="POST" action="/admin/billing/packages/delete-batch" id="batchDeleteForm">
        @csrf
        <input type="hidden" name="delete_router" id="batchDeleteRouter" value="">
    </form>

    {{-- Package Cards Grid --}}
    <div class="row g-3">
        @forelse($packages as $p)
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        {{-- Checkbox --}}
                        <div class="form-check pt-1">
                            <input type="checkbox" class="form-check-input package-checkbox" value="{{ $p->id }}" onchange="updateBatchBtns()">
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="fw-semibold text-dark mb-0 small">{{ $p->name }}</p>
                                    <p class="text-muted mb-0 small">{{ $p->customers_count ?? 0 }} pelanggan</p>
                                </div>
                                <div class="text-end">
                                    <p class="fw-bold text-primary mb-0 small">
                                        @if($p->price > 0)
                                            Rp{{ number_format($p->price, 0, ',', '.') }}
                                        @else
                                            <span class="text-muted fw-normal">Rp0 <span class="small text-warning fw-medium">— atur harga</span></span>
                                        @endif
                                    </p>
                                    @if($p->promo_price && $p->promo_cycles > 0)
                                    <p class="text-success small mb-0">Promo: Rp{{ number_format($p->promo_price, 0, ',', '.') }} ({{ $p->promo_cycles }}x)</p>
                                    @endif
                                    <p class="text-muted mb-0 small">{{ $p->profile_normal }}</p>
                                    @if($p->router)
                                    <p class="text-muted mb-0 small"><i class="bi bi-router"></i> {{ $p->router->name }}</p>
                                    @endif
                                    @if($p->use_ppn || $p->use_uso)
                                    <p class="text-muted small mb-0">
                                        @if($p->use_ppn) PPN {{ $p->ppn_percentage }}% @endif
                                        @if($p->use_uso) USO {{ $p->uso_percentage }}% @endif
                                    </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="d-flex gap-2 justify-content-end mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1"
                                onclick='openEditPackage(
                                    {{ $p->id }},
                                    "{{ addslashes($p->name) }}",
                                    {{ $p->price }},
                                    {{ $p->router_id ?? 'null' }},
                                    {{ $p->promo_price ?? 'null' }},
                                    {{ $p->promo_cycles ?? 0 }},
                                    "{{ addslashes($p->profile_normal) }}",
                                    "{{ addslashes($p->profile_isolir) }}",
                                    {{ $p->use_ppn ? '1' : '0' }},
                                    {{ $p->use_uso ? '1' : '0' }},
                                    {{ $p->prorate_first_invoice ? '1' : '0' }},
                                    {{ $p->admin_fee ?? 0 }},
                                    {{ $p->late_fee ?? 0 }},
                                    {{ $p->materai ?? 0 }},
                                    "{{ addslashes($p->description ?? '') }}",
                                    {{ $p->auto_isolir ?? 1 }},
                                    {{ $p->isolir_interval_months ?? 1 }}
                                )'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="openDeletePackage({{ $p->id }}, '{{ addslashes($p->name) }}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width:64px;height:64px;">
                <i class="bi bi-box-seam text-muted fs-2"></i>
            </div>
            <p class="text-muted small mb-0">Belum ada paket</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Package Modal (Create / Edit) --}}
<div id="packageModal" class="modal fade" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="packageModalLabel">Paket Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/billing/packages/add" id="packageForm">
                    @csrf
                    <input type="hidden" name="id" id="editPackageId" value="">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Nama Paket</label>
                            <input type="text" name="name" id="editName" required placeholder="cth: Basic 10M" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Harga Normal</label>
                            <input type="text" name="price" id="editPrice" required inputmode="numeric" placeholder="150000" class="form-control price-format">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium text-secondary">Router (opsional)</label>
                            <select name="router_id" id="editRouterId" class="form-select">
                                <option value="">-- Pilih Router --</option>
                                @foreach($routers as $router)
                                <option value="{{ $router->id }}">{{ $router->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Promo --}}
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3 mt-3">
                        <p class="small fw-semibold text-primary mb-2">Promo Awal</p>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-primary">Harga Promo</label>
                                <input type="text" name="promo_price" id="editPromoPrice" inputmode="numeric" placeholder="0" class="form-control price-format">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-primary">Siklus (bulan)</label>
                                <input type="number" name="promo_cycles" id="editPromoCycles" value="0" min="0" placeholder="0" class="form-control">
                            </div>
                        </div>
                        <p class="small text-primary mt-1 mb-0 opacity-75">Harga promo untuk N bulan pertama, lalu harga normal</p>
                    </div>

                    {{-- MikroTik Profiles / Speed Limit --}}
                    <div class="row g-3 mt-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Profile Normal / Limit Kecepatan (cth: 2M/2M)</label>
                            <input list="profile_normal_list" type="text" name="profile_normal" id="editProfileNormal" required placeholder="cth: 2M/2M atau pilih profile..." class="form-control font-monospace">
                            <datalist id="profile_normal_list">
                                <option value="2M/2M">2M/2M (Simple Queue ARP)</option>
                                <option value="3M/3M">3M/3M (Simple Queue ARP)</option>
                                <option value="5M/5M">5M/5M (Simple Queue ARP)</option>
                                <option value="10M/10M">10M/10M (Simple Queue ARP)</option>
                                <option value="15M/15M">15M/15M (Simple Queue ARP)</option>
                                <option value="20M/20M">20M/20M (Simple Queue ARP)</option>
                                <option value="30M/30M">30M/30M (Simple Queue ARP)</option>
                                <option value="50M/50M">50M/50M (Simple Queue ARP)</option>
                                <option value="100M/100M">100M/100M (Simple Queue ARP)</option>
                                @foreach($profiles as $pr)
                                <option value="{{ is_array($pr) ? $pr['name'] : (is_object($pr) ? $pr->name : $pr) }}">{{ is_array($pr) ? $pr['name'] : (is_object($pr) ? $pr->name : $pr) }} (PPP Profile)</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium text-secondary">Profile Isolir / Limit Isolir (cth: 512k/512k)</label>
                            <input list="profile_isolir_list" type="text" name="profile_isolir" id="editProfileIsolir" required placeholder="cth: 512k/512k atau isolir" class="form-control font-monospace">
                            <datalist id="profile_isolir_list">
                                <option value="512k/512k">512k/512k</option>
                                <option value="256k/256k">256k/256k</option>
                                <option value="1M/1M">1M/1M</option>
                                <option value="isolir">isolir</option>
                                <option value="expired">expired</option>
                                @foreach($profiles as $pr)
                                <option value="{{ is_array($pr) ? $pr['name'] : (is_object($pr) ? $pr->name : $pr) }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    {{-- Pajak & Biaya --}}
                    <div class="bg-light rounded-3 p-3 mt-3">
                        <p class="small fw-semibold text-secondary mb-2">Pajak & Biaya Tambahan</p>
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="form-check">
                                    <input type="checkbox" name="use_ppn" value="1" class="form-check-input" id="editUsePpn">
                                    <label class="form-check-label small text-secondary" for="editUsePpn">PPN <span class="text-muted">(11%)</span></label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check">
                                    <input type="checkbox" name="use_uso" value="1" class="form-check-input" id="editUseUso">
                                    <label class="form-check-label small text-secondary" for="editUseUso">USO <span class="text-muted">(1.75%)</span></label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check">
                                    <input type="checkbox" name="prorate_first_invoice" value="1" class="form-check-input" id="editProrateFirst">
                                    <label class="form-check-label small text-secondary" for="editProrateFirst">Prorata</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-4">
                                <input type="text" name="admin_fee" id="editAdminFee" value="0" inputmode="numeric" placeholder="Biaya Admin" class="form-control bg-white border-0 price-format">
                            </div>
                            <div class="col-4">
                                <input type="text" name="late_fee" id="editLateFee" value="0" inputmode="numeric" placeholder="Denda Telat" class="form-control bg-white border-0 price-format">
                            </div>
                            <div class="col-4">
                                <input type="text" name="materai" id="editMaterai" value="0" inputmode="numeric" placeholder="Materai" class="form-control bg-white border-0 price-format">
                            </div>
                        </div>
                    </div>

                    {{-- Isolir Otomatis --}}
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3 mt-3">
                        <p class="small fw-semibold text-warning mb-2"><i class="bi bi-shield-exclamation me-1"></i>Isolir Otomatis</p>
                        <div class="row g-2 align-items-center">
                            <div class="col-6">
                                <div class="form-check">
                                    <input type="checkbox" name="auto_isolir" value="1" class="form-check-input" id="editAutoIsolir" checked>
                                    <label class="form-check-label small text-warning" for="editAutoIsolir">Aktifkan isolir otomatis</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1">Interval (bulan)</label>
                                <select name="isolir_interval_months" id="editIsolirInterval" class="form-select bg-white border-0">
                                    <option value="1">1 bulan (standar)</option>
                                    <option value="2">2 bulan</option>
                                    <option value="3">3 bulan</option>
                                    <option value="6">6 bulan</option>
                                </select>
                            </div>
                        </div>
                        <p class="small text-muted mt-1 mb-0">Jika nonaktif, pelanggan dengan paket ini tidak akan diisolir otomatis</p>
                    </div>

                    <div class="mt-3">
                        <textarea name="description" id="editDescription" rows="2" placeholder="Deskripsi Paket" class="form-control bg-light border-0 resize-none"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-floppy"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Delete Single Package Modal --}}
<div id="deletePackageModal" class="modal fade" tabindex="-1" aria-labelledby="deletePackageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="deletePackageModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Hapus Paket
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="deletePackageName">Yakin ingin menghapus paket <strong></strong>?</p>
                <div class="d-flex flex-column gap-2">
                    <form method="POST" action="" id="deleteAppForm" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning d-flex align-items-center justify-content-center gap-2 py-2">
                            <i class="bi bi-database"></i>
                            <span class="text-start">
                                <strong>Hapus dari Aplikasi Saja</strong><br>
                                <small class="fw-normal">Paket dihapus dari database, data MikroTik tetap</small>
                            </span>
                        </button>
                    </form>
                    <form method="POST" action="" id="deleteMikrotikForm" class="d-grid">
                        @csrf
                        <input type="hidden" name="delete_router" value="1">
                        <button type="submit" class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2 py-2">
                            <i class="bi bi-router"></i>
                            <span class="text-start">
                                <strong>Hapus dari MikroTik Juga</strong><br>
                                <small class="fw-normal">Paket dihapus dari database dan profile MikroTik</small>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

{{-- Batch Delete Modal --}}
<div id="batchDeleteModal" class="modal fade" tabindex="-1" aria-labelledby="batchDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="batchDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Hapus Paket Terpilih
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="batchDeleteCount">Yakin ingin menghapus <strong>0</strong> paket terpilih?</p>
                <div class="d-flex flex-column gap-2">
                    <button type="button" class="btn btn-outline-warning d-flex align-items-center justify-content-center gap-2 py-2" onclick="batchDeleteAction(0)">
                        <i class="bi bi-database"></i>
                        <span class="text-start">
                            <strong>Hapus dari Aplikasi Saja</strong><br>
                            <small class="fw-normal">Paket dihapus dari database, data MikroTik tetap</small>
                        </span>
                    </button>
                    <button type="button" class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2 py-2" onclick="batchDeleteAction(1)">
                        <i class="bi bi-router"></i>
                        <span class="text-start">
                            <strong>Hapus dari MikroTik Juga</strong><br>
                            <small class="fw-normal">Paket dihapus dari database dan profile MikroTik</small>
                        </span>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

{{-- JavaScript --}}
@push('scripts')
<script>
// ========== Batch Select & Delete ==========
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.package-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBatchBtns();
}

function updateBatchBtns() {
    const checkboxes = document.querySelectorAll('.package-checkbox:checked');
    const count = checkboxes.length;
    const btn = document.getElementById('batchDeleteBtn');
    const countLabel = document.getElementById('selectedCount');

    if (count > 0) {
        btn.classList.remove('d-none');
        countLabel.textContent = count + ' dipilih';
    } else {
        btn.classList.add('d-none');
        countLabel.textContent = '0 dipilih';
    }

    // Uncheck selectAll if any checkbox is unchecked
    const allCheckboxes = document.querySelectorAll('.package-checkbox');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = allCheckboxes.length > 0 && allCheckboxes.length === count;
    }
}

function openBatchDeleteModal() {
    const checkboxes = document.querySelectorAll('.package-checkbox:checked');
    const count = checkboxes.length;
    if (count === 0) return;

    document.getElementById('batchDeleteCount').innerHTML = 'Yakin ingin menghapus <strong>' + count + '</strong> paket terpilih?';
    const modal = new bootstrap.Modal(document.getElementById('batchDeleteModal'));
    modal.show();
}

function batchDeleteAction(deleteRouter) {
    const form = document.getElementById('batchDeleteForm');
    const checkboxes = document.querySelectorAll('.package-checkbox:checked');

    // Remove any existing hidden inputs
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

    // Add selected ids
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });

    // Set delete_router flag
    document.getElementById('batchDeleteRouter').value = deleteRouter;

    // Close modal and submit
    const modal = bootstrap.Modal.getInstance(document.getElementById('batchDeleteModal'));
    if (modal) modal.hide();
    form.submit();
}

// ========== Package Form (Create / Edit) ==========
function resetPackageForm() {
    document.getElementById('packageModalLabel').textContent = 'Paket Baru';
    document.getElementById('packageForm').action = '/admin/billing/packages/add';
    document.getElementById('packageForm').method = 'POST';
    document.getElementById('editPackageId').value = '';
    document.getElementById('editName').value = '';
    document.getElementById('editPrice').value = '';
    document.getElementById('editRouterId').value = '';
    document.getElementById('editPromoPrice').value = '';
    document.getElementById('editPromoCycles').value = '0';
    document.getElementById('editProfileNormal').value = '';
    document.getElementById('editProfileIsolir').value = '';
    document.getElementById('editUsePpn').checked = false;
    document.getElementById('editUseUso').checked = false;
    document.getElementById('editProrateFirst').checked = false;
    document.getElementById('editAdminFee').value = '0';
    document.getElementById('editLateFee').value = '0';
    document.getElementById('editMaterai').value = '0';
    document.getElementById('editAutoIsolir').checked = true;
    document.getElementById('editIsolirInterval').value = '1';
    document.getElementById('editDescription').value = '';
    // Remove any existing _method hidden input
    const methodInput = document.getElementById('packageForm').querySelector('input[name="_method"]');
    if (methodInput) methodInput.remove();
}

function openEditPackage(id, name, price, routerId, promoPrice, promoCycles, profileNormal, profileIsolir, usePpn, useUso, prorata, adminFee, lateFee, materai, desc, autoIsolir, isolirInterval) {
    // Change modal title
    document.getElementById('packageModalLabel').textContent = 'Edit Paket: ' + name;

    // Change form action to update endpoint
    document.getElementById('packageForm').action = '/admin/billing/packages/update/' + id;

    // Set hidden id
    document.getElementById('editPackageId').value = id;

    // Pre-fill fields dengan format harga
    document.getElementById('editName').value = name;
    document.getElementById('editPrice').value = formatPrice(price);
    document.getElementById('editRouterId').value = routerId || '';
    document.getElementById('editPromoPrice').value = promoPrice ? formatPrice(promoPrice) : '';
    document.getElementById('editPromoCycles').value = promoCycles || 0;
    document.getElementById('editProfileNormal').value = profileNormal;
    document.getElementById('editProfileIsolir').value = profileIsolir;
    document.getElementById('editUsePpn').checked = usePpn == 1;
    document.getElementById('editUseUso').checked = useUso == 1;
    document.getElementById('editProrateFirst').checked = prorata == 1;
    document.getElementById('editAdminFee').value = adminFee || 0;
    document.getElementById('editLateFee').value = lateFee || 0;
    document.getElementById('editMaterai').value = materai || 0;
    document.getElementById('editAutoIsolir').checked = autoIsolir != 0;
    document.getElementById('editIsolirInterval').value = isolirInterval || 1;
    document.getElementById('editDescription').value = desc || '';

    // Open modal
    const modal = new bootstrap.Modal(document.getElementById('packageModal'));
    modal.show();
}

// ========== Auto-format harga dengan titik ==========
document.addEventListener('DOMContentLoaded', function() {
    // Helper: format angka ke format IDR dengan titik
    window.formatPrice = function(val) {
        if (!val && val !== 0) return '';
        let num = val.toString().replace(/[^0-9]/g, '');
        if (num === '') return '';
        return new Intl.NumberFormat('id-ID').format(parseInt(num, 10));
    };

    // Format saat user mengetik
    document.querySelectorAll('.price-format').forEach(function(input) {
        input.addEventListener('input', function(e) {
            // Simpan posisi kursor
            const pos = this.selectionStart;
            const lenBefore = this.value.length;
            
            // Hapus semua non-digit
            let val = this.value.replace(/[^0-9]/g, '');
            if (val === '') { this.value = ''; return; }
            
            // Format dengan titik ribuan
            this.value = new Intl.NumberFormat('id-ID').format(parseInt(val, 10));
            
            // Sesuaikan posisi kursor
            const lenAfter = this.value.length;
            const newPos = Math.max(0, pos + (lenAfter - lenBefore));
            this.setSelectionRange(newPos, newPos);
        });
        
        // Format saat blur (pastikan nilai valid)
        input.addEventListener('blur', function() {
            let val = this.value.replace(/[^0-9]/g, '');
            if (val !== '') {
                this.value = new Intl.NumberFormat('id-ID').format(parseInt(val, 10));
            }
        });
        
        // Format nilai awal (misal dari edit modal)
        if (input.value && input.value !== '0') {
            let val = input.value.replace(/[^0-9]/g, '');
            if (val !== '') {
                input.value = new Intl.NumberFormat('id-ID').format(parseInt(val, 10));
            }
        }
    });
});

// ========== Delete Single Package ==========
function openDeletePackage(id, name) {
    const url = '/admin/billing/packages/delete/' + id;
    document.getElementById('deletePackageName').innerHTML = 'Yakin ingin menghapus paket <strong>' + name + '</strong>?';
    document.getElementById('deleteAppForm').action = url;
    document.getElementById('deleteMikrotikForm').action = url;

    const modal = new bootstrap.Modal(document.getElementById('deletePackageModal'));
    modal.show();
}
</script>
@endpush
@endsection
