<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Voucher Hotspot · {{ $hotspotName ?? 'NODERA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <script src="/js/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --v-cols: 4;
            --v-gap: 2.5mm;
            --v-margin: 4mm;
            --v-border-style: dashed;
            --v-border-color: #94a3b8;
            --v-card-py: 5px;
            --v-card-px: 7px;
            --v-card-min-h: 95px;
            --v-font-title: 8.5px;
            --v-font-badge: 8px;
            --v-font-label: 6.5px;
            --v-font-code: 13px;
            --v-font-meta: 6.5px;
            --v-qr-size: 38px;
            --v-qr-display: block;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0b1118;
            color: #f1f5f9;
            padding: 12px;
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
        }

        /* ===== TOOLBAR KONTROL CETAK (NON-PRINT) ===== */
        .no-print-bar {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto 16px auto;
            background: #131d2a;
            border: 1px solid #1e2e42;
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
        }

        .bar-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
        }

        .bar-title-box {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .bar-title {
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.2px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .bar-badge {
            font-size: 9.5px;
            font-weight: 700;
            background: #1e2e42;
            color: #38bdf8;
            padding: 2px 7px;
            border-radius: 5px;
            border: 1px solid #283d56;
            white-space: nowrap;
        }

        .bar-badge.amber {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.12);
            border-color: rgba(251, 191, 36, 0.3);
        }

        .bar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* Controls */
        .ctrl-select {
            height: 36px;
            padding: 0 10px;
            font-size: 11.5px;
            font-weight: 700;
            font-family: inherit;
            color: #ffffff;
            background-color: #0b1118;
            border: 1px solid #283d56;
            border-radius: 8px;
            outline: none;
            cursor: pointer;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }

        .ctrl-select:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 36px;
            padding: 0 14px;
            font-size: 11.5px;
            font-weight: 700;
            font-family: inherit;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            white-space: nowrap;
            user-select: none;
            box-sizing: border-box;
            touch-action: manipulation;
        }

        .btn-print {
            background: #0284c7;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
        }

        .btn-print:hover, .btn-print:active {
            background: #0369a1;
            background: linear-gradient(135deg, #0369a1, #075985);
        }

        .btn-pdf {
            background: #059669;
            background: linear-gradient(135deg, #059669, #047857);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }

        .btn-pdf:hover, .btn-pdf:active {
            background: #047857;
            background: linear-gradient(135deg, #047857, #065f46);
        }

        .btn-back {
            background-color: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }

        .btn-back:hover {
            background-color: #334155;
            color: #ffffff;
        }

        /* Custom Advanced Controls */
        .bar-custom-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            padding-top: 10px;
            margin-top: 10px;
            border-top: 1px solid #1e2e42;
            align-items: center;
        }

        .ctrl-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .ctrl-label {
            font-size: 9.5px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ctrl-label span.val {
            color: #38bdf8;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
        }

        .svg-icon {
            width: 14px;
            height: 14px;
            stroke-width: 2.2;
            stroke: currentColor;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }

        .loading-spin {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== CONTAINER VOUCHER SHEET (A4 / F4) ===== */
        .sheet-wrapper {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: var(--v-margin);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            color: #0f172a;
            box-sizing: border-box;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            transition: padding 0.15s ease;
        }

        .voucher-sheet {
            display: grid;
            grid-template-columns: repeat(var(--v-cols), minmax(0, 1fr));
            gap: var(--v-gap);
            width: 100%;
            transition: gap 0.15s ease;
        }

        /* ===== VOUCHER CARD TICKET (DYNAMIC PROPORTIONAL SCALING) ===== */
        .voucher-card {
            border-width: 1px;
            border-style: var(--v-border-style);
            border-color: var(--v-border-color);
            background: #ffffff;
            padding: var(--v-card-py) var(--v-card-px);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
            border-radius: 5px;
            color: #0f172a;
            min-height: var(--v-card-min-h);
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        /* Card Header */
        .vc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 2.5px;
            gap: 4px;
        }

        .vc-brand-box {
            display: flex;
            align-items: center;
            gap: 3.5px;
            min-width: 0;
            flex: 1;
        }

        .vc-icon-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            width: calc(var(--v-font-title) + 5px);
            height: calc(var(--v-font-title) + 5px);
            border-radius: 3px;
            background: #eff6ff;
            color: #0284c7;
            flex-shrink: 0;
        }

        .vc-brand {
            font-size: var(--v-font-title);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vc-price-tag {
            font-size: var(--v-font-badge);
            font-weight: 700;
            background: #0284c7;
            color: #ffffff;
            padding: 1px 4.5px;
            border-radius: 3px;
            letter-spacing: 0.1px;
            white-space: nowrap;
            flex-shrink: 0;
            line-height: 1.2;
            max-width: 48%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Card Body */
        .vc-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            margin: 2.5px 0;
            flex: 1;
        }

        .vc-credentials {
            flex: 1;
            min-width: 0;
        }

        .vc-label {
            font-size: var(--v-font-label);
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 1px;
            line-height: 1;
        }

        .vc-code-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2px 3px;
            text-align: center;
            overflow: hidden;
        }

        .vc-code-single {
            font-size: var(--v-font-code);
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.8px;
            color: #0f172a;
            line-height: 1.15;
            word-break: break-all;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vc-up-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5px;
        }

        .vc-up-val {
            font-size: var(--v-font-title);
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vc-login-hint {
            font-size: var(--v-font-label);
            color: #64748b;
            margin-top: 1.5px;
            font-weight: 600;
            line-height: 1.1;
            align-items: center;
            gap: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
        }

        .vc-login-hint strong {
            color: #0284c7;
        }

        /* QR Code Container */
        .vc-qr {
            display: var(--v-qr-display);
            width: var(--v-qr-size);
            height: var(--v-qr-size);
            flex-shrink: 0;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 1px;
            background: #ffffff;
            transition: all 0.15s ease;
        }

        .vc-qr canvas, .vc-qr img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }

        /* Card Footer */
        .vc-footer {
            border-top: 1px dashed #e2e8f0;
            padding-top: 2px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: var(--v-font-meta);
        }

        .vc-limit-pill {
            font-weight: 700;
            color: #0284c7;
            background: #f0f9ff;
            padding: 0.5px 3.5px;
            border-radius: 2.5px;
            border: 1px solid #e0f2fe;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 50%;
            line-height: 1.2;
        }

        .vc-meta {
            color: #94a3b8;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: var(--v-font-meta);
            white-space: nowrap;
            max-width: 50%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===== CONTAINER STRUK THERMAL ===== */
        .thermal-sheet {
            max-width: 320px;
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: 14px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            color: #0f172a;
            box-sizing: border-box;
        }

        .thermal-card {
            padding: 10px 0;
            border-bottom: 1.5px dashed #94a3b8;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .thermal-card:last-child {
            border-bottom: 0;
        }

        .th-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }

        .th-sub {
            font-size: 9px;
            color: #64748b;
            font-weight: 600;
            margin-top: 1px;
            margin-bottom: 5px;
        }

        .th-divider {
            border-top: 1.5px dashed #64748b;
            margin: 5px 0;
        }

        .th-code-box {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px;
            margin: 5px 0;
        }

        .th-code {
            font-size: 16px;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 1.5px;
            color: #0f172a;
        }

        .th-price {
            font-size: 13px;
            font-weight: 800;
            color: #0284c7;
        }

        .th-meta {
            font-size: 9px;
            font-weight: 700;
            margin-top: 2px;
            color: #334155;
        }

        .th-qr {
            width: 80px;
            height: 80px;
            margin: 6px auto;
            border: 1px solid #e2e8f0;
            padding: 2px;
            border-radius: 5px;
            background: #fff;
        }

        .th-qr canvas, .th-qr img {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* ===== MOBILE SCREEN RESPONSIVE (LIVE PREVIEW) ===== */
        @media screen and (max-width: 768px) {
            body {
                padding: 6px;
            }

            .no-print-bar {
                padding: 10px;
                border-radius: 10px;
                margin-bottom: 10px;
            }

            .bar-main {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .bar-title-box {
                justify-content: space-between;
                width: 100%;
            }

            .bar-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                width: 100%;
            }

            .bar-actions select {
                grid-column: span 2;
                width: 100% !important;
                height: 38px;
            }

            .btn-action {
                width: 100%;
                height: 38px;
            }

            .bar-custom-controls {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }

            .sheet-wrapper {
                border-radius: 8px;
            }

            .voucher-sheet {
                min-width: calc(var(--v-cols) * 70px);
            }
        }

        /* ===== PRINT STYLES (EXACT MULTI-COLUMN A4/F4 PAPER MEASUREMENT) ===== */
        @media print {
            html, body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                color: #000000 !important;
                overflow: visible !important;
                height: auto !important;
                width: auto !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .sheet-wrapper {
                max-width: 100% !important;
                padding: var(--v-margin) !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                width: 100% !important;
                overflow: visible !important;
            }

            .voucher-sheet {
                display: grid !important;
                grid-template-columns: repeat(var(--v-cols), minmax(0, 1fr)) !important;
                gap: var(--v-gap) !important;
                width: 100% !important;
                min-width: 0 !important;
            }

            .voucher-card {
                box-shadow: none !important;
                border-color: var(--v-border-color) !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                background: #ffffff !important;
                color: #000000 !important;
            }

            .thermal-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 auto !important;
                max-width: 100% !important;
            }

            @page {
                size: auto;
                margin: var(--v-margin);
            }
        }
    </style>
</head>
<body>

    <!-- ===== TOP CONTROLS TOOLBAR ===== -->
    <div class="no-print-bar">
        <div class="bar-main">
            <!-- Title & Batch Info -->
            <div class="bar-title-box">
                <span class="bar-title">
                    <svg class="svg-icon" style="color: #38bdf8;" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                    Cetak Voucher
                </span>
                <span class="bar-badge">{{ count($vouchers) }} Voucher</span>
                @if($batchId)
                    <span class="bar-badge" style="font-family: 'JetBrains Mono', monospace;">Batch: {{ $batchId }}</span>
                @endif
                <span class="bar-badge amber" id="sheet-calc-badge">24 / Lembar A4</span>
            </div>

            <!-- Actions & Dropdowns -->
            <div class="bar-actions">
                @if($template !== 'thermal')
                    <!-- Dropdown Preset Kepadatan -->
                    <select id="sel-preset" class="ctrl-select" onchange="applyPresetFromDropdown(this.value)">
                        <option value="dense" selected>📋 Kompak Hemat (24 vch/lembar A4)</option>
                        <option value="max">⚡ Super Rapat (36 vch/lembar A4)</option>
                        <option value="standard">🎟️ Standar (15 vch/lembar A4)</option>
                        <option value="zerogap">✂️ 0 Gap Potong Cepat (24 vch/lembar)</option>
                        <option value="custom">⚙️ Kustomisasi Ukuran...</option>
                    </select>
                @endif

                <!-- Dropdown Format Kertas -->
                <select
                    class="ctrl-select"
                    onchange="window.location.href = this.value"
                >
                    <option value="?{{ http_build_query(array_merge(request()->all(), ['template' => 'a4'])) }}" {{ $template !== 'thermal' ? 'selected' : '' }}>
                        Format A4 / F4 (Grid)
                    </option>
                    <option value="?{{ http_build_query(array_merge(request()->all(), ['template' => 'thermal'])) }}" {{ $template === 'thermal' ? 'selected' : '' }}>
                        Format Thermal POS (58/80mm)
                    </option>
                </select>

                <!-- Tombol Cetak Browser -->
                <button type="button" id="btn-print-action" onclick="doPrint(event)" class="btn-action btn-print" title="Cetak via dialog printer browser bawaan">
                    <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                    <span>Cetak</span>
                </button>

                <!-- Tombol Download Dokumen PDF Siap Cetak -->
                <button type="button" id="btn-download-pdf" onclick="downloadAsPDF()" class="btn-action btn-pdf" title="Download langsung dokumen PDF A4 siap cetak (100% Berhasil di HP & Laptop)">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Download PDF</span>
                </button>

                <!-- Tombol Kembali -->
                <a href="/admin/voucher" class="btn-action btn-back">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Kembali</span>
                </a>
            </div>
        </div>

        @if($template !== 'thermal')
            <!-- Custom Advanced Controls (Toggleable via Dropdown Preset 'custom') -->
            <div id="custom-controls-box" class="bar-custom-controls" style="display: none;">
                <!-- Jumlah Kolom -->
                <div class="ctrl-group">
                    <label class="ctrl-label">
                        Kolom Per Lembar <span class="val" id="val-cols">4</span>
                    </label>
                    <select id="sel-cols" class="ctrl-select" onchange="updateCols(this.value)">
                        <option value="2">2 Kolom (Besar)</option>
                        <option value="3">3 Kolom (Standar)</option>
                        <option value="4" selected>4 Kolom (Hemat)</option>
                        <option value="5">5 Kolom (Rapat)</option>
                        <option value="6">6 Kolom (Super Rapat)</option>
                    </select>
                </div>

                <!-- Skala Teks / Font -->
                <div class="ctrl-group">
                    <label class="ctrl-label">Ukuran Teks / Font</label>
                    <select id="sel-font-scale" class="ctrl-select" onchange="updateFontScale(this.value)">
                        <option value="small">Kecil (Kompak)</option>
                        <option value="normal" selected>Proporsional (Otomatis)</option>
                        <option value="large">Besar (Ekstra Jelas)</option>
                    </select>
                </div>

                <!-- Jarak Antar Voucher (Gap) -->
                <div class="ctrl-group">
                    <label class="ctrl-label">
                        Jarak Antar Kartu <span class="val" id="val-gap">2.5 mm</span>
                    </label>
                    <select id="sel-gap" class="ctrl-select" onchange="updateGap(this.value)">
                        <option value="0mm">0 mm (Rapat Tanpa Celah)</option>
                        <option value="1mm">1 mm (Sangat Rapat)</option>
                        <option value="2.5mm" selected>2.5 mm (Hemat Standar)</option>
                        <option value="4mm">4 mm (Longgar)</option>
                        <option value="6mm">6 mm (Lebar)</option>
                    </select>
                </div>

                <!-- Margin Kertas -->
                <div class="ctrl-group">
                    <label class="ctrl-label">
                        Margin Kertas <span class="val" id="val-margin">4 mm</span>
                    </label>
                    <select id="sel-margin" class="ctrl-select" onchange="updateMargin(this.value)">
                        <option value="2mm">2 mm (Sangat Tipis)</option>
                        <option value="4mm" selected>4 mm (Standar Printer)</option>
                        <option value="8mm">8 mm (Sedang)</option>
                    </select>
                </div>

                <!-- Toggle QR Code -->
                <div class="ctrl-group">
                    <label class="ctrl-label">QR Code Login</label>
                    <select id="sel-qr" class="ctrl-select" onchange="updateQr(this.value)">
                        <option value="block" selected>Tampilkan QR Code</option>
                        <option value="none">Sembunyikan (Lebih Ringkas)</option>
                    </select>
                </div>

                <!-- Garis Potong -->
                <div class="ctrl-group">
                    <label class="ctrl-label">Garis Batas Potong</label>
                    <select id="sel-border" class="ctrl-select" onchange="updateBorder(this.value)">
                        <option value="dashed" selected>Garis Putus (Dashed)</option>
                        <option value="solid">Garis Solid Tipis (Solid)</option>
                        <option value="none">Tanpa Garis (Clean)</option>
                    </select>
                </div>
            </div>
        @endif
    </div>

    @if($template === 'thermal')
        <!-- ===== STRUK THERMAL POS FORMAT ===== -->
        <div class="thermal-sheet" id="printable-sheet-container">
            @forelse($vouchers as $idx => $v)
                @php
                    $isSingle = empty($v->password) || $v->username === $v->password;
                    $qrLoginUrl = "http://" . ($dnsName ?: 'nodera.login') . "/login?username=" . urlencode($v->username) . "&password=" . urlencode($v->password ?: $v->username);
                @endphp
                <div class="thermal-card">
                    <div class="th-title">{{ $hotspotName ?: 'NODERA HOTSPOT' }}</div>
                    <div class="th-sub">{{ $phone ? 'CS / WA: ' . $phone : 'Layanan Hotspot WiFi Cepat' }}</div>
                    <div class="th-divider"></div>

                    @if($isSingle)
                        <div style="font-size: 8px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-top: 2px;">KODE VOUCHER</div>
                        <div class="th-code-box">
                            <div class="th-code">{{ $v->username }}</div>
                        </div>
                    @else
                        <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; font-family: 'JetBrains Mono', monospace; margin: 6px 0;">
                            <span>USER: {{ $v->username }}</span>
                            <span>PASS: {{ $v->password }}</span>
                        </div>
                    @endif

                    <div class="th-meta">
                        @if($v->price > 0)
                            <div class="th-price">
                                Rp {{ number_format($v->price, 0, ',', '.') }}
                            </div>
                        @endif
                        <div style="font-size: 8.5px; color: #475569; margin-top: 2px;">
                            Paket: <strong style="color: #0284c7;">{{ $v->profile }}</strong>
                            @if($v->time_limit) · {{ $v->time_limit }} @endif
                            @if($v->data_limit) · {{ $v->data_limit }} @endif
                        </div>
                    </div>

                    <div class="th-qr">
                        <canvas class="qr-canvas" data-value="{{ $qrLoginUrl }}" data-size="120"></canvas>
                    </div>

                    <div style="font-size: 7.5px; color: #64748b; line-height: 1.3;">
                        Buka browser: <strong>http://{{ $dnsName ?: 'nodera.login' }}</strong><br>
                        Atau scan QR di atas untuk login otomatis
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 40px; color: #94a3b8; font-size: 13px;">Tidak ada voucher untuk dicetak.</div>
            @endforelse
        </div>
    @else
        <!-- ===== KERTAS A4 / F4 MULTI-VOUCHER GRID ===== -->
        <div class="sheet-wrapper" id="printable-sheet-container">
            <div class="voucher-sheet">
                @forelse($vouchers as $idx => $v)
                    @php
                        $isSingle = empty($v->password) || $v->username === $v->password;
                        $qrLoginUrl = "http://" . ($dnsName ?: 'nodera.login') . "/login?username=" . urlencode($v->username) . "&password=" . urlencode($v->password ?: $v->username);
                    @endphp
                    <div class="voucher-card">
                        <!-- Header -->
                        <div class="vc-header">
                            <div class="vc-brand-box">
                                @if($logo)
                                    <img src="{{ $logo }}" alt="" style="height: calc(var(--v-font-title) + 4px); max-width: 36px; object-fit: contain;">
                                @else
                                    <div class="vc-icon-pill">
                                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
                                    </div>
                                @endif
                                <span class="vc-brand">{{ $hotspotName ?: 'NODERA HOTSPOT' }}</span>
                            </div>

                            @if($v->price > 0)
                                <div class="vc-price-tag">
                                    Rp {{ number_format($v->price, 0, ',', '.') }}
                                </div>
                            @else
                                <div class="vc-price-tag">
                                    {{ $v->profile }}
                                </div>
                            @endif
                        </div>

                        <!-- Body (Voucher Hero Code + QR) -->
                        <div class="vc-body">
                            <div class="vc-credentials">
                                @if($isSingle)
                                    <div class="vc-label">KODE VOUCHER</div>
                                    <div class="vc-code-box">
                                        <div class="vc-code-single">{{ $v->username }}</div>
                                    </div>
                                @else
                                    <div class="vc-up-grid">
                                        <div>
                                            <div class="vc-label">USER</div>
                                            <div class="vc-code-box"><div class="vc-up-val">{{ $v->username }}</div></div>
                                        </div>
                                        <div>
                                            <div class="vc-label">PASS</div>
                                            <div class="vc-code-box"><div class="vc-up-val">{{ $v->password }}</div></div>
                                        </div>
                                    </div>
                                @endif

                                <div class="vc-login-hint">
                                    <svg style="width: 7px; height: 7px; flex-shrink: 0; stroke: currentColor; fill: none;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    <span>Login: <strong>{{ $dnsName ?: 'nodera.login' }}</strong></span>
                                </div>
                            </div>

                            <!-- QR Code Canvas (Instant Local QRious) -->
                            <div class="vc-qr" title="Scan untuk Login Otomatis">
                                <canvas class="qr-canvas" data-value="{{ $qrLoginUrl }}" data-size="90"></canvas>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="vc-footer">
                            <span class="vc-limit-pill">
                                @if($v->time_limit) {{ $v->time_limit }} @endif
                                @if($v->data_limit) · {{ $v->data_limit }} @endif
                                @if(!$v->time_limit && !$v->data_limit) Unlimited @endif
                            </span>
                            <span class="vc-meta">
                                #{{ $idx + 1 }} · {{ $v->profile }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div style="grid-column: span var(--v-cols); text-align: center; padding: 60px; color: #94a3b8; font-size: 13px;">
                        Tidak ada voucher untuk dicetak. Silakan generate voucher terlebih dahulu.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- QRious Library Inline (100% Offline & Instant) -->
    <script>
        /*! QRious v4.0.2 | (C) Alasdair Mercer | GPL v3 */
        !function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e():"function"==typeof define&&define.amd?define(e):t.QRious=e()}(this,function(){"use strict";function t(t,e){var n;return"function"==typeof Object.create?n=Object.create(t):(s.prototype=t,n=new s,s.prototype=null),e&&i(!0,n,e),n}function e(e,n,s,r){var o=this;return"string"!=typeof e&&(r=s,s=n,n=e,e=null),"function"!=typeof n&&(r=s,s=n,n=function(){return o.apply(this,arguments)}),i(!1,n,o,r),n.prototype=t(o.prototype,s),n.prototype.constructor=n,n.class_=e||o.class_,n.super_=o,n}function i(t,e,i){for(var n,s,a=0,h=(i=o.call(arguments,2)).length;a<h;a++){s=i[a];for(n in s)t&&!r.call(s,n)||(e[n]=s[n])}}function n(){}var s=function(){},r=Object.prototype.hasOwnProperty,o=Array.prototype.slice,a=e;n.class_="Nevis",n.super_=Object,n.extend=a;var h=n,f=h.extend(function(t,e,i){this.qrious=t,this.element=e,this.element.qrious=t,this.enabled=Boolean(i)},{draw:function(t){},getElement:function(){return this.enabled||(this.enabled=!0,this.render()),this.element},getModuleSize:function(t){var e=this.qrious,i=e.padding||0,n=Math.floor((e.size-2*i)/t.width);return Math.max(1,n)},getOffset:function(t){var e=this.qrious,i=e.padding;if(null!=i)return i;var n=this.getModuleSize(t),s=Math.floor((e.size-n*t.width)/2);return Math.max(0,s)},render:function(t){this.enabled&&(this.resize(),this.reset(),this.draw(t))},reset:function(){},resize:function(){}}),c=f.extend({draw:function(t){var e,i,n=this.qrious,s=this.getModuleSize(t),r=this.getOffset(t),o=this.element.getContext("2d");for(o.fillStyle=n.foreground,o.globalAlpha=n.foregroundAlpha,e=0;e<t.width;e++)for(i=0;i<t.width;i++)t.buffer[i*t.width+e]&&o.fillRect(s*e+r,s*i+r,s,s)},reset:function(){var t=this.qrious,e=this.element.getContext("2d"),i=t.size;e.lineWidth=1,e.clearRect(0,0,i,i),e.fillStyle=t.background,e.globalAlpha=t.backgroundAlpha,e.fillRect(0,0,i,i)},resize:function(){var t=this.element;t.width=t.height=this.qrious.size}}),u=h.extend(null,{BLOCK:[0,11,15,19,23,27,31,16,18,20,22,24,26,28,20,22,24,24,26,28,28,22,24,24,26,26,28,28,24,24,26,26,26,28,28,24,26,26,26,28,28]}),l=h.extend(null,{BLOCKS:[1,0,19,7,1,0,16,10,1,0,13,13,1,0,9,17,1,0,34,10,1,0,28,16,1,0,22,22,1,0,16,28,1,0,55,15,1,0,44,26,2,0,17,18,2,0,13,22,1,0,80,20,2,0,32,18,2,0,24,26,4,0,9,16,1,0,108,26,2,0,43,24,2,2,15,18,2,2,11,22,2,0,68,18,4,0,27,16,4,0,19,24,4,0,15,28,2,0,78,20,4,0,31,18,2,4,14,18,4,1,13,26,2,0,97,24,2,2,38,22,4,2,18,22,4,2,14,26,2,0,116,30,3,2,36,22,4,4,16,20,4,4,12,24,2,2,68,18,4,1,43,26,6,2,19,24,6,2,15,28,4,0,81,20,1,4,50,30,4,4,22,28,3,8,12,24,2,2,92,24,6,2,36,22,4,6,20,26,7,4,14,28,4,0,107,26,8,1,37,22,8,4,20,24,12,4,11,22,3,1,115,30,4,5,40,24,11,5,16,20,11,5,12,24,5,1,87,22,5,5,41,24,5,7,24,30,11,7,12,24,5,1,98,24,7,3,45,28,15,2,19,24,3,13,15,30,1,5,107,28,10,1,46,28,1,15,22,28,2,17,14,28,5,1,120,30,9,4,43,26,17,1,22,28,2,19,14,28,3,4,113,28,3,11,44,26,17,4,21,26,9,16,13,26,3,5,107,28,3,13,41,26,15,5,24,30,15,10,15,28,4,4,116,28,17,0,42,26,17,6,22,28,19,6,16,30,2,7,111,28,17,0,46,28,7,16,24,30,34,0,13,24,4,5,121,30,4,14,47,28,11,14,24,30,16,14,15,30,6,4,117,30,6,14,45,28,11,16,24,30,30,2,16,30,8,4,106,26,8,13,47,28,7,22,24,30,22,13,15,30,10,2,114,28,19,4,46,28,28,6,22,28,33,4,16,30,8,4,122,30,22,3,45,28,8,26,23,30,12,28,15,30,3,10,117,30,3,23,45,28,4,31,24,30,11,31,15,30,7,7,116,30,21,7,45,28,1,37,23,30,19,26,15,30,5,10,115,30,19,10,47,28,15,25,24,30,23,25,15,30,13,3,115,30,2,29,46,28,42,1,24,30,23,28,15,30,17,0,115,30,10,23,46,28,10,35,24,30,19,35,15,30,17,1,115,30,14,21,46,28,29,19,24,30,11,46,15,30,13,6,115,30,14,23,46,28,44,7,24,30,59,1,16,30,12,7,121,30,12,26,47,28,39,14,24,30,22,41,15,30,6,14,121,30,6,34,47,28,46,10,24,30,2,64,15,30,17,4,122,30,29,14,46,28,49,10,24,30,24,46,15,30,4,18,122,30,13,32,46,28,48,14,24,30,42,32,15,30,20,4,117,30,40,7,47,28,43,22,24,30,10,67,15,30,19,6,118,30,18,31,47,28,34,34,24,30,20,61,15,30],FINAL_FORMAT:[30660,29427,32170,30877,26159,25368,27713,26998,21522,20773,24188,23371,17913,16590,20375,19104,13663,12392,16177,14854,9396,8579,11994,11245,5769,5054,7399,6608,1890,597,3340,2107],LEVELS:{L:1,M:2,Q:3,H:4}}),_=h.extend(null,{EXPONENT:[1,2,4,8,16,32,64,128,29,58,116,232,205,135,19,38,76,152,45,90,180,117,234,201,143,3,6,12,24,48,96,192,157,39,78,156,37,74,148,53,106,212,181,119,238,193,159,35,70,140,5,10,20,40,80,160,93,186,105,210,185,111,222,161,95,190,97,194,153,47,94,188,101,202,137,15,30,60,120,240,253,231,211,187,107,214,177,127,254,225,223,163,91,182,113,226,217,175,67,134,17,34,68,136,13,26,52,104,208,189,103,206,129,31,62,124,248,237,199,147,59,118,236,197,151,51,102,204,133,23,46,92,184,109,218,169,79,158,33,66,132,21,42,84,168,77,154,41,82,164,85,170,73,146,57,114,228,213,183,115,230,209,191,99,198,145,63,126,252,229,215,179,123,246,241,255,227,219,171,75,150,49,98,196,149,55,110,220,165,87,174,65,130,25,50,100,200,141,7,14,28,56,112,224,221,167,83,166,81,162,89,178,121,242,249,239,195,155,43,86,172,69,138,9,18,36,72,144,61,122,244,245,247,243,251,235,203,139,11,22,44,88,176,125,250,233,207,131,27,54,108,216,173,71,142,0],LOG:[255,0,1,25,2,50,26,198,3,223,51,238,27,104,199,75,4,100,224,14,52,141,239,129,28,193,105,248,200,8,76,113,5,138,101,47,225,36,15,33,53,147,142,218,240,18,130,69,29,181,194,125,106,39,249,185,201,154,9,120,77,228,114,166,6,191,139,98,102,221,48,253,226,152,37,179,16,145,34,136,54,208,148,206,143,150,219,189,241,210,19,92,131,56,70,64,30,66,182,163,195,72,126,110,107,58,40,84,250,133,186,61,202,94,155,159,10,21,121,43,78,212,229,172,115,243,167,87,7,112,192,247,140,128,99,13,103,74,222,237,49,197,254,24,227,165,153,119,38,184,180,124,17,68,146,217,35,32,137,46,55,63,209,91,149,188,207,205,144,135,151,178,220,252,190,97,242,86,211,171,20,42,93,158,132,60,57,83,71,109,65,162,31,45,67,216,183,123,164,118,196,23,73,236,127,12,111,246,108,161,59,82,41,157,85,170,251,96,134,177,187,204,62,90,203,89,95,176,156,169,160,81,11,245,22,235,122,117,44,215,79,174,213,233,230,231,173,232,116,214,244,234,168,80,88,175]}),d=h.extend(null,{BLOCK:[3220,1468,2713,1235,3062,1890,2119,1549,2344,2936,1117,2583,1330,2470,1667,2249,2028,3780,481,4011,142,3098,831,3445,592,2517,1776,2234,1951,2827,1070,2660,1345,3177]}),v=h.extend(function(t){var e,i,n,s,r,o=t.value.length;for(this._badness=[],this._level=l.LEVELS[t.level],this._polynomial=[],this._value=t.value,this._version=0,this._stringBuffer=[];this._version<40&&(this._version++,n=4*(this._level-1)+16*(this._version-1),s=l.BLOCKS[n++],r=l.BLOCKS[n++],e=l.BLOCKS[n++],i=l.BLOCKS[n],n=e*(s+r)+r-3+(this._version<=9),!(o<=n)););this._dataBlock=e,this._eccBlock=i,this._neccBlock1=s,this._neccBlock2=r;var a=this.width=17+4*this._version;this.buffer=v._createArray(a*a),this._ecc=v._createArray(e+(e+i)*(s+r)+r),this._mask=v._createArray((a*(a+1)+1)/2),this._insertFinders(),this._insertAlignments(),this.buffer[8+a*(a-8)]=1,this._insertTimingGap(),this._reverseMask(),this._insertTimingRowAndColumn(),this._insertVersion(),this._syncMask(),this._convertBitStream(o),this._calculatePolynomial(),this._appendEccToData(),this._interleaveBlocks(),this._pack(),this._finish()},{_addAlignment:function(t,e){var i,n=this.buffer,s=this.width;for(n[t+s*e]=1,i=-2;i<2;i++)n[t+i+s*(e-2)]=1,n[t-2+s*(e+i+1)]=1,n[t+2+s*(e+i)]=1,n[t+i+1+s*(e+2)]=1;for(i=0;i<2;i++)this._setMask(t-1,e+i),this._setMask(t+1,e-i),this._setMask(t-i,e-1),this._setMask(t+i,e+1)},_appendData:function(t,e,i,n){var s,r,o,a=this._polynomial,h=this._stringBuffer;for(r=0;r<n;r++)h[i+r]=0;for(r=0;r<e;r++){if(255!==(s=_.LOG[h[t+r]^h[i]]))for(o=1;o<n;o++)h[i+o-1]=h[i+o]^_.EXPONENT[v._modN(s+a[n-o])];else for(o=i;o<i+n;o++)h[o]=h[o+1];h[i+n-1]=255===s?0:_.EXPONENT[v._modN(s+a[0])]}},_appendEccToData:function(){var t,e=0,i=this._dataBlock,n=this._calculateMaxLength(),s=this._eccBlock;for(t=0;t<this._neccBlock1;t++)this._appendData(e,i,n,s),e+=i,n+=s;for(t=0;t<this._neccBlock2;t++)this._appendData(e,i+1,n,s),e+=i+1,n+=s},_applyMask:function(t){var e,i,n,s,r=this.buffer,o=this.width;switch(t){case 0:for(s=0;s<o;s++)for(n=0;n<o;n++)n+s&1||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 1:for(s=0;s<o;s++)for(n=0;n<o;n++)1&s||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 2:for(s=0;s<o;s++)for(e=0,n=0;n<o;n++,e++)3===e&&(e=0),e||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 3:for(i=0,s=0;s<o;s++,i++)for(3===i&&(i=0),e=i,n=0;n<o;n++,e++)3===e&&(e=0),e||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 4:for(s=0;s<o;s++)for(e=0,n=0,i=s>>1&1;n<o;n++,e++)3===e&&(e=0,i=!i),i||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 5:for(i=0,s=0;s<o;s++,i++)for(3===i&&(i=0),e=0,n=0;n<o;n++,e++)3===e&&(e=0),(n&s&1)+!(!e|!i)||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 6:for(i=0,s=0;s<o;s++,i++)for(3===i&&(i=0),e=0,n=0;n<o;n++,e++)3===e&&(e=0),(n&s&1)+(e&&e===i)&1||this._isMasked(n,s)||(r[n+s*o]^=1);break;case 7:for(i=0,s=0;s<o;s++,i++)for(3===i&&(i=0),e=0,n=0;n<o;n++,e++)3===e&&(e=0),(e&&e===i)+(n+s&1)&1||this._isMasked(n,s)||(r[n+s*o]^=1)}},_calculateMaxLength:function(){return this._dataBlock*(this._neccBlock1+this._neccBlock2)+this._neccBlock2},_calculatePolynomial:function(){var t,e,i=this._eccBlock,n=this._polynomial;for(n[0]=1,t=0;t<i;t++){for(n[t+1]=1,e=t;e>0;e--)n[e]=n[e]?n[e-1]^_.EXPONENT[v._modN(_.LOG[n[e]]+t)]:n[e-1];n[0]=_.EXPONENT[v._modN(_.LOG[n[0]]+t)]}for(t=0;t<=i;t++)n[t]=_.LOG[n[t]]},_checkBadness:function(){var t,e,i,n,s,r=0,o=this._badness,a=this.buffer,h=this.width;for(s=0;s<h-1;s++)for(n=0;n<h-1;n++)(a[n+h*s]&&a[n+1+h*s]&&a[n+h*(s+1)]&&a[n+1+h*(s+1)]||!(a[n+h*s]||a[n+1+h*s]||a[n+h*(s+1)]||a[n+1+h*(s+1)]))&&(r+=v.N2);var f=0;for(s=0;s<h;s++){for(i=0,o[0]=0,t=0,n=0;n<h;n++)t===(e=a[n+h*s])?o[i]++:o[++i]=1,f+=(t=e)?1:-1;r+=this._getBadness(i)}f<0&&(f=-f);var c=0,u=f;for(u+=u<<2,u<<=1;u>h*h;)u-=h*h,c++;for(r+=c*v.N4,n=0;n<h;n++){for(i=0,o[0]=0,t=0,s=0;s<h;s++)t===(e=a[n+h*s])?o[i]++:o[++i]=1,t=e;r+=this._getBadness(i)}return r},_convertBitStream:function(t){var e,i,n=this._ecc,s=this._version;for(i=0;i<t;i++)n[i]=this._value.charCodeAt(i);var r=this._stringBuffer=n.slice(),o=this._calculateMaxLength();t>=o-2&&(t=o-2,s>9&&t--);var a=t;if(s>9){for(r[a+2]=0,r[a+3]=0;a--;)e=r[a],r[a+3]|=255&e<<4,r[a+2]=e>>4;r[2]|=255&t<<4,r[1]=t>>4,r[0]=64|t>>12}else{for(r[a+1]=0,r[a+2]=0;a--;)e=r[a],r[a+2]|=255&e<<4,r[a+1]=e>>4;r[1]|=255&t<<4,r[0]=64|t>>4}for(a=t+3-(s<10);a<o;)r[a++]=236,r[a++]=17},_getBadness:function(t){var e,i=0,n=this._badness;for(e=0;e<=t;e++)n[e]>=5&&(i+=v.N1+n[e]-5);for(e=3;e<t-1;e+=2)n[e-2]===n[e+2]&&n[e+2]===n[e-1]&&n[e-1]===n[e+1]&&3*n[e-1]===n[e]&&(0===n[e-3]||e+3>t||3*n[e-3]>=4*n[e]||3*n[e+3]>=4*n[e])&&(i+=v.N3);return i},_finish:function(){this._stringBuffer=this.buffer.slice();var t,e,i=0,n=3e4;for(e=0;e<8&&(this._applyMask(e),(t=this._checkBadness())<n&&(n=t,i=e),7!==i);e++)this.buffer=this._stringBuffer.slice();i!==e&&this._applyMask(i),n=l.FINAL_FORMAT[i+(this._level-1<<3)];var s=this.buffer,r=this.width;for(e=0;e<8;e++,n>>=1)1&n&&(s[r-1-e+8*r]=1,e<6?s[8+r*e]=1:s[8+r*(e+1)]=1);for(e=0;e<7;e++,n>>=1)1&n&&(s[8+r*(r-7+e)]=1,e?s[6-e+8*r]=1:s[7+8*r]=1)},_interleaveBlocks:function(){var t,e,i=this._dataBlock,n=this._ecc,s=this._eccBlock,r=0,o=this._calculateMaxLength(),a=this._neccBlock1,h=this._neccBlock2,f=this._stringBuffer;for(t=0;t<i;t++){for(e=0;e<a;e++)n[r++]=f[t+e*i];for(e=0;e<h;e++)n[r++]=f[a*i+t+e*(i+1)]}for(e=0;e<h;e++)n[r++]=f[a*i+t+e*(i+1)];for(t=0;t<s;t++)for(e=0;e<a+h;e++)n[r++]=f[o+t+e*s];this._stringBuffer=n},_insertAlignments:function(){var t,e,i,n=this._version,s=this.width;if(n>1)for(t=u.BLOCK[n],i=s-7;;){for(e=s-7;e>t-3&&(this._addAlignment(e,i),!(e<t));)e-=t;if(i<=t+9)break;i-=t,this._addAlignment(6,i),this._addAlignment(i,6)}},_insertFinders:function(){var t,e,i,n,s=this.buffer,r=this.width;for(t=0;t<3;t++){for(e=0,n=0,1===t&&(e=r-7),2===t&&(n=r-7),s[n+3+r*(e+3)]=1,i=0;i<6;i++)s[n+i+r*e]=1,s[n+r*(e+i+1)]=1,s[n+6+r*(e+i)]=1,s[n+i+1+r*(e+6)]=1;for(i=1;i<5;i++)this._setMask(n+i,e+1),this._setMask(n+1,e+i+1),this._setMask(n+5,e+i),this._setMask(n+i+1,e+5);for(i=2;i<4;i++)s[n+i+r*(e+2)]=1,s[n+2+r*(e+i+1)]=1,s[n+4+r*(e+i)]=1,s[n+i+1+r*(e+4)]=1}},_insertTimingGap:function(){var t,e,i=this.width;for(e=0;e<7;e++)this._setMask(7,e),this._setMask(i-8,e),this._setMask(7,e+i-7);for(t=0;t<8;t++)this._setMask(t,7),this._setMask(t+i-8,7),this._setMask(t,i-8)},_insertTimingRowAndColumn:function(){var t,e=this.buffer,i=this.width;for(t=0;t<i-14;t++)1&t?(this._setMask(8+t,6),this._setMask(6,8+t)):(e[8+t+6*i]=1,e[6+i*(8+t)]=1)},_insertVersion:function(){var t,e,i,n,s=this.buffer,r=this._version,o=this.width;if(r>6)for(t=d.BLOCK[r-7],e=17,i=0;i<6;i++)for(n=0;n<3;n++,e--)1&(e>11?r>>e-12:t>>e)?(s[5-i+o*(2-n+o-11)]=1,s[2-n+o-11+o*(5-i)]=1):(this._setMask(5-i,2-n+o-11),this._setMask(2-n+o-11,5-i))},_isMasked:function(t,e){var i=v._getMaskBit(t,e);return 1===this._mask[i]},_pack:function(){var t,e,i,n=1,s=1,r=this.width,o=r-1,a=r-1,h=(this._dataBlock+this._eccBlock)*(this._neccBlock1+this._neccBlock2)+this._neccBlock2;for(e=0;e<h;e++)for(t=this._stringBuffer[e],i=0;i<8;i++,t<<=1){128&t&&(this.buffer[o+r*a]=1);do{s?o--:(o++,n?0!==a?a--:(n=!n,6===(o-=2)&&(o--,a=9)):a!==r-1?a++:(n=!n,6===(o-=2)&&(o--,a-=8))),s=!s}while(this._isMasked(o,a))}},_reverseMask:function(){var t,e,i=this.width;for(t=0;t<9;t++)this._setMask(t,8);for(t=0;t<8;t++)this._setMask(t+i-8,8),this._setMask(8,t);for(e=0;e<7;e++)this._setMask(8,e+i-7)},_setMask:function(t,e){var i=v._getMaskBit(t,e);this._mask[i]=1},_syncMask:function(){var t,e,i=this.width;for(e=0;e<i;e++)for(t=0;t<=e;t++)this.buffer[t+i*e]&&this._setMask(t,e)}},{_createArray:function(t){var e,i=[];for(e=0;e<t;e++)i[e]=0;return i},_getMaskBit:function(t,e){var i;return t>e&&(i=t,t=e,e=i),i=e,i+=e*e,i>>=1,i+=t},_modN:function(t){for(;t>=255;)t=((t-=255)>>8)+(255&t);return t},N1:3,N2:3,N3:40,N4:10}),p=v,m=f.extend({draw:function(){this.element.src=this.qrious.toDataURL()},reset:function(){this.element.src=""},resize:function(){var t=this.element;t.width=t.height=this.qrious.size}}),g=h.extend(function(t,e,i,n){this.name=t,this.modifiable=Boolean(e),this.defaultValue=i,this._valueTransformer=n},{transform:function(t){var e=this._valueTransformer;return"function"==typeof e?e(t,this):t}}),k=h.extend(null,{abs:function(t){return null!=t?Math.abs(t):null},hasOwn:function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},noop:function(){},toUpperCase:function(t){return null!=t?t.toUpperCase():null}}),w=h.extend(function(t){this.options={},t.forEach(function(t){this.options[t.name]=t},this)},{exists:function(t){return null!=this.options[t]},get:function(t,e){return w._get(this.options[t],e)},getAll:function(t){var e,i=this.options,n={};for(e in i)k.hasOwn(i,e)&&(n[e]=w._get(i[e],t));return n},init:function(t,e,i){"function"!=typeof i&&(i=k.noop);var n,s;for(n in this.options)k.hasOwn(this.options,n)&&(s=this.options[n],w._set(s,s.defaultValue,e),w._createAccessor(s,e,i));this._setAll(t,e,!0)},set:function(t,e,i){return this._set(t,e,i)},setAll:function(t,e){return this._setAll(t,e)},_set:function(t,e,i,n){var s=this.options[t];if(!s)throw new Error("Invalid option: "+t);if(!s.modifiable&&!n)throw new Error("Option cannot be modified: "+t);return w._set(s,e,i)},_setAll:function(t,e,i){if(!t)return!1;var n,s=!1;for(n in t)k.hasOwn(t,n)&&this._set(n,t[n],e,i)&&(s=!0);return s}},{_createAccessor:function(t,e,i){var n={get:function(){return w._get(t,e)}};t.modifiable&&(n.set=function(n){w._set(t,n,e)&&i(n,t)}),Object.defineProperty(e,t.name,n)},_get:function(t,e){return e["_"+t.name]},_set:function(t,e,i){var n="_"+t.name,s=i[n],r=t.transform(null!=e?e:t.defaultValue);return i[n]=r,r!==s}}),M=w,b=h.extend(function(){this._services={}},{getService:function(t){var e=this._services[t];if(!e)throw new Error("Service is not being managed with name: "+t);return e},setService:function(t,e){if(this._services[t])throw new Error("Service is already managed with name: "+t);e&&(this._services[t]=e)}}),B=new M([new g("background",!0,"white"),new g("backgroundAlpha",!0,1,k.abs),new g("element"),new g("foreground",!0,"black"),new g("foregroundAlpha",!0,1,k.abs),new g("level",!0,"L",k.toUpperCase),new g("mime",!0,"image/png"),new g("padding",!0,null,k.abs),new g("size",!0,100,k.abs),new g("value",!0,"")]),y=new b,O=h.extend(function(t){B.init(t,this,this.update.bind(this));var e=B.get("element",this),i=y.getService("element"),n=e&&i.isCanvas(e)?e:i.createCanvas(),s=e&&i.isImage(e)?e:i.createImage();this._canvasRenderer=new c(this,n,!0),this._imageRenderer=new m(this,s,s===e),this.update()},{get:function(){return B.getAll(this)},set:function(t){B.setAll(t,this)&&this.update()},toDataURL:function(t){return this.canvas.toDataURL(t||this.mime)},update:function(){var t=new p({level:this.level,value:this.value});this._canvasRenderer.render(t),this._imageRenderer.render(t)}},{use:function(t){y.setService(t.getName(),t)}});Object.defineProperties(O.prototype,{canvas:{get:function(){return this._canvasRenderer.getElement()}},image:{get:function(){return this._imageRenderer.getElement()}}});var A=O,L=h.extend({getName:function(){}}).extend({createCanvas:function(){},createImage:function(){},getName:function(){return"element"},isCanvas:function(t){},isImage:function(t){}}).extend({createCanvas:function(){return document.createElement("canvas")},createImage:function(){return document.createElement("img")},isCanvas:function(t){return t instanceof HTMLCanvasElement},isImage:function(t){return t instanceof HTMLImageElement}});return A.use(new L),A});
    </script>

    <script>
        const totalVouchers = {{ count($vouchers) }};
        let currentFontScale = 'normal';

        function initQRCodes() {
            try {
                document.querySelectorAll('.qr-canvas').forEach(canvas => {
                    const val = canvas.getAttribute('data-value');
                    const size = parseInt(canvas.getAttribute('data-size') || 90);
                    if (val && !canvas._qrdone) {
                        new QRious({
                            element: canvas,
                            value: val,
                            size: size,
                            level: 'L'
                        });
                        canvas._qrdone = true;
                    }
                });
            } catch(e) {
                console.error("QR Code Error:", e);
            }
        }

        // Cetak Langsung via Browser
        function doPrint(e) {
            if (e) {
                try { e.preventDefault(); } catch(err) {}
            }
            window.focus();

            try {
                window.print();
            } catch(err) {
                console.error("Print Error:", err);
            }
        }

        // Download Sheet sebagai Dokumen PDF A4/Thermal (100% Berhasil di Semua HP & Laptop)
        function downloadAsPDF() {
            const btn = document.getElementById('btn-download-pdf');
            const origHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.innerHTML = '<span class="loading-spin"></span> <span>Membuat PDF...</span>';
                btn.disabled = true;
            }

            initQRCodes();

            const target = document.getElementById('printable-sheet-container') || document.querySelector('.thermal-sheet') || document.body;
            const isThermal = {{ $template === 'thermal' ? 'true' : 'false' }};
            const marginMm = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--v-margin')) || 4;

            const opt = {
                margin: isThermal ? [2, 2, 2, 2] : [marginMm, marginMm, marginMm, marginMm],
                filename: 'voucher-{{ $batchId ?? "hotspot" }}-' + (new Date().toISOString().slice(0, 10)) + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true,
                    windowWidth: target.scrollWidth || 1200
                },
                jsPDF: {
                    unit: 'mm',
                    format: isThermal ? [80, 297] : 'a4',
                    orientation: 'portrait'
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            function runPdf() {
                if (typeof html2pdf === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
                    script.onload = () => runPdf();
                    script.onerror = () => {
                        alert('Gagal memuat modul PDF. Silakan gunakan tombol cetak browser.');
                        if (btn) { btn.innerHTML = origHtml; btn.disabled = false; }
                    };
                    document.head.appendChild(script);
                    return;
                }

                html2pdf().set(opt).from(target).save().then(() => {
                    if (btn) {
                        btn.innerHTML = origHtml;
                        btn.disabled = false;
                    }
                }).catch(err => {
                    console.error('PDF error:', err);
                    alert('Gagal membuat PDF: ' + err.message);
                    if (btn) {
                        btn.innerHTML = origHtml;
                        btn.disabled = false;
                    }
                });
            }

            runPdf();
        }

        function recalculateSheets() {
            const cols = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--v-cols')) || 4;
            const rows = cols >= 5 ? 6 : (cols === 4 ? 6 : 5);
            const perSheet = cols * rows;
            const totalSheets = Math.ceil(totalVouchers / perSheet) || 1;

            const badge = document.getElementById('sheet-calc-badge');
            if (badge) {
                badge.innerText = `~${perSheet}/lbr (${totalSheets} lembar A4)`;
            }
        }

        // Terapkan penyesuaian font dan dimensi secara proporsional sesuai jumlah kolom dan skala teks
        function applyTypography(cols, textScale = currentFontScale) {
            cols = parseInt(cols) || 4;
            currentFontScale = textScale;
            let factor = 1.0;
            if (textScale === 'small') factor = 0.85;
            if (textScale === 'large') factor = 1.25;

            let baseTitle, baseBadge, baseLabel, baseCode, baseMeta, qrSize, padY, padX, minH;

            if (cols === 2) {
                baseTitle = 11.5;
                baseBadge = 10.5;
                baseLabel = 8.5;
                baseCode = 17;
                baseMeta = 8.5;
                qrSize = 56;
                padY = 10;
                padX = 12;
                minH = 130;
            } else if (cols === 3) {
                baseTitle = 10;
                baseBadge = 9;
                baseLabel = 7.5;
                baseCode = 14.5;
                baseMeta = 7.5;
                qrSize = 46;
                padY = 7;
                padX = 9;
                minH = 110;
            } else if (cols === 4) {
                baseTitle = 8.5;
                baseBadge = 8;
                baseLabel = 6.5;
                baseCode = 13;
                baseMeta = 6.5;
                qrSize = 38;
                padY = 5;
                padX = 7;
                minH = 95;
            } else if (cols === 5) {
                baseTitle = 7.5;
                baseBadge = 7;
                baseLabel = 5.8;
                baseCode = 11.5;
                baseMeta = 5.8;
                qrSize = 32;
                padY = 4;
                padX = 5.5;
                minH = 86;
            } else { // 6 cols
                baseTitle = 7;
                baseBadge = 6.5;
                baseLabel = 5.2;
                baseCode = 10;
                baseMeta = 5.2;
                qrSize = 26;
                padY = 3.5;
                padX = 4.5;
                minH = 78;
            }

            document.documentElement.style.setProperty('--v-font-title', (baseTitle * factor).toFixed(1) + 'px');
            document.documentElement.style.setProperty('--v-font-badge', (baseBadge * factor).toFixed(1) + 'px');
            document.documentElement.style.setProperty('--v-font-label', (baseLabel * factor).toFixed(1) + 'px');
            document.documentElement.style.setProperty('--v-font-code', (baseCode * factor).toFixed(1) + 'px');
            document.documentElement.style.setProperty('--v-font-meta', (baseMeta * factor).toFixed(1) + 'px');
            document.documentElement.style.setProperty('--v-qr-size', (qrSize * (textScale === 'large' ? 1.1 : textScale === 'small' ? 0.9 : 1.0)).toFixed(0) + 'px');
            document.documentElement.style.setProperty('--v-card-py', padY + 'px');
            document.documentElement.style.setProperty('--v-card-px', padX + 'px');
            document.documentElement.style.setProperty('--v-card-min-h', minH + 'px');
        }

        function updateCols(val) {
            val = parseInt(val) || 4;
            document.documentElement.style.setProperty('--v-cols', val);
            const el = document.getElementById('val-cols');
            if (el) el.innerText = val;
            
            applyTypography(val, currentFontScale);
            savePref();
            recalculateSheets();
        }

        function updateFontScale(val) {
            currentFontScale = val;
            const cols = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--v-cols')) || 4;
            applyTypography(cols, val);
            savePref();
        }

        function updateGap(val) {
            document.documentElement.style.setProperty('--v-gap', val);
            const el = document.getElementById('val-gap');
            if (el) el.innerText = val;
            savePref();
        }

        function updateMargin(val) {
            document.documentElement.style.setProperty('--v-margin', val);
            const el = document.getElementById('val-margin');
            if (el) el.innerText = val;
            savePref();
        }

        function updateQr(val) {
            document.documentElement.style.setProperty('--v-qr-display', val);
            savePref();
        }

        function updateBorder(val) {
            document.documentElement.style.setProperty('--v-border-style', val);
            savePref();
        }

        function applyPresetFromDropdown(type) {
            const customBox = document.getElementById('custom-controls-box');
            if (type === 'custom') {
                if (customBox) customBox.style.display = 'grid';
                savePref();
                return;
            } else {
                if (customBox) customBox.style.display = 'none';
            }

            const selCols = document.getElementById('sel-cols');
            const selGap = document.getElementById('sel-gap');
            const selMargin = document.getElementById('sel-margin');
            const selQr = document.getElementById('sel-qr');
            const selBorder = document.getElementById('sel-border');
            const selFont = document.getElementById('sel-font-scale');

            if (type === 'max') {
                // 36 per lembar (6 cols x 6 rows)
                if (selCols) selCols.value = "6";
                if (selGap) selGap.value = "1mm";
                if (selMargin) selMargin.value = "2mm";
                if (selQr) selQr.value = "none";
                if (selBorder) selBorder.value = "dashed";
                if (selFont) selFont.value = "normal";
                updateCols("6");
                updateGap("1mm");
                updateMargin("2mm");
                updateQr("none");
                updateBorder("dashed");
                updateFontScale("normal");
            } else if (type === 'dense') {
                // 24 per lembar (4 cols x 6 rows)
                if (selCols) selCols.value = "4";
                if (selGap) selGap.value = "2.5mm";
                if (selMargin) selMargin.value = "4mm";
                if (selQr) selQr.value = "block";
                if (selBorder) selBorder.value = "dashed";
                if (selFont) selFont.value = "normal";
                updateCols("4");
                updateGap("2.5mm");
                updateMargin("4mm");
                updateQr("block");
                updateBorder("dashed");
                updateFontScale("normal");
            } else if (type === 'zerogap') {
                // 0 Gap untuk potong lurus cepat
                if (selCols) selCols.value = "4";
                if (selGap) selGap.value = "0mm";
                if (selMargin) selMargin.value = "2mm";
                if (selQr) selQr.value = "block";
                if (selBorder) selBorder.value = "solid";
                if (selFont) selFont.value = "normal";
                updateCols("4");
                updateGap("0mm");
                updateMargin("2mm");
                updateQr("block");
                updateBorder("solid");
                updateFontScale("normal");
            } else if (type === 'standard') {
                // Standard 15 per lembar (3 cols x 5 rows)
                if (selCols) selCols.value = "3";
                if (selGap) selGap.value = "4mm";
                if (selMargin) selMargin.value = "4mm";
                if (selQr) selQr.value = "block";
                if (selBorder) selBorder.value = "dashed";
                if (selFont) selFont.value = "normal";
                updateCols("3");
                updateGap("4mm");
                updateMargin("4mm");
                updateQr("block");
                updateBorder("dashed");
                updateFontScale("normal");
            }
            savePref();
        }

        function savePref() {
            const pref = {
                preset: document.getElementById('sel-preset')?.value || "dense",
                cols: document.getElementById('sel-cols')?.value || "4",
                fontScale: document.getElementById('sel-font-scale')?.value || "normal",
                gap: document.getElementById('sel-gap')?.value || "2.5mm",
                margin: document.getElementById('sel-margin')?.value || "4mm",
                qr: document.getElementById('sel-qr')?.value || "block",
                border: document.getElementById('sel-border')?.value || "dashed",
            };
            try {
                localStorage.setItem('nodera_voucher_print_pref', JSON.stringify(pref));
            } catch(e) {}
        }

        function loadPref() {
            try {
                const saved = localStorage.getItem('nodera_voucher_print_pref');
                if (saved) {
                    const pref = JSON.parse(saved);
                    if (pref.preset && document.getElementById('sel-preset')) {
                        document.getElementById('sel-preset').value = pref.preset;
                        if (pref.preset === 'custom') {
                            const customBox = document.getElementById('custom-controls-box');
                            if (customBox) customBox.style.display = 'grid';
                        }
                    }
                    if (pref.fontScale && document.getElementById('sel-font-scale')) {
                        document.getElementById('sel-font-scale').value = pref.fontScale;
                        currentFontScale = pref.fontScale;
                    }
                    if (pref.cols && document.getElementById('sel-cols')) {
                        document.getElementById('sel-cols').value = pref.cols;
                        updateCols(pref.cols);
                    } else {
                        applyTypography(4, currentFontScale);
                    }
                    if (pref.gap && document.getElementById('sel-gap')) {
                        document.getElementById('sel-gap').value = pref.gap;
                        updateGap(pref.gap);
                    }
                    if (pref.margin && document.getElementById('sel-margin')) {
                        document.getElementById('sel-margin').value = pref.margin;
                        updateMargin(pref.margin);
                    }
                    if (pref.qr && document.getElementById('sel-qr')) {
                        document.getElementById('sel-qr').value = pref.qr;
                        updateQr(pref.qr);
                    }
                    if (pref.border && document.getElementById('sel-border')) {
                        document.getElementById('sel-border').value = pref.border;
                        updateBorder(pref.border);
                    }
                } else {
                    applyTypography(4, 'normal');
                    recalculateSheets();
                }
            } catch(e) {
                applyTypography(4, 'normal');
            }

            initQRCodes();
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadPref();
            const btn = document.getElementById('btn-print-action');
            if (btn) {
                btn.addEventListener('click', doPrint);
            }
        });
    </script>
</body>
</html>
