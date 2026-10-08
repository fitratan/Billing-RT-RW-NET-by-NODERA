<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Laporan Keuangan {{ $companyName ?? 'NODERA' }} — {{ $monthName }} {{ $year }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700&display=swap');

        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            font-family: 'Poppins', 'DejaVu Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            font-family: 'Poppins', 'DejaVu Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: {{ !empty($isPdf) && $isPdf ? '#ffffff' : '#f8fafc' }};
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.45;
        }
        
        /* ── Toolbar (Web View Only) ── */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }
        .toolbar-title {
            font-weight: 700;
            font-size: 13px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-outline {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #475569;
        }
        .btn-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .btn-secondary {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* ── Page Layout ── */
        .page-container {
            max-width: 860px;
            margin: {{ !empty($isPdf) && $isPdf ? '0' : '20px auto' }};
            background: #ffffff;
            padding: {{ !empty($isPdf) && $isPdf ? '0' : '28px 32px' }};
            border-radius: {{ !empty($isPdf) && $isPdf ? '0' : '10px' }};
            box-shadow: {{ !empty($isPdf) && $isPdf ? 'none' : '0 4px 16px rgba(0, 0, 0, 0.04)' }};
            border: {{ !empty($isPdf) && $isPdf ? 'none' : '1px solid #e2e8f0' }};
            width: {{ !empty($isPdf) && $isPdf ? '100%' : 'calc(100% - 24px)' }};
        }

        /* ── Header ── */
        table.header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
        }
        table.header-table td {
            vertical-align: middle;
            padding: 0 0 8px 0;
            border: none;
        }
        .brand-name {
            font-size: 18px;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: -0.3px;
            margin: 0;
            text-transform: uppercase;
        }
        .brand-subtitle {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .doc-meta {
            font-size: 9px;
            color: #64748b;
            text-align: right;
            margin-top: 3px;
        }

        /* ── Summary Metric Cards (HTML Table for Clean Dompdf & Web Rendering) ── */
        table.summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 8px -8px 14px -8px;
        }
        table.summary-table td.summary-card {
            padding: 10px 12px;
            border-radius: 6px;
            vertical-align: top;
        }
        .summary-card.profit {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
        }
        .summary-card.revenue {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
        .summary-card.expense {
            background-color: #fff1f2;
            border: 1px solid #fecdd3;
        }
        .summary-lbl {
            font-size: 8.5px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .summary-val {
            font-size: 14.5px;
            font-weight: 800;
            margin-top: 3px;
            color: #0f172a;
        }
        .summary-sub {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
            line-height: 1.35;
        }

        /* ── Section Title ── */
        .section-header {
            font-size: 10.5px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 14px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            page-break-after: avoid;
        }

        /* ── Tables ── */
        .table-responsive {
            width: 100%;
            margin-bottom: 12px;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            page-break-inside: auto;
        }
        table.report-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.report-table thead {
            display: table-header-group;
        }
        table.report-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            font-weight: 700;
            color: #334155;
            text-align: left;
            font-size: 9px;
        }
        table.report-table td {
            border: 1px solid #e2e8f0;
            padding: 4.5px 7px;
            color: #1e293b;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* ── Utilities ── */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Poppins', Consolas, 'Courier New', monospace; font-feature-settings: "tnum"; }
        .font-bold { font-weight: 700; }
        .text-emerald { color: #15803d; }
        .text-rose { color: #be123c; }
        .text-indigo { color: #4338ca; }
        .text-muted { color: #64748b; }

        /* ── Signatures Section ── */
        table.signature-table {
            width: 100%;
            margin-top: 22px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        table.signature-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            border: none;
            padding: 0 20px;
        }

        .footer-note {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
            page-break-inside: avoid;
        }

        /* ── Print Media (Browser Print) ── */
        @media print {
            .no-print, .toolbar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .page-container {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            table.report-table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    {{-- Toolbar only for Web Browser HTML preview --}}
    @if(empty($isPdf) || !$isPdf)
    <div class="toolbar no-print">
        <div class="toolbar-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Laporan Keuangan — {{ $monthName }} {{ $year }}</span>
        </div>
        <div class="toolbar-actions">
            <a href="javascript:void(0)" onclick="if(window.opener || window.history.length > 1) { window.history.back(); setTimeout(() => window.close(), 100); } else { window.location.href = '/admin/finance'; }" class="btn btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Kembali
            </a>
            <a href="/admin/finance/export-pdf?download=1&year={{ $year }}&month={{ $month }}" class="btn btn-secondary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh PDF
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Dokumen
            </button>
        </div>
    </div>
    @endif

    <div class="page-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="brand-name">{{ $companyName ?? 'NODERA BILLING' }}</h1>
                    <div class="brand-subtitle">{{ $companyAddress ?? 'Layanan Internet & Manajemen Billing Terpadu' }} @if(!empty($companyPhone))• Telp: {{ $companyPhone }}@endif</div>
                </td>
                <td class="text-right">
                    <div class="doc-title">Laporan Keuangan Bulanan</div>
                    <div class="doc-meta">Periode: <strong>{{ $monthName }} {{ $year }}</strong> • Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
                </td>
            </tr>
        </table>

        <!-- Summary Metrics -->
        <table class="summary-table">
            <tr>
                <td class="summary-card profit" style="width: 33.33%;">
                    <div class="summary-lbl">Laba / Rugi Bersih</div>
                    <div class="summary-val {{ $profitLoss >= 0 ? 'text-emerald' : 'text-rose' }}">
                        {{ $profitLoss >= 0 ? '+' : '' }}Rp {{ number_format($profitLoss, 0, ',', '.') }}
                    </div>
                    <div class="summary-sub">Inflow dikurangi Beban Operasional &amp; Komisi</div>
                </td>
                <td class="summary-card revenue" style="width: 33.33%;">
                    <div class="summary-lbl">Total Pemasukan</div>
                    <div class="summary-val text-emerald">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
                    <div class="summary-sub">Tagihan: Rp {{ number_format($invoiceRevenue, 0, ',', '.') }} | Voucher: Rp {{ number_format($voucherRevenue, 0, ',', '.') }}</div>
                </td>
                <td class="summary-card expense" style="width: 33.33%;">
                    <div class="summary-lbl">Total Pengeluaran</div>
                    <div class="summary-val text-rose">Rp {{ number_format($totalExpenses, 0, ',', '.') }}</div>
                    <div class="summary-sub">Operasional: Rp {{ number_format($operationalExpenses ?? ($totalExpenses - ($totalCollectorCommission ?? 0)), 0, ',', '.') }} | Komisi: Rp {{ number_format($totalCollectorCommission ?? 0, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        <!-- Rekapitulasi Komisi Petugas (Kolektor & Teknisi) (If Any) -->
        @if(!empty($collectorCommission) && count($collectorCommission) > 0)
        <div class="section-header">1. Rekapitulasi Komisi Petugas (Kolektor &amp; Teknisi Lapangan)</div>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 32%;">Petugas &amp; Peran</th>
                        <th class="text-right" style="width: 18%;">Invoice Ditagih</th>
                        <th class="text-right" style="width: 25%;">Total Tagihan</th>
                        <th class="text-right" style="width: 25%;">Estimasi Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalCommAll = 0; $totalInvAll = 0; @endphp
                    @foreach($collectorCommission as $c)
                    @php
                        $cName = $c['collector_name'] ?? $c['name'] ?? '-';
                        $cRole = $c['role_label'] ?? 'Petugas';
                        $cInvoices = $c['total_invoices'] ?? $c['invoice_count'] ?? 0;
                        $cTotal = (float) ($c['total_amount'] ?? $c['total_collected'] ?? 0);
                        $cComm = (float) ($c['estimated_commission'] ?? $c['commission_amount'] ?? 0);
                        $totalCommAll += $cComm;
                        $totalInvAll += $cInvoices;
                    @endphp
                    <tr>
                        <td>
                            <div class="font-bold">{{ $cName }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $cRole }} • {{ $c['commission_rate_label'] ?? '' }}</div>
                        </td>
                        <td class="text-right font-mono">{{ $cInvoices }} transaksi</td>
                        <td class="text-right font-bold font-mono">Rp {{ number_format($cTotal, 0, ',', '.') }}</td>
                        <td class="text-right font-bold text-indigo font-mono">Rp {{ number_format($cComm, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td>TOTAL KOMISI PETUGAS</td>
                        <td class="text-right font-mono">{{ $totalInvAll }} transaksi</td>
                        <td class="text-right font-mono">-</td>
                        <td class="text-right font-bold text-indigo font-mono">Rp {{ number_format($totalCommAll, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        <!-- Daftar Invoice Lunas -->
        <div class="section-header">2. Rincian Pembayaran Invoice Lunas ({{ count($invoices) }} Transaksi)</div>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">No. Invoice</th>
                        <th style="width: 32%;">Pelanggan</th>
                        <th style="width: 16%;">Metode Bayar</th>
                        <th style="width: 15%;">Waktu Lunas</th>
                        <th class="text-right" style="width: 15%;">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sumInv = 0; @endphp
                    @forelse($invoices as $inv)
                    @php $sumInv += (float) $inv->amount; @endphp
                    <tr>
                        <td class="font-mono font-bold">{{ $inv->invoice_number }}</td>
                        <td>
                            <span class="font-bold">{{ $inv->customer_name ?? ($inv->customer->name ?? '-') }}</span>
                            <span class="text-muted font-mono" style="font-size: 8px;">(ID: {{ $inv->customer_code ?? ($inv->customer->code ?? $inv->customer_id ?? '-') }})</span>
                        </td>
                        <td>{{ strtoupper($inv->payment_channel ?? $inv->payment_method ?? 'CASH') }}</td>
                        <td>{{ $inv->paid_at ? \Carbon\Carbon::parse($inv->paid_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td class="text-right font-bold text-emerald font-mono">Rp {{ number_format((float) $inv->amount, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding: 12px;">Belum ada transaksi invoice lunas pada periode ini.</td>
                    </tr>
                    @endforelse
                    @if(count($invoices) > 0)
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="4">TOTAL INVOICE LUNAS</td>
                        <td class="text-right font-bold text-emerald font-mono">Rp {{ number_format($sumInv, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Rincian Voucher Hotspot (If Any) -->
        @if(count($vouchers) > 0)
        <div class="section-header">3. Rincian Penjualan Voucher Hotspot ({{ count($vouchers) }} Voucher)</div>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Username / Kode Voucher</th>
                        <th style="width: 25%;">Profil Paket</th>
                        <th style="width: 20%;">Waktu Aktif</th>
                        <th class="text-right" style="width: 20%;">Harga Jual</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sumVcr = 0; @endphp
                    @foreach($vouchers as $v)
                    @php $sumVcr += (float) $v->price; @endphp
                    <tr>
                        <td class="font-mono font-bold">{{ $v->username }}</td>
                        <td>{{ $v->profile ?? 'Hotspot' }}</td>
                        <td>{{ $v->used_at ? \Carbon\Carbon::parse($v->used_at)->format('d/m/Y H:i') : ($v->created_at ? $v->created_at->format('d/m/Y H:i') : '-') }}</td>
                        <td class="text-right font-bold text-emerald font-mono">Rp {{ number_format((float) $v->price, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="3">TOTAL VOUCHER HOTSPOT</td>
                        <td class="text-right font-bold text-emerald font-mono">Rp {{ number_format($sumVcr, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        <!-- Rincian Pengeluaran Operasional -->
        <div class="section-header">4. Rincian Pengeluaran Operasional ({{ count($expenses) }} Item)</div>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Deskripsi Pengeluaran</th>
                        <th style="width: 25%;">Kategori</th>
                        <th style="width: 15%;">Tanggal</th>
                        <th class="text-right" style="width: 15%;">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sumExp = 0; @endphp
                    @forelse($expenses as $exp)
                    @php $sumExp += (float) $exp->amount; @endphp
                    <tr>
                        <td class="font-bold">{{ $exp->description }}</td>
                        <td>{{ $exp->category ?? '-' }}</td>
                        <td>{{ $exp->date ? \Carbon\Carbon::parse($exp->date)->format('d/m/Y') : '-' }}</td>
                        <td class="text-right font-bold text-rose font-mono">Rp {{ number_format((float) $exp->amount, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted" style="padding: 12px;">Belum ada pengeluaran dicatat pada periode ini.</td>
                    </tr>
                    @endforelse
                    @if(count($expenses) > 0)
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="3">TOTAL PENGELUARAN OPERASIONAL</td>
                        <td class="text-right font-bold text-rose font-mono">Rp {{ number_format($sumExp, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Tanda Tangan & Pengesahan -->
        <table class="signature-table">
            <tr>
                <td>
                    <div style="font-size: 9px; color: #64748b;">Dibuat Oleh:</div>
                    <div style="font-size: 10px; font-weight: bold; color: #0f172a; margin-top: 2px;">Staff / Bagian Keuangan</div>
                    <div style="height: 42px;"></div>
                    <div style="font-size: 10px; font-weight: bold; text-decoration: underline;">( ........................................... )</div>
                </td>
                <td>
                    <div style="font-size: 9px; color: #64748b;">Mengetahui &amp; Disetujui:</div>
                    <div style="font-size: 10px; font-weight: bold; color: #0f172a; margin-top: 2px;">Pimpinan / Manajemen ISP</div>
                    <div style="height: 42px;"></div>
                    <div style="font-size: 10px; font-weight: bold; text-decoration: underline;">( ........................................... )</div>
                </td>
            </tr>
        </table>

        <!-- Footer Note -->
        <div class="footer-note">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="border: none; padding: 0; font-size: 8px; color: #94a3b8;">
                        Dokumen resmi digenerate otomatis oleh <strong>NODERA Billing System</strong>.
                    </td>
                    <td style="border: none; padding: 0; text-align: right; font-size: 8px; color: #94a3b8;">
                        Dicetak pada {{ date('d F Y, H:i:s') }} WIB
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if(empty($isPdf) || !$isPdf)
    <script>
        function triggerPrint() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('auto_print')) {
                setTimeout(function () {
                    window.print();
                }, 300);
            }
        }
        if (document.readyState === 'complete') {
            triggerPrint();
        } else {
            window.addEventListener('load', triggerPrint);
        }
    </script>
    @endif
</body>
</html>
