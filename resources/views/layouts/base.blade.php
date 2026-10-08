<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ \App\Models\Setting::getValue('COMPANY_NAME', 'DN Solution') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png?v=37">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=37">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @stack('styles')
    <style>/*══════════════════════════════════════════════════
     NODERA DESIGN SYSTEM — mobile-first, glassmorphism
     ══════════════════════════════════════════════════*/
    :root {
        --bg:#f0f2f5;--card:#fff;--card-hover:#f8f9fa;
        --text:#1c1c1e;--text2:#5f6368;--text3:#9aa0a6;
        --primary:#2563eb;--primary-bg:#eff6ff;--primary-light:#3b82f6;
        --success:#16a34a;--success-bg:#f0fdf4;
        --warning:#d97706;--warning-bg:#fffbeb;
        --danger:#dc2626;--danger-bg:#fef2f2;
        --info:#0891b2;--info-bg:#ecfeff;
        --border:#e5e7eb;--border-light:#f3f4f6;
        --shadow:0 1px 3px rgba(0,0,0,.04);--shadow-md:0 4px 12px rgba(0,0,0,.06);
        --shadow-lg:0 8px 24px rgba(0,0,0,.08);
        --radius:16px;--radius-sm:12px;--radius-xs:8px;--radius-pill:9999px;
        --sidebar-w:260px;--header-h:56px;
    }
    [data-theme="dark"] {
        --bg:#0f172a;--card:#1e293b;--card-hover:#273449;
        --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
        --primary:#3b82f6;--primary-bg:rgba(59,130,246,.12);
        --success:#22c55e;--success-bg:rgba(34,197,94,.1);
        --warning:#f59e0b;--warning-bg:rgba(245,158,11,.1);
        --danger:#f87171;--danger-bg:rgba(248,113,113,.1);
        --info:#22d3ee;--info-bg:rgba(34,211,238,.1);
        --border:#334155;--border-light:rgba(255,255,255,.05);
        --shadow:0 1px 3px rgba(0,0,0,.2);--shadow-md:0 4px 12px rgba(0,0,0,.3);
        --shadow-lg:0 8px 24px rgba(0,0,0,.4);
    }
    *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
    body{
        font-family:'Inter',system-ui,-apple-system,sans-serif;
        background:var(--bg);color:var(--text);min-height:100vh;
        overflow-x:hidden;-webkit-font-smoothing:antialiased;
        transition:background .3s,color .3s;
    }
    a{color:var(--primary);text-decoration:none;}
    a:hover{color:var(--primary-light);}
    ::-webkit-scrollbar{width:4px;height:4px;}
    ::-webkit-scrollbar-track{background:transparent;}
    ::-webkit-scrollbar-thumb{background:rgba(148,163,184,.3);border-radius:4px;}

    /* ===== CARDS ===== */
    .card{
        background:var(--card);border:1px solid var(--border-light);
        border-radius:var(--radius);box-shadow:var(--shadow);
        transition:background .3s,border .3s,box-shadow .2s,transform .2s;
    }
    .card:hover{box-shadow:var(--shadow-md);}
    .card:active{transform:scale(.98);}
    .card-header{background:transparent;border-color:var(--border-light);font-weight:600;}

    /* ===== STAT CARDS ===== */
    .stat-card{cursor:pointer;overflow:hidden;position:relative;}
    .stat-card .stat-icon{
        width:44px;height:44px;border-radius:var(--radius-xs);
        display:flex;align-items:center;justify-content:center;flex-shrink:0;
        font-size:1.1rem;transition:transform .15s;
    }
    .stat-card:active .stat-icon{transform:scale(.9);}
    .stat-card .ripple{
        position:absolute;border-radius:50%;background:rgba(0,0,0,.06);
        transform:scale(0);animation:ripple .6s linear;pointer-events:none;
    }
    @keyframes ripple{to{transform:scale(4);opacity:0;}}

    /* stat icon gradients */
    .stat-blue .stat-icon{background:linear-gradient(135deg,#eef2ff,#dbeafe);color:#2563eb;}
    .stat-green .stat-icon{background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a;}
    .stat-yellow .stat-icon{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
    .stat-red .stat-icon{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
    .stat-purple .stat-icon{background:linear-gradient(135deg,#f5f3ff,#ede9fe);color:#7c3aed;}
    .stat-indigo .stat-icon{background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#4f46e5;}
    .stat-teal .stat-icon{background:linear-gradient(135deg,#f0fdfa,#ccfbf1);color:#0d9488;}
    [data-theme="dark"] .stat-blue .stat-icon{background:rgba(37,99,235,.15);color:#60a5fa;}
    [data-theme="dark"] .stat-green .stat-icon{background:rgba(22,163,74,.15);color:#4ade80;}
    [data-theme="dark"] .stat-yellow .stat-icon{background:rgba(217,119,6,.15);color:#fbbf24;}
    [data-theme="dark"] .stat-red .stat-icon{background:rgba(220,38,38,.15);color:#f87171;}
    [data-theme="dark"] .stat-purple .stat-icon{background:rgba(124,58,237,.15);color:#a78bfa;}
    [data-theme="dark"] .stat-indigo .stat-icon{background:rgba(79,70,229,.15);color:#818cf8;}
    [data-theme="dark"] .stat-teal .stat-icon{background:rgba(13,148,136,.15);color:#2dd4bf;}

    /* ===== DARK MODE BOOTSTRAP OVERRIDES ===== */
    [data-theme="dark"] .bg-light{background-color:var(--card-hover)!important;}
    [data-theme="dark"] .bg-white{background-color:var(--card)!important;}
    [data-theme="dark"] .bg-body{background-color:var(--bg)!important;}
    [data-theme="dark"] .text-dark{color:var(--text)!important;}
    [data-theme="dark"] .text-body{color:var(--text)!important;}
    [data-theme="dark"] .text-muted{color:var(--text2)!important;}
    [data-theme="dark"] .text-secondary{color:var(--text2)!important;}
    [data-theme="dark"] .text-black-50{color:var(--text3)!important;}

    /* ===== TEXT COLORS WITH BG-OPACITY ===== */
    [data-theme="dark"] .text-primary{color:#60a5fa!important;}
    [data-theme="dark"] .text-success{color:#4ade80!important;}
    [data-theme="dark"] .text-danger{color:#f87171!important;}
    [data-theme="dark"] .text-warning{color:#fbbf24!important;}
    [data-theme="dark"] .text-info{color:#22d3ee!important;}
    [data-theme="dark"] .text-white{color:#fff!important;}

    /* ===== BG OPACITY ===== */
    [data-theme="dark"] .bg-primary{background-color:#3b82f6!important;}
    [data-theme="dark"] .bg-success{background-color:#22c55e!important;}
    [data-theme="dark"] .bg-danger{background-color:#ef4444!important;}
    [data-theme="dark"] .bg-warning{background-color:#f59e0b!important;}
    [data-theme="dark"] .bg-info{background-color:#06b6d4!important;}
    [data-theme="dark"] .bg-secondary{background-color:#64748b!important;}

    /* ===== bg-opacity-* TEXT & BG combos ===== */
    [data-theme="dark"] .bg-primary.bg-opacity-10{background-color:rgba(59,130,246,.15)!important;}
    [data-theme="dark"] .bg-success.bg-opacity-10{background-color:rgba(34,197,94,.12)!important;}
    [data-theme="dark"] .bg-danger.bg-opacity-10{background-color:rgba(239,68,68,.12)!important;}
    [data-theme="dark"] .bg-warning.bg-opacity-10{background-color:rgba(245,158,11,.12)!important;}
    [data-theme="dark"] .bg-info.bg-opacity-10{background-color:rgba(6,182,212,.12)!important;}
    [data-theme="dark"] .bg-secondary.bg-opacity-10{background-color:rgba(100,116,139,.15)!important;}
    [data-theme="dark"] .bg-primary.bg-opacity-25{background-color:rgba(59,130,246,.25)!important;}
    [data-theme="dark"] .bg-success.bg-opacity-25{background-color:rgba(34,197,94,.25)!important;}
    [data-theme="dark"] .bg-danger.bg-opacity-25{background-color:rgba(239,68,68,.25)!important;}

    /* ===== BORDERS ===== */
    [data-theme="dark"] .border{border-color:var(--border)!important;}
    [data-theme="dark"] .border-top{border-color:var(--border)!important;}
    [data-theme="dark"] .border-bottom{border-color:var(--border)!important;}
    [data-theme="dark"] .border-start{border-color:var(--border)!important;}
    [data-theme="dark"] .border-end{border-color:var(--border)!important;}
    [data-theme="dark"] .border-light{border-color:var(--border-light)!important;}
    [data-theme="dark"] .border-secondary{border-color:var(--text3)!important;}

    /* ===== TABLES ===== */
    [data-theme="dark"] .table{color:var(--text);--bs-table-bg:transparent;--bs-table-color:var(--text);--bs-table-border-color:var(--border);}
    [data-theme="dark"] .table-light{--bs-table-bg:var(--card-hover);--bs-table-color:var(--text);}
    [data-theme="dark"] .table-dark{--bs-table-bg:#1e293b;--bs-table-color:#f1f5f9;}
    [data-theme="dark"] .table-striped>tbody>tr:nth-of-type(odd)>*{background:var(--card-hover);color:var(--text);}
    [data-theme="dark"] .table-hover>tbody>tr:hover>*{background:rgba(255,255,255,.03);color:var(--text);}
    [data-theme="dark"] .table>:not(caption)>*>*{border-color:var(--border);}
    [data-theme="dark"] .table-danger{--bs-table-bg:rgba(239,68,68,.08);--bs-table-color:var(--text);}
    [data-theme="dark"] .table-success{--bs-table-bg:rgba(34,197,94,.08);--bs-table-color:var(--text);}
    [data-theme="dark"] .table-warning{--bs-table-bg:rgba(245,158,11,.08);--bs-table-color:var(--text);}
    [data-theme="dark"] .table-info{--bs-table-bg:rgba(6,182,212,.08);--bs-table-color:var(--text);}

    /* ===== BUTTONS ===== */
    [data-theme="dark"] .btn-outline-secondary{color:var(--text2);border-color:var(--text3);}
    [data-theme="dark"] .btn-outline-secondary:hover{background:var(--card-hover);color:var(--text);border-color:var(--text2);}
    [data-theme="dark"] .btn-outline-light{color:var(--text2);border-color:var(--border);}
    [data-theme="dark"] .btn-outline-light:hover{background:var(--card-hover);color:var(--text);border-color:var(--text3);}
    [data-theme="dark"] .btn-light{background:var(--card-hover);border-color:var(--border);color:var(--text);}
    [data-theme="dark"] .btn-light:hover{background:var(--card);border-color:var(--text3);color:var(--text);}
    [data-theme="dark"] .btn-outline-primary{color:#60a5fa;border-color:#60a5fa;}
    [data-theme="dark"] .btn-outline-primary:hover{background:#60a5fa;color:#0f172a;border-color:#60a5fa;}
    [data-theme="dark"] .btn-outline-success{color:#4ade80;border-color:#4ade80;}
    [data-theme="dark"] .btn-outline-success:hover{background:#4ade80;color:#0f172a;border-color:#4ade80;}
    [data-theme="dark"] .btn-outline-warning{color:#fbbf24;border-color:#fbbf24;}
    [data-theme="dark"] .btn-outline-warning:hover{background:#fbbf24;color:#0f172a;border-color:#fbbf24;}
    [data-theme="dark"] .btn-outline-danger{color:#f87171;border-color:#f87171;}
    [data-theme="dark"] .btn-outline-danger:hover{background:#f87171;color:#0f172a;border-color:#f87171;}
    [data-theme="dark"] .btn-outline-info{color:#22d3ee;border-color:#22d3ee;}
    [data-theme="dark"] .btn-outline-info:hover{background:#22d3ee;color:#0f172a;border-color:#22d3ee;}

    /* ===== CARDS ===== */
    [data-theme="dark"] .card{background:var(--card);border-color:var(--border-light);}
    [data-theme="dark"] .card-header{background:var(--card-hover);border-color:var(--border);color:var(--text);}
    [data-theme="dark"] .card-footer{background:var(--card-hover);border-color:var(--border);}
    [data-theme="dark"] .card-footer{background:var(--card-hover);border-color:var(--border);}

    /* ===== ALERTS ===== */
    [data-theme="dark"] .alert-primary{background:rgba(59,130,246,.12);color:#60a5fa;border-color:rgba(59,130,246,.2);}
    [data-theme="dark"] .alert-success{background:rgba(34,197,94,.1);color:#4ade80;border-color:rgba(34,197,94,.2);}
    [data-theme="dark"] .alert-danger{background:rgba(239,68,68,.1);color:#f87171;border-color:rgba(239,68,68,.2);}
    [data-theme="dark"] .alert-warning{background:rgba(245,158,11,.1);color:#fbbf24;border-color:rgba(245,158,11,.2);}
    [data-theme="dark"] .alert-info{background:rgba(6,182,212,.1);color:#22d3ee;border-color:rgba(6,182,212,.2);}

    /* ===== FORMS ===== */
    [data-theme="dark"] .form-control,[data-theme="dark"] .form-select{
        background:var(--card);border-color:var(--border);color:var(--text);
    }
    [data-theme="dark"] .form-control:focus,[data-theme="dark"] .form-select:focus{
        border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-bg);
    }
    [data-theme="dark"] .form-control::placeholder{color:var(--text3);}
    [data-theme="dark"] .form-check-input{background-color:var(--card);border-color:var(--text3);}
    [data-theme="dark"] .form-check-input:checked{background-color:var(--primary);border-color:var(--primary);}
    [data-theme="dark"] .input-group-text{background:var(--card-hover);border-color:var(--border);color:var(--text2);}

    /* ===== MODALS ===== */
    [data-theme="dark"] .modal-content{background:var(--card);border-color:var(--border);}
    [data-theme="dark"] .modal-header,[data-theme="dark"] .modal-footer{border-color:var(--border);}
    [data-theme="dark"] .modal-backdrop.show{opacity:.7;}
    [data-theme="dark"] .btn-close{filter:invert(1);}

    /* ===== DROPDOWN ===== */
    [data-theme="dark"] .dropdown-menu{background:var(--card);border-color:var(--border);}
    [data-theme="dark"] .dropdown-item{color:var(--text);}
    [data-theme="dark"] .dropdown-item:hover{background:var(--card-hover);color:var(--text);}
    [data-theme="dark"] .dropdown-divider{border-color:var(--border);}
    [data-theme="dark"] .dropdown-header{color:var(--text3);}

    /* ===== BADGES ===== */
    [data-theme="dark"] .badge.bg-light{background:var(--card-hover)!important;color:var(--text2)!important;}
    [data-theme="dark"] .badge.bg-primary{background:rgba(59,130,246,.2)!important;color:#60a5fa!important;}
    [data-theme="dark"] .badge.bg-success{background:rgba(34,197,94,.2)!important;color:#4ade80!important;}
    [data-theme="dark"] .badge.bg-danger{background:rgba(239,68,68,.2)!important;color:#f87171!important;}
    [data-theme="dark"] .badge.bg-warning{background:rgba(245,158,11,.2)!important;color:#fbbf24!important;}
    [data-theme="dark"] .badge.bg-info{background:rgba(6,182,212,.2)!important;color:#22d3ee!important;}
    [data-theme="dark"] .badge.bg-secondary{background:rgba(100,116,139,.2)!important;color:#94a3b8!important;}

    /* ===== PAGINATION ===== */
    [data-theme="dark"] .page-link{background:var(--card);border-color:var(--border);color:var(--text2);}
    [data-theme="dark"] .page-link:hover{background:var(--card-hover);color:var(--text);border-color:var(--border);}
    [data-theme="dark"] .page-item.active .page-link{background:var(--primary);border-color:var(--primary);color:#fff;}
    [data-theme="dark"] .page-item.disabled .page-link{background:var(--card-hover);color:var(--text3);border-color:var(--border);}

    /* ===== LIST GROUP ===== */
    [data-theme="dark"] .list-group-item{background:var(--card);border-color:var(--border-light);color:var(--text);}
    [data-theme="dark"] .list-group-item-action:hover{background:var(--card-hover);color:var(--text);}
    [data-theme="dark"] .list-group-item.active{background:var(--primary);border-color:var(--primary);color:#fff;}

    /* ===== NAV ===== */
    [data-theme="dark"] .nav-tabs .nav-link{color:var(--text2);border-color:transparent;}
    [data-theme="dark"] .nav-tabs .nav-link:hover{color:var(--text);border-color:var(--border);}
    [data-theme="dark"] .nav-tabs .nav-link.active{color:var(--primary);background:var(--card);border-color:var(--border);border-bottom-color:var(--card);}
    [data-theme="dark"] .nav-pills .nav-link{color:var(--text2);}
    [data-theme="dark"] .nav-pills .nav-link.active{background:var(--primary);color:#fff;}

    /* ===== PROGRESS ===== */
    [data-theme="dark"] .progress{background:var(--card-hover);}
    [data-theme="dark"] .progress-bar{background:var(--primary);}

    /* ===== ACCORDION ===== */
    [data-theme="dark"] .accordion-item{background:var(--card);border-color:var(--border);}
    [data-theme="dark"] .accordion-button{background:var(--card);color:var(--text);}
    [data-theme="dark"] .accordion-button:not(.collapsed){background:var(--card-hover);color:var(--primary);}
    [data-theme="dark"] .accordion-button::after{filter:invert(.7);}

    /* ===== TOAST ===== */
    [data-theme="dark"] .toast{background:var(--card);border-color:var(--border);}
    [data-theme="dark"] .toast-header{background:var(--card-hover);border-color:var(--border);color:var(--text2);}

    /* ===== OFF CANVAS ===== */
    [data-theme="dark"] .offcanvas{background:var(--card);color:var(--text);}

    /* ===== MISC ===== */
    [data-theme="dark"] code{color:#e2e8f0;}
    [data-theme="dark"] hr{border-color:var(--border);}
    [data-theme="dark"] pre{color:var(--text);background:var(--card-hover);border:1px solid var(--border);padding:12px;border-radius:8px;}
    [data-theme="dark"] blockquote{color:var(--text2);border-left-color:var(--border);}
    [data-theme="dark"] .text-black{color:var(--text)!important;}

    /* ===== BOOTSTRAP 5.3 SUBTLE/EMPHASIS ===== */
    [data-theme="dark"] .bg-success-subtle{background-color:rgba(34,197,94,.15)!important;}
    [data-theme="dark"] .text-success-emphasis{color:#4ade80!important;}
    [data-theme="dark"] .bg-secondary-subtle{background-color:rgba(100,116,139,.15)!important;}
    [data-theme="dark"] .text-secondary-emphasis{color:#94a3b8!important;}
    [data-theme="dark"] .bg-body-secondary{background-color:var(--card-hover)!important;}
    [data-theme="dark"] .bg-body-tertiary{background-color:var(--card)!important;}

    /* ===== BUTTONS ===== */
    .btn{border-radius:var(--radius-xs);font-weight:500;transition:all .15s;}
    .btn:active{transform:scale(.96)!important;}
    .btn-primary{background:var(--primary);border-color:var(--primary);}
    .btn-primary:hover{background:var(--primary-light);border-color:var(--primary-light);}
    .btn-outline-primary{color:var(--primary);border-color:var(--primary);}
    .btn-outline-primary:hover{background:var(--primary-bg);border-color:var(--primary-light);color:var(--primary-light);}

    /* ===== FORMS ===== */
    .form-control,.form-select{
        border-color:var(--border);border-radius:var(--radius-xs);
        font-size:14px;transition:all .15s;
    }
    .form-control:focus,.form-select:focus{
        border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-bg);
    }
    .form-label{color:var(--text2);font-size:13px;font-weight:500;margin-bottom:4px;}

    /* ===== TABLES ===== */
    .table{margin-bottom:0;}
    .table th{font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;}
    .table td{font-size:13px;vertical-align:middle;}

    /* ===== BADGES ===== */
    .badge{font-weight:500;border-radius:var(--radius-pill);padding:4px 10px;font-size:10.5px;}

    /* ===== ALERTS ===== */
    .alert{border-radius:var(--radius-sm);border:1px solid var(--border-light);}

    /* ===== MODALS ===== */
    .modal-content{border-radius:var(--radius);box-shadow:var(--shadow-lg);}

    /* ===== SIDEBAR ===== */
    .sidebar{
        position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);
        background:var(--card);border-right:1px solid var(--border-light);
        z-index:40;display:flex;flex-direction:column;
        transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);
    }
    .sidebar-brand{
        padding:16px 18px;flex-shrink:0;
        display:flex;align-items:center;gap:10px;
    }
    .sidebar-brand .brand-icon{
        width:32px;height:32px;border-radius:8px;
        background:linear-gradient(135deg,#2563eb,#1d4ed8);
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 1px 6px rgba(37,99,235,.25);
    }
    .sidebar-brand .brand-icon i{color:#fff;font-size:.8rem;}
    .sidebar-nav{flex:1;overflow-y:auto;padding:4px 8px;}
    .sidebar-nav::-webkit-scrollbar{width:3px;}
    .sidebar-nav::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}
    .nav-group{margin-bottom:2px;}
    .nav-group-header{
        display:flex;align-items:center;gap:6px;
        padding:10px 12px 4px;font-size:9.5px;font-weight:700;
        color:var(--text3);text-transform:uppercase;letter-spacing:.6px;
        cursor:pointer;user-select:none;transition:color .15s;
    }
    .nav-group-header:hover{color:var(--text2);}
    .nav-group-header .arrow{font-size:8px;transition:transform .2s;color:var(--text3);}
    .nav-group-header.open .arrow{transform:rotate(90deg);}
    .nav-sub{overflow:hidden;max-height:0;transition:max-height .25s ease;}
    .nav-sub.open{max-height:600px;}
    .nav-item{
        display:flex;align-items:center;gap:10px;
        padding:7px 10px;margin:1px 4px;border-radius:8px;
        color:var(--text2);font-size:12.5px;font-weight:500;
        text-decoration:none;transition:all .12s;
    }
    .nav-item:hover{background:var(--card-hover);color:var(--text);}
    .nav-item.active{background:var(--primary-bg);color:var(--primary);font-weight:600;}
    .nav-item i{font-size:.8rem;width:18px;text-align:center;color:var(--text3);transition:color .12s;}
    .nav-item:hover i{color:var(--text2);}
    .nav-item.active i{color:var(--primary);}
    .nav-item.sub{padding-left:36px;font-size:12px;}
    .nav-divider{height:1px;background:var(--border-light);margin:6px 14px;}
    .sidebar-footer{
        padding:6px 8px 10px;border-top:1px solid var(--border-light);flex-shrink:0;
    }
    .sidebar-footer .nav-item{color:var(--danger);opacity:.6;margin:0;}
    .sidebar-footer .nav-item:hover{background:var(--danger-bg);opacity:1;color:var(--danger);}

    /* ===== DRAWER OVERLAY ===== */
    .drawer-overlay{
        display:none;
    }

    /* ===== MAIN CONTENT ===== */
    .main-content{margin-left:0;min-height:100vh;display:flex;flex-direction:column;transition:margin .3s;}

    /* ===== TOP HEADER ===== */
    .top-header{
        background:rgba(255,255,255,.88);backdrop-filter:blur(12px);
        -webkit-backdrop-filter:blur(12px);
        border-bottom:1px solid var(--border-light);
        padding:0 16px;height:var(--header-h);
        display:flex;align-items:center;justify-content:space-between;
        position:sticky;top:0;z-index:30;
        transition:background .3s,border .3s;
    }
    [data-theme="dark"] .top-header{background:rgba(15,23,42,.88);}
    .top-header-left, .top-header-right{flex:1;display:flex;align-items:center;min-width:0;}
    .top-header-right{justify-content:flex-end;}
    .top-header-center{
        font-weight:700;font-size:.85rem;color:var(--text);
        position:absolute;left:50%;transform:translateX(-50%);
        white-space:nowrap;
    }

    /* ===== THEME TOGGLE ===== */
    .theme-toggle{
        width:32px;height:32px;border-radius:8px;border:none;
        background:var(--card-hover);color:var(--text2);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:1rem;transition:all .15s;
    }
    .theme-toggle:active{transform:scale(.9);}
    .theme-toggle i{transition:transform .4s cubic-bezier(.68,-.55,.27,1.55);}
    .theme-toggle.spin i{transform:rotate(360deg);}

    /* ===== CONTENT AREA ===== */
    .content{padding:12px 12px 80px;flex:1;}
    .content-header{margin-bottom:14px;}
    .content-header h2{font-size:1.15rem;font-weight:700;color:var(--text);margin:0;}
    .content-header p{font-size:12px;color:var(--text2);margin:2px 0 0;}

    /* ===== BOTTOM NAV ===== */
    .bottom-nav-wrap{
        position:fixed;bottom:8px;left:12px;right:12px;z-index:35;
    }
    .bottom-nav{
        background:rgba(255,255,255,.88);backdrop-filter:blur(20px);
        -webkit-backdrop-filter:blur(20px);
        border-radius:24px;padding:8px 12px;max-width:512px;margin:0 auto;
        box-shadow:0 -2px 12px rgba(0,0,0,.04);
        border:1px solid rgba(0,0,0,.04);
        transition:background .3s,border .3s;
    }
    [data-theme="dark"] .bottom-nav{background:rgba(30,41,59,.88);border-color:rgba(255,255,255,.04);}
    .bottom-nav .bn-row{display:flex;justify-content:space-around;align-items:center;}
    .bn-item{
        display:flex;flex-direction:column;align-items:center;
        gap:2px;padding:5px 10px;border-radius:10px;
        color:#9aa0a6;font-size:10px;font-weight:500;
        text-decoration:none;transition:all .15s;position:relative;
        min-width:44px;min-height:44px;justify-content:center;
    }
    [data-theme="dark"] .bn-item{color:#64748b;}
    .bn-item i{font-size:20px;transition:all .15s;}
    .bn-item span{font-size:10px;letter-spacing:.2px;}
    .bn-item.active{color:#007aff;}
    [data-theme="dark"] .bn-item.active{color:#60a5fa;}
    .bn-item.active::after{
        content:'';position:absolute;top:-4px;left:50%;transform:translateX(-50%);
        width:18px;height:3px;background:#007aff;border-radius:0 0 3px 3px;
    }
    [data-theme="dark"] .bn-item.active::after{background:#60a5fa;}
    .bn-item:not(.fab):active{transform:scale(.9);}
    .bn-item.fab{
        margin-top:-16px;background:linear-gradient(135deg,#007aff,#0066d6);
        color:#fff;border-radius:14px;padding:8px 18px;min-width:52px;
        box-shadow:0 4px 16px rgba(0,122,255,.3);
    }
    .bn-item.fab.active::after{display:none;}
    .bn-item.fab:active{box-shadow:0 1px 4px rgba(0,122,255,.2);transform:scale(.95);}
    .bn-item.fab i{color:#fff;}
    .bottom-spacer{height:76px;}

    /* ===== TABLE DARK MODE ===== */
    [data-theme="dark"] .table,
    [data-theme="dark"] table{color:var(--text);}
    [data-theme="dark"] .table thead th,
    [data-theme="dark"] table thead th{
        color:var(--text2);border-color:var(--border);background:var(--card-hover);
    }
    [data-theme="dark"] .table td,
    [data-theme="dark"] table td{
        border-color:var(--border);background:var(--card);
    }
    [data-theme="dark"] .table-striped>tbody>tr:nth-of-type(odd)>*,
    [data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) td{
        background:var(--card-hover);color:var(--text);
    }
    [data-theme="dark"] .table-hover>tbody>tr:hover>*,
    [data-theme="dark"] .table-hover tbody tr:hover td{
        background:rgba(255,255,255,.03);color:var(--text);
    }
    [data-theme="dark"] .table-light,
    [data-theme="dark"] .table thead.table-light,
    [data-theme="dark"] thead.table-light{
        --bs-table-bg:var(--card-hover);--bs-table-color:var(--text);
    }
    [data-theme="dark"] .table-bordered,
    [data-theme="dark"] .table-bordered td,
    [data-theme="dark"] .table-bordered th{
        border-color:var(--border);
    }
    [data-theme="dark"] .progress{background:var(--card-hover);}

    /* ===== REVEAL ANIMATION ===== */
    .reveal{opacity:0;transform:translateY(10px);transition:opacity .35s,transform .35s;}
    .reveal.visible{opacity:1;transform:translateY(0);}

    /* ===== TOAST ===== */
    .toast-ctr{
        position:fixed;top:12px;right:12px;z-index:9999;
        display:flex;flex-direction:column;gap:6px;
    }
    .toast-msg{
        background:var(--card);border:1px solid var(--border);border-radius:var(--radius-sm);
        padding:10px 14px;box-shadow:var(--shadow-md);font-size:12.5px;
        display:flex;align-items:center;gap:8px;color:var(--text);
        animation:slideIn .3s cubic-bezier(.4,0,.2,1);
    }
    @keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}

    /* ===== DESKTOP ===== */
    @media(min-width:1024px){
        .sidebar{transform:translateX(0);}
        .main-content{margin-left:var(--sidebar-w);}
        .bottom-nav-wrap{display:none;}
        .bottom-spacer{display:none;}
        .content{padding:16px 20px 20px;}
    }
    </style>
</head>
<body>

{{-- SIDEBAR --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="/" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;">
            <div class="brand-icon"><i class="bi bi-shield-check"></i></div>
            <div>
                <div style="font-weight:700;font-size:.9rem;color:var(--text);line-height:1.2;">NODERA</div>
                <div style="font-size:8.5px;color:var(--text3);letter-spacing:.4px;text-transform:uppercase;">@yield('sidebar_subtitle', 'ISP Management')</div>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        @yield('sidebar_menu')
    </nav>

    <div class="sidebar-footer">
        <a href="/logout" class="nav-item" onclick="event.preventDefault();document.getElementById('logoutForm').submit();">
            <i class="bi bi-box-arrow-left"></i> Keluar
        </a>
    </div>
</aside>

{{-- MAIN --}}
<div class="main-content">

    {{-- TOP HEADER --}}
    <header class="top-header">
        <div class="top-header-left">
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Mode Gelap" style="flex-shrink:0;">
                <i class="bi bi-moon-fill"></i>
            </button>
            <a href="/lang/{{ session('locale', 'id') === 'id' ? 'en' : 'id' }}" class="theme-toggle" title="{{ session('locale', 'id') === 'id' ? 'English' : 'Indonesia' }}" style="text-decoration:none;margin-left:6px;">
                <i class="bi {{ session('locale', 'id') === 'id' ? 'bi-translate' : 'bi-translate' }}"></i>
                <span style="font-size:10px;font-weight:600;margin-left:1px;">{{ session('locale', 'id') === 'id' ? 'ID' : 'EN' }}</span>
            </a>
        </div>
        <div class="top-header-center">NODERA</div>
        <div class="top-header-right">
            @if(session('admin_role') === 'superadmin')
            <span class="badge rounded-pill" style="background:var(--danger-bg);color:var(--danger);font-size:10px;">Super</span>
            @endif
            <a href="/logout" class="theme-toggle" title="Keluar" onclick="event.preventDefault();document.getElementById('logoutForm').submit();" style="text-decoration:none;">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>

    {{-- CONTENT --}}
    <div class="content">
        @if(session('msg'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @yield('content')
    </div>

    {{-- FOOTER ===== --}}
    <div style="text-align:center;font-size:11px;color:var(--text3);padding:10px 20px;border-top:1px solid var(--border-light);">
        <i class="bi bi-shield-check me-1"></i> NODERA v1.0 — @yield('sidebar_subtitle', 'ISP Management')
    </div>
</div>

{{-- BOTTOM NAV --}}
<div class="bottom-nav-wrap">
    <nav class="bottom-nav">
        <div class="bn-row">
            @yield('bottom_nav')
        </div>
    </nav>
</div>
<div class="bottom-spacer"></div>

{{-- LOGOUT FORM --}}
<form id="logoutForm" method="POST" action="/logout" style="display:none;">@csrf</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== THEME =====
(function(){
    const s = localStorage.getItem('n_theme');
    const p = window.matchMedia('(prefers-color-scheme:dark)').matches;
    const t = s || (p?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);
    updateIcon(t);
})();
function toggleTheme(){
    const e = document.documentElement;
    const c = e.getAttribute('data-theme');
    const n = c==='dark'?'light':'dark';
    e.setAttribute('data-theme',n);
    localStorage.setItem('n_theme',n);
    const b = document.getElementById('themeToggle');
    if(b){b.classList.add('spin');setTimeout(()=>b.classList.remove('spin'),400);}
    updateIcon(n);
}
function updateIcon(t){
    const b = document.getElementById('themeToggle');
    if(!b)return;
    b.innerHTML = t==='dark'?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';
    b.title = t==='dark'?'Mode Terang':'Mode Gelap';
}

// ===== NAV GROUP TOGGLE =====
function toggleGroup(el){
    el.classList.toggle('open');
    const sub = el.nextElementSibling;
    if(sub)sub.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.nav-group-header').forEach(h=>{
        const s=h.nextElementSibling;
        if(s&&s.querySelector('.active')){h.classList.add('open');s.classList.add('open');}
    });
});

// ===== RIPPLE =====
document.addEventListener('click',function(e){
    const c=e.target.closest('.stat-card');
    if(!c)return;
    const r=document.createElement('span');r.className='ripple';
    const rect=c.getBoundingClientRect();
    const s=Math.max(rect.width,rect.height);
    r.style.width=r.style.height=s+'px';
    r.style.left=(e.clientX-rect.left-s/2)+'px';
    r.style.top=(e.clientY-rect.top-s/2)+'px';
    c.appendChild(r);setTimeout(()=>r.remove(),600);
});

// ===== SCROLL REVEAL =====
if('IntersectionObserver' in window){
    const o=new IntersectionObserver((e)=>{e.forEach(x=>{if(x.isIntersecting){x.target.classList.add('visible');o.unobserve(x.target);}})},{threshold:.1});
    document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.reveal').forEach(e=>o.observe(e)));
}

// ===== TOAST =====
function showToast(m,t='success'){
    const c=document.getElementById('toasts')||(()=>{const d=document.createElement('div');d.id='toasts';d.className='toast-ctr';document.body.appendChild(d);return d;})();
    const e=document.createElement('div');e.className='toast-msg';
    const i={success:'bi-check-circle-fill',error:'bi-x-circle-fill',info:'bi-info-circle-fill'};
    e.innerHTML=`<i class="bi ${i[t]||i.info}" style="color:var(--${t==='success'?'success':t==='error'?'danger':'primary'});"></i>${m}`;
    c.appendChild(e);
    setTimeout(()=>{e.style.opacity='0';e.style.transform='translateX(100%)';e.style.transition='all .3s';setTimeout(()=>e.remove(),300);},3500);
}
</script>
@stack('scripts')
</body>
</html>
