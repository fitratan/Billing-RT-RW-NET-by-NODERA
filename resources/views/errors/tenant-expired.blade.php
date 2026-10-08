<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Masa Aktif Layanan Berakhir — NODERA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg: #090d16;
        --card: rgba(18, 24, 40, 0.88);
        --card-border: rgba(255, 255, 255, 0.08);
        --text: #f1f5f9;
        --text2: #94a3b8;
        --text3: #64748b;
        --primary: #0084E3;
        --primary-hover: #0094FF;
        --warning: #f59e0b;
        --warning-bg: rgba(245, 158, 11, 0.12);
        --warning-border: rgba(245, 158, 11, 0.25);
        --info-bg: rgba(0, 115, 198, 0.12);
        --info-border: rgba(0, 115, 198, 0.28);
        --btn-bg: #0073C6;
        --btn-hover: #0084E3;
        --btn-text: #ffffff;
        --radius: 24px;
        --radius-sm: 14px;
    }
    [data-theme="light"] {
        --bg: #f4f6fb;
        --card: rgba(255, 255, 255, 0.95);
        --card-border: rgba(0, 0, 0, 0.08);
        --text: #0f172a;
        --text2: #475569;
        --text3: #94a3b8;
        --primary: #0284c7;
        --primary-hover: #0369a1;
        --warning: #d97706;
        --warning-bg: rgba(217, 119, 6, 0.08);
        --warning-border: rgba(217, 119, 6, 0.2);
        --info-bg: rgba(2, 132, 199, 0.08);
        --info-border: rgba(2, 132, 199, 0.2);
        --btn-bg: #0284c7;
        --btn-hover: #0369a1;
        --btn-text: #ffffff;
    }
    * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    body {
        font-family: 'Plus Jakarta Sans', 'Poppins', system-ui, -apple-system, sans-serif;
        background-color: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
        transition: background-color .3s, color .3s;
    }
    .ambient-glow {
        position: fixed;
        width: 480px;
        height: 480px;
        border-radius: 50%;
        filter: blur(140px);
        pointer-events: none;
        opacity: .25;
        z-index: 0;
    }
    .glow-1 {
        top: -100px;
        left: -100px;
        background: #f59e0b;
    }
    .glow-2 {
        bottom: -100px;
        right: -100px;
        background: #0084E3;
    }
    .card-wrap {
        position: relative;
        z-index: 10;
        background: var(--card);
        border: 1px solid var(--card-border);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: var(--radius);
        box-shadow: 0 25px 60px -15px rgba(0,0,0,.45);
        padding: 40px 32px;
        max-width: 480px;
        width: 100%;
        text-align: center;
        transition: all .3s;
    }
    .theme-toggle {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid var(--card-border);
        background: rgba(255, 255, 255, 0.04);
        color: var(--text2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        transition: all .2s;
    }
    .theme-toggle:hover {
        color: var(--text);
        transform: scale(1.06);
    }
    .icon-wrap {
        width: 76px;
        height: 76px;
        border-radius: 24px;
        background: var(--warning-bg);
        color: var(--warning);
        border: 1px solid var(--warning-border);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        font-size: 2.2rem;
        box-shadow: 0 8px 24px rgba(245, 158, 11, 0.15);
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 12px;
        background: var(--warning-bg);
        color: var(--warning);
        border: 1px solid var(--warning-border);
    }
    .badge-pulse {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background-color: var(--warning);
    }
    .title {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text);
        margin: 0 0 10px;
        letter-spacing: -0.02em;
    }
    .desc {
        font-size: 13.5px;
        color: var(--text2);
        margin: 0 0 20px;
        line-height: 1.6;
    }
    .panel-notice-box {
        background: var(--info-bg);
        border: 1px solid var(--info-border);
        border-radius: var(--radius-sm);
        padding: 16px 18px;
        text-align: left;
        margin-bottom: 24px;
        font-size: 12.5px;
        line-height: 1.55;
    }
    .panel-notice-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: #00C2FF;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .panel-notice-body {
        color: var(--text2);
    }
    .btn-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 13px 20px;
        border-radius: var(--radius-sm);
        background: var(--btn-bg);
        color: var(--btn-text);
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        transition: all .2s;
        box-shadow: 0 4px 18px rgba(0, 115, 198, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .btn-panel:hover {
        background: var(--btn-hover);
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 6px 22px rgba(0, 148, 255, 0.45);
    }
    .btn-panel:active {
        transform: scale(0.98);
    }
    .btn-secondary-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 14px;
        font-size: 12.5px;
        color: var(--text3);
        text-decoration: none;
        transition: color .2s;
    }
    .btn-secondary-link:hover {
        color: var(--text);
    }
    .footer-brand {
        margin-top: 24px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text3);
        letter-spacing: .04em;
    }
    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="card-wrap">
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Ganti Tema">
            <i class="bi bi-moon-fill"></i>
        </button>

        <div class="icon-wrap">
            <i class="bi bi-hourglass-bottom"></i>
        </div>

        <div>
            <span class="badge-status">
                <span class="badge-pulse"></span>
                Status: Masa Aktif Berakhir
            </span>
        </div>

        <h1 class="title">Masa Aktif Layanan Telah Berakhir</h1>

        <p class="desc">
            Akses ke layanan <strong>{{ $subdomain ?? '' }}.{{ config('app.base_domain', 'dgtlnetsolution.com') }}</strong> saat ini dinonaktifkan sementara karena masa berlangganan telah kedaluwarsa{{ !empty($expired_at) ? ' pada ' . date('d F Y', strtotime($expired_at)) : '' }}.
        </p>

        <!-- Informasi Perpanjangan Otomatis via Panel -->
        <div class="panel-notice-box">
            <div class="panel-notice-header">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                Perpanjang Langsung di Panel Layanan
            </div>
            <div class="panel-notice-body">
                Silakan lakukan perpanjangan langganan melalui <strong>Panel Pelanggan</strong>. Transaksi di Panel terkoneksi dan diproses secara <strong>otomatis</strong>, sehingga server dan sistem Anda akan langsung aktif seketika setelah pembayaran.
            </div>
        </div>

        <a href="{{ $panelUrl ?? ('https://panel.' . config('app.base_domain', 'dgtlnetsolution.com')) }}" class="btn-panel">
            <i class="bi bi-speedometer2"></i>
            Perpanjang di Panel Sekarang
        </a>

        <div>
            <a href="{{ config('app.url', '/') }}" class="btn-secondary-link">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda Utama
            </a>
        </div>

        <div class="footer-brand">
            NODERA · Digital Network Solution
        </div>
    </div>

<script>
(function(){
    var s = localStorage.getItem('n_theme');
    var p = window.matchMedia('(prefers-color-scheme:dark)').matches;
    var t = s || (p ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
    updateToggleIcon(t);
})();
function toggleTheme(){
    var e = document.documentElement;
    var c = e.getAttribute('data-theme');
    var n = c === 'dark' ? 'light' : 'dark';
    e.setAttribute('data-theme', n);
    localStorage.setItem('n_theme', n);
    updateToggleIcon(n);
}
function updateToggleIcon(t){
    var b = document.getElementById('themeToggle');
    if(b){
        b.innerHTML = t === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-fill"></i>';
        b.title = t === 'dark' ? 'Mode Terang' : 'Mode Gelap';
    }
}
</script>
</body>
</html>
