<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <link rel="icon" type="image/png" href="/favicon.png?v=37">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=37">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Kolektor — NODERA — {{ \App\Models\Setting::getValue('COMPANY_NAME', 'DN Solution') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg:#f0f2f5;--card:#fff;--card-hover:#f8f9fa;
        --text:#1c1c1e;--text2:#5f6368;--text3:#9aa0a6;
        --primary:#d97706;--primary-bg:#fffbeb;--primary-light:#f59e0b;
        --success:#16a34a;--success-bg:#f0fdf4;
        --warning:#d97706;--warning-bg:#fffbeb;
        --danger:#dc2626;--danger-bg:#fef2f2;
        --info:#0891b2;--info-bg:#ecfeff;
        --border:#e5e7eb;--border-light:#f3f4f6;
        --shadow:0 1px 3px rgba(0,0,0,.04);--shadow-md:0 4px 12px rgba(0,0,0,.06);
        --shadow-lg:0 8px 24px rgba(0,0,0,.08);
        --radius:16px;--radius-sm:12px;--radius-xs:8px;--radius-pill:9999px;
    }
    [data-theme="dark"] {
        --bg:#0f172a;--card:#1e293b;--card-hover:#273449;
        --text:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
        --primary:#f59e0b;--primary-bg:rgba(245,158,11,.12);
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
        background:var(--bg);color:var(--text);
        min-height:100vh;display:flex;align-items:center;justify-content:center;
        margin:0;padding:16px;
        -webkit-font-smoothing:antialiased;
        transition:background .3s,color .3s;
    }
    .login-wrap{width:100%;max-width:400px;}
    .login-card{
        background:var(--card);border:1px solid var(--border-light);
        border-radius:var(--radius);box-shadow:var(--shadow-lg);
        padding:32px 24px 28px;position:relative;
        transition:background .3s,border .3s,box-shadow .3s;
    }
    .brand-icon{
        width:64px;height:64px;border-radius:16px;
        background:linear-gradient(135deg,#d97706,#b45309);
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 4px 16px rgba(217,119,6,.3);
        margin:0 auto 16px;font-size:1.5rem;color:#fff;
    }
    .login-title{font-size:1.2rem;font-weight:700;color:var(--text);margin:0;}
    .login-subtitle{font-size:13px;color:var(--text2);margin:2px 0 0;}
    .form-label{color:var(--text2);font-size:13px;font-weight:500;margin-bottom:4px;}
    .form-control{
        border:1px solid var(--border);border-radius:var(--radius-xs);
        font-size:14px;background:var(--card);color:var(--text);
        transition:all .15s;padding:10px 12px;
    }
    .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-bg);}
    .form-control::placeholder{color:var(--text3);}
    .input-group-text{
        background:var(--card);border-color:var(--border);color:var(--text3);
        font-size:14px;border-radius:var(--radius-xs) 0 0 var(--radius-xs);
    }
    .input-group .form-control{border-radius:0 var(--radius-xs) var(--radius-xs) 0;}
    .btn-primary{
        background:var(--primary);border:none;border-radius:var(--radius-xs);
        font-weight:600;font-size:14px;padding:10px;color:#fff;
        transition:all .15s;cursor:pointer;
    }
    .btn-primary:hover{background:var(--primary-light);}
    .btn-primary:active{transform:scale(.96);}
    .alert{
        border-radius:var(--radius-sm);border:1px solid var(--border-light);
        font-size:13px;padding:10px 14px;
    }
    .alert-danger{background:var(--danger-bg);color:var(--danger);border-color:transparent;}
    .alert-success{background:var(--success-bg);color:var(--success);border-color:transparent;}
    .login-link{font-size:13px;color:var(--text2);text-decoration:none;font-weight:500;transition:color .15s;}
    .login-link:hover{color:var(--primary);}
    .footer-text{font-size:11px;color:var(--text3);text-align:center;margin-top:20px;}
    .footer-text i{color:var(--primary);}
    .theme-toggle{
        position:absolute;top:16px;right:16px;
        width:32px;height:32px;border-radius:8px;border:none;
        background:var(--card-hover);color:var(--text2);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:1rem;transition:all .15s;
    }
    .theme-toggle:active{transform:scale(.9);}
    .theme-toggle i{transition:transform .4s cubic-bezier(.68,-.55,.27,1.55);}
    .theme-toggle.spin i{transform:rotate(360deg);}
    @media(max-width:420px){
        .login-card{padding:24px 16px 20px;}
    }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Mode Gelap">
                <i class="bi bi-moon-fill"></i>
            </button>

            <div class="text-center">
                <div class="brand-icon"><i class="bi bi-person-badge"></i></div>
                <h1 class="login-title">Login Kolektor</h1>
                <p class="login-subtitle">Masuk untuk menagih pelanggan</p>
            </div>

            @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
            </div>
            @endif

            @if(session('msg'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle"></i> {{ session('msg') }}
            </div>
            @endif

            <form method="POST" action="/kolektor/login" class="mt-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" required value="{{ old('username') }}" placeholder="Username" class="form-control" autocomplete="username" autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" required placeholder="Password" class="form-control" autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk
                </button>
            </form>
        </div>

        <div class="footer-text">
            <i class="bi bi-person-badge me-1"></i> NODERA — Kolektor
        </div>
    </div>

<script>
(function(){
    const s=localStorage.getItem('n_theme');
    const p=window.matchMedia('(prefers-color-scheme:dark)').matches;
    const t=s||(p?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);
    const b=document.getElementById('themeToggle');
    if(b){b.innerHTML=t==='dark'?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';b.title=t==='dark'?'Mode Terang':'Mode Gelap';}
})();
function toggleTheme(){
    const e=document.documentElement;
    const c=e.getAttribute('data-theme');
    const n=c==='dark'?'light':'dark';
    e.setAttribute('data-theme',n);
    localStorage.setItem('n_theme',n);
    const b=document.getElementById('themeToggle');
    if(b){b.classList.add('spin');setTimeout(()=>b.classList.remove('spin'),400);b.innerHTML=n==='dark'?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';b.title=n==='dark'?'Mode Terang':'Mode Gelap';}
}
</script>
</body>
</html>
