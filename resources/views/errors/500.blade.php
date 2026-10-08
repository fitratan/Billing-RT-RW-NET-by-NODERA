<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Terjadi Kendala Sistem — NODERA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090B0E;
            --card: #161B22;
            --border: #2B3544;
            --text-primary: #FFFFFF;
            --text-secondary: #94A3B8;
            --cyan: #00C2FF;
            --rose: #F43F5E;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .bg-glow {
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(244, 63, 94, 0.15) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        .card-wrap {
            position: relative;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
            padding: 36px 28px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            z-index: 1;
        }
        .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: linear-gradient(135deg, #F43F5E, #BE123C);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 20px -5px rgba(244, 63, 94, 0.4);
        }
        .badge-pill {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--rose);
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            border-radius: 9999px;
            padding: 4px 12px;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 18px;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 12.5px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .btn-row {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .btn-primary {
            flex: 1;
            background: linear-gradient(135deg, #0073C6, #005299);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-weight: 700;
            font-size: 12.5px;
            padding: 11px 16px;
            border-radius: 14px;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 115, 198, 0.3);
            transition: all 0.2s;
        }
        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-secondary {
            flex: 1;
            background: #1D242E;
            border: 1px solid var(--border);
            color: #E2E8F0;
            font-weight: 700;
            font-size: 12.5px;
            padding: 11px 16px;
            border-radius: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            border-color: var(--cyan);
            color: var(--cyan);
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>
    <div class="card-wrap">
        <div class="icon-wrap">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div><span class="badge-pill">ERROR 500 · KENDALA SISTEM</span></div>
        <h1>Terjadi Kendala Sistem</h1>
        <p>
            Sistem sedang mengalami pemulihan koneksi internal. Silakan muat ulang atau kembali ke dashboard.
        </p>
        <div class="btn-row">
            <a href="javascript:location.reload()" class="btn-primary">
                Muat Ulang
            </a>
            <a href="/dashboard" class="btn-secondary">
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
