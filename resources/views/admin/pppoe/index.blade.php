@extends('layouts.admin')
@section('title', 'PPPoE Users')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold text-dark mb-0"><i class="bi bi-hdd-network me-2"></i>PPPoE Users</h1>
            <p class="small text-muted mb-0">Daftar user PPPoE dari MikroTik (read-only)</p>
        </div>
        <button onclick="location.reload()" class="btn btn-outline-secondary rounded-3 border shadow-sm">
            <i class="bi bi-arrow-clockwise me-1"></i> Sync
        </button>
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
            <form method="GET" action="{{ url('/admin/pppoe') }}" class="d-flex align-items-center gap-2">
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
            $aName = is_array($a) ? ($a['name'] ?? '') : ($a->name ?? '');
            $aAddr = is_array($a) ? ($a['address'] ?? '') : ($a->address ?? '');
            if ($aName) {
                $activeUsers[$aName] = $aAddr;
            }
        }
    @endphp

    {{-- Stats --}}
    @php
        $total = count($users);
        $aktif = count($active);
        $offline = $total - $aktif;
        $disabled = count(array_filter($users ?? [], fn($u) => (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true'));
    @endphp
    <div class="row g-2 g-lg-4 mb-4">
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

    {{-- Users Table --}}
    <div class="card shadow-sm rounded-3 border-0">
        <div class="card-body">
            <h6 class="fw-semibold text-secondary mb-3">PPPoE Users</h6>
            @if(count($users) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="userTable">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-medium small text-muted">Username</th>
                            <th class="fw-medium small text-muted">Profile</th>
                            <th class="fw-medium small text-muted">Status</th>
                            <th class="fw-medium small text-muted d-none d-lg-table-cell">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                        @php
                            $name = is_array($u) ? ($u['name'] ?? '-') : ($u->name ?? '-');
                            $profile = is_array($u) ? ($u['profile'] ?? '-') : ($u->profile ?? '-');
                            $disabled = (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true';
                            $isActive = isset($activeUsers[$name]);
                            $ipAddress = $isActive ? $activeUsers[$name] : '-';
                            $status = $isActive ? 'online' : ($disabled ? 'disabled' : 'offline');
                        @endphp
                        <tr data-status="{{ $status }}">
                            <td class="fw-medium text-dark">{{ $name }}</td>
                            <td class="text-secondary">{{ $profile }}</td>
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
            <p class="small text-muted text-center py-4 mb-0">Tidak dapat mengambil data MikroTik atau tidak ada user PPPoE.</p>
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

    // Update active stat card
    document.querySelectorAll('.stat-card').forEach(function(card) {
        card.classList.toggle('active', card.dataset.filter === status);
    });

    // Filter rows
    document.querySelectorAll('#userTable tbody tr').forEach(function(row) {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Auto-activate 'all' on load
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('.stat-card[data-filter="all"]')?.classList.add('active');
});
</script>
@endsection
