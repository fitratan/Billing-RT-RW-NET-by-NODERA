@extends('layouts.admin')
@section('title', 'MikroTik PPPoE')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold text-dark mb-0">
                @if(isset($router))
                <a href="/admin/mikrotik/routers" class="text-muted text-decoration-none me-2"><i class="bi bi-arrow-left small"></i></a>
                {{ $router->name }}
                @else
                PPPoE Management
                @endif
            </h1>
            @if(isset($router))
            <small class="text-muted">{{ $router->host }}:{{ $router->port }}</small>
            @endif
        </div>
        <div class="d-flex gap-2 mt-2 mt-lg-0">
            <a href="/admin/mikrotik/routers" class="btn btn-outline-secondary rounded-3 border shadow-sm">
                <i class="bi bi-server me-1"></i> Routers
            </a>
            <button onclick="location.reload()" class="btn btn-outline-secondary rounded-3 border shadow-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Sync
            </button>
            <a href="/admin/mikrotik/profiles" class="btn btn-outline-secondary rounded-3 border shadow-sm">Profiles</a>
        </div>
    </div>

    @if($error)
    <div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4 py-3" role="alert">
        <i class="bi bi-exclamation-triangle text-warning"></i>
        <span>{{ $error }} <a href="/admin/my-settings" class="alert-link fw-medium">Buka Pengaturan</a></span>
    </div>
    @endif

    {{-- Stats --}}
    @php
        $total = count($users);
        $aktif = count($active);
        $offline = $total - $aktif;
        $disabled = count(array_filter($users ?? [], fn($u) => (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true'));
    @endphp
    <div class="row g-2 g-lg-4 mb-4" id="statFilters">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 cursor-pointer stat-card" data-filter="all" onclick="filterTable('all')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Total User</p>
                    <p class="fs-4 fw-bold text-dark mb-0">{{ $total }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 cursor-pointer stat-card" data-filter="online" onclick="filterTable('online')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Online</p>
                    <p class="fs-4 fw-bold text-success mb-0">{{ $aktif }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 cursor-pointer stat-card" data-filter="offline" onclick="filterTable('offline')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Offline</p>
                    <p class="fs-4 fw-bold text-warning mb-0">{{ $offline }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm rounded-3 border-0 h-100 cursor-pointer stat-card" data-filter="disabled" onclick="filterTable('disabled')">
                <div class="card-body">
                    <p class="small text-secondary fw-medium mb-1">Disabled</p>
                    <p class="fs-4 fw-bold text-danger mb-0">{{ $disabled }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-semibold text-secondary mb-0">PPPoE Users</h6>
                <span class="small text-muted">{{ $total }} users</span>
            </div>
            @if(count($users) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-medium small text-muted">Username</th>
                            <th class="fw-medium small text-muted">Profile</th>
                            <th class="fw-medium small text-muted">Status</th>
                            <th class="fw-medium small text-muted d-none d-lg-table-cell">IP</th>
                            <th class="fw-medium small text-muted">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        @foreach($users as $u)
                        @php
                            $name = is_array($u) ? ($u['name'] ?? '-') : ($u->name ?? '-');
                            $profile = is_array($u) ? ($u['profile'] ?? '-') : ($u->profile ?? '-');
                            $disabled = (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true';
                            $isActive = in_array($name, array_map(fn($a) => is_array($a) ? ($a['name'] ?? '') : ($a->name ?? ''), $active ?? []));
                            $rowStatus = $isActive ? 'online' : ($disabled ? 'disabled' : 'offline');
                        @endphp
                        <tr data-status="{{ $rowStatus }}">
                            <td class="fw-medium text-dark">{{ $name }}</td>
                            <td class="text-secondary">{{ $profile }}</td>
                            <td>
                                @if($isActive)
                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis status-badge" data-status="online">Online</span>
                                @elseif($disabled)
                                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis status-badge" data-status="disabled">Disabled</span>
                                @else
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis status-badge" data-status="offline">Offline</span>
                                @endif
                            </td>
                            <td class="small text-muted d-none d-lg-table-cell">{{ is_array($u) ? ($u['address'] ?? '-') : ($u->address ?? '-') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if($disabled)
                                    <button onclick="toggleUser('{{ $name }}', true)" class="btn btn-sm btn-outline-success rounded-3">Aktifkan</button>
                                    @else
                                    <button onclick="toggleUser('{{ $name }}', false)" class="btn btn-sm btn-outline-warning rounded-3">Nonaktifkan</button>
                                    @endif
                                    <button onclick="deleteUser('{{ $name }}')" class="btn btn-sm btn-outline-danger rounded-3">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="small text-muted text-center py-4 mb-0">Tidak dapat mengambil data MikroTik. Pastikan koneksi sudah dikonfigurasi di Pengaturan.</p>
            @endif
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.stat-card { transition: transform .15s, box-shadow .15s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 .25rem .75rem rgba(0,0,0,.1) !important; }
.stat-card.active { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
</style>
<script>
let currentFilter = 'all';
function filterTable(status) {
    currentFilter = status;
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`.stat-card[data-filter="${status}"]`)?.classList.add('active');
    document.querySelectorAll('#userTableBody tr').forEach(row => {
        const rs = row.dataset.status;
        row.style.display = (status === 'all' || rs === status) ? '' : 'none';
    });
}
</script>
<script>
async function toggleUser(username, enabled) {
    if (!confirm((enabled ? 'Aktifkan' : 'Nonaktifkan') + ' user: ' + username + '?')) return;
    try {
        const r = await fetch('/admin/mikrotik/action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({action: 'toggle_pppoe', username: username, enabled: enabled})
        });
        const d = await r.json();
        alert(d.message || d.msg || 'OK');
        location.reload();
    } catch(e) { alert('Error: ' + e.message); }
}

async function deleteUser(username) {
    if (!confirm('Hapus user ' + username + ' dari MikroTik?')) return;
    try {
        const r = await fetch('/admin/mikrotik/action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({action: 'delete', username: username})
        });
        const d = await r.json();
        alert(d.message || d.msg || 'OK');
        location.reload();
    } catch(e) { alert('Error: ' + e.message); }
}
</script>
@endsection
