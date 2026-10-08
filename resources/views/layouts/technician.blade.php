@extends('layouts.base')

@section('sidebar_subtitle', session('tenant_name', 'Teknisi'))

@section('sidebar_menu')
    <a href="/teknisi/dashboard" class="nav-item {{ request()->is('teknisi/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i> Dashboard
    </a>

    <div class="nav-divider"></div>

    {{-- NETWORK --}}
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

    <div class="nav-divider"></div>

    {{-- LAYANAN --}}
    <div class="nav-group">
        <div class="nav-group-header {{ request()->is('admin/trouble*')?'open':'' }}" onclick="toggleGroup(this)">
            <i class="bi bi-chevron-right arrow"></i> Layanan
        </div>
        <div class="nav-sub {{ request()->is('admin/trouble*')?'open':'' }}">
            <a href="/admin/trouble" class="nav-item sub {{ request()->is('admin/trouble*')?'active':'' }}"><i class="bi bi-tools"></i> Gangguan</a>
        </div>
    </div>

    {{-- Profile --}}
    <a href="/admin/my-settings" class="nav-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-building"></i> {{ session('tenant_name', 'Perusahaan') }}
    </a>
@overwrite

@section('bottom_nav')
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
@overwrite
<form id="technicianLogoutForm" method="POST" action="/teknisi/logout" style="display:none;">@csrf</form>
