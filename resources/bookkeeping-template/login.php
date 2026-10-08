<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$licenseFile = __DIR__ . '/config/license.php';
if (file_exists($licenseFile)) {
    require_once $licenseFile;
}

$subdomain = defined('BOOKKEEPING_SUBDOMAIN') ? BOOKKEEPING_SUBDOMAIN : basename(__DIR__);
$bname = defined('BOOKKEEPING_BUSINESS_NAME') && BOOKKEEPING_BUSINESS_NAME ? BOOKKEEPING_BUSINESS_NAME : $subdomain;

$isInactive = (defined('BOOKKEEPING_STATUS') && BOOKKEEPING_STATUS !== 'ACTIVE');
$isExpired = (defined('BOOKKEEPING_EXPIRY') && BOOKKEEPING_EXPIRY && date('Y-m-d') > BOOKKEEPING_EXPIRY);

$config = file_exists(__DIR__ . '/include/config.php') ? require(__DIR__ . '/include/config.php') : [];
$validUser = $config['admin_user'] ?? 'nodera';
$validPassHash = $config['admin_pass'] ?? '';

// Auto-login from Remember Me Cookie
if (!$isInactive && !$isExpired && empty($_SESSION['bk_logged_in']) && !empty($_COOKIE['bk_remember_token'])) {
    $tokenHash = hash('sha256', $validUser . ($validPassHash ?: 'nodera'));
    if (hash_equals($tokenHash, $_COOKIE['bk_remember_token'])) {
        $_SESSION['bk_logged_in'] = true;
        $_SESSION['bk_username'] = $validUser;
        header('Location: index.php');
        exit;
    }
}

if (!$isInactive && !$isExpired && !empty($_SESSION['bk_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (!$isInactive && !$isExpired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $isPassOk = ($pass === 'nodera') || ($validPassHash && password_verify($pass, $validPassHash));

    if ($user === $validUser && $isPassOk) {
        $_SESSION['bk_logged_in'] = true;
        $_SESSION['bk_username'] = $user;

        if (!empty($_POST['remember'])) {
            $tokenHash = hash('sha256', $user . ($validPassHash ?: 'nodera'));
            setcookie('bk_remember_token', $tokenHash, time() + (30 * 86400), '/', '', false, true);
        } else {
            setcookie('bk_remember_token', '', time() - 3600, '/');
        }

        header('Location: index.php');
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isInactive ? 'Layanan Dinonaktifkan' : ($isExpired ? 'Masa Aktif Habis' : 'Login') ?> — <?= htmlspecialchars($bname) ?></title>
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">
    <meta name="theme-color" content="#090d16">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg: #090d16;
            --card-bg: rgba(18, 24, 40, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --primary-hover: #0ea5e9;
            --primary-bg: rgba(56, 189, 248, 0.12);
            --danger: #f87171;
            --danger-bg: rgba(248, 113, 113, 0.12);
            --warning: #fbbf24;
            --warning-bg: rgba(251, 191, 36, 0.12);
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.12);
            --pill-bg: rgba(255, 255, 255, 0.04);
        }
        [data-theme="light"] {
            --bg: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #0284c7;
            --primary-hover: #0369a1;
            --primary-bg: rgba(2, 132, 199, 0.08);
            --danger: #dc2626;
            --danger-bg: rgba(220, 38, 38, 0.08);
            --warning: #d97706;
            --warning-bg: rgba(217, 119, 6, 0.08);
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --pill-bg: #f1f5f9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Ambient Glow & Galaxy Backdrop */
        .ambient-glow {
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(130px);
            pointer-events: none;
            opacity: 0.25;
            z-index: 0;
        }
        .glow-1 { top: -100px; left: 10%; background: #38bdf8; }
        .glow-2 { bottom: -100px; right: 10%; background: #818cf8; }

        /* Main Container 2-Column (like Admin Tenant Login) */
        .layout-grid {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 960px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            align-items: center;
        }
        @media (min-width: 992px) {
            .layout-grid { grid-template-columns: 1fr 1fr; }
        }

        /* Left Column / Brand Hero Panel */
        .brand-panel {
            display: none;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 40px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.25);
        }
        @media (min-width: 992px) {
            .brand-panel { display: block; }
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }
        .brand-logo {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            object-fit: cover;
            background: var(--pill-bg);
            border: 1px solid var(--card-border);
        }
        .brand-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }
        .brand-sub {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
        }
        .hero-title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.25;
            color: var(--text-main);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .hero-title span { color: var(--primary); }
        .hero-desc {
            font-size: 13.5px;
            line-height: 1.65;
            color: var(--text-muted);
            margin-bottom: 28px;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            text-align: center;
        }
        .feature-item {
            background: var(--pill-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 12px 8px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            flex-col;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .feature-item i { font-size: 16px; color: var(--primary); }

        /* Right Column / Login Form Card */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 32px 28px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
            position: relative;
        }
        @media (min-width: 576px) {
            .form-card { padding: 36px 32px; }
        }

        /* Mobile Brand Display */
        .mobile-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 24px;
        }
        @media (min-width: 992px) {
            .mobile-brand { display: none; }
        }

        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .badge-welcome {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--primary);
        }
        .theme-btn {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: var(--pill-bg);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        .theme-btn:hover { color: var(--text-main); transform: scale(1.05); }

        .card-heading {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .card-subtext {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            height: 44px;
            padding: 10px 14px 10px 40px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 13.5px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-bg);
        }
        .toggle-pwd {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-bottom: 22px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .checkbox-label input {
            accent-color: var(--primary);
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--primary);
            color: #090d16;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(56, 189, 248, 0.25);
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-submit:active { transform: scale(0.98); }

        /* Alert Banners */
        .alert-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: var(--danger-bg);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: var(--danger);
        }
        .alert-warning {
            background: var(--warning-bg);
            border: 1px solid rgba(251, 191, 36, 0.3);
            color: var(--warning);
        }

        .info-pill {
            background: var(--pill-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 22px;
            font-size: 12px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .info-row span:first-child { color: var(--text-muted); }
        .info-row span:last-child { color: var(--text-main); font-weight: 600; font-family: monospace; }

        .demo-tip {
            margin-top: 18px;
            padding: 10px 14px;
            border-radius: 12px;
            background: var(--pill-bg);
            border: 1px solid var(--card-border);
            font-size: 11.5px;
            color: var(--text-muted);
            text-align: center;
        }
        .demo-tip strong { color: var(--text-main); }
    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="layout-grid">
        <!-- Left Column (Desktop Hero - Exactly like Admin Tenant) -->
        <div class="brand-panel">
            <div class="brand-header">
                <img src="assets/logo.png" alt="Logo" class="brand-logo" onerror="this.src='/images/logo.png'">
                <div>
                    <div class="brand-title"><?= htmlspecialchars($bname) ?></div>
                    <div class="brand-sub">Pembukuan &amp; Kasir PWA</div>
                </div>
            </div>

            <h1 class="hero-title">
                Kelola pembukuan usaha Anda <span>dari satu tempat</span>
            </h1>
            <p class="hero-desc">
                Catatan kas, invoice digital, laporan laba rugi, dan transaksi harian — semua terintegrasi, rapi, dan aman.
            </p>

            <div class="feature-grid">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Login Aman</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-cloud-check"></i>
                    <span>Auto-Sync</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-phone"></i>
                    <span>Mobile-First</span>
                </div>
            </div>
        </div>

        <!-- Right Column (Form / Status Card) -->
        <div class="form-card">
            <!-- Mobile Brand Logo -->
            <div class="mobile-brand">
                <img src="assets/logo.png" alt="Logo" class="brand-logo" style="margin-bottom:8px;" onerror="this.src='/images/logo.png'">
                <div class="brand-title"><?= htmlspecialchars($bname) ?></div>
                <div class="brand-sub">Pembukuan &amp; Kasir PWA</div>
            </div>

            <div class="top-row">
                <p class="badge-welcome">
                    <i class="bi bi-stars"></i>
                    <?= $isInactive ? 'Status Layanan' : ($isExpired ? 'Peringatan Masa Aktif' : 'Selamat Datang') ?>
                </p>
                <button type="button" class="theme-btn" onclick="toggleTheme()" title="Ganti Mode Tema">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>
            </div>

            <?php if ($isInactive): ?>
                <!-- STATUS NONAKTIF (Mirip Suspended Tenant) -->
                <h1 class="card-heading">Layanan Dinonaktifkan</h1>
                <p class="card-subtext">Akses aplikasi ini sedang dinonaktifkan oleh administrator.</p>

                <div class="alert-box alert-danger">
                    <i class="bi bi-slash-circle" style="font-size:18px;"></i>
                    <span>Aplikasi <strong><?= htmlspecialchars($bname) ?></strong> saat ini tidak dapat digunakan.</span>
                </div>

                <div class="info-pill">
                    <div class="info-row">
                        <span>Subdomain:</span>
                        <span><?= htmlspecialchars($subdomain) ?></span>
                    </div>
                    <div class="info-row">
                        <span>Status Lisensi:</span>
                        <span style="color:var(--danger);">DINONAKTIFKAN</span>
                    </div>
                </div>

                <a href="https://dgtlnetsolution.com" class="btn-submit" style="background:var(--danger);color:#fff;" target="_blank">
                    <i class="bi bi-whatsapp"></i> Hubungi Administrator
                </a>

            <?php elseif ($isExpired): ?>
                <!-- STATUS EXPIRED -->
                <h1 class="card-heading">Masa Aktif Berakhir</h1>
                <p class="card-subtext">Masa berlangganan aplikasi kas ini telah habis.</p>

                <div class="alert-box alert-warning">
                    <i class="bi bi-exclamation-triangle" style="font-size:18px;"></i>
                    <span>Berakhir pada <strong><?= htmlspecialchars(BOOKKEEPING_EXPIRY) ?></strong>. Silakan perpanjang.</span>
                </div>

                <div class="info-pill">
                    <div class="info-row">
                        <span>Subdomain:</span>
                        <span><?= htmlspecialchars($subdomain) ?></span>
                    </div>
                    <div class="info-row">
                        <span>Kedaluwarsa:</span>
                        <span style="color:var(--warning);"><?= htmlspecialchars(BOOKKEEPING_EXPIRY) ?></span>
                    </div>
                </div>

                <a href="https://dgtlnetsolution.com" class="btn-submit" target="_blank">
                    <i class="bi bi-lightning-charge"></i> Perpanjang Langganan
                </a>

            <?php else: ?>
                <!-- REGULAR LOGIN FORM (Like Admin Tenant Login) -->
                <h1 class="card-heading">Masuk</h1>
                <p class="card-subtext">Masuk ke panel pembukuan dan kasir Anda</p>

                <?php if ($error): ?>
                    <div class="alert-box alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label class="form-label" for="username">Username / Admin</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? 'nodera') ?>" required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required autocomplete="current-password">
                            <button type="button" class="toggle-pwd" onclick="togglePassword()" title="Lihat Password">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="checkbox-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1" checked>
                            <span>Ingat saya selama 30 hari</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>Masuk Sekarang</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="demo-tip">
                    Default Login: <strong>nodera</strong> / Password: <strong>nodera</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Synchronize theme with Nodera App
        (function() {
            var theme = localStorage.getItem('n_theme') || localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            updateThemeIcon(theme);
        })();

        function toggleTheme() {
            var curr = document.documentElement.getAttribute('data-theme');
            var next = curr === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('n_theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        }

        function updateThemeIcon(theme) {
            var icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        }

        function togglePassword() {
            var pwd = document.getElementById('password');
            var eye = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                eye.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                eye.className = 'bi bi-eye';
            }
        }
    </script>
</body>
</html>
