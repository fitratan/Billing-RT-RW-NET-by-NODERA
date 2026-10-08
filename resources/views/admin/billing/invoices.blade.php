@extends('layouts.admin')

@section('title', 'Invoice')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h1 class="h4 fw-bold text-dark mb-0">Invoice <span class="fw-normal text-muted fs-6">({{ $invoices->count() }})</span></h1>
        <form method="POST" action="/admin/billing/generate" class="d-inline" style="margin:0!important;">
            @csrf
            <button type="submit" class="btn btn-primary shadow-sm">
                <i class="bi bi-receipt"></i> Generate Invoice
            </button>
        </form>
    </div>

    {{-- Search + Filter --}}
    <div class="mb-3">
        <div class="input-group" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);">
            <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="invoiceSearch" placeholder="Cari pelanggan atau kode..." class="form-control border-0" onkeyup="filterInvoices()" style="font-size:14px;padding:10px 0;">
            <button class="btn btn-light border-0 text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#invoiceFilterCollapse" style="font-size:16px;"><i class="bi bi-funnel"></i></button>
        </div>
        <div class="collapse mt-2" id="invoiceFilterCollapse">
            <div class="d-flex gap-2 align-items-center bg-white rounded-3 p-2" style="box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <select id="statusFilter" class="form-select form-select-sm border-0" style="font-size:13px;" onchange="filterInvoices()">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Lunas</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Batch Actions + Select All row --}}
    <div class="d-flex align-items-center justify-content-between mb-3" id="batchBar">
        <div class="form-check mb-0" id="selectAllWrap">
            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleAll(this)">
            <label class="form-check-label small text-muted" for="selectAll">Pilih semua</label>
        </div>
        <div class="d-flex gap-2" id="batchActions">
            <button id="batchPayBtn" class="btn btn-success btn-sm d-none" onclick="batchAction('pay')">
                <i class="bi bi-check-all"></i> Bayar Terpilih
            </button>
            <button id="batchCancelBtn" class="btn btn-warning btn-sm d-none" onclick="batchAction('cancel')">
                <i class="bi bi-arrow-counterclockwise"></i> Batal Terpilih
            </button>
            <button id="batchDeleteBtn" class="btn btn-danger btn-sm d-none" onclick="batchAction('delete')">
                <i class="bi bi-trash"></i> Hapus Terpilih
            </button>
        </div>
    </div>

    {{-- Invoice List --}}
    <div class="d-flex flex-column gap-3">
        @forelse($invoices as $inv)
        <div class="card shadow-sm border-0 invoice-card" data-code="{{ $inv->customer_code ?? '' }}">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="form-check" style="padding-top:2px;">
                        <input type="checkbox" class="form-check-input invoice-check" value="{{ $inv->id }}" data-status="{{ $inv->status }}">
                    </div>
                    <div class="d-flex align-items-start justify-content-between flex-grow-1">
                        <div>
                            <p class="fw-semibold text-dark mb-0 small inv-name">{{ $inv->customer_name ?? '-' }}</p>
                            <p class="text-muted mb-0 small">{{ $inv->invoice_number }} · {{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}</p>
                        </div>
                        <div class="text-end">
                            <p class="fw-bold text-dark mb-0 small">Rp{{ number_format($inv->amount, 0, ',', '.') }}</p>
                            <span class="badge rounded-pill inv-status
                                {{ $inv->status === 'paid' ? 'bg-success bg-opacity-10 text-success' : '' }}
                                {{ $inv->status === 'pending' ? 'bg-warning bg-opacity-10 text-warning' : '' }}">
                                {{ $inv->status }}
                            </span>
                        </div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="d-flex gap-1 justify-content-between align-items-center">
                    <div class="d-flex gap-1 align-items-center">
                        @if($inv->status === 'paid' && $inv->processed_by)
                        <span class="small text-muted" style="font-size:10px;white-space:nowrap;">
                            <i class="bi bi-person-check text-success"></i> {{ $inv->processed_by }}
                        </span>
                        @endif
                    </div>
                    <div class="d-flex gap-1">
                    @if($inv->status === 'pending')
                    <button type="button" class="btn btn-sm btn-outline-success" title="Bayar" onclick="confirmAction('/admin/billing/pay/{{ $inv->id }}', 'Bayar invoice ini?')"><i class="bi bi-check"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="confirmAction('/admin/billing/delete-invoice/{{ $inv->id }}', 'Hapus invoice ini?')"><i class="bi bi-trash"></i></button>
                    @endif
                    @if($inv->status === 'paid')
                    <button type="button" class="btn btn-sm btn-outline-warning" title="Reset" onclick="confirmAction('/admin/billing/cancel/{{ $inv->id }}', 'Reset invoice ini ke unpaid?')"><i class="bi bi-arrow-counterclockwise"></i></button>
                    @endif
                    <a href="/admin/billing/print/{{ $inv->id }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print"><i class="bi bi-filetype-pdf"></i></a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width:64px;height:64px;">
                <i class="bi bi-receipt text-muted fs-2"></i>
            </div>
            <form method="POST" action="/admin/billing/generate" id="generateForm">
                @csrf
            </form>
            <p class="text-muted small mb-0">Belum ada invoice.
                <a href="/admin/billing/generate" class="text-primary fw-medium text-decoration-none"
                   onclick="event.preventDefault(); document.getElementById('generateForm').submit()">Generate sekarang?</a>
            </p>
        </div>
        @endforelse
    </div>
</div>

{{-- JS: Filter & Batch Actions --}}
<script>
function filterInvoices() {
    const q = document.getElementById('invoiceSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    let visible = 0;
    document.querySelectorAll('.invoice-card').forEach(card => {
        const name = card.querySelector('.inv-name').textContent.toLowerCase();
        const code = card.dataset.code || '';
        const invStatus = card.querySelector('.inv-status').textContent.trim().toLowerCase();
        const matchName = name.includes(q) || code.includes(q);
        const matchStatus = !status || invStatus === status;
        const show = matchName && matchStatus;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const bar = document.getElementById('batchBar');
    bar.style.display = visible > 0 ? 'flex' : 'none';
    document.getElementById('selectAll').checked = false;
    updateBatchBtns();
}

function getChecked() {
    return Array.from(document.querySelectorAll('.invoice-check:checked')).map(cb => cb.value);
}

function updateBatchBtns() {
    const checked = document.querySelectorAll('.invoice-check:checked');
    let pay = 0, cancel = 0, del = 0;
    checked.forEach(cb => {
        const s = cb.dataset.status;
        if (s === 'pending') { pay++; del++; }
        if (s === 'paid') cancel++;
        if (s === 'cancelled') del++;
    });
    document.getElementById('batchPayBtn').classList.toggle('d-none', pay === 0);
    if (pay > 0) document.getElementById('batchPayBtn').innerHTML = '<i class="bi bi-check-all"></i> Bayar ' + pay;
    document.getElementById('batchCancelBtn').classList.toggle('d-none', cancel === 0);
    if (cancel > 0) document.getElementById('batchCancelBtn').innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Reset ' + cancel;
    document.getElementById('batchDeleteBtn').classList.toggle('d-none', del === 0);
    if (del > 0) document.getElementById('batchDeleteBtn').innerHTML = '<i class="bi bi-trash"></i> Hapus ' + del;
}

function batchAction(action) {
    const ids = getChecked();
    if (ids.length === 0) return;

    const labels = { pay: 'Bayar', cancel: 'Reset', delete: 'Hapus' };
    if (!confirm(labels[action] + ' ' + ids.length + ' invoice terpilih?')) return;

    const routes = { pay: '/admin/billing/pay-batch', cancel: '/admin/billing/cancel-batch', delete: '/admin/billing/delete-batch' };
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = routes[action];
    form.innerHTML = '@csrf';
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoice_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function toggleAll(source) {
    document.querySelectorAll('.invoice-check').forEach(cb => {
        // Only affect visible (not filtered out) checkboxes
        const card = cb.closest('.invoice-card');
        if (card && card.style.display !== 'none') {
            cb.checked = source.checked;
        }
    });
    updateBatchBtns();
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('invoice-check')) updateBatchBtns();
});
document.addEventListener('DOMContentLoaded', filterInvoices);
document.addEventListener('DOMContentLoaded', function() {
    // CSRF token from meta tag or inline
    window._csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
});

function confirmAction(url, msg) {
    if (!msg || confirm(msg)) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = url;
        f.innerHTML = '<input name="_token" value="' + window._csrf + '">';
        document.body.appendChild(f);
        f.submit();
    }
}
</script>
@endsection
