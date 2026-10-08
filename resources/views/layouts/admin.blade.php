@extends('layouts.base')

@section('sidebar_subtitle', session('tenant_name', 'ISP Management'))

@section('sidebar_menu')
    @if(session('admin_role') === 'superadmin')
    {{-- Superadmin quick links --}}
    <a href="/superadmin" class="nav-item {{ request()->is('superadmin')&&!request()->is('superadmin/*')?'active':'' }}">
        <i class="bi bi-speedometer2"></i> Dashboard Superadmin
    </a>
    <a href="/superadmin/tenants" class="nav-item {{ request()->is('superadmin/tenants*')?'active':'' }}">
        <i class="bi bi-building"></i> Tenant
    </a>
    <a href="/superadmin/registrasi" class="nav-item {{ request()->is('superadmin/registrasi*')?'active':'' }}">
        <i class="bi bi-person-plus"></i> Registrasi
    </a>
    <a href="/superadmin/panel" class="nav-item {{ request()->is('superadmin/panel')?'active':'' }}">
        <i class="bi bi-grid-3x3-gap"></i> Panel Navigasi
    </a>

    @elseif(session('technician_logged_in'))
    {{-- Technician Menu --}}
    <a href="/teknisi/dashboard" class="nav-item {{ request()->is('teknisi/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a href="/teknisi/pool" class="nav-item {{ request()->is('teknisi/pool')?'active':'' }}">
        <i class="bi bi-inboxes"></i> Antrian
    </a>
    <a href="/teknisi/history" class="nav-item {{ request()->is('teknisi/history')?'active':'' }}">
        <i class="bi bi-clock-history"></i> Riwayat
    </a>

    <div class="nav-divider"></div>

    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/pppoe*')||request()->is('admin/map*')||request()->is('admin/olt*')||request()->is('admin/genieacs*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Network
        </div>
        <div class="nav-sub {{ request()->is('admin/pppoe*')||request()->is('admin/map*')||request()->is('admin/olt*')||request()->is('admin/genieacs*')?'open':'' }}">
            <a href="/admin/pppoe" class="nav-item sub {{ request()->is('admin/pppoe*')?'active':'' }}"><i class="bi bi-key"></i> PPPoE</a>
            <a href="/admin/olt" class="nav-item sub {{ request()->is('admin/olt*')?'active':'' }}"><i class="bi bi-server"></i> Aktivasi ONU</a>
            <a href="/admin/map" class="nav-item sub {{ request()->is('admin/map*')?'active':'' }}"><i class="bi bi-geo-alt"></i> Peta ONU</a>
            <a href="/admin/genieacs" class="nav-item sub {{ request()->is('admin/genieacs*')?'active':'' }}"><i class="bi bi-satellite"></i> GenieACS</a>
        </div>
    </div>

    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/trouble*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Layanan
        </div>
        <div class="nav-sub {{ request()->is('admin/trouble*')?'open':'' }}">
            <a href="/admin/trouble" class="nav-item sub {{ request()->is('admin/trouble*')?'active':'' }}"><i class="bi bi-tools"></i> Gangguan</a>
        </div>
    </div>

    <div class="nav-divider"></div>

    <a href="/admin/my-settings" class="nav-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-building"></i> {{ session('tenant_name', 'Perusahaan') }}
    </a>

    @elseif(session('collector_logged_in'))
    {{-- Collector Menu --}}
    <a href="/kolektor/dashboard" class="nav-item {{ request()->is('kolektor/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i> Dashboard
    </a>

    <div class="nav-divider"></div>

    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/pppoe*')||request()->is('admin/map*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Network
        </div>
        <div class="nav-sub {{ request()->is('admin/pppoe*')||request()->is('admin/map*')?'open':'' }}">
            <a href="/admin/pppoe" class="nav-item sub {{ request()->is('admin/pppoe*')?'active':'' }}"><i class="bi bi-key"></i> PPPoE</a>
            <a href="/admin/map" class="nav-item sub {{ request()->is('admin/map*')?'active':'' }}"><i class="bi bi-geo-alt"></i> Peta ONU</a>
        </div>
    </div>

    <div class="nav-divider"></div>

    <a href="/admin/my-settings" class="nav-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-building"></i> {{ session('tenant_name', 'Perusahaan') }}
    </a>

    @else
    {{-- Tenant Admin Menu --}}
    <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a href="/admin/analytics" class="nav-item {{ request()->is('admin/analytics*')?'active':'' }}">
        <i class="bi bi-graph-up"></i> Analytics
    </a>

    <div class="nav-divider"></div>

    {{-- BILLING --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/billing*')||request()->is('admin/payments*')||request()->is('admin/finance*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Billing
        </div>
        <div class="nav-sub {{ request()->is('admin/billing*')||request()->is('admin/payments*')||request()->is('admin/finance*')?'open':'' }}">
            <a href="/admin/billing/invoices" class="nav-item sub {{ request()->is('admin/billing/invoices*')?'active':'' }}"><i class="bi bi-receipt"></i> Invoice</a>
            <a href="/admin/billing/customers" class="nav-item sub {{ request()->is('admin/billing/customers*')?'active':'' }}"><i class="bi bi-people"></i> Pelanggan</a>
            <a href="/admin/billing/packages" class="nav-item sub {{ request()->is('admin/billing/packages*')?'active':'' }}"><i class="bi bi-box"></i> Paket</a>
            <a href="/admin/payments/gateway" class="nav-item sub {{ request()->is('admin/payments/gateway*')?'active':'' }}"><i class="bi bi-credit-card"></i> Payment Gateway</a>
            <a href="/admin/payments/qris" class="nav-item sub {{ request()->is('admin/payments/qris*')?'active':'' }}"><i class="bi bi-qr-code"></i> QRIS</a>
            <a href="/admin/finance" class="nav-item sub {{ request()->is('admin/finance')&&!request()->is('admin/finance/expenses*')?'active':'' }}"><i class="bi bi-pie-chart"></i> Laporan Keuangan</a>
            <a href="/admin/finance/expenses" class="nav-item sub {{ request()->is('admin/finance/expenses*')?'active':'' }}"><i class="bi bi-cash-stack"></i> Pengeluaran</a>
        </div>
    </div>

    {{-- NETWORK --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/mikrotik*')||request()->is('admin/pppoe*')||request()->is('admin/hotspot*')||request()->is('admin/olt*')||request()->is('admin/top-bandwidth*')||request()->is('admin/genieacs*')||request()->is('admin/map*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Network
        </div>
        <div class="nav-sub {{ request()->is('admin/mikrotik*')||request()->is('admin/pppoe*')||request()->is('admin/hotspot*')||request()->is('admin/olt*')||request()->is('admin/top-bandwidth*')||request()->is('admin/genieacs*')||request()->is('admin/map*')?'open':'' }}">
            <a href="/admin/mikrotik/routers" class="nav-item sub {{ request()->is('admin/mikrotik*')?'active':'' }}"><i class="bi bi-hdd-network"></i> MikroTik</a>
            <a href="/admin/pppoe" class="nav-item sub {{ request()->is('admin/pppoe*')?'active':'' }}"><i class="bi bi-key"></i> PPPoE</a>
            <a href="/admin/hotspot" class="nav-item sub {{ request()->is('admin/hotspot*')?'active':'' }}"><i class="bi bi-wifi"></i> Hotspot</a>
            <a href="/admin/olt" class="nav-item sub {{ request()->is('admin/olt*')?'active':'' }}"><i class="bi bi-server"></i> OLT</a>
            <a href="/admin/top-bandwidth" class="nav-item sub {{ request()->is('admin/top-bandwidth*')?'active':'' }}"><i class="bi bi-speedometer2"></i> Top Bandwidth</a>
            <a href="/admin/genieacs" class="nav-item sub {{ request()->is('admin/genieacs*')?'active':'' }}"><i class="bi bi-satellite"></i> GenieACS</a>
            <a href="/admin/map" class="nav-item sub {{ request()->is('admin/map*')?'active':'' }}"><i class="bi bi-geo-alt"></i> Peta ONU</a>
        </div>
    </div>

    {{-- LAYANAN --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/trouble*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Layanan
        </div>
        <div class="nav-sub {{ request()->is('admin/trouble*')?'open':'' }}">
            <a href="/admin/trouble" class="nav-item sub {{ request()->is('admin/trouble*')?'active':'' }}"><i class="bi bi-tools"></i> Gangguan</a>
        </div>
    </div>

    {{-- SYSTEM --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/employees*')||request()->is('admin/inventory*')||request()->is('admin/attendance*')||request()->is('admin/payroll*')||request()->is('admin/my-settings*')||request()->is('admin/api-apps*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> System
        </div>
        <div class="nav-sub {{ request()->is('admin/employees*')||request()->is('admin/inventory*')||request()->is('admin/attendance*')||request()->is('admin/payroll*')||request()->is('admin/my-settings*')||request()->is('admin/api-apps*')?'open':'' }}">
            <a href="/admin/employees" class="nav-item sub {{ request()->is('admin/employees*')?'active':'' }}"><i class="bi bi-people-fill"></i> Karyawan</a>
            <a href="/admin/inventory" class="nav-item sub {{ request()->is('admin/inventory*')?'active':'' }}"><i class="bi bi-boxes"></i> Inventory</a>
            <a href="/admin/attendance" class="nav-item sub {{ request()->is('admin/attendance*')?'active':'' }}"><i class="bi bi-clipboard-check"></i> Absensi</a>
            <a href="/admin/payroll" class="nav-item sub {{ request()->is('admin/payroll*')?'active':'' }}"><i class="bi bi-wallet2"></i> Payroll</a>
            <a href="/admin/api-apps" class="nav-item sub {{ request()->is('admin/api-apps*')?'active':'' }}"><i class="bi bi-code-slash"></i> API Apps</a>
            <a href="/admin/my-settings" class="nav-item sub {{ request()->is('admin/my-settings*')?'active':'' }}"><i class="bi bi-person-circle"></i> Profile</a>
        </div>
    </div>
    @endif
@overwrite

@section('bottom_nav')
    @if(session('technician_logged_in'))
    <a href="/teknisi/dashboard" class="bn-item {{ request()->is('teknisi/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i><span>Dashboard</span>
    </a>
    <a href="/admin/pppoe" class="bn-item {{ request()->is('admin/pppoe*')?'active':'' }}">
        <i class="bi bi-key"></i><span>PPPoE</span>
    </a>
    <a href="/admin/olt" class="bn-item {{ request()->is('admin/olt*')?'active':'' }}">
        <i class="bi bi-server"></i><span>ONU</span>
    </a>
    <a href="/admin/map" class="bn-item {{ request()->is('admin/map*')?'active':'' }}">
        <i class="bi bi-geo-alt"></i><span>Peta</span>
    </a>
    <a href="/teknisi/logout" class="bn-item" onclick="event.preventDefault();document.getElementById('technicianLogoutForm').submit();">
        <i class="bi bi-box-arrow-left"></i><span>Keluar</span>
    </a>

    @elseif(session('collector_logged_in'))
    <a href="/kolektor/dashboard" class="bn-item {{ request()->is('kolektor/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i><span>Dashboard</span>
    </a>
    <a href="/admin/pppoe" class="bn-item {{ request()->is('admin/pppoe*')?'active':'' }}">
        <i class="bi bi-key"></i><span>PPPoE</span>
    </a>
    <a href="/admin/map" class="bn-item {{ request()->is('admin/map*')?'active':'' }}">
        <i class="bi bi-geo-alt"></i><span>Peta</span>
    </a>
    <a href="/admin/my-settings" class="bn-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-building"></i><span>Profil</span>
    </a>
    <a href="/kolektor/logout" class="bn-item" onclick="event.preventDefault();document.getElementById('collectorLogoutForm').submit();">
        <i class="bi bi-box-arrow-left"></i><span>Keluar</span>
    </a>

    @else
    <a href="{{ route('dashboard') }}" class="bn-item {{ request()->routeIs('dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i><span>Beranda</span>
    </a>
    <a href="/admin/billing/invoices" class="bn-item {{ request()->is('admin/billing/invoices*')?'active':'' }}">
        <i class="bi bi-receipt"></i><span>Invoice</span>
    </a>
    <a href="/admin/billing/customers" class="bn-item fab {{ request()->is('admin/billing/customers*')?'active':'' }}">
        <i class="bi bi-people"></i><span>Pelanggan</span>
    </a>
    <a href="/admin/mikrotik/routers" class="bn-item {{ request()->is('admin/mikrotik*')?'active':'' }}">
        <i class="bi bi-hdd-network"></i><span>MikroTik</span>
    </a>
    <a href="/admin/my-settings" class="bn-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-person-circle"></i><span>Profile</span>
    </a>
    @endif
@overwrite

<form id="technicianLogoutForm" method="POST" action="/teknisi/logout" style="display:none;">@csrf</form>
<form id="collectorLogoutForm" method="POST" action="/kolektor/logout" style="display:none;">@csrf</form>
