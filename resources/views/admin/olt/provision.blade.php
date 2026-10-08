@extends('layouts.admin')
@section('title', 'ONU Provision - {{ $olt->name }}')
@section('content')
<style>
.onu-card{background:var(--card);border-radius:12px;padding:16px;margin-bottom:10px;box-shadow:var(--shadow);}
.onu-card .sn{font-size:13px;font-weight:600;}
.onu-card .meta{font-size:11px;color:var(--text3);}
.status-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:600;}
</style>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-hdd-stack text-primary me-2"></i>ONU Provision</h1>
            <p class="small text-muted mb-0">{{ $olt->name }} ({{ $olt->host }})</p>
        </div>
        <a href="/admin/olt" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    {{-- Scan --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-search text-primary me-1"></i>Scan ONU Baru</h6>
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-medium">PON Port</label>
                    <input type="text" id="ponInput" class="form-control" value="1/1/1" placeholder="1/1/1">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-medium">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="scanOnus()" id="scanBtn">
                        <i class="bi bi-search me-1"></i> Scan
                    </button>
                </div>
            </div>
            <div id="scanResult" class="mt-3"></div>
        </div>
    </div>

    {{-- Provisioned ONUs --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-check-circle text-success me-1"></i>ONU Terkonfigurasi ({{ $configuredOnus->count() }})</h6>
        </div>
        <div class="card-body p-0">
            @if($configuredOnus->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold ps-3">Serial</th>
                            <th class="fw-semibold">Nama</th>
                            <th class="fw-semibold">PON</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($configuredOnus as $onu)
                        <tr>
                            <td class="ps-3 fw-medium">{{ $onu->serial_number }}</td>
                            <td>{{ $onu->name }}</td>
                            <td>{{ $onu->pon_port }}</td>
                            <td><span class="status-badge bg-success bg-opacity-10 text-success">Active</span></td>
                            <td class="text-end pe-3">
                                <form method="POST" action="/admin/olt/onus/{{ $olt->id }}/delete/{{ $onu->id }}" class="d-inline" onsubmit="return confirm('Hapus ONU?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger px-2"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4 text-muted">
                <i class="bi bi-hdd-stack" style="font-size:2rem;"></i>
                <p class="small mt-1 mb-0">Belum ada ONU terkonfigurasi</p>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Provision Modal --}}
<div class="modal fade" id="provisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-hdd-stack text-success me-2"></i>Provision ONU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/admin/olt/provision-onu/{{ $olt->id }}">
                    @csrf
                    <input type="hidden" name="serial" id="provSerial">
                    <input type="hidden" name="pon" id="provPon">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">ONU Index</label>
                        <input type="number" name="onu_index" id="provIndex" required min="1" max="128" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nama ONU</label>
                        <input type="text" name="name" id="provName" required class="form-control" placeholder="Nama pelanggan atau lokasi">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">VLAN</label>
                            <input type="number" name="vlan" value="10" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">S-VLAN</label>
                            <input type="number" name="svlan" value="100" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Hubungkan ke Pelanggan (opsional)</label>
                        <select name="customer_id" class="form-select">
                            <option value="">-- Tanpa pelanggan --</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->pppoe_username }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-3 fw-semibold py-2">
                        <i class="bi bi-check-circle me-1"></i> Provision ONU
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function scanOnus() {
    const btn = document.getElementById('scanBtn');
    const pon = document.getElementById('ponInput').value;
    const result = document.getElementById('scanResult');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Scanning...';
    result.innerHTML = '';

    try {
        const r = await fetch('/admin/olt/scan-onus/{{ $olt->id }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({pon: pon})
        });
        const d = await r.json();

        if (d.success && d.onus.length > 0) {
            let html = `<div class="fw-semibold small mb-2">Ditemukan ${d.count} ONU tidak terkonfigurasi:</div>`;
            d.onus.forEach((onu, i) => {
                html += `<div class="onu-card d-flex align-items-center justify-content-between">
                    <div>
                        <div class="sn">${onu.serial}</div>
                        <div class="meta">PON: ${onu.pon}</div>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="openProvision('${onu.serial}', '${onu.pon}')">
                        <i class="bi bi-plus-circle me-1"></i> Provision
                    </button>
                </div>`;
            });
            result.innerHTML = html;
        } else if (d.success) {
            result.innerHTML = '<div class="alert alert-success mb-0 py-2 small"><i class="bi bi-check-circle me-1"></i> Tidak ada ONU baru ditemukan</div>';
        } else {
            result.innerHTML = '<div class="alert alert-danger mb-0 py-2 small">Gagal scan: ' + (d.message || 'Unknown error') + '</div>';
        }
    } catch (e) {
        result.innerHTML = '<div class="alert alert-danger mb-0 py-2 small">Error: ' + e.message + '</div>';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-search me-1"></i> Scan';
}

function openProvision(serial, pon) {
    document.getElementById('provSerial').value = serial;
    document.getElementById('provPon').value = pon;
    document.getElementById('provIndex').value = '';
    document.getElementById('provName').value = '';
    new bootstrap.Modal(document.getElementById('provisionModal')).show();
}
</script>
@endsection
