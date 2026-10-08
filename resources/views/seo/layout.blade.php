<!DOCTYPE html>
<html lang="id" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NODERA — Software Billing ISP & RT/RW Net')</title>
    <meta name="description" content="@yield('meta_description', 'Software billing ISP dan RT/RW Net untuk mengelola pelanggan, tagihan, MikroTik, OLT, pembayaran QRIS, monitoring jaringan, dan operasional ISP dalam satu platform.')">
    <meta name="keywords" content="@yield('meta_keywords', 'software billing isp, billing rt rw net, aplikasi billing isp, billing mikrotik, monitoring mikrotik, nms olt, gis fiber optic, mikhmon online, nodera billing')">
    <meta name="author" content="NODERA - DGTL Net Solution">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="google-site-verification" content="K_Kw8NU6vJyUuvSlNRhk9MRleaHWxp-_ds1EMoBf0Gs">
    
    @php
        $canonicalHost = preg_replace('/^www\./i', '', request()->getHost());
        $path = request()->getPathInfo() === '/' ? '' : rtrim(request()->getPathInfo(), '/');
        $canonicalUrl = 'https://' . $canonicalHost . ($path ? $path : '');
    @endphp
    <link rel="canonical" href="@yield('canonical', $canonicalUrl)">
    <link rel="shortcut icon" href="/favicon.ico?v=37">
    <link rel="icon" type="image/png" href="/favicon.png?v=37">

    <!-- Open Graph / Social Media Meta -->
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="id_ID">
    <meta property="og:url" content="@yield('canonical', $canonicalUrl)">
    <meta property="og:title" content="@yield('title', 'NODERA — Software Billing ISP & RT/RW Net')">
    <meta property="og:description" content="@yield('meta_description', 'Software billing ISP dan RT/RW Net untuk mengelola pelanggan, tagihan, MikroTik, OLT, pembayaran QRIS, monitoring jaringan, dan operasional ISP dalam satu platform.')">
    <meta property="og:image" content="{{ asset('images/logo.png?v=36') }}">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:site_name" content="NODERA Billing">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'NODERA — Software Billing ISP & RT/RW Net')">
    <meta name="twitter:description" content="@yield('meta_description', 'Software billing ISP dan RT/RW Net untuk mengelola pelanggan, tagihan, MikroTik, OLT, pembayaran QRIS, monitoring jaringan, dan operasional ISP dalam satu platform.')">
    <meta name="twitter:image" content="{{ asset('images/logo.png?v=36') }}">

    <!-- Structured Data Schema.org -->
    @yield('schema')

    <!-- Fonts & CSS -->
    <link href="/titan/assets/lib/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Condensed:400,700" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
    <link href="/titan/assets/lib/components-font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="/titan/assets/lib/et-line-font/et-line-font.css" rel="stylesheet">
    <link href="/titan/assets/css/style.css" rel="stylesheet">
    <link id="color-scheme" href="/titan/assets/css/colors/default.css" rel="stylesheet">

    <style>
        body, html {
            background-color: #070B11 !important;
            color: #94A3B8;
            font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow-x: hidden !important;
        }
        .navbar-custom {
            background: #0B111E !important;
            border-bottom: 2px solid rgba(0, 229, 255, 0.25) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            padding: 12px 0;
        }
        .navbar-custom .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #FFF !important;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 0.5px;
        }
        .navbar-custom .navbar-nav > li > a {
            color: #CBD5E1 !important;
            font-weight: 600;
            font-size: 13px;
            transition: color 0.2s;
        }
        .navbar-custom .navbar-nav > li > a:hover,
        .navbar-custom .navbar-nav > li.active > a {
            color: #00E5FF !important;
        }
        .dropdown-menu {
            background: #090E14 !important;
            border: 1px solid rgba(0, 229, 255, 0.2) !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }
        .dropdown-menu > li > a {
            color: #CBD5E1 !important;
            padding: 8px 16px;
            font-size: 13px;
        }
        .dropdown-menu > li > a:hover {
            background: rgba(0, 229, 255, 0.1) !important;
            color: #00E5FF !important;
        }
        .btn-cyan {
            background: linear-gradient(135deg, #00E5FF 0%, #0088FF 100%) !important;
            color: #070B11 !important;
            font-weight: 700;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 229, 255, 0.3);
            transition: all 0.2s;
        }
        .btn-cyan:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 229, 255, 0.5);
            color: #000 !important;
        }
        .seo-hero {
            padding: 140px 0 60px;
            background: radial-gradient(circle at 50% 20%, rgba(0, 229, 255, 0.08) 0%, rgba(7, 11, 17, 1) 70%);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }
        .seo-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            background: rgba(0, 229, 255, 0.1);
            border: 1px solid rgba(0, 229, 255, 0.3);
            color: #00E5FF;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .seo-h1 {
            color: #FFFFFF;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }
        .seo-subtitle {
            color: #94A3B8;
            font-size: 16px;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto 30px;
        }
        .breadcrumb-container {
            background: #090E14;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 12px;
        }
        .breadcrumb-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .breadcrumb-list a {
            color: #94A3B8;
            text-decoration: none;
        }
        .breadcrumb-list a:hover {
            color: #00E5FF;
        }
        .breadcrumb-list span {
            color: #475569;
        }
        .breadcrumb-list .active {
            color: #CBD5E1;
            font-weight: 600;
        }
        .feature-card {
            background: #0B111E;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 28px 22px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
            height: 100%;
        }
        .feature-card:hover {
            border-color: rgba(0, 229, 255, 0.4);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
        }
        .feature-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            background: rgba(0, 229, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #00E5FF;
            font-size: 22px;
            margin-bottom: 18px;
        }
        .feature-title {
            color: #FFFFFF;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .feature-desc {
            color: #94A3B8;
            font-size: 13.5px;
            line-height: 1.6;
            margin-bottom: 0;
        }
        .seo-content-section {
            padding: 70px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .seo-content-section h2 {
            color: #FFFFFF;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .seo-content-section h3 {
            color: #E2E8F0;
            font-size: 20px;
            font-weight: 600;
            margin-top: 25px;
            margin-bottom: 12px;
        }
        .seo-content-section p {
            color: #94A3B8;
            font-size: 15px;
            line-height: 1.75;
            margin-bottom: 16px;
        }
        .seo-content-section ul, .seo-content-section ol {
            color: #CBD5E1;
            font-size: 14.5px;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .seo-content-section a {
            color: #00E5FF;
            text-decoration: underline;
        }
        .seo-content-section a:hover {
            color: #38BDF8;
        }
        .callout-box {
            background: rgba(0, 229, 255, 0.05);
            border-left: 4px solid #00E5FF;
            border-radius: 0 8px 8px 0;
            padding: 20px 24px;
            margin: 30px 0;
        }
        .callout-box h4 {
            color: #00E5FF;
            font-size: 16px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 8px;
        }
        .callout-box p {
            color: #E2E8F0;
            margin-bottom: 0;
            font-size: 14px;
        }
        pre {
            background: #090E14 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px;
            padding: 16px;
            color: #38BDF8;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 20px 0;
        }
        .cta-banner {
            background: linear-gradient(135deg, #091322 0%, #032541 100%);
            border: 1px solid rgba(0, 229, 255, 0.3);
            border-radius: 16px;
            padding: 50px 30px;
            text-align: center;
            margin: 60px 0;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        .cta-banner h3 {
            color: #FFF;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cta-banner p {
            color: #CBD5E1;
            font-size: 15px;
            max-width: 650px;
            margin: 0 auto 25px;
        }
        /* Footer Styling */
        .footer-rich {
            background: #05080E;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 70px 0 30px;
        }
        .footer-col-title {
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        .footer-col-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background: #00E5FF;
        }
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-links li {
            margin-bottom: 10px;
        }
        .footer-links a {
            color: #94A3B8;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .footer-links a:hover {
            color: #00E5FF;
            transform: translateX(3px);
        }
        .footer-bottom {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748B;
        }
        @media (max-width: 767px) {
            .seo-h1 { font-size: 24px; }
            .seo-hero { padding: 110px 0 40px; }
            .footer-bottom { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- Navbar Header -->
    <nav class="navbar navbar-custom navbar-fixed-top" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <button class="navbar-toggle" type="button" data-toggle="collapse" data-target="#custom-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="/">
                    <img src="/images/logo.png?v=36" alt="NODERA Logo" width="28" height="28" style="object-fit: contain;">
                    <span>NODERA</span>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="custom-collapse">
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="/">Beranda</a></li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Solusi &amp; Produk <i class="fa fa-angle-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="/software-billing-isp"><i class="fa fa-server text-info"></i> Software Billing ISP</a></li>
                            <li><a href="/billing-rt-rw-net"><i class="fa fa-wifi text-success"></i> Billing RT/RW Net</a></li>
                            <li><a href="/billing-mikrotik"><i class="fa fa-cogs text-warning"></i> Billing MikroTik</a></li>
                            <li><a href="/monitoring-mikrotik"><i class="fa fa-area-chart text-danger"></i> Monitoring MikroTik</a></li>
                            <li><a href="/nms-olt"><i class="fa fa-sitemap text-primary"></i> NMS OLT (GPON/EPON)</a></li>
                            <li><a href="/gis-fiber-optic"><i class="fa fa-map-marker text-info"></i> GIS Fiber Optic (ODP/ODC)</a></li>
                            <li><a href="/mikhmon-online"><i class="fa fa-ticket text-success"></i> Mikhmon Online Cloud</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">MikroTik Tools <i class="fa fa-angle-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="/tools/loadbalance"><i class="fa fa-random text-info"></i> Load Balancing PCC</a></li>
                            <li><a href="/tools/game"><i class="fa fa-gamepad text-success"></i> Pisah Traffic Game</a></li>
                            <li><a href="/tools/stream"><i class="fa fa-youtube-play text-danger"></i> Pisah Traffic Video</a></li>
                            <li><a href="/tools/speedtest"><i class="fa fa-flash text-warning"></i> Speedtest Bypass</a></li>
                            <li><a href="/tools/security"><i class="fa fa-shield text-info"></i> Firewall Hardening</a></li>
                            <li><a href="/tools/hotspot-pppoe"><i class="fa fa-wifi text-primary"></i> Hotspot &amp; PPPoE</a></li>
                        </ul>
                    </li>
                    <li class="{{ request()->is('blog*') ? 'active' : '' }}"><a href="/blog">Blog &amp; Panduan</a></li>
                    <li class="{{ request()->is('harga*') ? 'active' : '' }}"><a href="/harga">Harga</a></li>
                    <li><a href="/docs">Docs</a></li>
                    <li style="margin-left: 10px;">
                        <a href="/register" class="btn btn-cyan btn-circle" style="padding: 7px 18px; color: #000 !important; font-weight: 800; font-size: 11px; margin-top: 8px;">
                            <i class="fa fa-rocket"></i> DAFTAR SEKARANG
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Yield -->
    <main>
        @yield('content')
    </main>

    <!-- Rich Internal Linking Footer (P1 SEO) -->
    <footer class="footer-rich">
        <div class="container">
            <div class="row">
                <!-- Col 1: Brand & About -->
                <div class="col-md-3 col-sm-6" style="margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <img src="/images/logo.png?v=36" alt="NODERA Logo" width="32" height="32">
                        <span style="color: #FFF; font-weight: 800; font-size: 18px;">NODERA</span>
                    </div>
                    <p style="font-size: 13px; line-height: 1.6; color: #94A3B8; margin-bottom: 20px;">
                        Platform software billing ISP dan RT/RW Net terpadu di Indonesia. Otomasi MikroTik, NMS OLT, peta GIS fiber optic, WhatsApp gateway, dan kasir thermal.
                    </p>
                    <div style="display: flex; gap: 12px;">
                        <a href="https://wa.me/{{ !empty($company['phone_wa']) ? $company['phone_wa'] : '6285155173547' }}" target="_blank" class="btn btn-circle" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.1); color: #25D366; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;"><i class="fa fa-whatsapp"></i></a>
                        <a href="https://dgtlnetsolution.com" target="_blank" class="btn btn-circle" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.1); color: #00E5FF; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;"><i class="fa fa-globe"></i></a>
                    </div>
                </div>

                <!-- Col 2: Solusi & Produk -->
                <div class="col-md-3 col-sm-6" style="margin-bottom: 30px;">
                    <h5 class="footer-col-title">Solusi &amp; Produk</h5>
                    <ul class="footer-links">
                        <li><a href="/software-billing-isp">Software Billing ISP</a></li>
                        <li><a href="/billing-rt-rw-net">Billing RT/RW Net</a></li>
                        <li><a href="/billing-mikrotik">Billing MikroTik</a></li>
                        <li><a href="/monitoring-mikrotik">Monitoring MikroTik</a></li>
                        <li><a href="/nms-olt">NMS OLT GPON/EPON</a></li>
                        <li><a href="/gis-fiber-optic">GIS Fiber Optic (ODP/ODC)</a></li>
                        <li><a href="/mikhmon-online">Mikhmon Online Cloud</a></li>
                        <li><a href="/harga">Paket &amp; Harga Lisensi</a></li>
                    </ul>
                </div>

                <!-- Col 3: Generator MikroTik Gratis -->
                <div class="col-md-3 col-sm-6" style="margin-bottom: 30px;">
                    <h5 class="footer-col-title">MikroTik Tools</h5>
                    <ul class="footer-links">
                        <li><a href="/tools/loadbalance">Load Balancing PCC 2-6 ISP</a></li>
                        <li><a href="/tools/game">Pisah Traffic Game Anti-Lag</a></li>
                        <li><a href="/tools/stream">Pisah Traffic Video &amp; CDN</a></li>
                        <li><a href="/tools/speedtest">Bypass Speedtest Ookla</a></li>
                        <li><a href="/tools/security">Firewall Security Hardening</a></li>
                        <li><a href="/tools/hotspot-pppoe">Hotspot &amp; PPPoE Template</a></li>
                        <li><a href="/tools/port-forward">DST-NAT Port Forwarding</a></li>
                        <li><a href="/tools/burst-qos">Burst QoS &amp; Simple Queue</a></li>
                    </ul>
                </div>

                <!-- Col 4: Panduan & Artikel Populer -->
                <div class="col-md-3 col-sm-6" style="margin-bottom: 30px;">
                    <h5 class="footer-col-title">Panduan &amp; Blog</h5>
                    <ul class="footer-links">
                        <li><a href="/blog/cara-membuat-billing-rt-rw-net-mikrotik">Cara Membuat Billing RT/RW Net</a></li>
                        <li><a href="/blog/apa-itu-software-billing-isp">Apa Itu Software Billing ISP?</a></li>
                        <li><a href="/blog/cara-membuat-pppoe-server-mikrotik">Cara Setting PPPoE Server</a></li>
                        <li><a href="/blog/cara-auto-isolir-pelanggan-mikrotik">Cara Auto Isolir MikroTik</a></li>
                        <li><a href="/blog/cara-monitoring-onu-pada-olt">Cara Monitoring ONU OLT</a></li>
                        <li><a href="/blog/cara-mengelola-jaringan-ftth">Manajemen Jaringan FTTH</a></li>
                        <li><a href="/docs">Buku Panduan Teknis (Handbook)</a></li>
                        <li><a href="/blog">Lihat Semua 20 Panduan <i class="fa fa-arrow-right" style="font-size: 10px;"></i></a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom Legal -->
            <div class="footer-bottom">
                <div>
                    &copy; {{ date('Y') }} <strong>NODERA INDONESIA</strong> (DGTL Net Solution). Hak Cipta Dilindungi.
                </div>
                <div style="display: flex; gap: 15px;">
                    <a href="/terms" style="color: #94A3B8; text-decoration: none;">Syarat &amp; Ketentuan</a>
                    <span>•</span>
                    <a href="/privacy" style="color: #94A3B8; text-decoration: none;">Kebijakan Privasi</a>
                    <span>•</span>
                    <a href="/docs" style="color: #94A3B8; text-decoration: none;">Buku Panduan</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="/titan/assets/lib/jquery/dist/jquery.js"></script>
    <script src="/titan/assets/lib/bootstrap/dist/js/bootstrap.min.js"></script>
</body>
</html>
