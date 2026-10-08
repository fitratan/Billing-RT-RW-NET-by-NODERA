<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Layanan Tidak Ditemukan — NODERA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg:#f0f2f5;--card:#fff;--card-hover:#f8f9fa;
        --text:#1c1c1e;--text2:#5f6368;--text3:#9aa0a6;
        --primary:#2563eb;--primary-bg:#eff6ff;--primary-light:#3b82f6;
        --danger:#dc2626;--danger-bg:#fef2f2;
        --border:#e5e7eb;--border-light:#f3f4f6;
        --shadow:0 1px 3px rgba(0,0,0,.04);--shadow-md:0 4px 12px rgba(0,0,0,.06);
        --shadow-lg:0 8px 24px rgba(0,0,0,.08);
        --radius:16px;--radius-sm:12px;--radius-xs:8px;--radius-pill:9999px;
    }
    [data-theme="dark"] {
        --bg:#0f172a;--card:#1e293b;--card-hover:#273449;
        --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
        --primary:#3b82f6;--primary-bg:rgba(59,130,246,.12);
        --danger:#f87171;--danger-bg:rgba(248,113,113,.1);
        --border:#334155;--border-light:rgba(255,255,255,.05);
        --shadow:0 1px 3px rgba(0,0,0,.2);--shadow-md:0 4px 12px rgba(0,0,0,.3);
        --shadow-lg:0 8px 24px rgba(0,0,0,.4);
    }
    *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
    body{
        font-family:'Poppins',system-ui,-apple-system,sans-serif;
        background:var(--bg);color:var(--text);
        min-height:100vh;display:flex;align-items:center;justify-content:center;
        margin:0;padding:16px;
        -webkit-font-smoothing:antialiased;
        transition:background .3s,color .3s;
    }
    .card-wrap{
        background:var(--card);border:1px solid var(--border-light);
        border-radius:var(--radius);box-shadow:var(--shadow-lg);
        padding:40px 28px;max-width:440px;width:100%;text-align:center;
        position:relative;
        transition:background .3s,border .3s,box-shadow .3s;
    }
    .icon-wrap{
        width:72px;height:72px;border-radius:50%;
        background:var(--danger-bg);color:var(--danger);
        display:inline-flex;align-items:center;justify-content:center;
        margin-bottom:16px;font-size:2rem;
    }
    .btn-primary{
        background:var(--primary);border:none;border-radius:var(--radius-xs);
        font-weight:600;font-size:14px;padding:10px 20px;color:#fff;
        text-decoration:none;display:inline-flex;align-items:center;gap:8px;
        transition:all .15s;
    }
    .btn-primary:hover{background:var(--primary-light);color:#fff;}
    .btn-primary:active{transform:scale(.96);}
    .theme-toggle{
        position:absolute;top:16px;right:16px;
        width:32px;height:32px;border-radius:8px;border:none;
        background:var(--card-hover);color:var(--text2);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:1rem;transition:all .15s;
    }
    .theme-toggle:active{transform:scale(.9);}
    .footer-text{font-size:11px;color:var(--text3);text-align:center;margin-top:20px;}
    </style>
<link href="{{ asset('css/corporate.css') }}" rel="stylesheet">
</head>
<body>
    <div class="card-wrap">
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Mode Gelap">
            <i class="bi bi-moon-fill"></i>
        </button>
        <div class="icon-wrap"><i class="bi bi-building-x"></i></div>
        <h1 class="fw-bold" style="font-size:1.2rem;color:var(--text);margin:0 0 8px;">Layanan Tidak Ditemukan</h1>
        <p style="font-size:13px;color:var(--text2);margin:0 0 20px;line-height:1.6;">
            Alamat layanan <strong>{{ $subdomain ?? '' }}.{{ config('app.base_domain', 'localhost') }}</strong> belum terdaftar atau sedang tidak aktif.
        </p>
        <a href="{{ config('app.url', '/') }}" class="btn-primary">
            <i class="bi bi-house"></i> Kembali ke Halaman Utama
        </a>
    </div>

<script>
(function(){
    var s=localStorage.getItem('n_theme');
    var p=window.matchMedia('(prefers-color-scheme:dark)').matches;
    var t=s||(p?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);
    updateToggleIcon(t);
})();
function toggleTheme(){
    var e=document.documentElement;
    var c=e.getAttribute('data-theme');
    var n=c==='dark'?'light':'dark';
    e.setAttribute('data-theme',n);
    localStorage.setItem('n_theme',n);
    updateToggleIcon(n);
}
function updateToggleIcon(t){
    var b=document.getElementById('themeToggle');
    if(b){b.innerHTML=t==='dark'?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';b.title=t==='dark'?'Mode Terang':'Mode Gelap';}
}
</script>
</body>
</html>
