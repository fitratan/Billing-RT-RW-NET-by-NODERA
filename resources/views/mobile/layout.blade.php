<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <title>@yield('title','NODERA') — NODERA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{ -webkit-tap-highlight-color:transparent; }
    body{ font-family:'Inter',ui-sans-serif,system-ui,sans-serif; background:#f0f2f5; color:#1c1c1e; -webkit-font-smoothing:antialiased; }
    .card{ background:#fff; border-radius:16px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .card-sm{ padding:14px; border-radius:12px; }
    .stat-icon{ width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
    .gradient-hero{ background:linear-gradient(135deg,#2563eb,#1d4ed8); }
    .gradient-blue{ background:linear-gradient(135deg,#eef2ff,#dbeafe); }
    .gradient-green{ background:linear-gradient(135deg,#ecfdf5,#d1fae5); }
    .gradient-yellow{ background:linear-gradient(135deg,#fffbeb,#fef3c7); }
    .gradient-purple{ background:linear-gradient(135deg,#f5f3ff,#ede9fe); }
    .gradient-red{ background:linear-gradient(135deg,#fef2f2,#fee2e2); }
    .gradient-orange{ background:linear-gradient(135deg,#fff7ed,#ffedd5); }
    .tag{ display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:500; }
    .tag-green{ background:#dcfce7; color:#166534; }
    .tag-red{ background:#fee2e2; color:#b91c1c; }
    .tag-yellow{ background:#fef9c3; color:#854d0e; }
    .tag-blue{ background:#dbeafe; color:#1e40af; }
    .bento-card{ transition:all .2s; cursor:pointer; }
    .bento-card:hover{ transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,0.06); }
    .bento-card:active{ transform:scale(0.98); }
    .btn-primary{ background:#2563eb; transition:all .2s; }
    .btn-primary:hover{ background:#1d4ed8; }
    .btn-primary:active{ transform:scale(.97); }
    input,select,textarea{ font-size:16px !important; } /* Prevent iOS zoom */
    input:focus{ outline:none; border-color:#007aff; box-shadow:0 0 0 3px rgba(0,122,255,0.12); }
    select:focus{ outline:none; border-color:#007aff; box-shadow:0 0 0 3px rgba(0,122,255,0.12); }

    /* Sidebar */
    .sidebar{ width:260px; background:#fff; border-right:1px solid #f0f0f0; }
    .sidebar-link{ display:flex; align-items:center; gap:12px; padding:9px 14px; border-radius:10px; color:#5f6368; font-size:14px; font-weight:500; transition:all .15s; cursor:pointer; }
    .sidebar-link:hover{ background:#f1f3f4; color:#007aff; }
    .sidebar-link.active{ background:#e8f0fe; color:#007aff; font-weight:600; }
    .sidebar-link i{ width:20px; text-align:center; font-size:16px; flex-shrink:0; }
    .sub-link{ padding-left:46px !important; font-size:13px; padding-top:7px !important; padding-bottom:7px !important; }
    .group-header{ user-select:none; font-size:11px; font-weight:600; color:#9aa0a6; letter-spacing:.5px; text-transform:uppercase; padding:14px 14px 6px; }
    .group-header .arrow{ font-size:10px; transition:transform .2s; color:#9aa0a6; }
    .group-header.open .arrow{ transform:rotate(90deg); }
    .sub-menu{ overflow:hidden; max-height:0; transition:max-height .25s ease; }
    .sub-menu.open{ max-height:500px; }

    /* Bottom nav */
    .bottom-nav{ backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); background:rgba(255,255,255,.88); border-radius:24px; box-shadow:0 -2px 12px rgba(0,0,0,0.04); }
    .nav-link{ display:flex; flex-direction:column; align-items:center; gap:2px; padding:5px 10px; border-radius:10px; color:#9aa0a6; font-size:10px; font-weight:500; transition:all .15s; }
    .nav-link.active{ color:#007aff; }
    .nav-link i{ font-size:20px; }
    .nav-link span{ font-size:10px; letter-spacing:.2px; }

    /* Drawer */
    .drawer-overlay{ position:fixed; inset:0; background:rgba(0,0,0,0.2); z-index:50; opacity:0; pointer-events:none; transition:opacity .3s; }
    .drawer-overlay.open{ opacity:1; pointer-events:auto; }
    .drawer{ position:fixed; top:0; left:0; bottom:0; width:280px; background:#fff; z-index:51; transform:translateX(-100%); transition:transform .3s; overflow-y:auto; }
    .drawer.open{ transform:translateX(0); }

    .stat-value{ font-size:28px; font-weight:700; line-height:1.1; }
    .stat-label{ font-size:13px; color:#5f6368; }

    .table-wrap{ overflow-x:auto; }
    .table-wrap table{ width:100%; border-collapse:collapse; }
    .table-wrap th{ text-align:left; padding:12px 16px; font-size:12px; font-weight:600; color:#5f6368; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #f0f0f0; white-space:nowrap; }
    .table-wrap td{ padding:12px 16px; font-size:13px; color:#1c1c1e; border-bottom:1px solid #f0f0f0; white-space:nowrap; }
    .table-wrap tr:hover td{ background:#f8f9fa; }

    @media(min-width:1024px){ .mobile-only{ display:none !important; } .desktop-only{ display:flex !important; } }
    @media(max-width:1023px){ .desktop-only{ display:none !important; } .mobile-only{ display:flex !important; } }
  </style>
  @stack('styles')
</head>
<body class="antialiased">

{{-- ========== DESKTOP SIDEBAR ========== --}}
@if(!request()->is('login') && !request()->is('panel/*/login'))
<div class="desktop-only fixed left-0 top-0 h-full sidebar z-40 flex flex-col">
  <div class="p-5 border-b border-gray-50">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow shadow-blue-200"><i class="fas fa-shield-halved text-white text-sm"></i></div>
      <div><h1 class="text-base font-bold text-gray-900">NODERA</h1><p class="text-[10px] text-gray-400">ISP Management</p></div>
    </div>
  </div>
  <nav class="flex-1 p-2 overflow-y-auto" id="sidebarMenu">

    <a href="/dashboard" class="sidebar-link {{ request()->is('dashboard') ? 'active' : '' }}"><i class="fas fa-home"></i> Dashboard</a>
    <a href="/admin/analytics" class="sidebar-link {{ request()->is('admin/analytics') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Analytics</a>

    {{-- BILLING --}}
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Billing</div>
    <div class="sub-menu">
      <a href="/admin/billing/invoices" class="sidebar-link sub-link {{ request()->is('admin/billing/invoices*')||request()->is('admin/invoices*') ? 'active' : '' }}"><i class="fas fa-file-invoice"></i> Invoice</a>
      <a href="/admin/billing/customers" class="sidebar-link sub-link {{ request()->is('admin/billing/customers*')||request()->is('admin/customers*') ? 'active' : '' }}"><i class="fas fa-users"></i> Pelanggan</a>
      <a href="/admin/billing/packages" class="sidebar-link sub-link {{ request()->is('admin/billing/packages*')||request()->is('admin/packages*') ? 'active' : '' }}"><i class="fas fa-box"></i> Paket</a>
      <a href="/admin/payments/gateway" class="sidebar-link sub-link {{ request()->is('admin/payments/gateway*') ? 'active' : '' }}"><i class="fas fa-credit-card"></i> Payment Gateway</a>
      <a href="/admin/payments/qris" class="sidebar-link sub-link {{ request()->is('admin/payments/qris*') ? 'active' : '' }}"><i class="fas fa-qrcode"></i> QRIS</a>
      <a href="/admin/agents" class="sidebar-link sub-link {{ request()->is('admin/agents*') ? 'active' : '' }}"><i class="fas fa-handshake"></i> Agen</a>
      <a href="/admin/collectors" class="sidebar-link sub-link {{ request()->is('admin/collectors*') ? 'active' : '' }}"><i class="fas fa-user-tie"></i> Kolektor</a>
      <a href="/admin/finance" class="sidebar-link sub-link {{ request()->is('admin/finance') ? 'active' : '' }}"><i class="fas fa-chart-pie"></i> Finance</a>
      <a href="/admin/finance/expenses" class="sidebar-link sub-link {{ request()->is('admin/finance/expenses*') ? 'active' : '' }}"><i class="fas fa-money-bill-wave"></i> Pengeluaran</a>
    </div>

    {{-- NETWORK --}}
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Network</div>
    <div class="sub-menu">
      <a href="/admin/mikrotik/routers" class="sidebar-link sub-link {{ request()->is('admin/mikrotik*') ? 'active' : '' }}"><i class="fas fa-network-wired"></i> MikroTik</a>
      <a href="/admin/hotspot" class="sidebar-link sub-link {{ request()->is('admin/hotspot*') ? 'active' : '' }}"><i class="fas fa-wifi"></i> Hotspot</a>
      <a href="/admin/olt" class="sidebar-link sub-link {{ request()->is('admin/olt*') ? 'active' : '' }}"><i class="fas fa-server"></i> OLT</a>
      <a href="/admin/genieacs" class="sidebar-link sub-link {{ request()->is('admin/genieacs*') ? 'active' : '' }}"><i class="fas fa-satellite-dish"></i> GenieACS</a>
      <a href="/admin/map" class="sidebar-link sub-link {{ request()->is('admin/map*') ? 'active' : '' }}"><i class="fas fa-map-marked-alt"></i> Peta ONU</a>
    </div>

    {{-- LAYANAN --}}
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Layanan</div>
    <div class="sub-menu">
      <a href="/admin/trouble" class="sidebar-link sub-link {{ request()->is('admin/trouble*') ? 'active' : '' }}"><i class="fas fa-tools"></i> Gangguan</a>
      <a href="/admin/technicians" class="sidebar-link sub-link {{ request()->is('admin/technicians*') ? 'active' : '' }}"><i class="fas fa-user-cog"></i> Teknisi</a>
    </div>

    {{-- SYSTEM --}}
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> System</div>
    <div class="sub-menu">
      <a href="/admin/inventory" class="sidebar-link sub-link {{ request()->is('admin/inventory*') ? 'active' : '' }}"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="/admin/attendance" class="sidebar-link sub-link {{ request()->is('admin/attendance*') ? 'active' : '' }}"><i class="fas fa-clipboard-check"></i> Absensi</a>
      <a href="/admin/payroll" class="sidebar-link sub-link {{ request()->is('admin/payroll*') ? 'active' : '' }}"><i class="fas fa-wallet"></i> Payroll</a>
      <a href="/admin/my-settings" class="sidebar-link sub-link {{ request()->is('admin/my-settings*') ? 'active' : '' }}"><i class="fas fa-cog"></i> Pengaturan</a>
    </div>

    <a href="/logout" class="sidebar-link mt-3 text-red-400 hover:text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt"></i> Keluar</a>
  </nav>
</div>
@endif

{{-- ========== MOBILE DRAWER ========== --}}
@if(!request()->is('login') && !request()->is('panel/*/login'))
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="p-4 border-b border-gray-50 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg flex items-center justify-center"><i class="fas fa-shield-halved text-white text-xs"></i></div>
      <span class="font-bold text-gray-900">NODERA</span>
    </div>
    <button onclick="closeDrawer()" class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-xs"></i></button>
  </div>
  <nav class="p-2 overflow-y-auto" id="mobileSidebar">
    <!-- Same links as desktop sidebar, copied by JS or hardcoded -->
    <a href="/dashboard" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
    <a href="/admin/analytics" class="sidebar-link"><i class="fas fa-chart-line"></i> Analytics</a>
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Billing</div>
    <div class="sub-menu">
      <a href="/admin/billing/invoices" class="sidebar-link sub-link"><i class="fas fa-file-invoice"></i> Invoice</a>
      <a href="/admin/billing/customers" class="sidebar-link sub-link"><i class="fas fa-users"></i> Pelanggan</a>
      <a href="/admin/billing/packages" class="sidebar-link sub-link"><i class="fas fa-box"></i> Paket</a>
      <a href="/admin/payments/gateway" class="sidebar-link sub-link"><i class="fas fa-credit-card"></i> Payment Gateway</a>
      <a href="/admin/payments/qris" class="sidebar-link sub-link"><i class="fas fa-qrcode"></i> QRIS</a>
      <a href="/admin/agents" class="sidebar-link sub-link"><i class="fas fa-handshake"></i> Agen</a>
      <a href="/admin/collectors" class="sidebar-link sub-link"><i class="fas fa-user-tie"></i> Kolektor</a>
      <a href="/admin/finance" class="sidebar-link sub-link"><i class="fas fa-chart-pie"></i> Finance</a>
      <a href="/admin/finance/expenses" class="sidebar-link sub-link"><i class="fas fa-money-bill-wave"></i> Pengeluaran</a>
    </div>
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Network</div>
    <div class="sub-menu">
      <a href="/admin/mikrotik/routers" class="sidebar-link sub-link"><i class="fas fa-network-wired"></i> MikroTik</a>
      <a href="/admin/hotspot" class="sidebar-link sub-link"><i class="fas fa-wifi"></i> Hotspot</a>
      <a href="/admin/olt" class="sidebar-link sub-link"><i class="fas fa-server"></i> OLT</a>
      <a href="/admin/genieacs" class="sidebar-link sub-link"><i class="fas fa-satellite-dish"></i> GenieACS</a>
      <a href="/admin/map" class="sidebar-link sub-link"><i class="fas fa-map-marked-alt"></i> Peta ONU</a>
    </div>
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> Layanan</div>
    <div class="sub-menu">
      <a href="/admin/trouble" class="sidebar-link sub-link"><i class="fas fa-tools"></i> Gangguan</a>
      <a href="/admin/technicians" class="sidebar-link sub-link"><i class="fas fa-user-cog"></i> Teknisi</a>
      <a href="/teknisi/dashboard" class="sidebar-link sub-link"><i class="fas fa-clipboard-list"></i> Dashboard Teknisi</a>
    </div>
    <div class="group-header" onclick="toggleGroup(this)"><i class="fas fa-chevron-right arrow mr-1"></i> System</div>
    <div class="sub-menu">
      <a href="/admin/inventory" class="sidebar-link sub-link"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="/admin/attendance" class="sidebar-link sub-link"><i class="fas fa-clipboard-check"></i> Absensi</a>
      <a href="/admin/payroll" class="sidebar-link sub-link"><i class="fas fa-wallet"></i> Payroll</a>
      <a href="/admin/my-settings" class="sidebar-link sub-link"><i class="fas fa-cog"></i> Pengaturan</a>
    </div>
    <a href="/logout" class="sidebar-link mt-3 text-red-400"><i class="fas fa-sign-out-alt"></i> Keluar</a>
  </nav>
</div>
@endif

{{-- ========== MAIN CONTENT ========== --}}
<div class="lg:ml-[260px] min-h-screen flex flex-col">
  {{-- Mobile Header --}}
  @if(!request()->is('login') && !request()->is('panel/*/login'))
  <div class="mobile-only sticky top-0 bg-white/90 backdrop-blur-xl border-b border-gray-100 z-30 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <button onclick="openDrawer()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500"><i class="fas fa-bars text-xs"></i></button>
      <div><h1 class="text-sm font-bold text-gray-900">NODERA</h1><p class="text-[9px] text-gray-400 -mt-0.5">@yield('title','Dashboard')</p></div>
    </div>
    <button onclick="location.href='/logout'" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-sign-out-alt text-xs"></i></button>
  </div>
  @endif

  {{-- Content --}}
  <main class="flex-1">
    @yield('content')
  </main>

  {{-- Footer --}}
  @if(!request()->is('login') && !request()->is('panel/*/login'))
  <div class="text-center text-[11px] text-gray-400 py-4 border-t border-gray-100 mx-6">
    <i class="fas fa-shield-halved mr-1"></i> NODERA v1.0 — ISP Management
  </div>
  @endif
</div>

{{-- ========== MOBILE BOTTOM NAV ========== --}}
@if(!request()->is('login') && !request()->is('panel/*/login'))
<nav class="mobile-only fixed bottom-0 left-0 right-0 z-50 px-3 pb-2 pt-1">
  <div class="bottom-nav px-3 py-2 max-w-lg mx-auto">
    <div class="flex items-center justify-around">
      <a href="/dashboard" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}"><i class="fas fa-home"></i><span>Home</span></a>
      <a href="/admin/billing/invoices" class="nav-link {{ request()->is('admin/billing/invoices*') ? 'active' : '' }}"><i class="fas fa-file-invoice"></i><span>Tagihan</span></a>
      <a href="/admin/billing/customers" class="nav-link {{ request()->is('admin/billing/customers*') ? 'active' : '' }}"><i class="fas fa-users"></i><span>Pelanggan</span></a>
      <a href="/admin/analytics" class="nav-link {{ request()->is('admin/analytics*') ? 'active' : '' }}"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
      <button onclick="openDrawer()" class="nav-link"><i class="fas fa-bars"></i><span>Menu</span></button>
    </div>
  </div>
</nav>
@endif

<script>
function toggleGroup(el){ el.classList.toggle('open'); const sub=el.nextElementSibling; if(sub)sub.classList.toggle('open'); }
function openDrawer(){ document.getElementById('drawerOverlay').classList.add('open'); document.getElementById('drawer').classList.add('open'); document.body.style.overflow='hidden'; }
function closeDrawer(){ document.getElementById('drawerOverlay').classList.remove('open'); document.getElementById('drawer').classList.remove('open'); document.body.style.overflow=''; }
// Auto-open active submenu groups
document.querySelectorAll('.group-header').forEach(h=>{ const s=h.nextElementSibling; if(s&&s.querySelector('.active')){ h.classList.add('open'); s.classList.add('open'); } });
</script>
@stack('scripts')
</body>
</html>
