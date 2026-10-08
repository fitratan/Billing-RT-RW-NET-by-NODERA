@extends('layouts.admin')
@section('title', 'GenieACS - Monitoring ONU')
@section('content')
<style>
.stat-card-mon{background:var(--card);border-radius:14px;padding:20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;}
.stat-card-mon .icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
.stat-card-mon .num{font-size:1.8rem;font-weight:700;line-height:1;}
.stat-card-mon .label{font-size:12px;color:var(--text2);margin-top:2px;}
.search-box{background:var(--card);border-radius:12px;overflow:hidden;box-shadow:var(--shadow);}
</style>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold mb-0"><i class="bi bi-satellite text-primary me-2"></i>Monitoring ONU</h1>
            <p class="small text-muted mb-0">GenieACS — {{ $total }} perangkat terdaftar</p>
        </div>
        <button class="btn btn-outline-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Sync</button>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card-mon">
                <div class="icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-hdd-network"></i></div>
                <div><div class="num">{{ $total }}</div><div class="label">Total</div></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card-mon">
                <div class="icon bg-success bg-opacity-10 text-success"><i class="bi bi-wifi"></i></div>
                <div><div class="num">{{ $online }}</div><div class="label">Online</div></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card-mon">
                <div class="icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-wifi-off"></i></div>
                <div><div class="num">{{ $offline }}</div><div class="label">Offline</div></div>
            </div>
        </div>
    </div>

    {{-- Search --}}
    <div class="search-box mb-3 p-2">
        <div class="input-group">
            <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="deviceSearch" class="form-control border-0 shadow-none" placeholder="Cari serial number, product class..." style="font-size:14px;">
        </div>
    </div>

    {{-- Device List --}}
    @if(count($devices) > 0)
    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="deviceTable">
                <thead class="table-light">
                    <tr>
                        <th class="fw-semibold ps-3">#</th>
                        <th class="fw-semibold">Serial Number</th>
                        <th class="fw-semibold">Product Class</th>
                        <th class="fw-semibold">Status</th>
                        <th class="fw-semibold">Last Inform</th>
                        <th class="fw-semibold text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $i => $d)
                    @php
                        $sn = $d['_deviceId']['_SerialNumber'] ?? $d['_id'] ?? '-';
                        $product = $d['_deviceId']['_ProductClass'] ?? '-';
                        $lastInform = $d['_lastInform'] ?? null;
                        $isOnline = false;
                        if ($lastInform) {
                            $timeDiff = time() - (is_string($lastInform) ? strtotime($lastInform) : ($lastInform / 1000));
                            $isOnline = $timeDiff < 300;
                        }
                        $statusColor = $isOnline ? 'success' : 'danger';
                        $statusText = $isOnline ? 'Online' : 'Offline';
                        $lastSeen = $lastInform ? \Carbon\Carbon::parse($lastInform)->diffForHumans() : '-';
                    @endphp
                    <tr class="device-row" data-search="{{ strtolower($sn . ' ' . $product) }}">
                        <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                        <td class="fw-medium">{{ $sn }}</td>
                        <td>{{ $product }}</td>
                        <td>
                            <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }}" style="font-size:10px;">
                                <i class="bi bi-circle-fill me-1" style="font-size:6px;"></i> {{ $statusText }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $lastSeen }}</td>
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-info px-2" title="Detail" onclick="showDeviceDetail('{{ $sn }}')"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-outline-warning px-2" title="Reboot" onclick="if(confirm('Reboot {{ $sn }}?')){ rebootDevice('{{ $sn }}'); }"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="text-center py-5">
        <i class="bi bi-satellite" style="font-size:3rem;color:var(--text3);"></i>
        <p class="text-muted small mt-2 mb-0">Tidak ada device terdaftar di GenieACS</p>
    </div>
    @endif
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="deviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-sm border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Detail Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deviceDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Live search
document.getElementById('deviceSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.device-row').forEach(row => {
        row.style.display = q === '' ? '' : (row.dataset.search.includes(q) ? '' : 'none');
    });
});

// Detail modal
async function showDeviceDetail(serial) {
    const modal = new bootstrap.Modal(document.getElementById('deviceModal'));
    document.getElementById('deviceDetailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    try {
        const r = await fetch('/admin/api/genieacs/device?serial=' + encodeURIComponent(serial));
        const d = await r.json();
        if (d.success) {
            const dev = d.device;
            let html = `
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:var(--card-hover);">
                            <p class="small text-muted mb-0">Serial Number</p>
                            <p class="fw-semibold mb-0">${dev._deviceId?._SerialNumber || dev._id || '-'}</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:var(--card-hover);">
                            <p class="small text-muted mb-0">Product Class</p>
                            <p class="fw-semibold mb-0">${dev._deviceId?._ProductClass || '-'}</p>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3" style="background:var(--card-hover);">
                            <p class="small text-muted mb-0">Manufacturer</p>
                            <p class="fw-semibold mb-0">${dev._deviceId?._Manufacturer || '-'}</p>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3" style="background:var(--card-hover);">
                            <p class="small text-muted mb-0">OUI</p>
                            <p class="fw-semibold mb-0">${dev._deviceId?._OUI || '-'}</p>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3" style="background:var(--card-hover);">
                            <p class="small text-muted mb-0">Last Inform</p>
                            <p class="fw-semibold mb-0">${dev._lastInform ? new Date(dev._lastInform).toLocaleString() : '-'}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6 class="fw-bold small text-muted mb-2">Tags</h6>
                    <div>${(dev._tags || []).map(t => `<span class="badge bg-primary bg-opacity-10 text-primary me-1">${t}</span>`).join('') || '<span class="text-muted small">Tidak ada tag</span>'}</div>
                </div>`;
            document.getElementById('deviceDetailBody').innerHTML = html;
        } else {
            document.getElementById('deviceDetailBody').innerHTML = `<div class="alert alert-danger">${d.message || 'Gagal mengambil detail device'}</div>`;
        }
    } catch (e) {
        document.getElementById('deviceDetailBody').innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
    }
}

// Reboot
async function rebootDevice(serial) {
    try {
        const r = await fetch('/admin/api/genieacs/reboot', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({serial: serial})
        });
        const d = await r.json();
        alert(d.message || 'OK');
    } catch(e) { alert('Error: ' + e.message); }
}
</script>
@endpush
