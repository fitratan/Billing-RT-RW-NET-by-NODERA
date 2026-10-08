<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sedang Pemeliharaan Sistem (503) — NODERA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg:#f0f2f5;--card:#fff;--card-hover:#f8f9fa;
        --text:#1c1c1e;--text2:#5f6368;--text3:#9aa0a6;
        --primary:#2563eb;--primary-light:#3b82f6;
        --danger:#dc2626;--danger-bg:#fef2f2;
        --border-light:#f3f4f6;
        --shadow-lg:0 8px 24px rgba(0,0,0,.08);
        --radius:16px;--radius-xs:8px;
    }
    [data-theme="dark"] {
        --bg:#0f172a;--card:#1e293b;--card-hover:#273449;
        --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
        --primary:#3b82f6;
        --danger:#f87171;--danger-bg:rgba(248,113,113,.1);
        --border-light:rgba(255,255,255,.05);
        --shadow-lg:0 8px 24px rgba(0,0,0,.4);
    }
    *{box-sizing:border-box;}
    body{
        font-family:'Poppins',system-ui,-apple-system,sans-serif;
        background:var(--bg);color:var(--text);
        min-height:100vh;display:flex;align-items:center;justify-content:center;
        margin:0;padding:16px;
        transition:background .3s,color .3s;
    }
    .card-wrap{
        background:var(--card);border:1px solid var(--border-light);
        border-radius:var(--radius);box-shadow:var(--shadow-lg);
        padding:40px 28px;max-width:440px;width:100%;text-align:center;
        position:relative;
    }
    .icon-wrap{
        width:72px;height:72px;border-radius:50%;
        background:rgba(37,99,235,.1);color:var(--primary);
        display:inline-flex;align-items:center;justify-content:center;
        margin-bottom:16px;font-size:2rem;
    }
    .btn-primary-action{
        background:var(--primary);border:none;border-radius:var(--radius-xs);
        font-weight:600;font-size:14px;padding:10px 20px;color:#fff;
        text-decoration:none;display:inline-flex;align-items:center;gap:8px;
        transition:all .15s;
    }
    .btn-primary-action:hover{background:var(--primary-light);color:#fff;}
    .theme-toggle{
        position:absolute;top:16px;right:16px;
        width:32px;height:32px;border-radius:8px;border:none;
        background:var(--card-hover);color:var(--text2);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:1rem;
    }
    </style>
</head>
<body>
    <div class="card-wrap">
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Mode Gelap">
            <i class="bi bi-moon-fill"></i>
        </button>
        <div class="icon-wrap"><i class="bi bi-tools"></i></div>
        <h1 class="fw-bold" style="font-size:1.2rem;color:var(--text);margin:0 0 8px;">Pemeliharaan Sistem</h1>
        <p style="font-size:13px;color:var(--text2);margin:0 0 20px;line-height:1.6;">
            Sistem sedang dalam proses pemeliharaan rutin untuk meningkatkan kualitas layanan. Kami akan segera kembali online.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="javascript:location.reload()" class="btn-primary-action">
                <i class="bi bi-arrow-clockwise"></i> Cek Ulang
            </a>
        </div>
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
    if(b){b.innerHTML=t==='dark'?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';}
}
</script>
</body>
</html>
