@extends('layouts.base')

@section('sidebar_subtitle', 'Super Admin')

@section('sidebar_menu')
    <a href="/superadmin" class="nav-item {{ request()->is('superadmin')&&!request()->is('superadmin/*')?'active':'' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="nav-divider"></div>

    {{-- BISNIS --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('superadmin/tenants*')||request()->is('superadmin/paket*')||request()->is('superadmin/registrasi*')||request()->is('superadmin/admin-tenant*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Bisnis
        </div>
        <div class="nav-sub {{ request()->is('superadmin/tenants*')||request()->is('superadmin/paket*')||request()->is('superadmin/registrasi*')||request()->is('superadmin/admin-tenant*')?'open':'' }}">
            <a href="/superadmin/tenants" class="nav-item sub {{ request()->is('superadmin/tenants*')?'active':'' }}">
                <i class="bi bi-building"></i> Semua Tenant
                @php $tc = \App\Models\Tenant::count(); @endphp
                @if($tc>0)<span class="badge rounded-pill ms-auto" style="background:var(--primary-bg);color:var(--primary);font-size:9px;">{{ $tc }}</span>@endif
            </a>
            <a href="/superadmin/paket" class="nav-item sub {{ request()->is('superadmin/paket*')?'active':'' }}">
                <i class="bi bi-box-seam"></i> Paket Langganan
            </a>
            <a href="/superadmin/registrasi" class="nav-item sub {{ request()->is('superadmin/registrasi*')?'active':'' }}">
                <i class="bi bi-person-plus"></i> Pendaftaran
                @php $pr = \App\Models\RegistrationRequest::pending()->count(); @endphp
                <span class="badge rounded-pill ms-auto {{ $pr>0?'bg-warning text-dark':'bg-light text-muted' }}" style="font-size:9px;">{{ $pr }}</span>
            </a>
            <a href="/superadmin/admin-tenant" class="nav-item sub {{ request()->is('superadmin/admin-tenant*')?'active':'' }}">
                <i class="bi bi-people"></i> Pengguna Tenant
            </a>
        </div>
    </div>

    {{-- KEUANGAN --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('superadmin/finance*')||request()->is('superadmin/transactions*')||request()->is('superadmin/expenses*')||request()->is('superadmin/payment-gateway*')||request()->is('superadmin/invoices*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Keuangan
        </div>
        <div class="nav-sub {{ request()->is('superadmin/finance*')||request()->is('superadmin/transactions*')||request()->is('superadmin/expenses*')||request()->is('superadmin/payment-gateway*')||request()->is('superadmin/invoices*')?'open':'' }}">
            <a href="/superadmin/finance" class="nav-item sub {{ request()->is('superadmin/finance')&&!request()->is('superadmin/finance/*')?'active':'' }}">
                <i class="bi bi-graph-up-arrow"></i> Ringkasan Keuangan
            </a>
            <a href="/superadmin/transactions" class="nav-item sub {{ request()->is('superadmin/transactions*')?'active':'' }}">
                <i class="bi bi-credit-card"></i> Transaksi
            </a>
            <a href="/superadmin/expenses" class="nav-item sub {{ request()->is('superadmin/expenses*')?'active':'' }}">
                <i class="bi bi-cash-stack"></i> Pengeluaran
            </a>
            <a href="/superadmin/payment-gateway" class="nav-item sub {{ request()->is('superadmin/payment-gateway*')?'active':'' }}">
                <i class="bi bi-shield-check"></i> Payment Gateway
            </a>
            <a href="/superadmin/invoices" class="nav-item sub {{ request()->is('superadmin/invoices*')?'active':'' }}">
                <i class="bi bi-receipt"></i> Tagihan Subscription
            </a>
        </div>
    </div>

    {{-- SISTEM --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('superadmin/audit-logs*')||request()->is('superadmin/notifications*')||request()->is('superadmin/settings*')||request()->is('superadmin/backup*')||request()->is('superadmin/monitoring*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Sistem
        </div>
        <div class="nav-sub {{ request()->is('superadmin/audit-logs*')||request()->is('superadmin/notifications*')||request()->is('superadmin/settings*')||request()->is('superadmin/backup*')||request()->is('superadmin/monitoring*')?'open':'' }}">
            <a href="/superadmin/audit-logs" class="nav-item sub {{ request()->is('superadmin/audit-logs*')?'active':'' }}">
                <i class="bi bi-journal-text"></i> Log Aktivitas
            </a>
            <a href="/superadmin/notifications" class="nav-item sub {{ request()->is('superadmin/notifications*')?'active':'' }}">
                <i class="bi bi-bell"></i> Notifikasi
            </a>
            <a href="/superadmin/settings" class="nav-item sub {{ request()->is('superadmin/settings*')?'active':'' }}">
                <i class="bi bi-gear"></i> Pengaturan
            </a>
            <a href="/superadmin/backup" class="nav-item sub {{ request()->is('superadmin/backup*')?'active':'' }}">
                <i class="bi bi-database"></i> Backup
            </a>
            <a href="/superadmin/monitoring" class="nav-item sub {{ request()->is('superadmin/monitoring*')?'active':'' }}">
                <i class="bi bi-heart-pulse"></i> Monitoring
            </a>
        </div>
    </div>

    {{-- VPN --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('superadmin/vpn-servers*')||request()->is('superadmin/vpn/topup*')||request()->is('superadmin/vpn/users*')||request()->is('superadmin/vpn-paket*')||request()->is('superadmin/vpn/accounts*')||request()->is('superadmin/announcements*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> VPN
        </div>
        <div class="nav-sub {{ request()->is('superadmin/vpn-servers*')||request()->is('superadmin/vpn/topup*')||request()->is('superadmin/vpn/users*')||request()->is('superadmin/vpn-paket*')||request()->is('superadmin/vpn/accounts*')||request()->is('superadmin/announcements*')?'open':'' }}">
            <a href="/superadmin/vpn-paket" class="nav-item sub {{ request()->is('superadmin/vpn-paket*')?'active':'' }}">
                <i class="bi bi-box-seam"></i> Paket VPN
            </a>
            <a href="/superadmin/vpn-servers" class="nav-item sub {{ request()->is('superadmin/vpn-servers*')?'active':'' }}">
                <i class="bi bi-hdd-network"></i> Server VPN
            </a>
            <a href="/superadmin/vpn/accounts" class="nav-item sub {{ request()->is('superadmin/vpn/accounts*')?'active':'' }}">
                <i class="bi bi-shield-lock"></i> Akun VPN
                @php $pa = \App\Models\VpnAccount::where('status', 'PENDING_APPROVAL')->count(); @endphp
                @if($pa>0)<span class="badge rounded-pill ms-auto bg-warning text-dark" style="font-size:9px;">{{ $pa }}</span>@endif
            </a>
            <a href="/superadmin/vpn/topup" class="nav-item sub {{ request()->is('superadmin/vpn/topup*')?'active':'' }}">
                <i class="bi bi-cash-stack"></i> Topup
                @php $tp = \App\Models\VpnTopupRequest::pending()->count(); @endphp
                @if($tp>0)<span class="badge rounded-pill ms-auto bg-warning text-dark" style="font-size:9px;">{{ $tp }}</span>@endif
            </a>
            <a href="/superadmin/vpn/users" class="nav-item sub {{ request()->is('superadmin/vpn/users*')?'active':'' }}">
                <i class="bi bi-people"></i> Pengguna VPN
            </a>
            <a href="/superadmin/announcements" class="nav-item sub {{ request()->is('superadmin/announcements*')?'active':'' }}">
                <i class="bi bi-megaphone"></i> Pengumuman
            </a>
        </div>
    </div>
@overwrite

@section('bottom_nav')
    <a href="/superadmin" class="bn-item {{ request()->is('superadmin')&&!request()->is('superadmin/*')?'active':'' }}">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
    </a>
    <a href="/superadmin/tenants" class="bn-item {{ request()->is('superadmin/tenants*')?'active':'' }}">
        <i class="bi bi-building"></i><span>Tenant</span>
    </a>
    <a href="/superadmin/registrasi" class="bn-item fab {{ request()->is('superadmin/registrasi*')?'active':'' }}">
        <i class="bi bi-person-plus"></i><span>Registrasi</span>
    </a>
    <a href="/superadmin/finance" class="bn-item {{ request()->is('superadmin/finance*')?'active':'' }}">
        <i class="bi bi-graph-up-arrow"></i><span>Keuangan</span>
    </a>
    <a href="/superadmin/settings" class="bn-item {{ request()->is('superadmin/settings*')?'active':'' }}">
        <i class="bi bi-gear"></i><span>Setelan</span>
    </a>
@overwrite
