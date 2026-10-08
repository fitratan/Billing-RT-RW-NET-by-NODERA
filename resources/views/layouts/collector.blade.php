@extends('layouts.base')

@section('sidebar_subtitle', session('tenant_name', 'Kolektor'))

@section('sidebar_menu')
    <a href="/kolektor/dashboard" class="nav-item {{ request()->is('kolektor/dashboard')?'active':'' }}">
        <i class="bi bi-house-door"></i> Dashboard
    </a>

    <div class="nav-divider"></div>

    {{-- NETWORK --}}
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

    {{-- Profile --}}
    <a href="/admin/my-settings" class="nav-item {{ request()->is('admin/my-settings*')?'active':'' }}">
        <i class="bi bi-building"></i> {{ session('tenant_name', 'Perusahaan') }}
    </a>
@overwrite

@section('bottom_nav')
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
@overwrite
<form id="collectorLogoutForm" method="POST" action="/kolektor/logout" style="display:none;">@csrf</form>
