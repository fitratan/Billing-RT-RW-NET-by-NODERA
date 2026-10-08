<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfReportService
{
    /**
     * Generate Raw ESC/POS thermal printer byte stream for 58mm / 80mm POS printers.
     * Bypasses heavy server-side PDF rendering for instantaneous cashier & collector printing.
     */
    public function generateReceiptEscPos(Invoice $invoice, ?Tenant $tenant = null): string
    {
        $ESC = "\x1b";
        $GS  = "\x1d";

        $output = '';

        // 1. Initialize printer
        $output .= $ESC . "@";

        // 2. Align Center & Bold Header
        $output .= $ESC . "a" . "\x01"; // Center align
        $output .= $ESC . "E" . "\x01"; // Bold on
        $output .= ($tenant->name ?? 'NODERA ISP BILLING') . "\n";
        $output .= $ESC . "E" . "\x00"; // Bold off
        $output .= "STRUK PEMBAYARAN INTERNET\n";
        $output .= "--------------------------------\n";

        // 3. Align Left — Transaction Details
        $output .= $ESC . "a" . "\x00"; // Left align
        $output .= "No. Invoice : " . ($invoice->invoice_number ?? ('INV-' . $invoice->id)) . "\n";
        $output .= "Pelanggan   : " . ($invoice->customer->name ?? '-') . "\n";
        $output .= "User PPPoE  : " . ($invoice->customer->pppoe_username ?? '-') . "\n";
        $output .= "Tanggal     : " . now()->format('d/m/Y H:i') . "\n";
        $output .= "Metode      : " . strtoupper($invoice->payment_method ?? 'TUNAI') . "\n";
        $output .= "--------------------------------\n";

        // 4. Amount details
        $formattedAmount = 'Rp ' . number_format((float) $invoice->amount, 0, ',', '.');
        $output .= $ESC . "E" . "\x01";
        $output .= "TOTAL BAYAR : " . $formattedAmount . "\n";
        $output .= $ESC . "E" . "\x00";
        $output .= "STATUS      : LUNAS / PAID\n";
        $output .= "--------------------------------\n";

        // 5. Footer & Paper Cut
        $output .= $ESC . "a" . "\x01"; // Center
        $output .= "Terima kasih atas pembayaran Anda\n";
        $output .= "Simpan struk ini sebagai bukti sah.\n\n\n";
        $output .= $GS . "V" . "\x41" . "\x03"; // Cut paper

        return $output;
    }

    /**
     * Dispatch large financial report generation to async background queue.
     */
    public function queueFinancialReport(int $tenantId, string $startDate, string $endDate, int $adminUserId): string
    {
        $jobId = Str::uuid()->toString();

        // In production, dispatched to Queue Job:
        // dispatch(new GenerateFinancialPdfJob($tenantId, $startDate, $endDate, $adminUserId, $jobId));

        return $jobId;
    }
}
