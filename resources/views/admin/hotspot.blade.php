@extends('layouts.admin')
@section('title', 'Hotspot')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-0"><i class="bi bi-wifi me-2"></i>Hotspot</h1>
    </div>

    @if($error)
    <div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3" role="alert">
        <i class="bi bi-exclamation-triangle text-warning"></i>
        <span>{{ $error }}</span>
    </div>
    @endif

    {{-- Router Selector --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ url('/admin/hotspot') }}" class="d-flex align-items-center gap-2">
                <label class="small text-muted fw-medium">Router:</label>
                <select name="router_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    @foreach($routers as $r)
                    <option value="{{ $r->id }}" {{ $routerId == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Build active IP lookup --}}
    @php
        $activeUsers = [];
        foreach ($active ?? [] as $a) {
            $aName = is_array($a) ? ($a['user'] ?? '') : ($a->user ?? '');
            $aAddr = is_array($a) ? ($a['address'] ?? '') : ($a->address ?? '');
            if ($aName) {
                $activeUsers[$aName] = $aAddr;
            }
        }
        $total = count($users);
        $aktif = count($active);
        $offline = $total - $aktif;
        $disabled = count(array_filter($users ?? [], fn($u) => (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true'));
    @endphp

    {{-- Stats (clickable) --}}
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 stat-card cursor-pointer" data-filter="all" onclick="filterTable('all')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Total User</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $total }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 stat-card cursor-pointer" data-filter="online" onclick="filterTable('online')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Online</p>
                    <p class="fs-4 fw-bold text-success mb-0">{{ $aktif }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 stat-card cursor-pointer" data-filter="offline" onclick="filterTable('offline')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Offline</p>
                    <p class="fs-4 fw-bold text-warning mb-0">{{ $offline }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 stat-card cursor-pointer" data-filter="disabled" onclick="filterTable('disabled')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Disabled</p>
                    <p class="fs-4 fw-bold text-danger mb-0">{{ $disabled }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Generate Voucher Form --}}
    <div class="card shadow-sm rounded-3 border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-ticket-perforated me-2"></i>Generate Voucher Hotspot</h5>
        </div>
        <div class="card-body">
            <form id="voucherForm" onsubmit="generateVouchers(event)">
                @csrf
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Jumlah</label>
                        <input type="number" name="qty" class="form-control" value="10" min="1" max="100" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium small">Karakter</label>
                        <div class="d-flex gap-3 pt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="charset" value="huruf" checked>
                                <label class="form-check-label small">Huruf</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="charset" value="angka">
                                <label class="form-check-label small">Angka</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="charset" value="kombinasi">
                                <label class="form-check-label small">Kombinasi</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Huruf</label>
                        <select name="case" class="form-select">
                            <option value="kombinasi">Kombinasi</option>
                            <option value="besar">Besar</option>
                            <option value="kecil">Kecil</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Profile</label>
                        <select name="profile" class="form-select" required>
                            @foreach($profiles as $p)
                            <option value="{{ is_array($p) ? $p['name'] ?? '' : $p->name ?? '' }}">
                                {{ is_array($p) ? $p['name'] ?? '' : $p->name ?? '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium small">Masa Aktif</label>
                        <div class="input-group">
                            <input type="number" name="duration" class="form-control" value="1" min="1" max="365">
                            <select name="duration_unit" class="form-select" style="max-width:90px">
                                <option value="d">Hari</option>
                                <option value="h">Jam</option>
                                <option value="m">Menit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3" id="btnGenerate">
                    <i class="bi bi-magic me-1"></i> Generate & Kirim ke MikroTik
                </button>
            </form>
            <div id="generateResult" class="mt-3 d-none"></div>
        </div>
    </div>

    {{-- Users List --}}
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body">
            <h6 class="fw-semibold text-secondary mb-3">Hotspot Users</h6>
            @if(count($users) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="userTable">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-medium small text-muted">Username</th>
                            <th class="fw-medium small text-muted">Profile</th>
                            <th class="fw-medium small text-muted d-none d-lg-table-cell">Limit Uptime</th>
                            <th class="fw-medium small text-muted">Status</th>
                            <th class="fw-medium small text-muted d-none d-lg-table-cell">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                        @php
                            $name = is_array($u) ? ($u['name'] ?? '-') : ($u->name ?? '-');
                            $profile = is_array($u) ? ($u['profile'] ?? '-') : ($u->profile ?? '-');
                            $uptime = is_array($u) ? ($u['limit-uptime'] ?? '-') : ($u->limit_uptime ?? '-');
                            $disabled = (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true';
                            $isActive = isset($activeUsers[$name]);
                            $ipAddress = $isActive ? $activeUsers[$name] : '-';
                            $status = $isActive ? 'online' : ($disabled ? 'disabled' : 'offline');
                        @endphp
                        <tr data-status="{{ $status }}">
                            <td class="fw-medium text-dark">{{ $name }}</td>
                            <td class="text-secondary">{{ $profile }}</td>
                            <td class="small text-muted d-none d-lg-table-cell">{{ $uptime }}</td>
                            <td>
                                @if($isActive)
                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Online</span>
                                @elseif($disabled)
                                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis">Disabled</span>
                                @else
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Offline</span>
                                @endif
                            </td>
                            <td class="small text-muted d-none d-lg-table-cell">
                                <code>{{ $ipAddress }}</code>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="small text-muted text-center py-4 mb-0">Tidak ada data hotspot</p>
            @endif
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.stat-card { transition: all 0.2s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; }
.stat-card.active { outline: 2px solid #0d6efd; outline-offset: -2px; }
</style>

<script>
let currentFilter = 'all';

function filterTable(status) {
    currentFilter = status;

    document.querySelectorAll('.stat-card').forEach(function(card) {
        card.classList.toggle('active', card.dataset.filter === status);
    });

    document.querySelectorAll('#userTable tbody tr').forEach(function(row) {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('.stat-card[data-filter="all"]')?.classList.add('active');
});

function generateVouchers(e) {
    e.preventDefault();
    const form = document.getElementById('voucherForm');
    const formData = new FormData(form);
    const btn = document.getElementById('btnGenerate');
    const result = document.getElementById('generateResult');

    const qty = parseInt(formData.get('qty')) || 10;
    const charset = formData.get('charset');
    const caseType = formData.get('case');
    const profile = formData.get('profile');
    const duration = formData.get('duration');
    const durationUnit = formData.get('duration_unit');

    let chars = '';
    if (charset === 'huruf' || charset === 'kombinasi') {
        if (caseType === 'besar' || caseType === 'kombinasi') chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (caseType === 'kecil' || caseType === 'kombinasi') chars += 'abcdefghijklmnopqrstuvwxyz';
    }
    if (charset === 'angka' || charset === 'kombinasi') chars += '0123456789';
    if (!chars) chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    let limitUptime = '';
    if (duration && parseInt(duration) > 0) {
        limitUptime = duration + durationUnit;
    }

    const vouchers = [];
    for (let i = 0; i < qty; i++) {
        let username = '';
        for (let j = 0; j < 8; j++) {
            username += chars[Math.floor(Math.random() * chars.length)];
        }
        let password = '';
        for (let j = 0; j < 6; j++) {
            password += chars[Math.floor(Math.random() * chars.length)];
        }
        vouchers.push({ username, password, profile, limit_uptime: limitUptime });
    }

    let preview = '<div class="alert alert-info"><strong>Generate ' + qty + ' voucher:</strong><br><small>' +
        vouchers.slice(0, 3).map(v => v.username + ' / ' + v.password).join(', ') +
        (qty > 3 ? ' ... dan ' + (qty - 3) + ' lainnya' : '') + '</small></div>';

    result.innerHTML = preview + '<div class="text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Mengirim ke MikroTik...</div>';
    result.classList.remove('d-none');
    btn.disabled = true;

    fetch('/admin/mikrotik/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ action: 'generate_vouchers', vouchers: vouchers })
    })
    .then(r => r.json())
    .then(data => {
        result.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + ' d-flex align-items-center gap-2">' +
            '<i class="bi bi-' + (data.success ? 'check-circle' : 'exclamation-circle') + '"></i> ' +
            (data.message || 'OK') + '</div>';
    })
    .catch(err => {
        result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Error: ' + err.message + '</div>';
    })
    .finally(() => {
        btn.disabled = false;
    });
}
</script>
@endsection
