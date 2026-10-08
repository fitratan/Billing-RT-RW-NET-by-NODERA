<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$businessName = defined('BOOKKEEPING_BUSINESS_NAME') ? BOOKKEEPING_BUSINESS_NAME : ($config['business_name'] ?? 'Pembukuan Usaha');
$subdomain    = defined('BOOKKEEPING_SUBDOMAIN')      ? BOOKKEEPING_SUBDOMAIN      : 'kas';
$pageTitle    = $pageTitle ?? 'Dashboard';
$activeTab    = $activeTab ?? 'dashboard';
$pageFab      = $pageFab   ?? [];   // per-page FAB actions set by each page file

$navItems = [
    ['page'=>'dashboard',    'icon'=>'bi-grid-fill',      'label'=>'Beranda'],
    ['page'=>'transactions', 'icon'=>'bi-card-list',      'label'=>'Transaksi'],
    ['page'=>'chat',         'icon'=>'bi-chat-dots-fill', 'label'=>'Chat'],
    ['page'=>'reports',      'icon'=>'bi-bar-chart-fill', 'label'=>'Laporan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($businessName) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NODERA PEMBUKUAN">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"></noscript>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <script>
        // Theme init before render (prevent FOUC)
        (function(){
            var s = localStorage.getItem('bk_theme');
            var dark = s ? s==='dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; scrollbar-width: none !important; -ms-overflow-style: none !important; }
        ::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
        .no-scrollbar { -webkit-overflow-scrolling: touch; scrollbar-width: none !important; -ms-overflow-style: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
        button:active, a.card-clickable:active, .btn:active { transform: scale(0.97); transition: transform 0.1s ease; }
        /* ── CSS Variables identical to Admin Tenant app.css ── */
        :root {
            --bg:          #ffffff; --fg:          #0f172a;
            --card:        #ffffff; --card-fg:     #0f172a;
            --primary:     #2563eb; --primary-fg:  #ffffff;
            --muted:       #f4f6fa; --muted-fg:    #64748b;
            --border:      #e2e8f0;
            --accent:      #eaf1ff; --accent-fg:   #0f172a;
            --sidebar:     #ffffff; --sidebar-border: #e2e8f0;
            --destructive: #dc2626;
        }
        .dark {
            --bg:          #141a2c; --fg:          #eceff9;
            --card:        #1a2136; --card-fg:     #eceff9;
            --primary:     #7da0ff; --primary-fg:  #0a1020;
            --muted:       #222b45; --muted-fg:    #9aa3c0;
            --border:      #2f3a5c;
            --accent:      #25304d; --accent-fg:   #eceff9;
            --sidebar:     #121729; --sidebar-border: #2f3a5c;
            --destructive: #ff8ba0;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-tap-highlight-color: transparent; touch-action: manipulation; scroll-behavior: smooth; }
        body, button, input, select, textarea {
            font-family: 'Poppins', sans-serif !important;
            background-color: var(--bg);
            color: var(--fg);
            -webkit-font-smoothing: antialiased;
        }
        body { margin:0; min-height:100dvh; overflow-x:hidden; }

        /* ── Galaxy bg (dark only, identical to Admin Tenant) ── */
        html.dark body::before {
            content:''; position:fixed; top:0; right:0; bottom:0; left:0; z-index:0; pointer-events:none;
            background-image:
                radial-gradient(1px 1px at 25% 20%, rgba(255,255,255,.85) 50%,transparent 51%),
                radial-gradient(1px 1px at 66% 12%, rgba(255,255,255,.70) 50%,transparent 51%),
                radial-gradient(1px 1px at 80% 35%, rgba(210,226,255,.80) 50%,transparent 51%),
                radial-gradient(1px 1px at 12% 60%, rgba(255,255,255,.60) 50%,transparent 51%),
                radial-gradient(1px 1px at 45% 72%, rgba(255,255,255,.75) 50%,transparent 51%),
                radial-gradient(1px 1px at 92% 80%, rgba(200,215,255,.70) 50%,transparent 51%),
                radial-gradient(1.5px 1.5px at 55% 30%, rgba(255,255,255,.9) 50%,transparent 52%),
                radial-gradient(1.5px 1.5px at 70% 62%, rgba(255,255,255,.75) 50%,transparent 52%),
                radial-gradient(ellipse 55% 40% at 82% 18%, rgba(124,142,222,.18),transparent 70%),
                radial-gradient(ellipse 50% 40% at 14% 88%, rgba(156,122,240,.14),transparent 70%),
                radial-gradient(ellipse 45% 38% at 50% 46%, rgba(74,108,215,.10), transparent 70%);
            background-size: 220px 220px,240px 240px,260px 260px,280px 280px,300px 300px,320px 320px,200px 200px,230px 230px,100% 100%,100% 100%,100% 100%;
            background-repeat: repeat,repeat,repeat,repeat,repeat,repeat,repeat,repeat,no-repeat,no-repeat,no-repeat;
            animation: starfield 60s linear infinite;
        }
        @keyframes starfield {
            from { background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0; }
            to { background-position: -220px -220px, -240px -240px, -260px -260px, -280px -280px, -300px -300px, -320px -320px, -200px -200px, -230px -230px, 0 0, 0 0, 0 0; }
        }

        /* Light bg gradient */
        body::after {
            content:''; position:fixed; top:0; right:0; bottom:0; left:0; z-index:0; pointer-events:none;
            background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(37,99,235,.06),transparent);
        }
        html.dark body::after { display:none; }

        /* ── DesktopSidebar (identical to Admin Tenant — fixed, w-60, hidden on mobile) ── */
        .sidebar {
            position: fixed; top: 0; bottom: 0; left: 0; z-index: 30;
            display: none; /* hidden on mobile */
            flex-direction: column;
            width: 240px;
            border-right: 1px solid var(--sidebar-border);
            background-color: var(--sidebar);
        }
        @media (min-width: 1024px) { .sidebar { display: flex; } }

        .sidebar-header {
            display: flex; align-items: center; gap: 10px;
            height: 56px; padding: 0 20px;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }
        .sidebar-logo {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--primary); color: var(--primary-fg);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; flex-shrink: 0;
        }
        .sidebar-name { font-size: 14px; font-weight: 600; color: var(--fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-sub  { font-size: 11px; color: var(--muted-fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .sidebar-nav { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 2px; }

        .sidebar-group-label {
            padding: 12px 12px 4px;
            font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--muted-fg); opacity: 0.7;
        }

        .sidebar-link {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 12px; border-radius: 8px;
            font-size: 14px; font-weight: 500;
            text-decoration: none; color: var(--muted-fg);
            transition: background .15s, color .15s;
            position: relative;
        }
        .sidebar-link:hover { background: var(--accent); color: var(--fg); }
        .sidebar-link.active { background: rgba(125,160,255,.1); color: var(--primary); }
        .dark .sidebar-link.active { background: rgba(125,160,255,.12); }
        .sidebar-link i { font-size: 18px; flex-shrink: 0; transition: transform .15s; }
        .sidebar-link:hover i { transform: scale(1.1); }
        .sidebar-link.active i { transform: scale(1.05); }
        .sidebar-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); margin-left: auto; flex-shrink: 0; }

        .sidebar-footer { border-top: 1px solid var(--sidebar-border); padding: 12px; }
        .sidebar-logout {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 12px; border-radius: 8px;
            font-size: 14px; font-weight: 500; color: var(--muted-fg);
            text-decoration: none; border: none; background: none; cursor: pointer;
            width: 100%; transition: background .15s, color .15s;
        }
        .sidebar-logout:hover { background: rgba(239,68,68,.08); color: var(--destructive); }
        .sidebar-logout i { font-size: 18px; }

        /* ── MobileHeader (sticky, z-30, h-14) — hidden on desktop ── */
        .mob-header {
            position: sticky; top: 0; z-index: 30;
            height: 56px; display: flex; align-items: center; gap: 8px;
            border-bottom: 1px solid var(--border);
            background: color-mix(in srgb, var(--bg) 90%, transparent);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            padding: 0 12px;
        }
        @media (min-width: 1024px) { .mob-header { display: none; } }

        .hdr-btn {
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: 18px; color: var(--muted-fg);
            border: none; background: none; cursor: pointer; text-decoration: none;
            transition: background .15s, color .15s; flex-shrink: 0;
        }
        .hdr-btn:hover { background: var(--accent); color: var(--fg); }

        /* ── Desktop top header (shown only on lg) ── */
        .desk-header {
            display: none;
            height: 56px; align-items: center; gap: 8px;
            border-bottom: 1px solid var(--border);
            background: color-mix(in srgb, var(--bg) 90%, transparent);
            backdrop-filter: blur(12px);
            padding: 0 24px;
            position: sticky; top: 0; z-index: 20;
        }
        @media (min-width: 1024px) { .desk-header { display: flex; } }

        /* ── Page wrapper ── */
        .page-shell { position: relative; display: flex; min-height: 100dvh; flex-direction: column; }
        @media (min-width: 1024px) { .page-shell { padding-left: 240px; } }

        .page-content {
            flex: 1; max-width: 900px; margin: 0 auto; width: 100%;
            padding: 16px 16px 120px;
        }
        @media (min-width: 1024px) { .page-content { padding: 32px 32px 64px; } }

        /* ── Card ── */
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px -1px rgba(0,0,0,.06);
        }
        .dark .card { box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        .card-clickable { cursor: pointer; transition: border-color .15s, transform .1s, box-shadow .15s; }
        .card-clickable:hover { border-color: var(--primary); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .dark .card-clickable:hover { box-shadow: 0 4px 16px rgba(0,0,0,.4); }
        .card-clickable:active { transform: translateY(0); }

        /* ── Bottom nav (mobile only — hidden on lg) ── */
        .bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0; width: 100%; z-index: 40;
            border-top: 1px solid var(--border);
            background: color-mix(in srgb, var(--bg) 95%, transparent);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bottom-nav-inner {
            display: flex; height: 64px; max-width: 512px; margin: 0 auto; width: 100%; align-items: stretch;
            overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
        }
        .bottom-nav-inner::-webkit-scrollbar { display: none; }
        @media (min-width: 1024px) { .bottom-nav { display: none; } }

        .bottom-nav a {
            display: flex; flex: 1; flex-direction: column;
            align-items: center; justify-content: center; gap: 4px;
            font-size: 12px; font-weight: 500; color: var(--muted-fg);
            text-decoration: none; position: relative;
            transition: color .15s; -webkit-tap-highlight-color: transparent;
        }
        .bottom-nav a i { font-size: 24px; transition: transform .15s; line-height: 1; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav a.active::before { content:''; position:absolute; top:0; width:32px; height:2px; background:var(--primary); border-radius:0 0 4px 4px; }
        .bottom-nav a.active i { transform: translateY(-1px) scale(1.05); }
        .bottom-nav a.active span { font-weight: 600; }

        /* ── FAB (mobile: above bottom nav, desktop: bottom-right corner) ── */
        .fab-btn {
            position: fixed;
            bottom: calc(64px + env(safe-area-inset-bottom) + 12px);
            right: 16px; z-index: 50;
            width: 56px; height: 56px; border-radius: 18px;
            background: var(--primary); color: var(--primary-fg);
            border: none; cursor: pointer; font-size: 24px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 16px color-mix(in srgb, var(--primary) 40%, transparent);
            transition: transform .22s cubic-bezier(.16,1,.3,1), box-shadow .2s;
        }
        @media (min-width: 1024px) {
            .fab-btn { display: flex; bottom: 28px; right: 28px; }
            .fab-actions { bottom: calc(28px + 56px + 16px) !important; right: 28px !important; }
        }
        .fab-btn:active { transform: scale(0.9); }

        /* ── Modal ── */
        .modal-backdrop { position:fixed; top:0; right:0; bottom:0; left:0; z-index:60; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); display:none; align-items:flex-end; justify-content:center; }
        @media (min-width: 640px) { .modal-backdrop { align-items:center; } }
        .modal-box { background:var(--card); border:1px solid var(--border); border-radius:20px 20px 0 0; padding:24px; width:100%; max-width:460px; max-height:85dvh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 -10px 40px rgba(0,0,0,.3); transform:translateY(100%); transition:transform .25s cubic-bezier(0.16, 1, 0.3, 1); }
        @media (min-width: 640px) { .modal-box { border-radius:20px; transform:scale(.95); transition:transform .2s, opacity .2s; } }
        .modal-backdrop.open { display:flex; }
        .modal-backdrop.open .modal-box { transform:translateY(0); }
        @media (min-width: 640px) { .modal-backdrop.open .modal-box { transform:scale(1); } }
        .modal-header { display:flex; align-items:center; justify-content:space-between; padding-bottom:16px; border-bottom:1px solid var(--border); margin-bottom:20px; }
        .modal-title { font-size:15px; font-weight:700; color:var(--fg); }
        .modal-footer { display:flex; gap:8px; margin-top:20px; padding-top:16px; border-top:1px solid var(--border); justify-content:flex-end; }

        /* ── Form ── */
        .field label { display:block; font-size:12px; font-weight:600; color:var(--muted-fg); margin-bottom:6px; }
        .field input,.field select,.field textarea {
            width:100%; padding:10px 14px; border-radius:10px;
            border:1px solid var(--border); background:var(--muted);
            color:var(--fg); font-family:'Poppins',sans-serif; font-size:13px; outline:none;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus,.field select:focus { border-color:var(--primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 20%,transparent); }
        .field select option { background:var(--card); }

        /* ── Buttons ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 18px; border-radius:10px; font-family:'Poppins',sans-serif; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:opacity .15s,transform .1s; text-decoration:none; }
        .btn:active { transform:scale(.96); }
        .btn-primary { background:var(--primary); color:var(--primary-fg); box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 30%,transparent); }
        .btn-primary:hover { opacity:.9; }
        .btn-outline { background:transparent; border-color:var(--border); color:var(--fg); }
        .btn-outline:hover { background:var(--muted); }
        .btn-sm { padding:7px 14px; font-size:12px; }
        .btn-ghost { background:transparent; color:var(--muted-fg); border-color:transparent; }
        .btn-ghost:hover { background:var(--muted); color:var(--fg); }
        .w-full { width:100%; }

        /* ── Flash banner ── */
        .flash { display:flex; align-items:center; gap:10px; border-radius:12px; border:1px solid; padding:12px 16px; font-size:13px; font-weight:500; margin-bottom:16px; }
        .flash.success { border-color:rgba(34,197,94,.3); background:rgba(34,197,94,.1); color:#22c55e; }
        .flash.error   { border-color:rgba(239,68,68,.3);  background:rgba(239,68,68,.1);  color:#ef4444; }
        .flash.info    { border-color:rgba(14,165,233,.3);  background:rgba(14,165,233,.1);  color:#38bdf8; }

        /* ── Misc ── */
        .divide-row { border-bottom: 1px solid var(--border); }
        .divide-row:last-child { border-bottom: none; }
        .empty-state { text-align:center; padding:40px 0; }
        .empty-state i { font-size:40px; color:var(--muted-fg); opacity:.4; display:block; margin-bottom:8px; }
        .empty-state p { font-size:12px; color:var(--muted-fg); margin:0; }
        .section-title { font-size:13px; font-weight:700; color:var(--fg); }
        .text-primary { color:var(--primary); }
        .text-success { color:#22c55e; }
        .text-danger  { color:#ef4444; }
        .text-muted   { color:var(--muted-fg); font-size:12px; }

        @keyframes spin { to { transform:rotate(360deg); } }
        .spin-once { animation: spin .6s ease; }

        @media print {
            .sidebar, .mob-header, .desk-header, .bottom-nav, .fab-btn, .fab-overlay, .fab-actions, .no-print, .hdr-btn { display: none !important; }
            body::before, body::after { display: none !important; }
            body { padding: 0 !important; background: #ffffff !important; color: #0f172a !important; font-family: 'Poppins', sans-serif !important; }
            .page-shell { padding-left: 0 !important; }
            .page-content { padding: 0 !important; max-width: 100% !important; }
            .card { background: #ffffff !important; border: 1px solid #cbd5e1 !important; box-shadow: none !important; margin-bottom: 16px !important; page-break-inside: avoid; }
            .print-only { display: block !important; }
            .month-card > div[id^="month_card_"] { display: block !important; }
            .toggle-icon { display: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th, td { border-bottom: 1px solid #e2e8f0 !important; }
        }
    </style>
<body>

<!-- ── Animated PWA Splash Screen (Matches Admin Tenant) ── -->
<div id="pwa-splash" style="position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0f172a;transition:opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.5s ease;">
    <div style="position:relative;display:flex;align-items:center;justify-content:center;width:120px;height:120px;">
        <div style="position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(37,99,235,0.35);filter:blur(24px);animation:splash-pulse 2s infinite ease-in-out;"></div>
        <img src="assets/logo.png" alt="NODERA PEMBUKUAN" width="72" height="72" style="width:72px!important;height:72px!important;max-width:72px!important;max-height:72px!important;object-fit:contain;border-radius:20px;position:relative;z-index:10;box-shadow:0 12px 30px -5px rgba(0,0,0,0.6);animation:splash-scale 0.8s cubic-bezier(0.16,1,0.3,1);" onerror="this.src='/images/logo.png'">
    </div>
    <h1 style="margin-top:20px;font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;animation:splash-fade 0.9s ease;">NODERA PEMBUKUAN</h1>
    <p style="margin-top:4px;font-family:'Poppins',sans-serif;font-size:12px;color:#94a3b8;font-weight:500;letter-spacing:0.5px;animation:splash-fade 1.1s ease;"><?= htmlspecialchars($businessName) ?></p>
    <div style="margin-top:32px;display:flex;align-items:center;gap:6px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#2563eb;animation:splash-dot 1.4s infinite ease-in-out;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;animation:splash-dot 1.4s infinite ease-in-out 0.2s;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#60a5fa;animation:splash-dot 1.4s infinite ease-in-out 0.4s;"></span>
    </div>
</div>
<style>
    @keyframes splash-pulse { 0%, 100% { transform: scale(1); opacity: 0.35; } 50% { transform: scale(1.35); opacity: 0.7; } }
    @keyframes splash-scale { from { transform: scale(0.75); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes splash-fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes splash-dot { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1.15); opacity: 1; } }
</style>
<script>
    (function(){
        if (sessionStorage.getItem('bk_splash_shown')) {
            var el = document.getElementById('pwa-splash');
            if (el) el.remove();
        } else {
            sessionStorage.setItem('bk_splash_shown', '1');
            setTimeout(function() {
                var splash = document.getElementById('pwa-splash');
                if (splash) {
                    splash.style.opacity = '0';
                    splash.style.pointerEvents = 'none';
                    setTimeout(function() { if (splash && splash.parentNode) splash.parentNode.removeChild(splash); }, 500);
                }
            }, 1200);
        }
    })();
</script>

<!-- ══════════════════════════════════════════ -->
<!--  DesktopSidebar (lg:flex, hidden on mobile) -->
<!-- ══════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo" style="padding:0;background:none;border:none;display:flex;align-items:center;justify-content:center;">
            <img src="assets/logo.png" alt="NODERA" style="width:32px;height:32px;object-fit:contain;border-radius:8px;" onerror="this.src='/images/logo.png'">
        </div>
        <div style="min-width:0;">
            <div class="sidebar-name"><?= htmlspecialchars($businessName) ?></div>
            <div class="sidebar-sub"><?= htmlspecialchars($subdomain) ?>.dgtlnetsolution.com</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php
        $sidebarGroups = [
            'Utama'    => [
                ['page'=>'dashboard',    'icon'=>'bi-grid-fill',      'label'=>'Dashboard'],
            ],
            'Keuangan' => [
                ['page'=>'transactions', 'icon'=>'bi-arrow-left-right','label'=>'Transaksi'],
                ['page'=>'chat',         'icon'=>'bi-chat-dots-fill',  'label'=>'Chat'],
                ['page'=>'reports',      'icon'=>'bi-bar-chart-fill',  'label'=>'Laporan'],
                ['page'=>'categories',   'icon'=>'bi-tags-fill',       'label'=>'Kategori'],
            ],
            'Lainnya'  => [
                ['page'=>'activitylog',  'icon'=>'bi-clock-history',   'label'=>'Log Aktivitas'],
                ['page'=>'settings',     'icon'=>'bi-gear-fill',       'label'=>'Setelan'],
            ],
        ];
        foreach ($sidebarGroups as $group => $items):
        ?>
            <div class="sidebar-group-label"><?= $group ?></div>
            <?php foreach ($items as $item):
                $isActive = $activeTab === $item['page'];
            ?>
                <a href="index.php?page=<?= $item['page'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= $item['label'] ?></span>
                    <?php if ($isActive): ?><span class="sidebar-dot"></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" onclick="return confirm('Keluar dari aplikasi?')" class="sidebar-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
    </div>
</aside>

<!-- ══════════════════════════════════════════ -->
<!--  Page Shell (content area right of sidebar) -->
<!-- ══════════════════════════════════════════ -->
<div class="page-shell">

    <!-- MobileHeader (visible on < lg) -->
    <header class="mob-header">
        <a href="index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;flex:1;min-width:0;">
            <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <img src="assets/logo.png" alt="NODERA" style="width:32px;height:32px;object-fit:contain;border-radius:8px;" onerror="this.src='/images/logo.png'">
            </div>
            <div style="min-width:0;flex:1;">
                <h1 style="margin:0;font-size:15px;font-weight:600;color:var(--fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($businessName) ?></h1>
                <p style="margin:0;font-size:11px;color:var(--muted-fg);"><?= htmlspecialchars($pageTitle) ?></p>
            </div>
        </a>
        <div style="display:flex;align-items:center;gap:2px;flex-shrink:0;">
            <button onclick="triggerReload()" class="hdr-btn" title="Muat Ulang"><i class="bi bi-arrow-clockwise" id="reloadIcon"></i></button>
            <button onclick="toggleTheme()" class="hdr-btn" title="Tema"><i class="bi bi-sun" id="themeIcon"></i></button>
            <a href="logout.php" onclick="return confirm('Keluar?')" class="hdr-btn" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <!-- Desktop header bar (visible on >= lg) -->
    <header class="desk-header">
        <div style="flex:1;min-width:0;">
            <h1 style="margin:0;font-size:15px;font-weight:600;color:var(--fg);"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div style="display:flex;align-items:center;gap:4px;">
            <button onclick="triggerReload()" class="hdr-btn" title="Muat Ulang"><i class="bi bi-arrow-clockwise" id="reloadIconDesk"></i></button>
            <button onclick="toggleTheme()" class="hdr-btn" title="Tema"><i class="bi bi-sun" id="themeIconDesk"></i></button>
        </div>
    </header>

    <!-- Page content -->
    <div class="page-content">
        <script type="application/json" id="pageFabData"><?= json_encode($pageFab) ?></script>

        <!-- Corporate Print Letterhead (Visible ONLY when printing) -->
        <div class="print-only" style="display:none;margin-bottom:24px;border-bottom:2px solid #0f172a;padding-bottom:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h1 style="margin:0;font-size:20px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;"><?= htmlspecialchars($businessName) ?></h1>
                    <p style="margin:2px 0 0;font-size:12px;color:#475569;">Laporan Pembukuan Kas & Rekapitulasi Keuangan</p>
                </div>
                <div style="text-align:right;font-size:11px;color:#64748b;">
                    <div>Tanggal Cetak: <?= date('d F Y') ?></div>
                    <div>Domain: <?= htmlspecialchars($subdomain) ?>.dgtlnetsolution.com</div>
                </div>
            </div>
        </div>
