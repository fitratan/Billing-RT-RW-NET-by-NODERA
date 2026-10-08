@extends('layouts.admin')

@php $title = 'Dashboard'; @endphp
@section('title', 'Dashboard')

@section('content')
<div class="container-fluid px-0 pb-5" id="app">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold text-dark mb-0">Dashboard</h1>
            @php
                $companyName = \Illuminate\Support\Facades\DB::table('settings')->where('key','COMPANY_NAME')->value('value');
                $companyAddr = \Illuminate\Support\Facades\DB::table('settings')->where('key','COMPANY_ADDRESS')->value('value');
                $companyPhone = \Illuminate\Support\Facades\DB::table('settings')->where('key','COMPANY_PHONE')->value('value');
            @endphp
            @if($companyName || $companyAddr || $companyPhone)
            <p class="small mt-1 mb-0" style="color:var(--text2);">
                @if($companyName)<span class="fw-semibold">{{ $companyName }}</span>@endif
                @if($companyAddr) &middot; {{ $companyAddr }}@endif
                @if($companyPhone) &middot; {{ $companyPhone }}@endif
            </p>
            @endif
            <p class="small mt-0 mb-0" style="color:var(--text3);" id="userName">Selamat datang, Administrator</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/analytics" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                <i class="bi bi-graph-up"></i><span class="d-none d-sm-inline">Analytics</span>
            </a>
            <a href="/admin/mikrotik/routers" class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center" style="width:36px;height:36px;" title="MikroTik">
                <i class="bi bi-hdd-network"></i>
            </a>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="row g-2 g-lg-4 mb-3 mb-lg-4">
        {{-- Revenue Bulan Ini -- hero card desktop --}}
        <div class="col-12 col-md-4">
            <a href="/admin/finance" class="text-decoration-none">
                <div class="card bg-primary text-white border-0 shadow-sm h-100 stat-click">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded text-white flex-shrink-0" style="width:48px;height:48px;background:rgba(255,255,255,0.2);">
                            <i class="bi bi-currency-dollar fs-4"></i>
                        </div>
                        <div>
                            <p class="small fw-medium mb-0 opacity-75">Pendapatan Bulan Ini</p>
                            <p class="h4 fw-bold mb-0" id="todayRevenue">Rp0</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        {{-- Pelanggan --}}
        <div class="col-6 col-md-2">
            <a href="/admin/billing/customers" class="text-decoration-none">
                <div class="card bg-light border-0 shadow-sm h-100 stat-click">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-1 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-primary bg-opacity-10 text-primary" style="width:40px;height:40px;">
                            <i class="bi bi-people fs-5"></i>
                        </div>
                        <p class="h5 fw-bold text-dark mb-0" id="totalPelanggan">-</p>
                        <p class="small text-muted fw-medium mb-0" style="font-size:11px;">Pelanggan</p>
                    </div>
                </div>
            </a>
        </div>
        {{-- Online --}}
        <div class="col-6 col-md-2">
            <a href="/admin/pppoe" class="text-decoration-none">
                <div class="card bg-light border-0 shadow-sm h-100 stat-click">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-1 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-success bg-opacity-10 text-success" style="width:40px;height:40px;">
                            <i class="bi bi-wifi fs-5"></i>
                        </div>
                        <p class="h5 fw-bold text-dark mb-0" id="onlinePppoe">-</p>
                        <p class="small text-muted fw-medium mb-0" style="font-size:11px;">Online</p>
                    </div>
                </div>
            </a>
        </div>
        {{-- Tertunda --}}
        <div class="col-6 col-md-2">
            <a href="/admin/billing/invoices" class="text-decoration-none">
                <div class="card bg-light border-0 shadow-sm h-100 stat-click">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-1 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-warning bg-opacity-10 text-warning" style="width:40px;height:40px;">
                            <i class="bi bi-receipt fs-5"></i>
                        </div>
                        <p class="h5 fw-bold text-dark mb-0" id="invoiceTertunda">-</p>
                        <p class="small text-muted fw-medium mb-0" style="font-size:11px;">Tertunda</p>
                    </div>
                </div>
            </a>
        </div>
        {{-- Gangguan --}}
        <div class="col-6 col-md-2">
            <a href="/admin/trouble" class="text-decoration-none">
                <div class="card bg-light border-0 shadow-sm h-100 stat-click">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-1 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-danger bg-opacity-10 text-danger" style="width:40px;height:40px;">
                            <i class="bi bi-exclamation-triangle fs-5"></i>
                        </div>
                        <p class="h5 fw-bold text-dark mb-0" id="tiketGangguan">-</p>
                        <p class="small text-muted fw-medium mb-0" style="font-size:11px;">Gangguan</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Content: 2-column on desktop --}}
    <div class="row g-3 g-lg-4 mb-3">
        {{-- Left: Quick Actions + All Menu + Pending Invoices --}}
        <div class="col-lg-8 d-flex flex-column gap-3 gap-lg-4">
            {{-- Quick Actions (desktop only) --}}
            <div class="d-none d-md-block card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-semibold text-secondary d-flex align-items-center gap-2 mb-0">
                            <i class="bi bi-lightning text-primary"></i> Menu Cepat
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" onclick="editQuickMenu()" title="Atur Menu Cepat">
                            <i class="bi bi-gear"></i>
                        </button>
                    </div>
                    <div class="row row-cols-3 row-cols-md-6 g-2 g-lg-3" id="quickMenuContainer">
                        {{-- Diisi oleh JS dari localStorage --}}
                    </div>
                </div>
            </div>

            {{-- Semua Menu --}}
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-semibold text-secondary d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-grid-3x3-gap text-primary"></i> Semua Menu
                    </h6>
                    <div class="row row-cols-3 row-cols-sm-4 row-cols-lg-6 g-2" id="semuaMenuGrid">
                        {{-- Network items (6 visible default di mobile) --}}
                        <a href="/admin/mikrotik/routers" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-hdd-network fs-5" style="color:#6f42c1;"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">MikroTik</span>
                            </div>
                        </a>
                        <a href="/admin/pppoe" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-key fs-5 text-primary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">PPPoE</span>
                            </div>
                        </a>
                        <a href="/admin/hotspot" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-wifi fs-5 text-info"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Hotspot</span>
                            </div>
                        </a>
                        <a href="/admin/olt" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-server fs-5 text-warning"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">OLT</span>
                            </div>
                        </a>
                        <a href="/admin/top-bandwidth" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-speedometer2 fs-5 text-primary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Bandwidth</span>
                            </div>
                        </a>
                        <a href="/admin/genieacs" class="col text-decoration-none menu-item">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-send fs-5 text-info"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">GenieACS</span>
                            </div>
                        </a>
                        {{-- Hidden items di mobile, visible di desktop via d-lg-block --}}
                        <a href="/admin/billing/invoices" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-receipt fs-5 text-warning"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Invoice</span>
                            </div>
                        </a>
                        <a href="/admin/billing/customers" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-people fs-5 text-primary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Pelanggan</span>
                            </div>
                        </a>
                        <a href="/admin/billing/packages" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-box-seam fs-5 text-success"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Paket</span>
                            </div>
                        </a>
                        <a href="/admin/api-apps" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-code-slash fs-5 text-info"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">API Apps</span>
                            </div>
                        </a>
                        <a href="/admin/finance" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-pie-chart fs-5 text-success"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Keuangan</span>
                            </div>
                        </a>
                        <a href="/admin/finance/expenses" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-cash-stack fs-5 text-danger"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Pengeluaran</span>
                            </div>
                        </a>
                        <a href="/admin/attendance" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-clipboard-check fs-5 text-secondary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Absensi</span>
                            </div>
                        </a>
                        <a href="/admin/payroll" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-wallet2 fs-5 text-warning"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Payroll</span>
                            </div>
                        </a>
                        <a href="/admin/map" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-geo-alt fs-5 text-danger"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Peta ONU</span>
                            </div>
                        </a>
                        <a href="/admin/trouble" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-tools fs-5 text-danger"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Gangguan</span>
                            </div>
                        </a>
                        <a href="/admin/employees" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-people-fill fs-5 text-secondary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Karyawan</span>
                            </div>
                        </a>
                        <a href="/admin/inventory" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-boxes fs-5 text-warning"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Inventory</span>
                            </div>
                        </a>
                        <a href="/admin/analytics" class="col text-decoration-none menu-item d-none d-lg-block" data-more="1">
                            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                                <i class="bi bi-graph-up fs-5 text-primary"></i>
                                <span class="small fw-medium text-secondary" style="font-size:11px;">Analytics</span>
                            </div>
                        </a>
                    </div>
                    <button class="btn btn-sm btn-outline-primary w-100 mt-2 rounded-3 d-lg-none" id="lihatSemuaBtn" onclick="toggleSemuaMenu()">
                        <i class="bi bi-chevron-down me-1"></i> Lihat Semua
                    </button>
                </div>
            </div>

            {{-- Pending Invoices --}}
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-semibold text-secondary d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-clock text-primary"></i> Tagihan Tertunda
                    </h6>
                    <div id="tagihanList">
                        <div class="py-3 text-center small text-muted">Memuat data...</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Ringkasan --}}
        <div class="col-lg-4 d-flex flex-column gap-3 gap-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-semibold text-secondary d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-pie-chart text-primary"></i> Ringkasan
                    </h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                            <span class="small text-secondary">Total Pelanggan</span>
                            <span class="small fw-bold text-dark" id="summaryTotal">-</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                            <span class="small text-secondary">Invoice Lunas</span>
                            <span class="small fw-bold text-dark" id="summaryLunas">-</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                            <span class="small text-secondary">Tertunda</span>
                            <span class="small fw-bold text-warning" id="summaryTertunda">-</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-primary bg-opacity-10 rounded-3">
                            <span class="small text-secondary">Pendapatan Hari Ini</span>
                            <span class="small fw-bold text-primary" id="summaryHariIni">-</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-success bg-opacity-10 rounded-3">
                            <span class="small text-secondary">Pendapatan Bulan Ini</span>
                            <span class="small fw-bold text-success" id="summaryRevenue">-</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-danger bg-opacity-10 rounded-3">
                            <span class="small text-secondary">Tiket Gangguan</span>
                            <span class="small fw-bold text-danger" id="summaryTickets">-</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Link Cepat Desktop --}}
            <div class="d-none d-lg-block card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-semibold text-secondary d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-link-45deg text-primary"></i> Link Cepat
                    </h6>
                    <div class="d-flex flex-column gap-2">
                        <a href="/admin/map" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light text-decoration-none small text-secondary">
                            <i class="bi bi-geo-alt text-primary" style="width:20px;text-align:center;"></i> Peta ONU
                        </a>
                        <a href="/admin/hotspot" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light text-decoration-none small text-secondary">
                            <i class="bi bi-wifi text-success" style="width:20px;text-align:center;"></i> Hotspot
                        </a>
                        <a href="/admin/pppoe" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light text-decoration-none small text-secondary">
                            <i class="bi bi-hdd-network text-info" style="width:20px;text-align:center;"></i> PPPoE
                        </a>
                        <a href="/admin/top-bandwidth" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light text-decoration-none small text-secondary">
                            <i class="bi bi-speedometer2 text-primary" style="width:20px;text-align:center;"></i> Top Bandwidth
                        </a>
                        <a href="/admin/olt" class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light text-decoration-none small text-secondary">
                            <i class="bi bi-server text-warning" style="width:20px;text-align:center;"></i> OLT
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Atur Menu Cepat --}}
    <div class="modal fade" id="quickMenuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-sm border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-gear me-2"></i>Atur Menu Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Centang menu yg ingin muncul di Dashboard:</p>
                    <div id="quickMenuCheckboxes" class="d-flex flex-column gap-2"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="saveQuickMenu()"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-center small text-muted pt-3 pb-3 border-top mt-3">
        <i class="bi bi-shield-check me-1"></i> NODERA v1.0 &mdash; ISP Management System
    </div>
</div>

<style>
.stat-click { transition: transform .15s, box-shadow .15s; cursor: pointer; }
.stat-click:hover { transform: translateY(-2px); box-shadow: 0 .25rem .75rem rgba(0,0,0,.1) !important; }
.quick-menu-item { transition: transform .15s; }
.quick-menu-item:hover { transform: translateY(-2px); }
</style>

@push('scripts')
<script>
// ===== DEFAULT QUICK MENU =====
const ALL_MENUS = [
    { id: 'invoice', label: 'Invoice', icon: 'bi-receipt', color: '#ffc107', url: '/admin/billing/invoices' },
    { id: 'pelanggan', label: 'Pelanggan', icon: 'bi-people', color: '#0d6efd', url: '/admin/billing/customers' },
    { id: 'paket', label: 'Paket', icon: 'bi-box-seam', color: '#198754', url: '/admin/billing/packages' },
    { id: 'gangguan', label: 'Gangguan', icon: 'bi-tools', color: '#dc3545', url: '/admin/trouble' },
    { id: 'mikrotik', label: 'MikroTik', icon: 'bi-hdd-network', color: '#6f42c1', url: '/admin/mikrotik/routers' },
    { id: 'hotspot', label: 'Hotspot', icon: 'bi-wifi', color: '#0dcaf0', url: '/admin/hotspot' },
    { id: 'pppoe', label: 'PPPoE', icon: 'bi-key', color: '#0d6efd', url: '/admin/pppoe' },
    { id: 'bandwidth', label: 'Top Bandwidth', icon: 'bi-speedometer2', color: '#6f42c1', url: '/admin/top-bandwidth' },
    { id: 'laporan', label: 'Keuangan', icon: 'bi-pie-chart', color: '#198754', url: '/admin/finance' },
    { id: 'payment', label: 'Payment', icon: 'bi-credit-card', color: '#0d6efd', url: '/admin/payments/gateway' },
    { id: 'genieacs', label: 'GenieACS', icon: 'bi-send', color: '#0dcaf0', url: '/admin/genieacs' },
    { id: 'olt', label: 'OLT', icon: 'bi-server', color: '#ffc107', url: '/admin/olt' },
];

function getQuickMenu() {
    try {
        const saved = localStorage.getItem('nodera_quick_menu');
        return saved ? JSON.parse(saved) : ['invoice', 'pelanggan', 'paket', 'gangguan', 'mikrotik', 'hotspot'];
    } catch { return ['invoice', 'pelanggan', 'paket', 'gangguan', 'mikrotik', 'hotspot']; }
}

function renderQuickMenu() {
    const ids = getQuickMenu();
    const container = document.getElementById('quickMenuContainer');
    container.innerHTML = ids.map(id => {
        const m = ALL_MENUS.find(x => x.id === id);
        if (!m) return '';
        return `<a href="${m.url}" class="col text-decoration-none quick-menu-item">
            <div class="d-flex flex-column align-items-center gap-1 p-3 rounded-3 bg-light text-center h-100">
                <i class="${m.icon}" style="color:${m.color}"></i>
                <span class="small fw-medium text-secondary" style="font-size:11px;">${m.label}</span>
            </div>
        </a>`;
    }).join('');
}

function editQuickMenu() {
    const ids = getQuickMenu();
    const checkboxes = document.getElementById('quickMenuCheckboxes');
    checkboxes.innerHTML = ALL_MENUS.map(m => `
        <label class="d-flex align-items-center gap-3 p-2 rounded-3 bg-light">
            <input type="checkbox" class="form-check-input quick-menu-cb" value="${m.id}" ${ids.includes(m.id) ? 'checked' : ''}>
            <i class="${m.icon}" style="color:${m.color}"></i>
            <span class="small">${m.label}</span>
        </label>
    `).join('');
    new bootstrap.Modal(document.getElementById('quickMenuModal')).show();
}

function saveQuickMenu() {
    const checked = [...document.querySelectorAll('.quick-menu-cb:checked')].map(cb => cb.value);
    localStorage.setItem('nodera_quick_menu', JSON.stringify(checked));
    renderQuickMenu();
    bootstrap.Modal.getInstance(document.getElementById('quickMenuModal')).hide();
}

// ===== LOAD STATS =====
async function loadStats() {
    try {
        const res = await fetch('/api/stats');
        const data = await res.json();
        if (data.totalPelanggan === undefined && !data.stats) return;

        const totalPelanggan = data.totalPelanggan ?? data.stats?.onlinePppoe ?? 0;
        const onlinePppoe = data.onlinePppoe ?? data.stats?.onlinePppoe ?? 0;
        const invoiceTertunda = data.invoiceTertunda ?? data.stats?.pendingInvoices ?? 0;
        const pendapatanHariIni = data.pendapatanHariIni ?? data.stats?.todayRevenue ?? 0;
        const pendapatanBulanIni = data.pendapatanBulanIni ?? data.stats?.monthlyRevenue ?? 0;
        const tiketGangguan = data.tiketGangguan ?? data.stats?.pendingTickets ?? 0;
        const totalInvoiceLunas = data.totalInvoiceLunas ?? data.paidInvoices ?? 0;
        const tagihan = data.tagihanTertunda ?? [];

        const fmt = v => 'Rp' + Number(v).toLocaleString('id-ID');

        document.getElementById('totalPelanggan').textContent = totalPelanggan;
        document.getElementById('onlinePppoe').textContent = onlinePppoe;
        document.getElementById('invoiceTertunda').textContent = invoiceTertunda;
        document.getElementById('todayRevenue').textContent = fmt(pendapatanBulanIni);
        document.getElementById('tiketGangguan').textContent = tiketGangguan;

        document.getElementById('summaryTotal').textContent = totalPelanggan;
        document.getElementById('summaryLunas').textContent = totalInvoiceLunas;
        document.getElementById('summaryTertunda').textContent = invoiceTertunda;
        document.getElementById('summaryHariIni').textContent = fmt(pendapatanHariIni);
        document.getElementById('summaryRevenue').textContent = fmt(pendapatanBulanIni);
        document.getElementById('summaryTickets').textContent = tiketGangguan;

        if (data.user) {
            const el = document.getElementById('userName');
            if (el) el.textContent = 'Selamat datang, ' + data.user;
        }

        const list = document.getElementById('tagihanList');
        if (!tagihan || tagihan.length === 0) {
            list.innerHTML = '<div class="py-3 text-center small text-muted">Semua tagihan sudah dibayar </div>';
        } else {
            list.innerHTML = tagihan.map(t => `
                <div class="py-3 d-flex align-items-center justify-content-between border-top">
                    <div>
                        <p class="small fw-medium text-dark mb-0">${t.customer}</p>
                        <p class="small text-muted mb-0">${t.invoice} · ${t.due}</p>
                    </div>
                    <div class="text-end">
                        <p class="small fw-semibold text-dark mb-0">Rp${t.amount}</p>
                        <span class="badge rounded-pill bg-amber-100 text-amber-700" style="font-size:10px;">BELUM</span>
                    </div>
                </div>
            `).join('');
        }
    } catch (e) {
        console.log('Stats error', e);
    }
}

renderQuickMenu();
loadStats();
setInterval(loadStats, 30000);

// Toggle Semua Menu show/hide
function toggleSemuaMenu() {
    const hidden = document.querySelectorAll('.menu-item.d-none[data-more]');
    const btn = document.getElementById('lihatSemuaBtn');
    if (hidden.length > 0) {
        hidden.forEach(el => el.classList.remove('d-none'));
        btn.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Sembunyikan';
    } else {
        document.querySelectorAll('.menu-item[data-more]').forEach(el => el.classList.add('d-none'));
        btn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Lihat Semua';
    }
}
</script>
@endpush
@endsection
