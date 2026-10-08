<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembayaran {{ $invoice['invoice_number'] ?? '' }} — {{ $companyName ?? 'NODERA' }}</title>
    
    <!-- Open Graph & Social Meta Tags (Prevents WhatsApp from picking QR Code image as thumbnail) -->
    <meta property="og:title" content="Bukti Pembayaran {{ $invoice['invoice_number'] ?? '' }} — {{ $companyName ?? 'NODERA' }}">
    <meta property="og:description" content="Bukti transaksi & struk resmi {{ $companyName ?? 'NODERA' }}">
    <meta property="og:image" content="{{ asset('images/logo.png?v=36') }}">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="{{ asset('images/logo.png?v=37') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico?v=37') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png?v=37') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            padding: 24px 12px;
            min-height: 100vh;
        }

        /* Top Toolbar (Hidden on print) */
        .no-print-toolbar {
            max-width: 680px;
            margin: 0 auto 20px;
            background: #0f172a;
            color: #fff;
            padding: 10px 16px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toolbar-label {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-group-toggle {
            display: flex;
            background: #1e293b;
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
        }

        .btn-mode {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 6px 12px;
            border-radius: 7px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
        }

        .btn-mode:hover {
            color: #f8fafc;
        }

        .btn-mode.active {
            background: #0284c7;
            color: #fff;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.4);
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .btn-print {
            background: #10b981;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #059669;
        }

        .btn-copy {
            background: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
        }

        .btn-copy:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        /* -------------------------------------------------------------
           1. MODE DIGITAL (BUKTI PEMBAYARAN PROPER & RESMI)
        ------------------------------------------------------------- */
        .digital-wrapper {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08), 0 4px 12px -2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .digital-header {
            padding: 28px 32px 20px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo-badge {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);
            flex-shrink: 0;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.25;
            letter-spacing: -0.3px;
        }

        .brand-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 3px;
            line-height: 1.35;
        }

        .receipt-pill-col {
            text-align: right;
            flex-shrink: 0;
        }

        .receipt-pill {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #e2e8f0;
        }

        .receipt-invoice-num {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 5px;
            display: block;
        }

        /* Status Cards */
        .status-card {
            margin: 20px 32px;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .status-paid-card {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .status-unpaid-card {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .status-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .status-paid-card .status-icon-box {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .status-unpaid-card .status-icon-box {
            background: #f59e0b;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
        }

        .status-title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .status-desc {
            font-size: 11.5px;
            margin-top: 1px;
            opacity: 0.9;
        }

        .status-extra {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
            flex-shrink: 0;
        }

        /* 2-Column Info Grid */
        .info-grid {
            padding: 0 32px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
        }

        .panel-heading {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 12px;
            margin-bottom: 8px;
            gap: 12px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: #64748b;
            flex-shrink: 0;
        }

        .info-value {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
            word-break: break-word;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Items Table */
        .items-section {
            padding: 0 32px;
            margin-bottom: 24px;
        }

        .table-responsive {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .styled-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .styled-table tbody td {
            padding: 14px 16px;
            font-size: 12.5px;
            color: #0f172a;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .styled-table tbody tr:last-child td {
            border-bottom: none;
        }

        .item-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
        }

        .item-subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Total Section */
        .total-banner {
            margin: 0 32px 24px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
        }

        .total-caption {
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .total-subcaption {
            font-size: 11px;
            color: #cbd5e1;
            margin-top: 2px;
        }

        .total-digits {
            font-size: 24px;
            font-weight: 800;
            color: #38bdf8;
            letter-spacing: -0.5px;
        }

        /* Verification & Seal */
        .verification-block {
            margin: 0 32px 28px;
            padding: 16px 20px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .qr-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .qr-wrapper img {
            width: 76px;
            height: 76px;
            border-radius: 6px;
            background: #fff;
            padding: 4px;
            border: 1px solid #e2e8f0;
        }

        .qr-caption {
            font-size: 9px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .seal-content {
            flex: 1;
        }

        .seal-stamp {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .seal-text {
            font-size: 11px;
            color: #64748b;
            line-height: 1.45;
        }

        .seal-text strong {
            color: #334155;
        }

        /* Digital Footer */
        .digital-footer {
            padding: 18px 32px 24px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.5;
        }

        /* -------------------------------------------------------------
           2. MODE THERMAL (58mm / 80mm POS RECEIPT)
        ------------------------------------------------------------- */
        .thermal-wrapper {
            display: none;
            background: #fff;
            margin: 0 auto;
            color: #000;
            font-family: 'Courier New', Courier, monospace, 'Consolas', monospace;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 6px;
        }

        .mode-58mm-active .digital-wrapper { display: none !important; }
        .mode-58mm-active .thermal-wrapper {
            display: block !important;
            width: 58mm;
            max-width: 58mm;
            font-size: 10.5px;
            padding: 10px 8px;
            line-height: 1.35;
        }

        .mode-80mm-active .digital-wrapper { display: none !important; }
        .mode-80mm-active .thermal-wrapper {
            display: block !important;
            width: 80mm;
            max-width: 80mm;
            font-size: 12px;
            padding: 14px 12px;
            line-height: 1.35;
        }

        .mode-a4-active .digital-wrapper {
            max-width: 210mm !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            border: 1px solid #cbd5e1 !important;
        }

        .thermal-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .thermal-header .t-brand {
            font-size: 13.5px;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .mode-80mm-active .thermal-header .t-brand {
            font-size: 15.5px;
        }

        .thermal-header .t-sub {
            font-size: 9px;
            color: #222;
            margin-top: 2px;
            line-height: 1.3;
        }

        .t-dashed {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        .t-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5px;
        }

        .t-row .t-label {
            color: #111;
            white-space: nowrap;
        }

        .t-row .t-value {
            font-weight: 700;
            text-align: right;
            word-break: break-word;
            padding-left: 6px;
        }

        .t-total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 900;
            font-size: 13px;
            padding: 4px 0;
        }

        .mode-80mm-active .t-total-box {
            font-size: 15px;
        }

        .t-badge-box {
            text-align: center;
            padding: 3px;
            font-weight: 900;
            font-size: 11px;
            letter-spacing: 1px;
            border: 1px solid #000;
            margin: 6px 0;
        }

        .t-footer {
            text-align: center;
            font-size: 9px;
            margin-top: 6px;
            color: #222;
            line-height: 1.35;
        }

        /* -------------------------------------------------------------
           3. PRINT MEDIA STYLES
        ------------------------------------------------------------- */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .digital-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
                max-width: 100% !important;
                padding: 0 !important;
            }

            .mode-58mm-active .thermal-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 58mm !important;
                max-width: 58mm !important;
            }

            .mode-80mm-active .thermal-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 80mm !important;
                max-width: 80mm !important;
            }

            @page {
                margin: 0;
                size: auto;
            }
        }

        @media (max-width: 640px) {
            html, body {
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
            }

            body {
                padding: 8px 6px 24px;
            }

            .no-print-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 10px 12px;
                margin: 0 0 14px;
                width: 100%;
                border-radius: 12px;
            }

            .toolbar-left {
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
                width: 100%;
            }

            .toolbar-label {
                font-size: 10px;
            }

            .btn-group-toggle {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                width: 100%;
                padding: 2px;
                gap: 2px;
            }

            .btn-mode {
                padding: 6px 2px;
                font-size: 10px;
                justify-content: center;
                text-align: center;
                white-space: nowrap;
            }

            .btn-mode svg {
                display: none;
            }

            .toolbar-right {
                width: 100%;
                display: flex;
                gap: 8px;
            }

            .toolbar-right .btn-action {
                flex: 1;
                justify-content: center;
                padding: 8px 10px;
                font-size: 11.5px;
            }

            .digital-wrapper {
                border-radius: 16px;
                width: 100%;
                max-width: 100%;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            }

            .digital-header {
                padding: 18px 14px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .brand-container {
                gap: 10px;
            }

            .brand-name {
                font-size: 15px;
            }

            .brand-sub {
                font-size: 10.5px;
            }

            .receipt-pill-col {
                text-align: left;
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                border-top: 1px dashed #e2e8f0;
                padding-top: 10px;
                margin-top: 2px;
            }

            .receipt-invoice-num {
                margin-top: 0;
                font-size: 12px;
            }

            .status-card {
                margin: 12px 14px;
                padding: 12px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .status-extra {
                text-align: left;
                margin-top: 2px;
                border-top: 1px dashed rgba(0, 0, 0, 0.1);
                padding-top: 8px;
                width: 100%;
            }

            .info-grid {
                grid-template-columns: 1fr;
                padding: 0 14px;
                gap: 10px;
                margin-bottom: 14px;
            }

            .info-panel {
                padding: 12px 14px;
            }

            .items-section {
                padding: 0 14px;
                margin-bottom: 14px;
                width: 100%;
            }

            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 12px;
            }

            .styled-table {
                min-width: 100%;
            }

            .styled-table thead th,
            .styled-table tbody td {
                padding: 10px 8px !important;
                font-size: 11.5px !important;
            }

            .styled-table thead th:nth-child(2),
            .styled-table tbody td:nth-child(2) {
                width: auto !important;
                white-space: nowrap;
            }

            .styled-table thead th:nth-child(3),
            .styled-table tbody td:nth-child(3) {
                width: auto !important;
                white-space: nowrap;
            }

            .total-banner {
                margin: 0 14px 14px;
                padding: 12px 16px;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }

            .total-digits {
                font-size: 18px;
                text-align: right;
            }

            .verification-block {
                margin: 0 14px 14px;
                padding: 12px;
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 10px;
            }

            .qr-wrapper img {
                width: 80px;
                height: 80px;
            }

            .digital-footer {
                padding: 14px 14px 18px;
                text-align: center;
            }
        }
    </style>
</head>
<body id="pageBody" class="mode-digital-active">

    <!-- Top Toolbar (Hidden saat cetak) -->
    <div class="no-print-toolbar">
        <div class="toolbar-left">
            <span class="toolbar-label">Tampilan:</span>
            <div class="btn-group-toggle">
                <button type="button" class="btn-mode active" id="btnDigital" onclick="setMode('digital')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Bukti Resmi
                </button>
                <button type="button" class="btn-mode" id="btn58" onclick="setMode('58mm')">
                    Struk 58mm
                </button>
                <button type="button" class="btn-mode" id="btn80" onclick="setMode('80mm')">
                    Struk 80mm
                </button>
                <button type="button" class="btn-mode" id="btnA4" onclick="setMode('a4')">
                    Format A4
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn-action btn-copy" onclick="copyReceiptLink()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span id="copyBtnText">Salin Link</span>
            </button>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak / PDF</span>
            </button>
        </div>
    </div>

    @php
        $invNum = $invoice['invoice_number'] ?? ('INV-' . str_pad((string)($invoice['id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $custName = $invoice['customer_name'] ?? 'Pelanggan';
        $pppoeUser = $invoice['pppoe_username'] ?? '-';
        $custPhone = $invoice['customer_phone'] ?? '';
        $custAddress = $invoice['address'] ?? '';
        $routerName = $invoice['router_name'] ?? '';
        $rawDate = $invoice['date'] ?? $invoice['created_at'] ?? now();
        $dateFormatted = \Carbon\Carbon::parse($rawDate)->translatedFormat('d F Y, H:i');

        $rawCustCode = $invoice['customer_code'] ?? null;
        $custId = $invoice['customer_id'] ?? null;
        $userId = !empty($rawCustCode)
            ? $rawCustCode
            : (!empty($pppoeUser) && $pppoeUser !== '-'
                ? $pppoeUser
                : (!empty($custId) ? ('#' . $custId) : null));
        
        $rawPeriod = $invoice['period'] ?? null;
        if (empty($rawPeriod)) {
            $periodFormatted = !empty($invoice['due_date']) ? \Carbon\Carbon::parse($invoice['due_date'])->translatedFormat('F Y') : \Carbon\Carbon::parse($rawDate)->translatedFormat('F Y');
        } elseif (preg_match('/^(\d{4})-(\d{1,2})/', $rawPeriod, $pm)) {
            $periodFormatted = \Carbon\Carbon::createFromDate((int)$pm[1], (int)$pm[2], 1)->translatedFormat('F Y');
        } else {
            $periodFormatted = $rawPeriod;
        }

        $packageName = $invoice['package_name'] ?? 'Layanan Internet Bulanan';
        $amount = (float)($invoice['amount'] ?? 0);
        $status = strtolower($invoice['status'] ?? '');
        $isPaid = $status === 'paid' || !empty($invoice['paid']);
        $paidAt = !empty($invoice['paid_at']) ? \Carbon\Carbon::parse($invoice['paid_at'])->translatedFormat('d F Y, H:i') : null;
        $paymentChannel = $invoice['payment_channel'] ?? $invoice['payment_method'] ?? null;
        $companyAddress = $company['COMPANY_ADDRESS'] ?? '';
        $companyPhone = $company['COMPANY_PHONE'] ?? '';
        $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $companyName ?: 'ND'), 0, 2)) ?: 'ND';
        $breakdown = !empty($invoice['periods_breakdown']) ? (is_array($invoice['periods_breakdown']) ? $invoice['periods_breakdown'] : json_decode($invoice['periods_breakdown'], true)) : [];
        $verifyUrl = url()->current();
    @endphp

    <!-- 1. CONTAINER BUKTI DIGITAL RESMI -->
    <div id="digitalContainer" class="digital-wrapper">
        <!-- Header Perusahaan -->
        <div class="digital-header">
            <div class="brand-container">
                @php
                    $logoRaw = $company['COMPANY_LOGO'] ?? ($company['logo'] ?? ($companyLogo ?? null));
                    $logoUrl = \App\Models\Setting::resolveLogoUrl($logoRaw, null);
                @endphp
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="brand-logo-img" style="height: 48px; max-width: 130px; object-fit: contain; border-radius: 8px; margin-right: 12px; background: rgba(255,255,255,0.06); padding: 2px;" onerror="this.style.display='none';" />
                @else
                    <div class="brand-logo-badge">{{ $initials }}</div>
                @endif
                <div>
                    <h1 class="brand-name">{{ $companyName }}</h1>
                    @if(!empty($companyAddress))
                        <p class="brand-sub">{{ $companyAddress }}</p>
                    @endif
                    @if(!empty($companyPhone))
                        <p class="brand-sub">Telp / WhatsApp: {{ $companyPhone }}</p>
                    @endif
                </div>
            </div>
            <div class="receipt-pill-col">
                <span class="receipt-pill">Bukti Pembayaran Elektronik</span>
                <span class="receipt-invoice-num">{{ $invNum }}</span>
            </div>
        </div>

        <!-- Status Pembayaran -->
        @if($isPaid)
            <div class="status-card status-paid-card">
                <div class="status-main">
                    <div class="status-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <div class="status-title">PEMBAYARAN LUNAS & TERVERIFIKASI</div>
                        <div class="status-desc">Tagihan internet Anda telah diselesaikan dan diverifikasi oleh sistem.</div>
                    </div>
                </div>
                <div class="status-extra">
                    @if($paidAt)
                        <div><strong>Waktu:</strong> {{ $paidAt }} WIB</div>
                    @endif
                    @if($paymentChannel)
                        <div><strong>Metode:</strong> <span style="text-transform: uppercase;">{{ $paymentChannel }}</span></div>
                    @endif
                </div>
            </div>
        @else
            <div class="status-card status-unpaid-card">
                <div class="status-main">
                    <div class="status-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <div>
                        <div class="status-title">MENUNGGU PEMBAYARAN</div>
                        <div class="status-desc">Tagihan belum dilunasi. Mohon selesaikan pembayaran sebelum jatuh tempo.</div>
                    </div>
                </div>
                <div class="status-extra">
                    @if(!empty($invoice['due_date']))
                        <div><strong>Jatuh Tempo:</strong> {{ \Carbon\Carbon::parse($invoice['due_date'])->translatedFormat('d F Y') }}</div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Informasi Pelanggan & Tagihan (2 Kolom) -->
        <div class="info-grid">
            <div class="info-panel">
                <div class="panel-heading">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Data Pelanggan
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Pelanggan</span>
                    <span class="info-value">{{ $custName }}</span>
                </div>
                @if(!empty($userId))
                <div class="info-row">
                    <span class="info-label">User ID</span>
                    <span class="info-value font-mono">{{ $userId }}</span>
                </div>
                @endif
                @if(!empty($custPhone))
                <div class="info-row">
                    <span class="info-label">Nomor WhatsApp</span>
                    <span class="info-value">{{ $custPhone }}</span>
                </div>
                @endif
                @if(!empty($custAddress))
                <div class="info-row">
                    <span class="info-label">Alamat Pemasangan</span>
                    <span class="info-value">{{ $custAddress }}</span>
                </div>
                @endif
                @if(!empty($routerName))
                <div class="info-row">
                    <span class="info-label">Server / POP</span>
                    <span class="info-value">{{ $routerName }}</span>
                </div>
                @endif
            </div>

            <div class="info-panel">
                <div class="panel-heading">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Data Transaksi
                </div>
                <div class="info-row">
                    <span class="info-label">Nomor Invoice</span>
                    <span class="info-value font-mono" style="color: #0284c7;">{{ $invNum }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal Terbit</span>
                    <span class="info-value">{{ $dateFormatted }} WIB</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Periode Layanan</span>
                    <span class="info-value">{{ $periodFormatted }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Tagihan</span>
                    <span class="info-value" style="font-weight: 800; color: {{ $isPaid ? '#059669' : '#d97706' }};">
                        {{ $isPaid ? 'LUNAS' : 'BELUM BAYAR' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Rincian Layanan Table -->
        <div class="items-section">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Deskripsi Layanan</th>
                            <th style="text-align: center; width: 140px;">Periode</th>
                            <th style="text-align: right; width: 160px;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($breakdown) && count($breakdown) > 1)
                            @foreach($breakdown as $bd)
                                <tr>
                                    <td>
                                        <div class="item-title">{{ $packageName }}</div>
                                        <div class="item-subtitle">{{ $bd['label'] ?? $bd['period'] }}</div>
                                    </td>
                                    <td style="text-align: center; font-size: 12px; color: #475569;">{{ $bd['label'] ?? $bd['period'] }}</td>
                                    <td style="text-align: right; font-weight: 700; color: #0f172a;">Rp {{ number_format((float)($bd['amount'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>
                                    <div class="item-title">{{ $packageName }}</div>
                                    <div class="item-subtitle">Akses Internet Dedicated / Broadband Unlimited</div>
                                </td>
                                <td style="text-align: center; font-size: 12px; color: #475569;">{{ $periodFormatted }}</td>
                                <td style="text-align: right; font-weight: 700; color: #0f172a;">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Total Box -->
        <div class="total-banner">
            <div>
                <div class="total-caption">Total Pembayaran</div>
                <div class="total-subcaption">Keterangan: {{ $isPaid ? 'Sudah Dilunasi' : 'Wajib Dibayar' }}</div>
            </div>
            <div class="total-digits">
                Rp {{ number_format($amount, 0, ',', '.') }}
            </div>
        </div>

        <!-- Verifikasi & Keaslian -->
        <div class="verification-block">
            <div class="qr-wrapper">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&data={{ urlencode($verifyUrl) }}" alt="QR Verifikasi" />
                <span class="qr-caption">Scan Validasi</span>
            </div>
            <div class="seal-content">
                <div class="seal-stamp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5zm-2 16l-4-4 1.41-1.41L10 15.17l6.59-6.59L18 10l-8 8z"/></svg>
                    <span>TERVERIFIKASI SISTEM ELEKTRONIK</span>
                </div>
                <p class="seal-text">
                    Dokumen ini adalah bukti transaksi digital resmi yang diterbitkan secara sah oleh sistem <strong>{{ $companyName }}</strong>. Simpan bukti transaksi ini sebagai bukti pembayaran sah.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="digital-footer">
            <p>Terima kasih telah menggunakan layanan internet <strong>{{ $companyName }}</strong>.</p>
            <p style="margin-top: 3px; font-size: 10px;">ID Transaksi: {{ $invNum }} &bull; Dibuat otomatis oleh Sistem Billing {{ $companyName }}</p>
        </div>
    </div>

    <!-- 2. CONTAINER STRUK THERMAL (58mm / 80mm POS KASIR) -->
    <div id="thermalContainer" class="thermal-wrapper">
        <div class="thermal-header">
            @if(!empty($logoUrl))
                <div style="text-align: center; margin-bottom: 6px;">
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 40px; max-width: 120px; object-fit: contain;" onerror="this.style.display='none';" />
                </div>
            @endif
            <div class="t-brand">{{ $companyName }}</div>
            @if(!empty($companyAddress))
                <div class="t-sub">{{ $companyAddress }}</div>
            @endif
            @if(!empty($companyPhone))
                <div class="t-sub">Telp/WA: {{ $companyPhone }}</div>
            @endif
        </div>

        <div class="t-dashed"></div>
        <div style="text-align: center; font-weight: bold; font-size: 11px;">STRUK PEMBAYARAN INTERNET</div>
        <div class="t-dashed"></div>

        <div class="t-row">
            <span class="t-label">No. Invoice</span>
            <span class="t-value">{{ $invNum }}</span>
        </div>
        <div class="t-row">
            <span class="t-label">Tanggal</span>
            <span class="t-value">{{ \Carbon\Carbon::parse($rawDate)->format('d/m/Y H:i') }}</span>
        </div>
        <div class="t-row">
            <span class="t-label">Pelanggan</span>
            <span class="t-value">{{ $custName }}</span>
        </div>
        @if(!empty($userId))
        <div class="t-row">
            <span class="t-label">User ID</span>
            <span class="t-value">{{ $userId }}</span>
        </div>
        @endif
        @if(!empty($routerName))
        <div class="t-row">
            <span class="t-label">Router</span>
            <span class="t-value">{{ $routerName }}</span>
        </div>
        @endif
        <div class="t-row">
            <span class="t-label">Periode</span>
            <span class="t-value">{{ $periodFormatted }}</span>
        </div>

        <div class="t-dashed"></div>

        @if(!empty($breakdown) && count($breakdown) > 1)
            <div class="t-row" style="font-weight: 700;">
                <span class="t-label">{{ $packageName }}</span>
                <span class="t-value">({{ count($breakdown) }} Periode)</span>
            </div>
            @foreach($breakdown as $bd)
                <div class="t-row" style="font-size: 9.5px; padding-left: 4px;">
                    <span class="t-label">• {{ $bd['label'] ?? $bd['period'] }}</span>
                    <span class="t-value">Rp{{ number_format((float)($bd['amount'] ?? 0), 0, ',', '.') }}</span>
                </div>
            @endforeach
        @else
            <div class="t-row">
                <span class="t-label">{{ $packageName }}</span>
                <span class="t-value">Rp{{ number_format($amount, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="t-dashed"></div>

        <div class="t-total-box">
            <span>TOTAL</span>
            <span>Rp{{ number_format($amount, 0, ',', '.') }}</span>
        </div>

        <div class="t-dashed"></div>

        @if($isPaid)
            <div class="t-badge-box">
                *** LUNAS ***
            </div>
            @if($paidAt)
            <div class="t-row" style="font-size: 9px;">
                <span class="t-label">Waktu:</span>
                <span class="t-value">{{ $paidAt }}</span>
            </div>
            @endif
            @if($paymentChannel)
            <div class="t-row" style="font-size: 9px;">
                <span class="t-label">Metode:</span>
                <span class="t-value" style="text-transform: uppercase;">{{ $paymentChannel }}</span>
            </div>
            @endif
        @else
            <div class="t-badge-box">
                *** BELUM LUNAS ***
            </div>
            @if(!empty($invoice['due_date']))
            <div class="t-row" style="font-size: 9px;">
                <span class="t-label">Jatuh Tempo:</span>
                <span class="t-value">{{ \Carbon\Carbon::parse($invoice['due_date'])->format('d/m/Y') }}</span>
            </div>
            @endif
        @endif

        <div class="t-dashed"></div>

        <div class="t-footer">
            <div>Simpan struk ini sebagai bukti sah.</div>
            <div style="margin-top: 3px; font-weight: bold;">Terima Kasih</div>
        </div>
    </div>

    <script>
        var currentMode = '{{ $defaultMode ?? "digital" }}';

        function setMode(mode) {
            currentMode = mode;
            var body = document.getElementById('pageBody');
            var btnDigital = document.getElementById('btnDigital');
            var btn58 = document.getElementById('btn58');
            var btn80 = document.getElementById('btn80');
            var btnA4 = document.getElementById('btnA4');

            // Reset class
            body.classList.remove('mode-digital-active', 'mode-58mm-active', 'mode-80mm-active', 'mode-a4-active');
            btnDigital.classList.remove('active');
            btn58.classList.remove('active');
            btn80.classList.remove('active');
            btnA4.classList.remove('active');

            if (mode === '58mm') {
                body.classList.add('mode-58mm-active');
                btn58.classList.add('active');
            } else if (mode === '80mm') {
                body.classList.add('mode-80mm-active');
                btn80.classList.add('active');
            } else if (mode === 'a4') {
                body.classList.add('mode-a4-active');
                btnA4.classList.add('active');
            } else {
                body.classList.add('mode-digital-active');
                btnDigital.classList.add('active');
                mode = 'digital';
            }

            try {
                localStorage.setItem('nodera_receipt_view_mode', mode);
            } catch(e) {}
        }

        function copyReceiptLink() {
            var url = window.location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }

        function fallbackCopy(text) {
            var input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showCopySuccess();
        }

        function showCopySuccess() {
            var btnText = document.getElementById('copyBtnText');
            var original = btnText.innerText;
            btnText.innerText = 'Tersalin!';
            setTimeout(function() {
                btnText.innerText = original;
            }, 2000);
        }

        window.onload = function() {
            var urlParams = new URLSearchParams(window.location.search);
            var modeParam = urlParams.get('mode') || urlParams.get('format');
            var autoprint = urlParams.get('autoprint') || urlParams.get('print');

            var initialMode = modeParam || '{{ $defaultMode ?? "digital" }}';
            setMode(initialMode);

            // Auto print logic (active for print-thermal route or explicit ?autoprint=1)
            var shouldAutoPrint = {{ ($autoPrint ?? false) ? 'true' : 'false' }};
            if (autoprint === '1' || autoprint === 'true') {
                shouldAutoPrint = true;
            } else if (autoprint === '0' || autoprint === 'false') {
                shouldAutoPrint = false;
            }

            if (shouldAutoPrint) {
                setTimeout(function() {
                    window.print();
                }, 350);
            }
        };
    </script>
</body>
</html>
