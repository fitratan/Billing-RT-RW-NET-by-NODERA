<!DOCTYPE html>
<html lang="id" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company['landing_title'] ?? $company['name'] ?? 'OFFICIAL STORE' }} | {{ $company['landing_tagline'] ?? 'Pusat Berbagai Kebutuhan Anda' }}</title>
    <link rel="icon" type="image/png" href="{{ $company['logo'] ?? '/favicon.png?v=37' }}">
    
    <!-- Stylesheets from TITAN Template -->
    <link href="/titan/assets/lib/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Condensed:400,700,800" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
    <link href="/titan/assets/lib/components-font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="/titan/assets/css/style.css" rel="stylesheet">

    <style>
      /* ==================== GLOBAL DARK THEME & ANTI-OVERFLOW ==================== */
      *, *:before, *:after {
        box-sizing: border-box;
      }
      html, body {
        background-color: #070B11 !important;
        color: #94A3B8;
        font-family: 'Open Sans', sans-serif;
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
      }
      .main {
        position: relative;
        background-color: #070B11 !important;
        color: #94A3B8;
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      section.module, section.module-small, section.module-medium, section.module-extra-small {
        background-color: #070B11 !important;
        color: #94A3B8;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
      }
      .container {
        width: 100% !important;
        max-width: 1170px !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        box-sizing: border-box !important;
      }
      @media (max-width: 767px) {
        .container {
          padding-left: 10px !important;
          padding-right: 10px !important;
        }
      }
      
      /* Navigation Bar - Solid Dark & Fixed */
      .navbar-custom {
        background: #0B111E !important;
        border-bottom: 2px solid rgba(0, 229, 255, 0.25) !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        transition: all 0.3s ease;
        padding: 0 !important;
        min-height: 56px !important;
        height: auto !important;
      }
      .navbar-custom.navbar-scrolled {
        border-bottom: 2px solid #00E5FF !important;
        box-shadow: 0 4px 25px rgba(0, 229, 255, 0.45) !important;
      }
      
      .navbar-custom .container {
        position: relative !important;
        width: 100% !important;
        max-width: 1170px !important;
        margin: 0 auto !important;
        padding: 0 15px !important;
      }
      .navbar-custom:before,
      .navbar-custom:after,
      .navbar-custom .container:before,
      .navbar-custom .container:after,
      .navbar-custom .navbar-header:before,
      .navbar-custom .navbar-header:after,
      .navbar-custom .navbar-collapse:before,
      .navbar-custom .navbar-collapse:after,
      .navbar-custom .nav:before,
      .navbar-custom .nav:after {
        display: none !important;
        content: none !important;
        width: 0 !important;
        height: 0 !important;
      }

      /* Header Container */
      .navbar-custom .navbar-header {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        height: 56px !important;
        margin: 0 !important;
        padding: 0 !important;
        float: none !important;
        width: 100% !important;
      }

      /* Desktop: Brand on left, Navigation Links Centered */
      @media (min-width: 768px) {
        .navbar-custom .container {
          display: flex !important;
          flex-direction: row !important;
          align-items: center !important;
          justify-content: flex-start !important;
          min-height: 56px !important;
        }
        .navbar-custom .navbar-header {
          display: inline-flex !important;
          align-items: center !important;
          justify-content: flex-start !important;
          text-align: left !important;
          margin: 0 !important;
          padding: 0 !important;
          float: left !important;
          z-index: 20 !important;
          height: 56px !important;
          width: auto !important;
        }
        .navbar-custom .navbar-toggle {
          display: none !important;
        }
        .navbar-custom .navbar-collapse {
          position: absolute !important;
          left: 50% !important;
          top: 50% !important;
          transform: translate(-50%, -50%) !important;
          display: block !important;
          width: auto !important;
          padding: 0 !important;
          margin: 0 !important;
          border: none !important;
          box-shadow: none !important;
          background: transparent !important;
          z-index: 10 !important;
        }
        .navbar-custom .navbar-nav-center {
          display: flex !important;
          flex-direction: row !important;
          align-items: center !important;
          justify-content: center !important;
          gap: 16px !important;
          margin: 0 !important;
          padding: 0 !important;
          float: none !important;
        }
      }

      /* Mobile: Brand on Left, Hamburger on Right */
      @media (max-width: 767px) {
        .navbar-custom .container {
          display: block !important;
          padding-left: 10px !important;
          padding-right: 10px !important;
        }
        .navbar-custom .navbar-header {
          display: flex !important;
          flex-direction: row !important;
          align-items: center !important;
          justify-content: space-between !important;
          width: 100% !important;
          height: 56px !important;
          margin: 0 !important;
          padding: 0 !important;
          float: none !important;
          text-align: left !important;
        }
        .navbar-custom .navbar-toggle {
          display: block !important;
          margin: 0 !important;
          padding: 8px 10px !important;
          float: none !important;
          border: 1px solid rgba(0, 229, 255, 0.4) !important;
          border-radius: 8px !important;
          background: rgba(0, 229, 255, 0.1) !important;
        }
        .navbar-custom .navbar-toggle .icon-bar {
          background-color: #00E5FF !important;
          width: 20px !important;
          height: 2px !important;
        }
        .navbar-custom .navbar-collapse {
          background: #0B111E !important;
          border: 1px solid rgba(255, 255, 255, 0.1) !important;
          border-radius: 12px !important;
          margin: 6px 0 12px 0 !important;
          padding: 12px 14px !important;
          box-shadow: 0 12px 35px rgba(0, 0, 0, 0.8) !important;
          max-height: 80vh !important;
          overflow-y: auto !important;
        }
        .navbar-custom .navbar-nav-center {
          margin: 0 !important;
          padding: 0 !important;
          display: flex !important;
          flex-direction: column !important;
          gap: 6px !important;
        }
      }

      /* Navbar Brand with Logo - Strictly Left Aligned Single-Line */
      .navbar-custom .navbar-brand,
      .navbar-brand {
        display: inline-flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        text-align: left !important;
        padding: 0 !important;
        margin: 0 !important;
        height: 56px !important;
        text-decoration: none !important;
        float: none !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
      }
      .navbar-custom .navbar-brand img,
      .navbar-brand img {
        display: inline-block !important;
        height: 32px !important;
        width: 32px !important;
        margin: 0 8px 0 0 !important;
        vertical-align: middle !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
      }
      .brand-title {
        font-family: 'Roboto Condensed', sans-serif !important;
        font-weight: 800 !important;
        letter-spacing: 1px !important;
        color: #ffffff !important;
        font-size: 17px !important;
        text-transform: uppercase !important;
        text-align: left !important;
        white-space: nowrap !important;
        line-height: 1 !important;
        margin: 0 !important;
        padding: 0 !important;
        display: inline-block !important;
      }
      
      .navbar-custom .nav li > a {
        color: #CBD5E1 !important;
        font-weight: 700;
        font-size: 13px;
        padding: 8px 14px !important;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
      }
      .navbar-custom .nav li.active > a,
      .navbar-custom .nav li > a:hover {
        color: #00E5FF !important;
        background: rgba(0, 229, 255, 0.08);
      }

      .color-cyan { color: #00E5FF; }
      .btn-cyan { background: #00E5FF; border-color: #00E5FF; color: #070B11 !important; font-weight: 700; }
      .btn-cyan:hover { background: #00B4D8; border-color: #00B4D8; color: #fff !important; }
      
      /* ==================== FULLSCREEN ONBOARDING SCREEN WITH SWIPEABLE CAROUSEL ==================== */
      .fullscreen-onboarding-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        z-index: 9999999;
        background: radial-gradient(circle at 50% 25%, #0E182A 0%, #070B11 80%, #03070D 100%);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        padding: 24px 20px 28px 20px;
        transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.45s ease;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
      }
      .fullscreen-onboarding-overlay.hidden-onboarding {
        transform: translateY(-100%);
        opacity: 0;
        pointer-events: none;
      }
      .onboarding-top-bar {
        width: 100%;
        max-width: 540px;
        text-align: center;
      }

      /* Swipeable Carousel Container */
      .onboarding-slider-container {
        position: relative;
        width: 100%;
        max-width: 520px;
        margin: auto 0;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .onboarding-carousel-viewport {
        width: 100%;
        overflow: hidden;
        touch-action: pan-y;
        cursor: grab;
        user-select: none;
      }
      .onboarding-carousel-viewport:active {
        cursor: grabbing;
      }
      .onboarding-carousel-track {
        display: flex;
        transition: transform 0.35s cubic-bezier(0.25, 1, 0.5, 1);
        will-change: transform;
        width: 100%;
      }
      .onboarding-carousel-slide {
        min-width: 100%;
        width: 100%;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px 14px;
        box-sizing: border-box;
        text-align: center;
      }


      .onboarding-icon-box {
        width: 76px;
        height: 76px;
        border-radius: 22px;
        background: rgba(0, 229, 255, 0.12);
        border: 2px solid #00E5FF;
        box-shadow: 0 8px 28px rgba(0, 229, 255, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #00E5FF;
        font-size: 32px;
        margin-bottom: 16px;
      }
      .onboarding-badge {
        font-size: 11px;
        font-weight: 800;
        color: #00E5FF;
        background: rgba(0, 229, 255, 0.12);
        border: 1px solid rgba(0, 229, 255, 0.3);
        padding: 4px 14px;
        border-radius: 20px;
        letter-spacing: 1.5px;
        margin-bottom: 10px;
        text-transform: uppercase;
      }
      .onboarding-title {
        color: #ffffff;
        font-size: 22px;
        font-weight: 800;
        font-family: 'Roboto Condensed', sans-serif;
        margin: 4px 0 8px 0;
        letter-spacing: 0.5px;
      }
      .onboarding-desc {
        color: #94A3B8;
        font-size: 13px;
        line-height: 1.6;
        max-width: 440px;
        margin: 0 auto;
      }
      .onboarding-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 16px;
      }
      .onboarding-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        cursor: pointer;
        transition: all 0.25s ease;
      }
      .onboarding-dot.active {
        width: 24px;
        border-radius: 10px;
        background: #00E5FF;
      }
      .onboarding-bottom-bar {
        width: 100%;
        max-width: 440px;
        margin-top: 10px;
      }
      .btn-get-started {
        width: 100%;
        background: #00E5FF;
        color: #070B11 !important;
        font-size: 15px;
        font-weight: 800;
        padding: 13px 24px;
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 30px rgba(0, 229, 255, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
        transition: all 0.25s ease;
        letter-spacing: 0.5px;
      }
      .btn-get-started:hover {
        background: #00B4D8;
        color: #fff !important;
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0, 229, 255, 0.65);
      }

      @media (max-width: 767px) {
        .fullscreen-onboarding-overlay { padding: 20px 15px 22px 15px; }
        .onboarding-icon-box { width: 60px; height: 60px; font-size: 26px; border-radius: 18px; margin-bottom: 12px; }
        .onboarding-title { font-size: 18px !important; }
        .onboarding-desc { font-size: 12px !important; }
        .btn-get-started { font-size: 14px; padding: 12px 18px; border-radius: 10px; }
      }

      /* ==================== HERO HEADER ==================== */
      .shop-hero-bg {
        position: relative;
        padding-top: 90px;
        padding-bottom: 22px;
        background: #070B11;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      }
      .hero-tagline {
        font-family: 'Roboto Condensed', sans-serif;
        font-size: 11px;
        letter-spacing: 2px;
        color: #00E5FF;
        font-weight: 800;
        text-transform: uppercase;
        margin: 0 0 6px 0;
        display: inline-block;
        background: rgba(0, 229, 255, 0.1);
        padding: 4px 16px;
        border-radius: 20px;
        border: 1px solid rgba(0, 229, 255, 0.25);
      }
      .hero-main-title {
        font-family: 'Roboto Condensed', sans-serif;
        font-size: 24px;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #ffffff;
        margin: 4px 0 6px 0;
      }
      .hero-description {
        font-size: 13px;
        max-width: 640px;
        margin: 0 auto;
        color: #94A3B8;
        line-height: 1.6;
      }

      /* ==================== UNIFIED SINGLE-BUTTON FILTER BAR ==================== */
      .shop-toolbar {
        background: #0B111E;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 8px 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .search-box-wrap {
        position: relative;
        flex: 1;
      }
      .search-input-field {
        background: #070B11 !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        color: #fff !important;
        border-radius: 8px;
        padding: 7px 12px 7px 34px;
        font-size: 13px;
        width: 100%;
        transition: border-color 0.2s;
      }
      .search-input-field:focus {
        border-color: #00E5FF !important;
        outline: none;
      }
      .search-icon-inside {
        position: absolute;
        left: 11px;
        top: 9px;
        color: #64748B;
        font-size: 13px;
      }
      .btn-filter-toggle {
        background: #070B11;
        border: 1px solid rgba(0, 229, 255, 0.3);
        color: #00E5FF !important;
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
      }
      .btn-filter-toggle:hover,
      .btn-filter-toggle.active {
        background: rgba(0, 229, 255, 0.15);
        border-color: #00E5FF;
      }

      /* Active filter indicator chip */
      .active-filter-chips {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 15px;
      }
      .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(0, 229, 255, 0.1);
        border: 1px solid rgba(0, 229, 255, 0.3);
        color: #00E5FF;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
      }
      .filter-chip-remove {
        cursor: pointer;
        opacity: 0.8;
      }
      .filter-chip-remove:hover { opacity: 1; }

      /* ==================== PRODUCT GRID ==================== */
      .product-grid-4 {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 16px !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
        box-sizing: border-box !important;
      }
      .product-grid-4 > div {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      @media (max-width: 1199px) {
        .product-grid-4 {
          grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
          gap: 14px !important;
        }
      }
      @media (max-width: 991px) {
        .product-grid-4 {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          gap: 12px !important;
        }
      }
      @media (max-width: 575px) {
        .product-grid-4 {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          gap: 10px !important;
        }
      }
      @media (max-width: 380px) {
        .product-grid-4 {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          gap: 8px !important;
        }
      }

      /* PRODUCT CARD */
      .product-card {
        background: #0B111E;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        transition: all 0.25s ease;
        position: relative;
        cursor: pointer;
      }
      .product-card:hover {
        transform: translateY(-4px);
        border-color: #00E5FF;
        box-shadow: 0 12px 30px rgba(0, 229, 255, 0.2);
      }
      .product-img-box {
        position: relative;
        width: 100%;
        padding-top: 75%;
        background: #020617;
        overflow: hidden;
        box-sizing: border-box;
      }
      .product-img-box img {
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.35s ease;
      }
      .product-card:hover .product-img-box img {
        transform: scale(1.05);
      }
      .product-badge {
        position: absolute;
        top: 6px;
        left: 6px;
        background: rgba(0, 229, 255, 0.9);
        color: #070B11;
        font-size: 9px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        z-index: 2;
        max-width: calc(100% - 55px);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .product-discount-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        background: #EF4444;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        padding: 2px 5px;
        border-radius: 4px;
        z-index: 2;
      }
      .product-gallery-indicator {
        position: absolute;
        bottom: 6px;
        right: 6px;
        background: rgba(7, 11, 17, 0.85);
        border: 1px solid rgba(0, 229, 255, 0.4);
        color: #00E5FF;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        backdrop-filter: blur(4px);
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 4px;
      }
      .product-card-thumbs {
        display: flex;
        gap: 4px;
        padding: 6px 8px;
        overflow-x: auto;
        overflow-y: hidden;
        background: #070B11;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      .product-card-thumb {
        width: 28px;
        height: 28px;
        border-radius: 4px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.15);
        cursor: pointer;
        transition: all 0.2s ease;
        opacity: 0.65;
        flex-shrink: 0;
      }
      .product-card-thumb:hover,
      .product-card-thumb.active {
        opacity: 1;
        border-color: #00E5FF;
        box-shadow: 0 0 6px rgba(0, 229, 255, 0.4);
        transform: scale(1.06);
      }
      @media (max-width: 767px) {
        .product-card-thumbs {
          padding: 4px 6px;
          gap: 3px;
        }
        .product-card-thumb {
          width: 24px;
          height: 24px;
        }
      }
      
      .qv-img-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        background: #020617;
        margin-bottom: 8px;
      }
      .qv-nav-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.7);
        border: 1px solid rgba(0, 229, 255, 0.5);
        color: #00E5FF;
        font-size: 16px;
        line-height: 1;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 5;
        padding: 0;
      }
      .qv-nav-arrow:hover {
        background: #00E5FF;
        color: #070B11;
      }
      .qv-arrow-prev { left: 6px; }
      .qv-arrow-next { right: 6px; }
      .qv-thumb {
        height: 38px;
        width: 38px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        cursor: pointer;
        transition: all 0.2s ease;
        opacity: 0.65;
        flex-shrink: 0;
      }
      .qv-thumb:hover,
      .qv-thumb.active {
        opacity: 1;
        border-color: #00E5FF;
        box-shadow: 0 0 8px rgba(0, 229, 255, 0.5);
        transform: scale(1.05);
      }
      
      .product-body {
        padding: 12px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: space-between;
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
      }
      .product-cat {
        font-size: 10px;
        color: #00E5FF;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .product-name {
        font-family: 'Roboto Condensed', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #ffffff;
        margin: 0 0 4px 0;
        line-height: 1.25;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
        overflow-wrap: break-word;
      }
      .product-pricing {
        margin-top: auto;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
      }
      .product-pricing-left {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
        flex: 1;
      }
      .product-price-current {
        font-size: 14px;
        font-weight: 800;
        color: #ffffff;
        white-space: nowrap;
      }
      .product-price-original {
        font-size: 10px;
        color: #64748B;
        text-decoration: line-through;
        margin-left: 4px;
        white-space: nowrap;
      }
      
      /* Stock Badge Above Action Buttons */
      .product-stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: rgba(0, 229, 255, 0.08);
        border: 1px solid rgba(0, 229, 255, 0.25);
        color: #00E5FF;
        font-size: 9.5px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 8px;
        width: fit-content;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .product-card-actions {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
      }
      .btn-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        padding: 0;
      }
      .btn-card-icon.btn-card-preview {
        background: rgba(0, 229, 255, 0.12);
        border: 1px solid rgba(0, 229, 255, 0.35);
        color: #00E5FF;
      }
      .btn-card-icon.btn-card-preview:hover {
        background: rgba(0, 229, 255, 0.3);
        color: #ffffff;
        border-color: #00E5FF;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 229, 255, 0.35);
      }
      .btn-card-icon.btn-card-cart {
        background: #00E5FF;
        color: #070B11;
        box-shadow: 0 2px 8px rgba(0, 229, 255, 0.3);
      }
      .btn-card-icon.btn-card-cart:hover {
        background: #38BDF8;
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(0, 229, 255, 0.5);
      }
      .btn-card-icon:active {
        transform: scale(0.92);
      }
      .product-img-overlay-actions {
        position: absolute;
        top: 6px;
        right: 6px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        z-index: 4;
        opacity: 0;
        transform: translateY(-3px);
        transition: all 0.25s ease;
      }
      .product-card:hover .product-img-overlay-actions {
        opacity: 1;
        transform: translateY(0);
      }
      .card-float-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(7, 11, 17, 0.85);
        border: 1px solid rgba(0, 229, 255, 0.4);
        color: #00E5FF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        cursor: pointer;
        backdrop-filter: blur(4px);
        transition: all 0.2s ease;
        padding: 0;
      }
      .card-float-btn:hover {
        background: #00E5FF;
        color: #070B11;
        transform: scale(1.1);
        box-shadow: 0 2px 10px rgba(0, 229, 255, 0.5);
      }

      @media (max-width: 767px) {
        .product-body { padding: 9px !important; }
        .product-name { font-size: 12px !important; }
        .product-price-current { font-size: 12.5px !important; }
        .btn-card-icon { width: 28px; height: 28px; font-size: 12px; border-radius: 6px; }
        .product-card-actions { gap: 4px; }
        .product-img-overlay-actions { opacity: 1; transform: translateY(0); }
      }

      /* ==================== FLOATING CART ICON (PROMINENT & ENLARGED) ==================== */
      .floating-cart-btn {
        position: fixed;
        bottom: 26px;
        right: 26px;
        z-index: 9999;
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, #00E5FF 0%, #0073C6 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #070B11 !important;
        text-decoration: none !important;
        box-shadow: 0 10px 30px rgba(0, 229, 255, 0.6), 0 0 15px rgba(0, 229, 255, 0.4);
        transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 2px solid rgba(255, 255, 255, 0.25);
      }
      .floating-cart-btn:hover {
        transform: scale(1.12) translateY(-3px);
        box-shadow: 0 14px 35px rgba(0, 229, 255, 0.75), 0 0 20px rgba(0, 229, 255, 0.6);
      }
      .floating-cart-btn:active {
        transform: scale(0.95);
      }
      .floating-cart-btn i { font-size: 26px; color: #070B11; }

      .cart-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #EF4444;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        min-width: 22px;
        height: 22px;
        line-height: 18px;
        text-align: center;
        padding: 0 5px;
        border-radius: 11px;
        border: 2px solid #070B11;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
      }

      body.modal-open .floating-cart-btn,
      .modal-open .floating-cart-btn {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
      }

      @media (max-width: 767px) {
        .floating-cart-btn {
          bottom: 20px;
          right: 20px;
          width: 58px;
          height: 58px;
        }
        .floating-cart-btn i { font-size: 24px; }
      }

      /* ==================== NON-OVERFLOWING SLIM TOAST ALERT (DI BAWAH TOP NAV) ==================== */
      .shop-toast {
        position: fixed;
        top: 68px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        background: rgba(11, 17, 30, 0.96);
        border: 1px solid #00E5FF;
        box-shadow: 0 6px 25px rgba(0, 229, 255, 0.4);
        color: #ffffff;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 999998;
        opacity: 0;
        transition: all 0.25s ease;
        pointer-events: none;
        max-width: 90vw;
        width: auto;
        box-sizing: border-box;
        text-align: center;
      }
      .shop-toast.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
      }
      .shop-toast i { color: #00E5FF; font-size: 14px; flex-shrink: 0; }
      .shop-toast span {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
        word-break: break-word;
      }

      /* ==================== CLEAN MODALS ==================== */
      .modal { overflow-y: auto !important; }
      .modal-dialog { margin: 25px auto !important; max-width: 600px; width: 92%; }
      .modal-content {
        background: #0B111E !important;
        border-radius: 16px !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.85) !important;
        overflow: hidden !important;
        color: #CBD5E1;
      }
      .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        padding: 12px 18px !important;
        background: #0F172A !important;
      }
      .modal-header .close { color: #fff !important; opacity: 0.7; font-size: 22px; margin-top: -2px; }
      .modal-body { padding: 18px !important; background: #0B111E !important; }

      /* ==================== HOTSPOT LIVE PREVIEW MODAL ==================== */
      #modalHotspotPreview .modal-dialog {
        max-width: 1100px !important;
        width: 95% !important;
        margin: 15px auto !important;
      }
      .preview-modal-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        background: #0B1120 !important;
        padding: 12px 20px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
      }
      .preview-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
      }
      .preview-device-btn, .preview-page-btn {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #94A3B8;
        font-size: 11px;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
      }
      .preview-device-btn:hover, .preview-page-btn:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
      }
      .preview-device-btn.active {
        background: #0073C6;
        border-color: #00E5FF;
        color: #fff;
        box-shadow: 0 0 10px rgba(0, 229, 255, 0.3);
      }
      .preview-page-btn.active {
        background: rgba(0, 229, 255, 0.15);
        border-color: #00E5FF;
        color: #00E5FF;
      }
      .preview-stage-container {
        height: 72vh;
        min-height: 520px;
        background: radial-gradient(#1E293B 1px, transparent 1px);
        background-size: 20px 20px;
        background-color: #04060A;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        overflow: hidden;
        position: relative;
      }
      .preview-iframe-wrapper {
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .preview-iframe-wrapper.mobile {
        width: 390px;
        max-width: 100%;
        height: 100%;
        border: 8px solid #1E293B;
        border-radius: 36px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9);
        overflow: hidden;
        background: #000;
      }
      .preview-iframe-wrapper.desktop {
        width: 100%;
        max-width: 1000px;
        height: 100%;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
        overflow: hidden;
        background: #000;
      }
      .preview-iframe-element {
        width: 100%;
        height: 100%;
        border: none;
        background: transparent;
      }
      .btn-preview-theme {
        background: rgba(0, 229, 255, 0.12) !important;
        border: 1px solid rgba(0, 229, 255, 0.35) !important;
        color: #00E5FF !important;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        width: 100%;
        margin-bottom: 6px;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
      }
      .btn-preview-theme:hover {
        background: rgba(0, 229, 255, 0.25) !important;
        color: #fff !important;
        border-color: #00E5FF !important;
        box-shadow: 0 0 14px rgba(0, 229, 255, 0.4);
        transform: translateY(-1px);
      }
      @keyframes pulseDot {
        0% { transform: scale(0.9); opacity: 0.7; }
        50% { transform: scale(1.3); opacity: 1; }
        100% { transform: scale(0.9); opacity: 0.7; }
      }

      /* Cart Item Row */
      .cart-item-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      }
      .cart-item-img {
        width: 44px;
        height: 44px;
        border-radius: 6px;
        object-fit: cover;
        background: #020617;
        flex-shrink: 0;
      }
      .cart-item-info { flex-grow: 1; }
      .cart-item-title { font-size: 12px; font-weight: 700; color: #fff; margin: 0 0 2px 0; }
      .cart-item-price { font-size: 11px; color: #00E5FF; font-weight: 700; }
      .cart-qty-ctrl {
        display: flex;
        align-items: center;
        gap: 5px;
        background: #070B11;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 6px;
        padding: 2px 5px;
      }
      .cart-qty-btn {
        background: none;
        border: none;
        color: #fff;
        font-weight: bold;
        cursor: pointer;
        padding: 0 4px;
        font-size: 11px;
      }

      /* Form controls */
      .shop-form-control {
        background: #070B11 !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #fff !important;
        border-radius: 8px;
        padding: 7px 10px;
        width: 100%;
        margin-bottom: 8px;
        font-size: 12px;
      }
      .shop-form-label {
        font-size: 11px;
        font-weight: 700;
        color: #CBD5E1;
        margin-bottom: 2px;
        display: block;
      }
      .bank-box {
        background: #0E1626;
        border: 1px solid rgba(0, 229, 255, 0.25);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .bank-box:hover {
        border-color: #00E5FF;
        background: #131E33;
      }
      .bank-box.bank-box-selected {
        border-color: #00E5FF;
        background: rgba(0, 229, 255, 0.09);
        box-shadow: 0 0 14px rgba(0, 229, 255, 0.28);
      }
      .bank-box-title { font-size: 12px; font-weight: 800; color: #fff; }
      .bank-box-acc { font-family: monospace; font-size: 14px; color: #00E5FF; font-weight: 700; }
      .btn-copy {
        background: rgba(0, 229, 255, 0.15);
        border: 1px solid rgba(0, 229, 255, 0.3);
        color: #00E5FF;
        font-size: 9px;
        padding: 2px 6px;
        border-radius: 4px;
        cursor: pointer;
      }
      .input-validation-msg {
        font-size: 11px;
        margin-top: -5px;
        margin-bottom: 6px;
        display: none;
      }

      /* ==================== FOOTER SOCIAL LINKS & MOBILE CENTERING ==================== */
      .footer-social-links {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 16px;
        flex-wrap: wrap;
      }
      .footer-social-links a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #94A3B8;
        font-size: 12px;
        text-decoration: none !important;
        transition: all 0.2s ease;
      }
      .footer-social-links a:hover {
        color: #00E5FF;
      }
      @media (max-width: 767px) {
        .footer .container .row {
          display: flex !important;
          flex-direction: column !important;
          align-items: center !important;
          justify-content: center !important;
          text-align: center !important;
        }
        .footer .col-sm-6, .footer .text-left, .footer .text-right {
          width: 100% !important;
          text-align: center !important;
          float: none !important;
        }
        .footer-social-links {
          display: flex !important;
          justify-content: center !important;
          align-items: center !important;
          text-align: center !important;
          gap: 16px !important;
          margin: 12px auto 0 auto !important;
          width: 100% !important;
        }
        .footer-social-links a {
          justify-content: center !important;
        }
      }
    </style>
  </head>
  <body data-spy="scroll" data-target=".onpage-navigation" data-offset="60">
    <!-- ==================== FULLSCREEN ONBOARDING SCREEN WITH SWIPEABLE CAROUSEL ==================== -->
    @php
      $defaultSlides = [
        [
          'badge' => 'OFFICIAL STORE',
          'title' => 'Pusat Berbagai Kebutuhan Anda',
          'desc' => 'Menyediakan beragam produk pilihan, mulai dari voucher wifi, perangkat jaringan, elektronik, hingga kebutuhan internet lainnya.',
          'icon' => 'fa-shopping-bag',
          'color' => '#00E5FF',
        ],
        [
          'badge' => 'JARINGAN & SERVER',
          'title' => 'Router MikroTik & Server Siap Pakai',
          'desc' => 'Routerboard Gigabit, switch manage, mini PC server, dan perlengkapan jaringan handal dengan performa maksimal.',
          'icon' => 'fa-server',
          'color' => '#00E5FF',
        ],
        [
          'badge' => 'VOUCHER INTERNET WIFI',
          'title' => 'Aktivasi Instan & Otomatis',
          'desc' => 'Beli voucher wifi hotspot instan, bayar via transfer / QRIS, dan akun langsung otomatis aktif di router.',
          'icon' => 'fa-wifi',
          'color' => '#00E5FF',
        ],
        [
          'badge' => 'TRANSAKSI MUDAH & AMAN',
          'title' => 'Pemesanan Praktis via WhatsApp',
          'desc' => 'Pilih produk favorit Anda, checkout tanpa ribet, dan konfirmasi pesanan terhubung langsung ke admin.',
          'icon' => 'fa-whatsapp',
          'color' => '#25D366',
        ],
      ];

      $activeSlides = !empty($shopSlides) && is_array($shopSlides) && count($shopSlides) > 0 ? $shopSlides : $defaultSlides;
    @endphp

    <div id="fullscreenOnboarding" class="fullscreen-onboarding-overlay">
      <!-- Center Swipeable Track -->
      <div class="onboarding-slider-container">
        <div class="onboarding-carousel-viewport" id="onbViewport">
          <div class="onboarding-carousel-track" id="onbTrack">
            @foreach($activeSlides as $slide)
              @php
                $sColor = !empty($slide['color']) ? $slide['color'] : '#00E5FF';
                $sIcon = !empty($slide['icon']) ? $slide['icon'] : 'fa-shopping-bag';
              @endphp
              <div class="onboarding-carousel-slide">
                <div class="onboarding-icon-box" style="border-color: {{ $sColor }}; color: {{ $sColor }}; box-shadow: 0 8px 28px {{ $sColor }}40; background: {{ $sColor }}1A;">
                  <i class="fa {{ $sIcon }}"></i>
                </div>
                <span class="onboarding-badge" style="border-color: {{ $sColor }}60; color: {{ $sColor }}; background: {{ $sColor }}1A;">{{ $slide['badge'] ?? 'OFFICIAL STORE' }}</span>
                <h2 class="onboarding-title">{{ $slide['title'] ?? '' }}</h2>
                <p class="onboarding-desc">
                  {{ $slide['desc'] ?? $slide['description'] ?? '' }}
                </p>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <!-- Dots Indicator -->
      <div class="onboarding-dots">
        @foreach($activeSlides as $idx => $slide)
          <span class="onboarding-dot {{ $idx === 0 ? 'active' : '' }}" onclick="setOnbSlide({{ $idx }})"></span>
        @endforeach
      </div>

      <!-- Bottom Bar: Mulai Belanja CTA -->
      <div class="onboarding-bottom-bar">
        <button type="button" onclick="closeOnboarding()" class="btn-get-started">
          <span>Mulai Belanja Sekarang</span>
          <i class="fa fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <main>
      @php
        $saPhone = !empty($company['phone_wa']) ? preg_replace('/[^0-9]/', '', $company['phone_wa']) : '6281234567890';
      @endphp

      <!-- ==================== NAVIGATION (BRAND STRICTLY LEFT, LINKS CENTERED ON DESKTOP) ==================== -->
      <nav class="navbar navbar-custom navbar-fixed-top" role="navigation">
        <div class="container">
          <div class="navbar-header">
            <a class="navbar-brand" href="/" style="padding-left: 0;">
              <span class="brand-title" style="font-size: 17px; font-weight: 800; letter-spacing: 0.5px; color: #fff;">{{ $company['name'] ?? 'OFFICIAL STORE' }}</span>
            </a>
            <button class="navbar-toggle" type="button" data-toggle="collapse" data-target="#custom-collapse">
              <span class="sr-only">Menu</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
          </div>

          <!-- Strictly Centered Navigation on Desktop: Katalog Toko, Bantuan WA -->
          <div class="collapse navbar-collapse" id="custom-collapse">
            <ul class="nav navbar-nav navbar-nav-center">
              <li class="active"><a href="/">Katalog Toko</a></li>
              @if(!empty($saPhone))
                <li><a href="https://wa.me/{{ $saPhone }}" target="_blank"><i class="fa fa-whatsapp"></i> Bantuan WA</a></li>
              @endif
            </ul>
          </div>
        </div>
      </nav>

      <!-- ==================== HERO HEADER ==================== -->
      <section class="shop-hero-bg">
        <div class="container text-center">
          <span class="hero-tagline">{{ $company['landing_tagline'] ?? $company['name'] ?? 'OFFICIAL STORE' }}</span>
          <h1 class="hero-main-title">{{ $company['landing_title'] ?? "Selamat Datang di {$company['name']}" }}</h1>
          <p class="hero-description">
            {{ $company['landing_description'] ?? 'Penyedia layanan internet WiFi Hotspot dan produk berkualitas tinggi untuk Anda.' }}
          </p>
        </div>
      </section>

      <!-- ==================== MAIN SHOPPING SECTION ==================== -->
      <section class="module-small" id="catalogSection" style="padding-top: 15px;">
        <div class="container">
          <!-- Single-Button Unified Filter Bar (Realtime Search + Filter Icon) -->
          <div class="shop-toolbar">
            <div class="search-box-wrap">
              <i class="fa fa-search search-icon-inside"></i>
              <input
                type="text"
                id="liveSearchInput"
                placeholder="Cari router, OLT, SFP, server, komputer..."
                class="search-input-field"
                oninput="onLiveSearch(this.value)"
              >
            </div>

            <!-- Single Filter Button with Icon -->
            <button type="button" class="btn-filter-toggle" id="btnFilterToggle" onclick="openFilterModal()">
              <i class="fa fa-sliders"></i>
              <span>Filter</span>
            </button>
          </div>

          <!-- Active Filter Chips -->
          <div class="active-filter-chips" id="activeFilterChips" style="display: none;"></div>

          <!-- Product Grid Container (Rendered Instantly with Fast Client JS) -->
          <div class="product-grid-4" id="productGridContainer"></div>

          <!-- Empty State -->
          <div id="productEmptyState" style="display: none; text-align: center; padding: 45px 15px; background: #0B111E; border-radius: 14px; border: 1px solid rgba(255,255,255,0.06);">
            <i class="fa fa-inbox" style="font-size: 36px; color: #475569; margin-bottom: 10px;"></i>
            <h3 style="color: #fff; font-size: 16px; margin: 0 0 4px 0;">Produk Tidak Ditemukan</h3>
            <p style="color: #94A3B8; font-size: 12px; margin-bottom: 12px;">Coba kata kunci lain atau reset filter pencarian Anda.</p>
            <button type="button" onclick="resetAllFilters()" class="btn btn-cyan btn-round" style="padding: 6px 16px; font-size: 11px;">
              Reset Filter
            </button>
          </div>
        </div>
      </section>

      <!-- ==================== FOOTER ==================== -->
      <footer class="footer bg-dark" style="margin-top: 50px;">
        <div class="container">
          <div class="row">
            <div class="col-sm-6 text-left">
              <p class="copyright font-alt">&copy; {{ date('Y') }} <a href="/">{{ $company['name'] ?? 'STORE' }}</a>. All Rights Reserved.</p>
            </div>
            <div class="col-sm-6 text-right">
              <div class="footer-social-links">
                @if(!empty($saPhone))
                  <a href="https://wa.me/{{ $saPhone }}" target="_blank"><i class="fa fa-whatsapp"></i> WhatsApp</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      </footer>

      <!-- ==================== FLOATING CART (ONLY CART) ==================== -->
      <a href="javascript:void(0)" onclick="openCartModal()" class="floating-cart-btn" title="Buka Keranjang Belanja">
        <i class="fa fa-shopping-cart"></i>
        <span class="cart-badge" id="floatCartCount">0</span>
      </a>

      <!-- Slim Horizontal Toast Alert (DI BAWAH TOP NAV & ANTI-OVERFLOW) -->
      <div id="shopToast" class="shop-toast">
        <i class="fa fa-check-circle"></i>
        <span id="shopToastMsg">Barang berhasil masuk keranjang</span>
      </div>
    </main>

    <!-- ==================== SINGLE FILTER MODAL ==================== -->
    <div class="modal fade" id="modalFilter" tabindex="-1" role="dialog">
      <div class="modal-dialog" style="max-width: 420px;" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h4 class="modal-title font-alt" style="color: #fff; font-size: 14px;">
              <i class="fa fa-sliders color-cyan"></i> Filter &amp; Urutkan Produk
            </h4>
          </div>
          <div class="modal-body">
            <div style="margin-bottom: 14px;">
              <label class="shop-form-label">Kategori Produk</label>
              <select id="modalCategorySelect" class="shop-form-control">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                  <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>

            <div style="margin-bottom: 16px;">
              <label class="shop-form-label">Urutkan Berdasarkan</label>
              <select id="modalSortSelect" class="shop-form-control">
                <option value="featured">Populer / Rekomendasi</option>
                <option value="price_asc">Harga : Termurah &rarr; Termahal</option>
                <option value="price_desc">Harga : Termahal &rarr; Termurah</option>
                <option value="newest">Produk Terbaru</option>
              </select>
            </div>

            <div style="display: flex; gap: 8px;">
              <button type="button" onclick="resetAllFilters()" class="btn btn-border-w btn-round" style="flex: 1; padding: 8px; font-size: 12px;">
                Reset
              </button>
              <button type="button" onclick="applyModalFilter()" class="btn btn-cyan btn-round" style="flex: 1.5; padding: 8px; font-size: 12px; font-weight: 700;">
                Terapkan Filter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== QUICK VIEW PRODUCT MODAL ==================== -->
    <div class="modal fade" id="modalQuickView" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h4 class="modal-title font-alt" id="qvTitle" style="color: #fff; font-size: 14px;">Detail Produk</h4>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-sm-5 text-center">
                <div class="qv-img-wrapper">
                  <img id="qvImage" src="/images/logo.png?v=36" alt="Product" style="width: 100%; max-height: 220px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 0;" onerror="this.onerror=null; this.src='/images/logo.png?v=36';">
                  <button type="button" class="qv-nav-arrow qv-arrow-prev" id="qvPrevBtn" onclick="prevQvImage()" title="Foto Sebelumnya">&lsaquo;</button>
                  <button type="button" class="qv-nav-arrow qv-arrow-next" id="qvNextBtn" onclick="nextQvImage()" title="Foto Berikutnya">&rsaquo;</button>
                  <span id="qvIndexBadge" style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.75); color: #00E5FF; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(0,229,255,0.3); z-index: 4;">1 / 1</span>
                </div>
                <div id="qvGalleryStrip" style="display: flex; gap: 6px; overflow-x: auto; justify-content: center; margin-bottom: 8px; padding: 2px 0;"></div>
                <div style="font-size: 10px; color: #94A3B8;">SKU : <strong id="qvSku" style="color: #00E5FF;">-</strong></div>
              </div>
              <div class="col-sm-7">
                <span id="qvCategory" class="product-cat">KATEGORI</span>
                <h3 id="qvName" style="color: #fff; font-size: 16px; font-weight: 700; margin: 2px 0 6px 0;">Nama Produk</h3>
                <div style="margin-bottom: 8px;">
                  <span id="qvPrice" style="font-size: 18px; font-weight: 800; color: #ffffff;">Rp 0</span>
                  <span id="qvOriginalPrice" style="font-size: 11px; color: #64748B; text-decoration: line-through; margin-left: 6px;"></span>
                </div>
                <div id="qvStock" style="color: #00E5FF; font-size: 11px; margin-bottom: 8px;">
                  <i class="fa fa-check-circle"></i> Stok Tersedia
                </div>
                <div style="font-size: 11px; color: #CBD5E1; line-height: 1.5; margin-bottom: 10px;" id="qvDesc">
                  Deskripsi produk
                </div>
                <div id="qvSpecsBox" style="display: none; background: #070B11; border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; padding: 8px; margin-bottom: 12px;">
                  <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #00E5FF; margin-bottom: 2px;">Spesifikasi :</div>
                  <pre id="qvSpecs" style="background: none; border: none; color: #94A3B8; font-size: 10px; padding: 0; margin: 0; white-space: pre-wrap; font-family: inherit;"></pre>
                </div>
                <button id="qvBtnPreview" class="btn btn-round btn-block btn-preview-theme" style="display: none; padding: 8px 12px; font-size: 12px; font-weight: 700; margin-bottom: 8px;">
                  <i class="fa fa-eye"></i> Buka Live Preview Simulator
                </button>
                <button id="qvBtnAdd" class="btn btn-cyan btn-round btn-block" style="padding: 8px 12px; font-size: 12px; font-weight: 700;">
                  <i class="fa fa-shopping-cart"></i> + Keranjang
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== HOTSPOT LIVE PREVIEW MODAL ==================== -->
    <div class="modal fade" id="modalHotspotPreview" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="preview-modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="preview-live-dot" style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#00E5FF; box-shadow:0 0 8px #00E5FF; animation: pulseDot 1.5s infinite;"></span>
              <h4 class="modal-title font-alt" id="previewThemeTitle" style="color: #fff; font-size: 13px; font-weight: 700; margin: 0; display: inline-block;">
                Interactive Hotspot Simulator
              </h4>
            </div>

            <!-- Device Selector & Controls -->
            <div class="preview-toolbar">
              <div class="btn-group" role="group" aria-label="Device Toggle" style="display: inline-flex;">
                <button type="button" class="preview-device-btn active" id="btnPreviewMobile" onclick="setPreviewDevice('mobile')" title="Tampilan Smartphone (390px)">
                  <i class="fa fa-mobile" style="font-size: 14px; margin-right: 4px;"></i> Mobile
                </button>
                <button type="button" class="preview-device-btn" id="btnPreviewDesktop" onclick="setPreviewDevice('desktop')" title="Tampilan Desktop / Laptop">
                  <i class="fa fa-desktop" style="font-size: 12px; margin-right: 4px;"></i> Desktop
                </button>
              </div>

              <!-- Page Switcher -->
              <div class="btn-group" role="group" aria-label="Page Selector" style="display: inline-flex; margin-left: 6px;">
                <button type="button" class="preview-page-btn active" id="btnPageLogin" onclick="setPreviewPage('login.html')">login.html</button>
                <button type="button" class="preview-page-btn" id="btnPageStatus" onclick="setPreviewPage('status.html')">status.html</button>
                <button type="button" class="preview-page-btn" id="btnPageLogout" onclick="setPreviewPage('logout.html')">logout.html</button>
              </div>

              <a id="previewOpenNewTabBtn" href="#" target="_blank" class="preview-device-btn" style="text-decoration:none;" title="Buka di Tab Baru">
                <i class="fa fa-external-link"></i> Tab Baru
              </a>

              <button id="previewBuyNowBtn" type="button" class="btn btn-cyan btn-round" style="padding: 4px 12px; font-size: 11px; font-weight: 700;">
                <i class="fa fa-shopping-cart"></i> Beli Sekarang
              </button>

              <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8; font-size: 24px; margin-left: 4px; line-height: 1;">&times;</button>
            </div>
          </div>
          <div class="preview-stage-container">
            <div class="preview-iframe-wrapper mobile" id="previewIframeWrapper">
              <iframe id="previewHotspotIframe" class="preview-iframe-element" src="about:blank" allow="fullscreen"></iframe>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== CART & CHECKOUT MODAL ==================== -->
    <div class="modal fade" id="modalCart" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h4 class="modal-title font-alt" style="color: #fff; font-size: 14px;">
              <i class="fa fa-shopping-cart color-cyan"></i> Keranjang &amp; Checkout
            </h4>
          </div>
          <div class="modal-body">
            <!-- Step 1: Cart Items List -->
            <div id="cartItemsSection">
              <div id="cartItemsList"></div>

              <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 8px;">
                <span style="font-size: 13px; font-weight: 700; color: #fff;">Total :</span>
                <span id="cartGrandTotal" style="font-size: 17px; font-weight: 800; color: #00E5FF;">Rp 0</span>
              </div>

              <div id="cartActionBtn" style="margin-top: 6px;">
                <button type="button" onclick="showCheckoutForm()" class="btn btn-cyan btn-block btn-round" style="padding: 9px; font-size: 12px; font-weight: 700;">
                  Lanjut Checkout &rarr;
                </button>
              </div>
            </div>

            <!-- Step 2: Checkout Form & Payment (Bukti via WA) -->
            <div id="checkoutFormSection" style="display: none;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px;">
                <button type="button" onclick="backToCart()" class="btn btn-border-w btn-xs btn-round" style="padding: 3px 8px; font-size: 10px;">
                  &larr; Kembali ke Keranjang
                </button>
                <span style="font-size: 11px; color: #00E5FF; font-weight: 700;">Data Pengiriman</span>
              </div>

              <form id="formCheckout" onsubmit="submitOrder(event)">
                <div class="row">
                  <div class="col-sm-6">
                    <label class="shop-form-label">Nama Lengkap *</label>
                    <input type="text" name="customer_name" required placeholder="Nama Anda" class="shop-form-control">

                    <label class="shop-form-label">Nomor WhatsApp *</label>
                    <input
                      type="tel"
                      name="customer_phone"
                      id="inputPhone"
                      required
                      placeholder="Contoh: 08123456789"
                      class="shop-form-control"
                      oninput="validatePhoneRealtime(this.value)"
                    >
                    <div id="phoneValidationMsg" class="input-validation-msg" style="color: #F87171;"></div>

                    <label class="shop-form-label">Email (Opsional)</label>
                    <input type="email" name="customer_email" placeholder="nama@email.com" class="shop-form-control">
                  </div>

                  <div class="col-sm-6">
                    <!-- Voucher WiFi Credentials Box (Dynamic Multi-Voucher List) -->
                    <div id="voucherSectionFields" style="display: none; background: rgba(0, 229, 255, 0.08); border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 12px; padding: 10px 12px; margin-bottom: 10px;">
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 11px; font-weight: 800; color: #00E5FF; text-transform: uppercase; display: flex; align-items: center; gap: 5px;">
                          <i class="fa fa-wifi"></i> <span id="voucherSectionTitle">Akun Login WiFi Hotspot</span>
                        </div>
                        <button type="button" onclick="randomizeAllVoucherAccounts()" class="btn btn-xs" style="background: rgba(0, 229, 255, 0.15); border: 1px solid rgba(0, 229, 255, 0.4); color: #00E5FF; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 6px;">
                          <i class="fa fa-random"></i> Acak Semua
                        </button>
                      </div>
                      <p style="font-size: 10px; color: #94A3B8; margin-bottom: 8px; line-height: 1.4;">
                        Akun ini yang akan Anda gunakan untuk login ke WiFi Hotspot setelah di-ACC.
                      </p>
                      <div id="voucherAccountsList" style="display: flex; flex-direction: column; gap: 8px; max-height: 240px; overflow-y: auto; padding-right: 2px;">
                        <!-- Injected dynamically via JavaScript -->
                      </div>
                    </div>

                    <!-- Physical Goods Shipping Address (Shown when buying physical goods) -->
                    <div id="shippingAddressField">
                      <label class="shop-form-label">Alamat Pengiriman Lengkap *</label>
                      <textarea name="shipping_address" id="inputShippingAddress" rows="2" placeholder="Jalan, No Rumah, RT/RW, Kecamatan, Kota/Kab, Kode Pos" class="shop-form-control"></textarea>
                    </div>

                    <label class="shop-form-label">Catatan Pesanan (Opsional)</label>
                    <input type="text" name="customer_notes" placeholder="Catatan khusus..." class="shop-form-control">

                    <label class="shop-form-label">Metode Pembayaran</label>
                    <select name="payment_method" id="selectPaymentMethod" onchange="togglePaymentMethodBox(this.value)" class="shop-form-control" style="font-weight: 700; color: #00E5FF; background: #0E1626;">
                      <option value="Transfer Bank">Transfer Bank Manual</option>
                      <option value="QRIS / E-Wallet">QRIS / E-Wallet (DANA/OVO/GoPay/ShopeePay)</option>
                    </select>
                  </div>
                </div>

                <!-- Rekening Bank Manual Box -->
                <div id="bankPaymentBox" style="margin-top: 4px;">
                  <div style="font-size: 10.5px; font-weight: 700; color: #00E5FF; margin-bottom: 6px; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa fa-university"></i> Pilih Rekening Bank Tujuan Transfer:</span>
                    <span style="font-size: 9px; color: #94A3B8; font-weight: 400;">(Klik kartu untuk memilih)</span>
                  </div>

                  @php
                    $initialBank = '';
                    if (!empty($bankAccounts) && count($bankAccounts) > 0) {
                        $firstB = $bankAccounts[0];
                        $initialBank = $firstB->bank_name . ' - ' . $firstB->account_number . ' (a.n ' . $firstB->account_name . ')';
                    } else {
                        $initialBank = 'Rekening ' . ($company['name'] ?? 'NODERA') . ' - ' . ($company['phone_wa'] ?? '081234567890');
                    }
                  @endphp
                  <input type="hidden" name="payment_bank" id="inputPaymentBank" value="{{ $initialBank }}">

                  @if(!empty($bankAccounts) && count($bankAccounts) > 0)
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                      @foreach($bankAccounts as $idx => $bank)
                        <div class="bank-box {{ $idx === 0 ? 'bank-box-selected' : '' }}" 
                             id="bankBox_{{ $bank->id }}" 
                             onclick="selectBankOption('{{ $bank->id }}', '{{ addslashes($bank->bank_name) }}', '{{ addslashes($bank->account_number) }}', '{{ addslashes($bank->account_name) }}')"
                             style="margin-bottom: 0;">
                          <input type="radio" name="payment_bank_id" id="bankRadio_{{ $bank->id }}" value="{{ $bank->id }}" 
                                 data-bank-name="{{ $bank->bank_name }}" 
                                 data-acc-number="{{ $bank->account_number }}" 
                                 data-acc-name="{{ $bank->account_name }}" 
                                 {{ $idx === 0 ? 'checked' : '' }} 
                                 style="display: none;">
                          <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="bank-box-title" style="display: flex; align-items: center; gap: 6px;">
                              <i class="fa fa-check-circle check-bank-icon" id="checkIcon_{{ $bank->id }}" style="color: #00E5FF; font-size: 13px; {{ $idx === 0 ? '' : 'display: none;' }}"></i>
                              <span>{{ $bank->bank_name }}</span>
                            </div>
                            <button type="button" class="btn-copy" onclick="event.stopPropagation(); copyText('{{ $bank->account_number }}')">
                              <i class="fa fa-copy"></i> Salin
                            </button>
                          </div>
                          <div class="bank-box-acc" style="margin-top: 3px;">{{ $bank->account_number }}</div>
                          <div style="font-size: 10px; color: #94A3B8;">a.n. {{ $bank->account_name }}</div>
                        </div>
                      @endforeach
                    </div>
                  @else
                    <div class="bank-box bank-box-selected">
                      <div class="bank-box-title">Rekening Pembayaran {{ $company['name'] ?? 'NODERA' }}</div>
                      <div class="bank-box-acc" style="margin-top: 3px;">{{ $company['phone_wa'] ?? '081234567890' }}</div>
                      <div style="font-size: 10px; color: #94A3B8;">{{ $company['name'] ?? 'NODERA Official' }}</div>
                    </div>
                  @endif
                </div>

                <!-- QRIS / E-Wallet Display Box (Shown when QRIS is selected) -->
                <div id="qrisPaymentBox" style="display: none; margin-top: 4px;">
                  <div style="background: #0E1626; border: 1px solid rgba(0, 229, 255, 0.35); border-radius: 12px; padding: 12px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 800; color: #00E5FF; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">
                      <i class="fa fa-qrcode"></i> Scan QRIS untuk Pembayaran Cepat
                    </div>
                    <p style="font-size: 9.5px; color: #94A3B8; margin-bottom: 8px;">
                      Mendukung BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay &amp; LinkAja.
                    </p>

                    @php
                      $qrisCodeUrl = !empty($qris['image']) ? $qris['image'] : 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode('https://wa.me/' . ($company['phone_wa'] ?? '6281234567890'));
                    @endphp
                    <div style="display: inline-block; background: #ffffff; padding: 6px; border-radius: 10px; box-shadow: 0 4px 18px rgba(0,229,255,0.25); margin-bottom: 6px;">
                      <img id="checkoutQrisImg" src="{{ $qrisCodeUrl }}" alt="QRIS Code" style="width: 160px; height: 160px; object-fit: contain; display: block; margin: 0 auto;" onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=NODERASHOP';">
                    </div>

                    <div style="display: flex; gap: 8px; justify-content: center; margin-top: 2px;">
                      <a href="{{ $qrisCodeUrl }}" target="_blank" download="QRIS_NODERA.png" class="btn-copy" style="text-decoration: none !important; padding: 3px 8px; font-size: 9.5px; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fa fa-external-link"></i> Buka Gambar QR
                      </a>
                    </div>
                  </div>
                </div>

                <div style="background: rgba(37, 211, 102, 0.08); border: 1px solid rgba(37, 211, 102, 0.25); border-radius: 6px; padding: 6px 10px; margin-bottom: 10px; font-size: 10px; color: #E2E8F0;">
                  <i class="fa fa-whatsapp" style="color: #25D366; font-size: 12px;"></i>
                  <strong>Kirim Bukti via WhatsApp :</strong> Setelah pesanan dibuat, Anda akan diarahkan ke WhatsApp untuk mengirim bukti pembayaran.
                </div>

                <button type="submit" id="btnSubmitOrder" class="btn btn-cyan btn-block btn-round" style="padding: 9px; font-size: 12px; font-weight: 700;">
                  <i class="fa fa-check-circle"></i> Buat Pesanan Sekarang &rarr;
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- JavaScripts -->
    <script src="/titan/assets/lib/jquery/dist/jquery.js"></script>
    <script src="/titan/assets/lib/bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="/titan/assets/js/main.js"></script>

    <script>
      // ==================== RAW PRODUCTS DATA FOR INSTANT CLIENT FILTERING ====================
      const RAW_PRODUCTS = @json($products);
      let currentCategory = "{{ $selectedCategorySlug }}";
      let currentSort = "featured";
      let currentSearch = "";

      const CART_STORAGE_KEY = "nodera_shop_cart";

      function formatIDR(num) {
        if (num === null || num === undefined || isNaN(Number(num))) return 'Rp 0';
        return 'Rp ' + Number(num).toLocaleString('id-ID');
      }

      // ==================== SWIPEABLE & DRAGGABLE ONBOARDING CAROUSEL ====================
      let onbIndex = 0;
      const totalOnb = 5;
      let onbInterval = null;

      function setOnbSlide(idx) {
        onbIndex = (idx + totalOnb) % totalOnb;
        const track = document.getElementById('onbTrack');
        if (track) {
          track.style.transform = 'translateX(-' + (onbIndex * 100) + '%)';
        }
        $('.onboarding-dot').removeClass('active');
        $('.onboarding-dot').eq(onbIndex).addClass('active');
      }

      function nextOnbSlide() {
        setOnbSlide(onbIndex + 1);
      }

      function prevOnbSlide() {
        setOnbSlide(onbIndex - 1);
      }

      function startOnbTimer() {
        if (onbInterval) clearInterval(onbInterval);
        onbInterval = setInterval(nextOnbSlide, 4500);
      }

      function closeOnboarding() {
        if (onbInterval) clearInterval(onbInterval);
        $('#fullscreenOnboarding').addClass('hidden-onboarding');
      }

      // Touch Swipe & Mouse Drag Handling
      let touchStartX = 0;
      let touchEndX = 0;
      let isDragging = false;

      const viewportEl = document.getElementById('onbViewport');
      if (viewportEl) {
        viewportEl.addEventListener('touchstart', function(e) {
          touchStartX = e.touches[0].clientX;
          if (onbInterval) clearInterval(onbInterval);
        }, { passive: true });

        viewportEl.addEventListener('touchend', function(e) {
          touchEndX = e.changedTouches[0].clientX;
          handleSwipeGesture();
          startOnbTimer();
        }, { passive: true });

        viewportEl.addEventListener('mousedown', function(e) {
          touchStartX = e.clientX;
          isDragging = true;
          if (onbInterval) clearInterval(onbInterval);
        });

        viewportEl.addEventListener('mouseup', function(e) {
          if (!isDragging) return;
          isDragging = false;
          touchEndX = e.clientX;
          handleSwipeGesture();
          startOnbTimer();
        });

        viewportEl.addEventListener('mouseleave', function(e) {
          if (!isDragging) return;
          isDragging = false;
          touchEndX = e.clientX;
          handleSwipeGesture();
          startOnbTimer();
        });
      }

      function handleSwipeGesture() {
        const diffX = touchEndX - touchStartX;
        if (diffX < -40) {
          nextOnbSlide();
        } else if (diffX > 40) {
          prevOnbSlide();
        }
      }

      // ==================== INSTANT CLIENT-SIDE FILTERING ====================
      function renderProducts() {
        let filtered = [...RAW_PRODUCTS];

        // 1. Filter by category
        if (currentCategory) {
          filtered = filtered.filter(p => {
            const catSlug = (p.category && p.category.slug) ? p.category.slug : (p.category_slug || '');
            const isVch = (p.id >= 900000) || (p.product_type === 'voucher') || (p.badge === 'WIFI') || (catSlug === 'voucher-wifi') || (p.category_id == 999999);
            if (currentCategory === 'voucher-wifi') {
              return isVch;
            }
            return (catSlug === currentCategory) && !isVch;
          });
        }

        // 2. Filter by search query (instant realtime)
        if (currentSearch) {
          const q = currentSearch.toLowerCase();
          filtered = filtered.filter(p => 
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.short_description && p.short_description.toLowerCase().includes(q)) ||
            (p.category && p.category.name && p.category.name.toLowerCase().includes(q))
          );
        }

        // 3. Sort
        if (currentSort === 'price_asc') {
          filtered.sort((a, b) => a.price - b.price);
        } else if (currentSort === 'price_desc') {
          filtered.sort((a, b) => b.price - a.price);
        } else if (currentSort === 'newest') {
          filtered.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        }

        // Render to DOM
        const container = $('#productGridContainer');
        const emptyState = $('#productEmptyState');
        container.empty();

        if (filtered.length === 0) {
          container.hide();
          emptyState.show();
          return;
        }

        emptyState.hide();
        container.show();

        filtered.forEach(p => {
          const gallery = resolveProductGallery(p);
          const badgeHtml = p.badge ? `<div class="product-badge">${p.badge}</div>` : '';
          const discHtml = p.discount_percent ? `<div class="product-discount-badge">-${p.discount_percent}%</div>` : '';
          const currentPrice = p.formatted_price || formatIDR(p.price);
          const origPriceFormatted = p.formatted_original_price || (p.original_price && p.original_price > p.price ? formatIDR(p.original_price) : null);
          const origPriceHtml = origPriceFormatted ? `<span class="product-price-original">${origPriceFormatted}</span>` : '';
          const pImg = gallery[0] || '/images/logo.png?v=36';
          const catName = p.category ? p.category.name : 'Produk';
          const galleryBadgeHtml = gallery.length > 1 ? `<div class="product-gallery-indicator" title="${gallery.length} Foto Produk"><i class="fa fa-clone"></i> ${gallery.length} Foto</div>` : '';
          
          const previewUrl = extractPreviewUrl(p);
          let previewBtnHtml = '';
          let previewOverlayBtn = '';
          if (previewUrl) {
            const escapedName = (p.name || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            previewBtnHtml = `
              <button type="button" class="btn-card-icon btn-card-preview" title="Live Preview Hotspot" onclick="event.stopPropagation(); openHotspotPreview('${escapedName}', '${previewUrl}', ${p.id});">
                <i class="fa fa-eye"></i>
              </button>
            `;
            previewOverlayBtn = `
              <button type="button" class="card-float-btn" title="Live Preview Hotspot" onclick="event.stopPropagation(); openHotspotPreview('${escapedName}', '${previewUrl}', ${p.id});">
                <i class="fa fa-eye"></i>
              </button>
            `;
          }
          
          let thumbsHtml = '';
          if (gallery.length > 1) {
            thumbsHtml = `
              <div class="product-card-thumbs" onclick="event.stopPropagation();">
                ${gallery.map((gUrl, idx) => `
                  <img src="${gUrl}" 
                       class="product-card-thumb ${idx === 0 ? 'active' : ''}" 
                       onmouseenter="changeCardThumb(${p.id}, '${gUrl}', this)" 
                       onclick="event.stopPropagation(); changeCardThumb(${p.id}, '${gUrl}', this); quickView(${p.id});" 
                       alt="" 
                       loading="lazy"
                       onerror="this.onerror=null; this.src='/images/logo.png?v=36';">
                `).join('')}
              </div>
            `;
          }

          const cardHtml = `
            <div>
              <div class="product-card" onclick="quickView(${p.id})">
                <div class="product-img-box">
                  ${badgeHtml}
                  ${discHtml}
                  ${galleryBadgeHtml}
                  <img id="cardImg_${p.id}" src="${pImg}" alt="${p.name}" loading="lazy" onerror="this.onerror=null; this.src='/images/logo.png?v=36';">
                  <div class="product-img-overlay-actions">
                    ${previewOverlayBtn}
                    <button type="button" class="card-float-btn" title="Detail Produk" onclick="event.stopPropagation(); quickView(${p.id});">
                      <i class="fa fa-expand"></i>
                    </button>
                  </div>
                </div>
                ${thumbsHtml}

                <div class="product-body">
                  <div>
                    <div class="product-cat">${catName}</div>
                    <h3 class="product-name" title="${p.name}">${p.name}</h3>
                  </div>

                  <div class="product-pricing">
                    <div class="product-pricing-left">
                      <div>
                        <span class="product-price-current">${currentPrice}</span>
                        ${origPriceHtml}
                      </div>
                      <div>
                        <span class="product-stock-badge">
                          <i class="fa fa-check-circle"></i> Stok: ${p.stock}
                        </span>
                      </div>
                    </div>

                    <div class="product-card-actions">
                      ${previewBtnHtml}
                      <button type="button" class="btn-card-icon btn-card-cart" title="Tambah ke Keranjang" onclick="event.stopPropagation(); addToCartById(${p.id})">
                        <i class="fa fa-shopping-cart"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
          container.append(cardHtml);
        });

        updateFilterChips();
      }

      function changeCardThumb(productId, imgUrl, el) {
        $('#cardImg_' + productId).attr('src', imgUrl);
        if (el) {
          $(el).siblings().removeClass('active');
          $(el).addClass('active');
        }
      }

      function onLiveSearch(val) {
        currentSearch = val.trim();
        renderProducts();
      }

      function openFilterModal() {
        $('#modalCategorySelect').val(currentCategory);
        $('#modalSortSelect').val(currentSort);
        $('#modalFilter').modal('show');
      }

      function applyModalFilter() {
        currentCategory = $('#modalCategorySelect').val();
        currentSort = $('#modalSortSelect').val();
        renderProducts();
        $('#modalFilter').modal('hide');
      }

      function resetAllFilters() {
        currentCategory = "";
        currentSort = "featured";
        currentSearch = "";
        $('#liveSearchInput').val('');
        $('#modalCategorySelect').val('');
        $('#modalSortSelect').val('featured');
        renderProducts();
        $('#modalFilter').modal('hide');
      }

      function updateFilterChips() {
        const chipsContainer = $('#activeFilterChips');
        chipsContainer.empty();

        let hasActiveFilter = false;

        if (currentCategory) {
          hasActiveFilter = true;
          const catOption = $(`#modalCategorySelect option[value="${currentCategory}"]`).text() || currentCategory;
          chipsContainer.append(`
            <span class="filter-chip">
              Kategori : ${catOption}
              <i class="fa fa-times filter-chip-remove" onclick="currentCategory=''; renderProducts();"></i>
            </span>
          `);
        }

        if (currentSort && currentSort !== 'featured') {
          hasActiveFilter = true;
          const sortOption = $(`#modalSortSelect option[value="${currentSort}"]`).text();
          chipsContainer.append(`
            <span class="filter-chip">
              Urutan : ${sortOption}
              <i class="fa fa-times filter-chip-remove" onclick="currentSort='featured'; $('#modalSortSelect').val('featured'); renderProducts();"></i>
            </span>
          `);
        }

        if (currentSearch) {
          hasActiveFilter = true;
          chipsContainer.append(`
            <span class="filter-chip">
              Cari : "${currentSearch}"
              <i class="fa fa-times filter-chip-remove" onclick="$('#liveSearchInput').val(''); onLiveSearch('');"></i>
            </span>
          `);
        }

        if (hasActiveFilter) {
          chipsContainer.show();
          $('#btnFilterToggle').addClass('active');
        } else {
          chipsContainer.hide();
          $('#btnFilterToggle').removeClass('active');
        }
      }

      // ==================== CART FUNCTIONS ====================
      function getCart() {
        try {
          const stored = localStorage.getItem(CART_STORAGE_KEY);
          return stored ? JSON.parse(stored) : [];
        } catch (e) {
          return [];
        }
      }

      function saveCart(cart) {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
        updateCartBadges();
      }

      function updateCartBadges() {
        const cart = getCart();
        const totalItems = cart.reduce((acc, item) => acc + (item.quantity || 1), 0);
        $('#floatCartCount').text(totalItems);
      }

      let toastTimeout = null;
      function showShopToast(msg) {
        $('#shopToastMsg').text(msg);
        const toast = $('#shopToast');
        toast.addClass('show');
        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
          toast.removeClass('show');
        }, 2200);
      }

      function addToCartById(productId) {
        const p = RAW_PRODUCTS.find(item => item.id === productId);
        if (!p) return;
        const gallery = p.gallery && Array.isArray(p.gallery) && p.gallery.length > 0 ? p.gallery : (p.image ? [p.image] : ['/images/logo.png?v=36']);
        const pImg = gallery[0] || '/images/logo.png?v=36';
        addToCart({
          id: p.id,
          name: p.name,
          price: p.price,
          formatted_price: p.formatted_price || formatIDR(p.price),
          image: pImg,
          stock: p.stock
        });
      }

      function addToCart(product) {
        let cart = getCart();
        const existingIndex = cart.findIndex(item => item.id === product.id);
        if (existingIndex > -1) {
          cart[existingIndex].quantity += 1;
        } else {
          cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            formatted_price: product.formatted_price,
            image: product.image,
            stock: product.stock,
            product_type: product.product_type || (product.id >= 900000 ? 'voucher' : 'general'),
            quantity: 1
          });
        }
        saveCart(cart);
        const shortName = product.name && product.name.length > 32 ? product.name.substring(0, 32) + '...' : product.name;
        showShopToast(shortName + ' masuk keranjang');
      }

      function updateCartQty(productId, delta) {
        let cart = getCart();
        const item = cart.find(i => i.id === productId);
        if (item) {
          item.quantity += delta;
          if (item.quantity <= 0) {
            cart = cart.filter(i => i.id !== productId);
          }
        }
        saveCart(cart);
        renderCartItems();
      }

      function removeCartItem(productId) {
        let cart = getCart();
        cart = cart.filter(i => i.id !== productId);
        saveCart(cart);
        renderCartItems();
      }

      function renderCartItems() {
        const cart = getCart();
        const container = $('#cartItemsList');
        container.empty();

        if (cart.length === 0) {
          container.html(`
            <div style="text-align: center; padding: 25px 10px;">
              <i class="fa fa-shopping-basket" style="font-size: 28px; color: #475569; margin-bottom: 6px;"></i>
              <h4 style="color: #fff; font-size: 13px; margin: 0 0 2px 0;">Keranjang Masih Kosong</h4>
              <p style="font-size: 11px; color: #94A3B8;">Pilih produk yang Anda butuhkan dan klik '+ Keranjang'.</p>
            </div>
          `);
          $('#cartGrandTotal').text('Rp 0');
          $('#cartActionBtn').hide();
          return;
        }

        $('#cartActionBtn').show();
        let total = 0;

        cart.forEach(item => {
          const itemSubtotal = item.price * item.quantity;
          total += itemSubtotal;

          container.append(`
            <div class="cart-item-row">
              <img src="${item.image}" alt="${item.name}" class="cart-item-img" onerror="this.onerror=null; this.src='/images/logo.png?v=36';">
              <div class="cart-item-info">
                <h5 class="cart-item-title">${item.name}</h5>
                <div class="cart-item-price">${formatIDR(item.price)}</div>
              </div>
              <div class="cart-qty-ctrl">
                <button type="button" class="cart-qty-btn" onclick="updateCartQty(${item.id}, -1)">-</button>
                <span style="font-size: 11px; font-weight: 700; color: #fff;">${item.quantity}</span>
                <button type="button" class="cart-qty-btn" onclick="updateCartQty(${item.id}, 1)">+</button>
              </div>
              <div style="text-align: right; min-width: 70px;">
                <div style="font-size: 11px; font-weight: 800; color: #fff;">${formatIDR(itemSubtotal)}</div>
                <button type="button" onclick="removeCartItem(${item.id})" style="background: none; border: none; color: #EF4444; font-size: 9px; padding: 0; cursor: pointer;">
                  <i class="fa fa-trash"></i> Hapus
                </button>
              </div>
            </div>
          `);
        });

        $('#cartGrandTotal').text(formatIDR(total));
      }

      function openCartModal() {
        $('#cartItemsSection').show();
        $('#checkoutFormSection').hide();
        renderCartItems();
        $('#modalCart').modal('show');
      }

      function isVoucherItem(item) {
        if (!item) return false;
        if (item.id >= 900000) return true;
        const raw = (typeof RAW_PRODUCTS !== 'undefined') ? RAW_PRODUCTS.find(p => p.id === item.id) : null;
        if (raw && (raw.product_type === 'voucher' || raw.badge === 'WIFI')) return true;
        return (item.name && item.name.toLowerCase().indexOf('voucher') !== -1);
      }

      function hasVoucherInOrder() {
        const cart = getCart();
        return cart.some(item => isVoucherItem(item));
      }

      function hasPhysicalGoodsInOrder() {
        const cart = getCart();
        return cart.some(item => !isVoucherItem(item));
      }

      function getVoucherCartSlots() {
        const cart = getCart();
        const slots = [];
        cart.forEach(item => {
          if (isVoucherItem(item)) {
            const raw = (typeof RAW_PRODUCTS !== 'undefined') ? RAW_PRODUCTS.find(p => p.id === item.id) : null;
            const profile = (raw && raw.voucher_profile) ? raw.voucher_profile : (item.voucher_profile || (item.name ? item.name.replace(/^Voucher WiFi:\s*/i, '') : 'default'));
            const qty = item.quantity || 1;
            for (let i = 0; i < qty; i++) {
              slots.push({
                package_id: item.id >= 900000 ? (item.id - 900000) : (raw?.voucher_package_id || item.id),
                package_name: item.name,
                profile: profile,
                qty_suffix: (qty > 1) ? ` (Ke-${i + 1})` : ''
              });
            }
          }
        });
        return slots;
      }

      function generateRandomPassString() {
        const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        let pass = '';
        for (let i = 0; i < 6; i++) {
          pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return pass;
      }

      function randomizeSlotUser(idx) {
        const phone = ($('#inputPhone').val() || '').replace(/\D/g, '');
        const suffix = phone.length >= 4 ? phone.slice(-4) : Math.floor(1000 + Math.random() * 9000);
        $(`#vchUser_${idx}`).val(`user${suffix}_${Math.floor(10 + Math.random() * 90)}`);
      }

      function randomizeSlotPass(idx) {
        $(`#vchPass_${idx}`).val(generateRandomPassString());
      }

      function randomizeAllVoucherAccounts() {
        const slots = getVoucherCartSlots();
        const phone = ($('#inputPhone').val() || '').replace(/\D/g, '');
        const baseSuffix = phone.length >= 4 ? phone.slice(-4) : Math.floor(1000 + Math.random() * 9000);

        slots.forEach((slot, idx) => {
          const userVal = slots.length === 1 ? `user${baseSuffix}` : `user${baseSuffix}_${idx + 1}`;
          $(`#vchUser_${idx}`).val(userVal);
          $(`#vchPass_${idx}`).val(generateRandomPassString());
        });
      }

      function renderVoucherAccountFields() {
        const slots = getVoucherCartSlots();
        const container = $('#voucherAccountsList');
        container.empty();

        if (slots.length === 0) return;

        $('#voucherSectionTitle').text(`Akun WiFi Hotspot (${slots.length} Akun)`);

        const phone = ($('#inputPhone').val() || '').replace(/\D/g, '');
        const baseSuffix = phone.length >= 4 ? phone.slice(-4) : Math.floor(1000 + Math.random() * 9000);

        slots.forEach((slot, idx) => {
          const defaultUser = slots.length === 1 ? `user${baseSuffix}` : `user${baseSuffix}_${idx + 1}`;
          const defaultPass = generateRandomPassString();

          const slotHtml = `
            <div class="voucher-slot-row" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 7px 9px;">
              <div style="font-size: 10px; font-weight: 700; color: #E2E8F0; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #00E5FF; font-weight: 800;">🎟️ #${idx + 1} ${slot.package_name}${slot.qty_suffix}</span>
                <span style="font-size: 8.5px; color: #94A3B8; background: rgba(255,255,255,0.06); padding: 1px 5px; border-radius: 4px;">Profil: ${slot.profile}</span>
              </div>
              <input type="hidden" name="voucher_accounts[${idx}][package_id]" value="${slot.package_id}">
              <input type="hidden" name="voucher_accounts[${idx}][package_name]" value="${slot.package_name}">
              <input type="hidden" name="voucher_accounts[${idx}][profile]" value="${slot.profile}">
              <div class="row">
                <div class="col-xs-6" style="padding-right: 4px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <label class="shop-form-label" style="margin: 0; font-size: 9px;">Username *</label>
                    <button type="button" onclick="randomizeSlotUser(${idx})" style="background: none; border: none; color: #00E5FF; font-size: 8.5px; cursor: pointer; padding: 0;">
                      <i class="fa fa-random"></i> Acak
                    </button>
                  </div>
                  <input type="text" name="voucher_accounts[${idx}][username]" id="vchUser_${idx}" value="${defaultUser}" required placeholder="Username" class="shop-form-control vch-input-user" style="font-family: monospace; font-weight: 700; color: #00E5FF; padding: 4px 7px; font-size: 11px; height: 28px;">
                </div>
                <div class="col-xs-6" style="padding-left: 4px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <label class="shop-form-label" style="margin: 0; font-size: 9px;">Password *</label>
                    <button type="button" onclick="randomizeSlotPass(${idx})" style="background: none; border: none; color: #00E5FF; font-size: 8.5px; cursor: pointer; padding: 0;">
                      <i class="fa fa-random"></i> Acak
                    </button>
                  </div>
                  <input type="text" name="voucher_accounts[${idx}][password]" id="vchPass_${idx}" value="${defaultPass}" required placeholder="Password" class="shop-form-control vch-input-pass" style="font-family: monospace; font-weight: 700; color: #00E5FF; padding: 4px 7px; font-size: 11px; height: 28px;">
                </div>
              </div>
            </div>
          `;
          container.append(slotHtml);
        });
      }

      function showCheckoutForm() {
        const cart = getCart();
        if (cart.length === 0) {
          showShopToast('Keranjang masih kosong.');
          return;
        }

        const isVoucher = hasVoucherInOrder();
        const hasPhysical = hasPhysicalGoodsInOrder();

        if (isVoucher) {
          renderVoucherAccountFields();
          $('#voucherSectionFields').slideDown(150);
        } else {
          $('#voucherSectionFields').hide();
          $('#voucherAccountsList').empty();
        }

        if (hasPhysical) {
          $('#shippingAddressField').show();
          $('#inputShippingAddress').prop('required', true);
          if ($('#inputShippingAddress').val() === 'Layanan Voucher Online') {
            $('#inputShippingAddress').val('');
          }
        } else {
          $('#shippingAddressField').hide();
          $('#inputShippingAddress').prop('required', false);
          $('#inputShippingAddress').val('Layanan Voucher Online');
        }

        $('#cartItemsSection').hide();
        $('#checkoutFormSection').show();
      }

      function backToCart() {
        $('#checkoutFormSection').hide();
        $('#cartItemsSection').show();
      }

      function selectBankOption(id, name, accNo, accName) {
        $('.bank-box').removeClass('bank-box-selected');
        $('.check-bank-icon').hide();

        $(`#bankBox_${id}`).addClass('bank-box-selected');
        $(`#checkIcon_${id}`).show();
        $(`#bankRadio_${id}`).prop('checked', true);

        const summary = `${name} - ${accNo} (a.n ${accName})`;
        $('#inputPaymentBank').val(summary);
      }

      function togglePaymentMethodBox(method) {
        if (!method) return;
        if (method.indexOf('QRIS') !== -1 || method.indexOf('qrcode') !== -1 || method.indexOf('QR') !== -1 || method.indexOf('Wallet') !== -1) {
          $('#bankPaymentBox').slideUp(150);
          $('#qrisPaymentBox').slideDown(200);
          $('#inputPaymentBank').val('QRIS Instant');
        } else {
          $('#qrisPaymentBox').slideUp(150);
          $('#bankPaymentBox').slideDown(200);
          
          const checkedRadio = $('input[name="payment_bank_id"]:checked');
          if (checkedRadio.length > 0) {
            const bName = checkedRadio.data('bank-name') || '';
            const bAcc = checkedRadio.data('acc-number') || '';
            const bHolder = checkedRadio.data('acc-name') || '';
            $('#inputPaymentBank').val(`${bName} - ${bAcc} (a.n ${bHolder})`);
          }
        }
      }

      function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
          showShopToast('Nomor rekening berhasil disalin!');
        });
      }

      function validatePhoneRealtime(val) {
        const clean = val.replace(/[^0-9+]/g, '');
        const msgBox = $('#phoneValidationMsg');
        
        if (!clean) {
          msgBox.hide();
          return false;
        }

        const digits = clean.replace(/[^0-9]/g, '');
        if (digits.length < 9 || digits.length > 14) {
          msgBox.text('Nomor WhatsApp harus terdiri dari 9 - 14 digit angka').show();
          return false;
        }

        msgBox.hide();
        return true;
      }

      let currentQvGallery = [];
      let currentQvIndex = 0;

      function setQvImageIndex(idx) {
        if (!currentQvGallery || currentQvGallery.length === 0) return;
        if (idx < 0) idx = currentQvGallery.length - 1;
        if (idx >= currentQvGallery.length) idx = 0;
        currentQvIndex = idx;

        $('#qvImage').attr('src', currentQvGallery[currentQvIndex]);
        $('#qvIndexBadge').text((currentQvIndex + 1) + ' / ' + currentQvGallery.length);

        $('.qv-thumb').removeClass('active');
        $(`.qv-thumb[data-idx="${currentQvIndex}"]`).addClass('active');
      }

      function prevQvImage() {
        setQvImageIndex(currentQvIndex - 1);
      }

      function nextQvImage() {
        setQvImageIndex(currentQvIndex + 1);
      }

      function quickView(productId) {
        const p = RAW_PRODUCTS.find(item => item.id === productId);
        if (p) {
          const gallery = resolveProductGallery(p);
          currentQvGallery = gallery;
          currentQvIndex = 0;
          
          const qvCurrentPrice = p.formatted_price || formatIDR(p.price);
          const qvOrigPrice = p.formatted_original_price || (p.original_price && p.original_price > p.price ? formatIDR(p.original_price) : null);

          $('#qvTitle').text(p.name);
          $('#qvImage').attr('src', gallery[0]);
          $('#qvCategory').text(p.category ? p.category.name : 'PRODUK');
          $('#qvName').text(p.name);
          $('#qvSku').text(p.sku || '-');
          $('#qvPrice').text(qvCurrentPrice);
          if (qvOrigPrice) {
            $('#qvOriginalPrice').text(qvOrigPrice).show();
          } else {
            $('#qvOriginalPrice').hide();
          }
          $('#qvStock').html('<i class="fa fa-check-circle"></i> Stok: ' + p.stock + ' unit');
          $('#qvDesc').html(p.description || p.short_description || '-');
          if (p.specifications) {
            $('#qvSpecs').text(p.specifications);
            $('#qvSpecsBox').show();
          } else {
            $('#qvSpecsBox').hide();
          }

          // Setup arrows and index badge
          if (gallery.length > 1) {
            $('#qvPrevBtn, #qvNextBtn, #qvIndexBadge').show();
            $('#qvIndexBadge').text('1 / ' + gallery.length);
          } else {
            $('#qvPrevBtn, #qvNextBtn, #qvIndexBadge').hide();
          }

          const galleryStrip = $('#qvGalleryStrip');
          galleryStrip.empty();
          if (gallery.length > 1) {
            gallery.forEach((gImg, idx) => {
              galleryStrip.append(`
                <img src="${gImg}" 
                     class="qv-thumb ${idx === 0 ? 'active' : ''}" 
                     data-idx="${idx}" 
                     alt="" 
                     onclick="setQvImageIndex(${idx})"
                     onerror="this.onerror=null; this.src='/images/logo.png?v=36';">
              `);
            });
            galleryStrip.show();
          } else {
            galleryStrip.hide();
          }

          const previewUrl = extractPreviewUrl(p);
          if (previewUrl) {
            const escapedName = (p.name || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            $('#qvBtnPreview').show().off('click').on('click', function() {
              $('#modalQuickView').modal('hide');
              openHotspotPreview(escapedName, previewUrl, p.id);
            });
          } else {
            $('#qvBtnPreview').hide();
          }

          $('#qvBtnAdd').off('click').on('click', function() {
            addToCart({
              id: p.id,
              name: p.name,
              price: p.price,
              formatted_price: qvCurrentPrice,
              image: gallery[0],
              stock: p.stock
            });
            $('#modalQuickView').modal('hide');
          });

          $('#modalQuickView').modal('show');
        }
      }

      // ==================== HOTSPOT LIVE PREVIEW SIMULATOR ====================
      var currentPreviewBaseUrl = '';
      var currentPreviewPage = 'login.html';
      var currentPreviewThemeName = '';
      var currentPreviewProductId = null;

      function resolveThemePathFromSlug(slug) {
        if (!slug) return null;
        slug = slug.toLowerCase().replace('template-hotspot-', '').replace('dgtlnet-', '');
        
        const signatures = [
          'ocean-abyss', 'glass-dock', 'bento-grid', 'cyber-matrix', 'anime-mecha',
          'fintech-wallet', 'tokyo-night', 'gaming-hud', 'aurora-glow', 'neo-brutalist',
          'retro-vaporwave', 'soft-neumorphic', 'isometric-station', 'paper-origami',
          'space-orbital', 'pixel-arcade', 'stealth-tactical', 'artisan-cafe',
          'steampunk-industrial', 'swiss-minimal', 'black-gold', 'enterprise-pro'
        ];
        const classics = ['biru-t2', 'biru-t4'];
        const colorways = ['tema-cyan', 'tema-emerald', 'tema-amber', 'tema-crimson', 'tema-monochrome'];

        if (signatures.includes(slug)) return '01_SIGNATURE_STYLES/dgtlnet-' + slug;
        if (classics.includes(slug)) return '02_CLASSIC_BESTSELLER/dgtlnet-' + slug;
        if (colorways.includes(slug)) return '03_COLORWAY_EDITIONS/dgtlnet-' + slug;
        return null;
      }

      function resolveProductGallery(p) {
        if (!p) return ['/images/logo.png?v=36'];

        const isBundle = (p.slug === 'bundle-30-template-hotspot-mikrotik') || (p.name && p.name.toLowerCase().includes('bundle 30'));
        if (isBundle) {
          return [
            '/hotspot-themes/01_SIGNATURE_STYLES/dgtlnet-ocean-abyss/preview-login.png',
            '/hotspot-themes/01_SIGNATURE_STYLES/dgtlnet-bento-grid/preview-login.png',
            '/hotspot-themes/01_SIGNATURE_STYLES/dgtlnet-glass-dock/preview-login.png',
            '/hotspot-themes/01_SIGNATURE_STYLES/dgtlnet-cyber-matrix/preview-login.png'
          ];
        }

        const themePath = resolveThemePathFromSlug(p.slug) || (extractPreviewUrl(p) ? extractPreviewUrl(p).replace('/shop/preview-hotspot/', '').replace(/\/login\.html.*$/i, '').replace(/^\/+/, '') : null);

        if (themePath) {
          return [
            '/hotspot-themes/' + themePath + '/preview-login.png',
            '/hotspot-themes/' + themePath + '/preview-status.png'
          ];
        }

        let list = [];
        if (p.gallery) {
          if (Array.isArray(p.gallery)) {
            list = p.gallery.filter(Boolean);
          } else if (typeof p.gallery === 'string') {
            try {
              const parsed = JSON.parse(p.gallery);
              if (Array.isArray(parsed)) list = parsed.filter(Boolean);
            } catch(e) {
              if (p.gallery.trim()) list = [p.gallery.trim()];
            }
          }
        }
        if (list.length === 0 && p.image) {
          list = [p.image];
        }

        return list.length > 0 ? list : ['/images/logo.png?v=36'];
      }

      function extractPreviewUrl(product) {
        if (!product) return null;
        if (product.specifications) {
          const match = product.specifications.match(/(?:Preview URL|URL Aper\u00e7u|URL Apercu)\s*:\s*([^\r\n]+)/i);
          if (match && match[1]) {
            return match[1].trim();
          }
        }
        if (product.description) {
          const match = product.description.match(/\/(?:hotspot-themes|shop\/preview-hotspot)\/[^\s"'>]+/i);
          if (match && match[0]) {
            return match[0].trim();
          }
        }
        const themePath = resolveThemePathFromSlug(product.slug);
        if (themePath) {
          return '/shop/preview-hotspot/' + themePath + '/login.html';
        }
        const catSlug = (product.category && product.category.slug) ? product.category.slug : (product.category_slug || '');
        if (catSlug === 'template-hotspot' || (product.name && product.name.toLowerCase().includes('template hotspot'))) {
          return '/shop/preview-hotspot/01_SIGNATURE_STYLES/dgtlnet-ocean-abyss/login.html';
        }
        return null;
      }

      function openHotspotPreview(themeName, url, productId) {
        let rawUrl = url || '/shop/preview-hotspot/01_SIGNATURE_STYLES/dgtlnet-ocean-abyss/login.html';
        let targetUrl = rawUrl;

        if (targetUrl.startsWith('/hotspot-themes/')) {
          targetUrl = '/shop/preview-hotspot/' + targetUrl.replace('/hotspot-themes/', '');
        } else if (!targetUrl.startsWith('/shop/preview-hotspot/')) {
          targetUrl = '/shop/preview-hotspot/' + targetUrl.replace(/^\/+/, '');
        }

        if (!targetUrl.endsWith('.html')) {
          targetUrl = targetUrl.replace(/\/+$/, '') + '/login.html';
        }

        window.open(targetUrl, '_blank');
      }

      function setPreviewDevice(mode) {
        $('.preview-device-btn').removeClass('active');
        if (mode === 'desktop') {
          $('#btnPreviewDesktop').addClass('active');
          $('#previewIframeWrapper').removeClass('mobile').addClass('desktop');
        } else {
          $('#btnPreviewMobile').addClass('active');
          $('#previewIframeWrapper').removeClass('desktop').addClass('mobile');
        }
      }

      function setPreviewPage(page, reload) {
        if (typeof reload === 'undefined') reload = true;
        currentPreviewPage = page;
        $('.preview-page-btn').removeClass('active');
        if (page === 'login.html') $('#btnPageLogin').addClass('active');
        else if (page === 'status.html') $('#btnPageStatus').addClass('active');
        else if (page === 'logout.html') $('#btnPageLogout').addClass('active');

        if (reload) {
          loadPreviewIframe();
        }
      }

      function loadPreviewIframe() {
        let fullUrl = currentPreviewBaseUrl;
        if (currentPreviewPage && !currentPreviewBaseUrl.endsWith('.html')) {
          fullUrl = currentPreviewBaseUrl.replace(/\/+$/, '') + '/' + currentPreviewPage;
        }
        $('#previewHotspotIframe').attr('src', fullUrl);
        $('#previewOpenNewTabBtn').attr('href', fullUrl);
      }

      var isSubmittingOrder = false;
      function submitOrder(e) {
        e.preventDefault();
        if (isSubmittingOrder) return false;

        const cart = getCart();
        if (cart.length === 0) {
          showShopToast('Keranjang masih kosong.');
          return;
        }

        const phoneVal = $('#inputPhone').val().trim();
        if (!validatePhoneRealtime(phoneVal)) {
          alert('Nomor WhatsApp belum valid (9-14 digit).');
          $('#inputPhone').focus();
          return;
        }

        isSubmittingOrder = true;
        const btn = $('#btnSubmitOrder');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses Pesanan...');

        const formData = new FormData(document.getElementById('formCheckout'));
        formData.append('items', JSON.stringify(cart));
        formData.append('cart_items', JSON.stringify(cart));
        @if(!empty($isTenantShop) && !empty($tenant))
        formData.append('tenant_id', '{{ $tenant->id }}');
        formData.append('tenant_slug', '{{ $tenant->slug }}');
        @endif

        $.ajax({
          url: '{{ $checkoutUrl ?? "/shop/checkout" }}',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          success: function(res) {
            if (res.success) {
              localStorage.removeItem(CART_STORAGE_KEY);
              updateCartBadges();
              window.location.href = res.redirect_url;
            } else {
              isSubmittingOrder = false;
              alert(res.message || 'Gagal memproses pesanan.');
              btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Buat Pesanan Sekarang &rarr;');
            }
          },
          error: function(xhr) {
            isSubmittingOrder = false;
            btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Buat Pesanan Sekarang &rarr;');
            const err = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem.';
            alert('Gagal: ' + err);
          }
        });
      }

      $(document).ready(function() {
        updateCartBadges();
        renderProducts();
        startOnbTimer();

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('checkout') || urlParams.has('cart')) {
          setTimeout(function() {
            openCartModal();
            if (urlParams.has('checkout') && getCart().length > 0) {
              showCheckoutForm();
            }
          }, 300);
        }

        function checkNavScroll() {
          if ($(window).scrollTop() > 20) {
            $('.navbar-custom').addClass('navbar-scrolled');
          } else {
            $('.navbar-custom').removeClass('navbar-scrolled');
          }
        }
        $(window).on('scroll load resize', checkNavScroll);
        checkNavScroll();
      });
    </script>
  </body>
</html>
