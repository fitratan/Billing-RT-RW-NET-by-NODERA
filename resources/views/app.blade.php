<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $tId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenantModel = $tId ? \App\Models\Tenant::withoutGlobalScopes()->find($tId) : null;
        $tenantSettingName = $tId ? \App\Models\Setting::withoutGlobalScopes()->where('tenant_id', $tId)->where('key', 'COMPANY_NAME')->value('value') : null;
        $tenantSettingLogo = $tId ? \App\Models\Setting::withoutGlobalScopes()->where('tenant_id', $tId)->where('key', 'COMPANY_LOGO')->value('value') : null;
        
        $mikhmonAutoLogo = null;
        if (!$tenantModel?->logo && !$tenantSettingLogo && $tenantModel) {
            $sCandidates = array_filter([$tenantModel->slug ?? null, $tenantModel->subdomain ?? null, $tenantModel->username ?? null]);
            foreach ($sCandidates as $sCand) {
                $mkLogoPath = public_path("hotspot-{$sCand}/img/logo.png");
                if (file_exists($mkLogoPath) && filesize($mkLogoPath) > 0) {
                    $mikhmonAutoLogo = "/hotspot-{$sCand}/img/logo.png";
                    break;
                }
            }
        }

        $brandTitleName = $tenantSettingName ?: ($tenantModel?->company_name ?: ($tenantModel?->name ?: config('app.name', 'NODERA')));
        $brandFaviconUrl = \App\Models\Setting::resolveLogoUrl($tenantModel?->logo ?: ($tenantSettingLogo ?: $mikhmonAutoLogo), asset('favicon.png?v=37'));
    @endphp
    @php
        $canonicalHost = preg_replace('/^www\./i', '', request()->getHost());
        $cleanPath = request()->getPathInfo() === '/' ? '' : rtrim(request()->getPathInfo(), '/');
        $canonicalUrl = 'https://' . $canonicalHost . $cleanPath;

        $isPublicLanding = request()->is('/', 'register', 'terms', 'privacy', 'docs', 'tools', 'tools/*', 'shop', 'shop/*');
        $isInternalOrAuth = request()->is('admin*', 'superadmin*', 'teknisi*', 'kolektor*', 'cashier*', 'portal*', 'pelanggan*', 'vpn*', 'topup*', 'login*', 'dashboard*', 'receipt*', 'invoice*', 'register/sukses*', 'register/status*', 'register/cancel*');

        $pageTitle = $brandTitleName . ' - Software Billing ISP & RT/RW Net';
        $pageDesc = 'Software billing ISP dan RT/RW Net untuk mengelola pelanggan, tagihan, MikroTik, OLT, pembayaran QRIS, monitoring jaringan, dan operasional ISP dalam satu platform.';
        if (request()->is('tools/loadbalance*')) {
            $pageTitle = 'Generator Load Balancing PCC MikroTik | Tools NODERA';
            $pageDesc = 'Script generator Load Balance PCC MikroTik multi WAN otomatis dengan failover, port forwarding, dan bypass routing.';
        } elseif (request()->is('tools/hotspot-pppoe*')) {
            $pageTitle = 'Script Generator Hotspot & PPPoE MikroTik | Tools NODERA';
            $pageDesc = 'Generator script otomatis konfigurasi Hotspot Server dan PPPoE Server di MikroTik RouterOS v6 & v7.';
        } elseif (request()->is('tools/game*')) {
            $pageTitle = 'Generator Pisah Traffic Game Online MikroTik | Tools NODERA';
            $pageDesc = 'Script pemisah traffic game online (Mobile Legends, PUBG, Free Fire, Valorant) di MikroTik untuk ping stabil.';
        } elseif (request()->is('tools/stream*')) {
            $pageTitle = 'Generator Pisah Traffic Video Streaming MikroTik | Tools NODERA';
            $pageDesc = 'Script pemisah bandwidth YouTube, TikTok, Netflix, Facebook Video di MikroTik agar browsing tetap lancar.';
        } elseif (request()->is('tools/speedtest*')) {
            $pageTitle = 'Generator Bypass Speedtest MikroTik | Tools NODERA';
            $pageDesc = 'Konfigurasi bypass limit bandwidth untuk Speedtest Ookla di MikroTik secara akurat.';
        } elseif (request()->is('tools/port-forward*')) {
            $pageTitle = 'Generator Port Forwarding & NAT MikroTik | Tools NODERA';
            $pageDesc = 'Generator script NAT Port Forwarding (DST-NAT) MikroTik untuk akses CCTV, Web Server, dan IP Camera dari luar.';
        } elseif (request()->is('tools/burst-qos*')) {
            $pageTitle = 'Kalkulator Burst Limit & QoS Queue MikroTik | Tools NODERA';
            $pageDesc = 'Kalkulator burst threshold, burst time, dan limit-at Simple Queue MikroTik untuk kecepatan internet optimal.';
        } elseif (request()->is('tools/security*')) {
            $pageTitle = 'Generator Firewall Security & Anti DDoS MikroTik | Tools NODERA';
            $pageDesc = 'Script proteksi keamanan firewall RouterOS dari serangan brute force SSH, Winbox, DoS, dan port scanner.';
        } elseif (request()->is('tools*')) {
            $pageTitle = 'Koleksi MikroTik Tools & Script Generator Gratis | NODERA';
            $pageDesc = 'Koleksi generator script RouterOS gratis: Load Balancing PCC, Pisah Traffic Game & Streaming, Port Forwarding, dan Firewall Security.';
        } elseif (request()->is('register*') || request()->is('daftar-isp*')) {
            $pageTitle = 'Daftar Akun Software Billing ISP NODERA';
            $pageDesc = 'Daftar sekarang dan kelola operasional ISP, RT/RW Net, hotspot voucher Mikhmon, dan auto isolir MikroTik secara otomatis.';
        }
    @endphp

    @if($isInternalOrAuth)
    <meta name="robots" content="noindex, nofollow, noarchive">
    @else
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif

    <title inertia>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDesc }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDesc }}">
    <meta property="og:image" content="{{ $brandFaviconUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDesc }}">
    <meta name="twitter:image" content="{{ $brandFaviconUrl }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico?v=37') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/icon-32x32.png?v=37') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon-16x16.png?v=37') }}">
    <link rel="icon" type="image/png" href="{{ $brandFaviconUrl }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png?v=37') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    @php
        $host = request()->getHost();
        $reqPath = request()->getPathInfo();
        $isGatewayDomain = str_starts_with($host, 'gateway.') || str_contains($host, 'gateway.') || str_starts_with($reqPath, '/noderapay');
        $isWaDomain = str_starts_with($host, 'wa.') || str_contains($host, 'wa.') || str_starts_with($reqPath, '/wagateway');
        $pwaRole = 'admin';
        $pwaTitle = 'NODERA ADMIN';
        if (str_starts_with($reqPath, '/superadmin')) {
            $pwaRole = 'superadmin';
            $pwaTitle = 'NODERA SUPERADMIN';
        } elseif ($isGatewayDomain) {
            $pwaRole = 'gateway';
            $pwaTitle = 'NODERA PAY';
        } elseif ($isWaDomain) {
            $pwaRole = 'wagateway';
            $pwaTitle = 'NODERA WA';
        } elseif (str_contains($reqPath, '/kolektor')) {
            $pwaRole = 'kolektor';
            $pwaTitle = 'NODERA KOLEKTOR';
        } elseif (str_contains($reqPath, '/teknisi')) {
            $pwaRole = 'teknisi';
            $pwaTitle = 'NODERA TEKNISI';
        } elseif (str_contains($reqPath, '/portal') || str_contains($reqPath, '/pelanggan')) {
            $pwaRole = 'customer';
            $pwaTitle = 'NODERA CUSTOMER';
        } elseif (str_contains($reqPath, '/cashier')) {
            $pwaRole = 'cashier';
            $pwaTitle = 'NODERA KASIR';
        }
    @endphp
    <meta name="apple-mobile-web-app-title" content="{{ $pwaTitle }}">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json?role={{ $pwaRole }}">
    <script>
        window.__pwaStartTime = Date.now();
        (function () {
            try {
                var savedTheme = localStorage.getItem('theme');
                var isGateway = window.location.hostname.indexOf('gateway.') === 0 || window.location.pathname.indexOf('/noderapay') === 0;
                var theme = savedTheme ? savedTheme : (isGateway ? 'light' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    document.documentElement.style.colorScheme = 'light';
                }
            } catch (e) {}
        })();

        // Handle Vite dynamic chunk / CSS preload errors gracefully (auto recovery after deployment)
        function recoverFromStaleChunks() {
            var key = 'nodera_chunk_reload';
            var lastReload = sessionStorage.getItem(key);
            var now = Date.now();
            if (!lastReload || now - parseInt(lastReload, 10) > 8000) {
                sessionStorage.setItem(key, String(now));
                if ('caches' in window) {
                    caches.keys().then(function(keys) {
                        keys.forEach(function(k) { caches.delete(k); });
                    });
                }
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistrations().then(function(regs) {
                        regs.forEach(function(r) { r.unregister(); });
                    });
                }
                window.location.reload();
            }
        }
        window.addEventListener('vite:preloadError', function (event) {
            event.preventDefault();
            recoverFromStaleChunks();
        });

        // 🚨 Universal Frontend Error Tracking -> Auto-forward unhandled exceptions to Telegram Superadmin
        var __reportedClientErrors = {};
        function __reportClientError(data) {
            try {
                var key = (data.message || '') + (data.source || '');
                if (__reportedClientErrors[key]) return;
                __reportedClientErrors[key] = true;

                var payload = JSON.stringify({
                    level: 'error',
                    message: data.message || 'Uncaught Client Error',
                    stack: data.stack || null,
                    source: data.source || (window.location.pathname || 'app.blade'),
                    url: window.location.href || ''
                });

                if (navigator.sendBeacon) {
                    var blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon('/api/client-logs', blob);
                } else {
                    fetch('/api/client-logs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: payload,
                        keepalive: true
                    }).catch(function () {});
                }
            } catch (e) {}
        }

        window.addEventListener('error', function (event) {
            var msg = (event && event.message) || '';
            if (msg.includes('chunk') || msg.includes('dynamically imported') || msg.includes('Failed to fetch') || msg.includes('undefined for Vpn') || msg.includes('undefined for Admin')) {
                recoverFromStaleChunks();
                return;
            }
            if (!msg || msg.includes('ResizeObserver loop') || msg.includes('Script error.')) {
                return;
            }
            __reportClientError({
                message: msg,
                stack: (event.error && event.error.stack) || ((event.filename || '') + ':' + (event.lineno || '')),
                source: 'window.onerror: ' + window.location.pathname
            });
        });

        window.addEventListener('unhandledrejection', function (event) {
            var reason = event && event.reason;
            var msg = (reason && (reason.message || reason)) || 'Unhandled Promise Rejection';
            if (typeof msg === 'string') {
                if (msg.includes('chunk') || msg.includes('dynamically imported') || msg.includes('Failed to fetch') || msg.includes('ResizeObserver loop')) {
                    return;
                }
                __reportClientError({
                    message: msg,
                    stack: (reason && reason.stack) || null,
                    source: 'unhandledrejection: ' + window.location.pathname
                });
            }
        });

        window.__pwaDeferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            window.__pwaDeferredPrompt = e;
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(reg) {
                    reg.update();
                }).catch(function(e) {
                    console.log('SW registration error:', e);
                });
            });
        }
        if (window.sessionStorage && sessionStorage.getItem('nodera_splash_seen')) {
            document.write('<style>#pwa-splash{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;}</style>');
        }
    </script>
    <meta name="theme-color" content="#090B0E">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Boldonse&family=Inter+Tight:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap">
    <style>
        /* 100% Native App Feel — Hide Browser Scrollbars Globally */
        *, *::before, *::after, html, body, div, section, main, article, aside, nav, form, ul, ol, dialog {
            scrollbar-width: none !important;
            scrollbar-color: transparent transparent !important;
            -ms-overflow-style: none !important;
        }
        *::-webkit-scrollbar, ::-webkit-scrollbar {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
            background: transparent !important;
            -webkit-appearance: none !important;
        }
        *::-webkit-scrollbar-track, ::-webkit-scrollbar-track,
        *::-webkit-scrollbar-thumb, ::-webkit-scrollbar-thumb,
        *::-webkit-scrollbar-corner, ::-webkit-scrollbar-corner {
            display: none !important;
            background: transparent !important;
            -webkit-appearance: none !important;
        }
    </style>
    
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased bg-background text-foreground">
    <!-- Ultra-Clean Borderless NODERA PWA Splash Screen -->
    <div id="pwa-splash" style="position:fixed;inset:0;z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:radial-gradient(circle at 50% 45%, #08111A 0%, #04080D 60%, #020406 100%);transition:opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);user-select:none;touch-action:none;overflow:hidden;">
        <!-- Ambient Glowing Aura -->
        <div style="position:absolute;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle, rgba(0, 194, 255, 0.25) 0%, rgba(59, 130, 246, 0.12) 40%, transparent 70%);filter:blur(60px);animation:nodera-pulse 3.5s infinite ease-in-out;pointer-events:none;"></div>

        <!-- Center Content (Seamless, No Box, No Card) -->
        <div style="position:relative;z-index:10;display:flex;flex-direction:column;align-items:center;animation:nodera-fade-in 0.9s cubic-bezier(0.16, 1, 0.3, 1);">
            
            <!-- Floating Logo -->
            <div style="position:relative;display:flex;align-items:center;justify-content:center;animation:nodera-float 3.5s infinite ease-in-out;">
                <img src="/images/logo-color.png?v=37" alt="NODERA" width="76" height="76" style="width:76px!important;height:76px!important;max-width:76px!important;max-height:76px!important;object-fit:contain;border-radius:20px;filter:drop-shadow(0 16px 30px rgba(0, 194, 255, 0.45));">
            </div>

            <!-- Brand Typography -->
            <h1 style="margin-top:24px;font-family:'Plus Jakarta Sans','Poppins',sans-serif;font-size:28px;font-weight:900;color:#ffffff;letter-spacing:3px;line-height:1;margin-bottom:0;text-align:center;">NODERA</h1>
            <p style="margin-top:8px;margin-bottom:0;font-family:'Plus Jakarta Sans','Poppins',sans-serif;font-size:11.5px;font-weight:600;color:#00C2FF;letter-spacing:2px;text-transform:uppercase;text-align:center;opacity:0.95;">Billing &amp; Network Management System</p>

            <!-- Sleek Minimalist Loading Bar -->
            <div style="margin-top:32px;position:relative;width:160px;height:4px;border-radius:999px;background:rgba(255,255,255,0.08);overflow:hidden;">
                <div style="position:absolute;top:0;left:0;height:100%;width:40%;border-radius:999px;background:linear-gradient(90deg, #00C2FF, #3B82F6);animation:nodera-shimmer 1.8s infinite ease-in-out;box-shadow:0 0 14px rgba(0,194,255,0.85);"></div>
            </div>
        </div>

        <!-- Footer Tag -->
        <div style="position:absolute;bottom:32px;font-family:'Plus Jakarta Sans','Poppins',sans-serif;font-size:11px;font-weight:600;color:rgba(148, 163, 184, 0.5);letter-spacing:1px;text-transform:uppercase;">
            Sistem Manajemen Jaringan ISP • Indonesia
        </div>
    </div>
    <script>
        (function() {
            function removeSplash() {
                var s = document.getElementById('pwa-splash');
                if (s && s.style.opacity !== '0') {
                    s.style.opacity = '0';
                    s.style.transform = 'scale(1.02)';
                    s.style.pointerEvents = 'none';
                    try { sessionStorage.setItem('nodera_splash_seen', '1'); } catch(e){}
                    setTimeout(function() { if (s && s.parentNode) s.remove(); }, 350);
                }
            }
            if (document.readyState === 'complete') {
                setTimeout(removeSplash, 300);
            } else {
                window.addEventListener('load', function() { setTimeout(removeSplash, 300); });
            }
            setTimeout(removeSplash, 1200);
        })();
    </script>
    <style>
        @keyframes nodera-pulse { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(1.25); opacity: 0.6; } }
        @keyframes nodera-fade-in { from { opacity: 0; transform: translateY(14px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes nodera-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes nodera-shimmer { 0% { left: -40%; width: 35%; } 50% { left: 40%; width: 50%; } 100% { left: 100%; width: 35%; } }
    </style>
    @inertia
</body>
</html>
