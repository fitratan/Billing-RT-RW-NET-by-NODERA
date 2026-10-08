<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClientLog;
use App\Models\CustomerUsage;
use App\Models\HotspotVoucherOrder;
use App\Models\Mikrotik;
use App\Models\NoderaPayMerchantTransaction;
use App\Models\NoderaPayTransaction;
use App\Models\PaymentTransaction;
use App\Models\ShopOrder;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SystemMaintenanceCommand extends Command
{
    protected $signature = 'system:maintenance 
                            {--days-vouchers=7 : Retensi hari untuk voucher hotspot expired/used}
                            {--days-transactions=30 : Retensi hari untuk transaksi gateway pending/expired}
                            {--days-logs=30 : Retensi hari untuk system audit & client error logs}
                            {--keep-usage-months=12 : Retensi bulan untuk riwayat traffic pelanggan}';

    protected $description = 'Otomatisasi pembersihan data riwayat kedaluwarsa (Voucher Expired di DB & MikroTik, Transaksi Unpaid/Expired, Log Lama) tanpa menghapus bukti sah keuangan';

    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('🚀 MEMULAI SYSTEM MAINTENANCE & DATA RETENTION NODERA');
        $this->info('===============================================================');

        $daysVouchers = (int) ($this->option('days-vouchers') ?: 7);
        $daysTransactions = (int) ($this->option('days-transactions') ?: 30);
        $daysLogs = (int) ($this->option('days-logs') ?: 30);
        $keepUsageMonths = (int) ($this->option('keep-usage-months') ?: 12);

        $results = [];

        // -------------------------------------------------------------
        // 1. PEMBERSIHAN VOUCHER HOTSPOT EXPIRED/USED (DATABASE & MIKROTIK)
        // -------------------------------------------------------------
        $this->line("\n[1/5] Membersihkan voucher hotspot kedaluwarsa/terpakai...");
        try {
            $voucherCutoff = now()->subDays($daysVouchers);
            $expiredVouchers = Voucher::withoutGlobalScopes()
                ->where(function ($q) use ($voucherCutoff) {
                    $q->where('used', true)->where('used_at', '<', $voucherCutoff);
                })
                ->orWhere(function ($q) use ($voucherCutoff) {
                    $q->where('used', true)->whereNull('used_at')->where('updated_at', '<', $voucherCutoff);
                })
                ->get();

            $totalExpiredVouchers = $expiredVouchers->count();
            $deletedFromRouters = 0;

            if ($totalExpiredVouchers > 0) {
                // Hapus user dari masing-masing router MikroTik agar RAM router lega
                $byRouter = $expiredVouchers->groupBy('router_id');
                foreach ($byRouter as $routerId => $vouchers) {
                    if (!$routerId) continue;
                    $router = Mikrotik::withoutGlobalScopes()->find($routerId);
                    if ($router && $router->is_active) {
                        try {
                            $service = new MikrotikService($router);
                            if ($service->isConnected()) {
                                $usernames = $vouchers->pluck('username')->filter()->toArray();
                                if (!empty($usernames)) {
                                    $deletedFromRouters += $service->deleteHotspotUsersBatch($usernames);
                                }
                            }
                        } catch (\Throwable $e) {
                            Log::warning("[SystemMaintenance] Router {$router->name} voucher cleanup failed: " . $e->getMessage());
                        }
                    }
                }

                // Hapus dari database
                $deletedVoucherDb = Voucher::withoutGlobalScopes()
                    ->whereIn('id', $expiredVouchers->pluck('id'))
                    ->delete();

                $results[] = ['Voucher Hotspot Expired (DB)', "{$deletedVoucherDb} baris", 'Dibersihkan (> ' . $daysVouchers . ' hari)'];
                $results[] = ['Hotspot Users di MikroTik', "{$deletedFromRouters} user", 'Dihapus dari RAM/Disk Router'];
                $this->line("  ✓ {$deletedVoucherDb} voucher DB & {$deletedFromRouters} user MikroTik dibersihkan.");
            } else {
                $results[] = ['Voucher Hotspot Expired', '0 baris', 'Sudah bersih'];
                $this->line("  ✓ Voucher hotspot sudah bersih.");
            }
        } catch (\Throwable $e) {
            $this->error("  ✗ Gagal membersihkan voucher: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 2. PEMBERSIHAN TRANSAKSI QRIS & GATEWAY EXPIRED (NON-PAID ONLY)
        // -------------------------------------------------------------
        $this->line("\n[2/5] Membersihkan transaksi QRIS & Gateway kedaluwarsa (HANYA status expired/pending)...");
        try {
            $txCutoff = now()->subDays($daysTransactions);

            // A. NODERA Pay Gateway Transactions & Legacy QRIS
            $deletedNp = 0;
            if (Schema::hasTable('nodera_pay_merchant_transactions')) {
                $deletedNp += NoderaPayMerchantTransaction::whereIn('status', ['expired', 'pending', 'failed'])
                    ->where('created_at', '<', $txCutoff)
                    ->whereNull('paid_at')
                    ->delete();
            }
            if (Schema::hasTable('nodera_pay_transactions')) {
                $deletedNp += NoderaPayTransaction::whereIn('status', ['expired', 'pending'])
                    ->where('created_at', '<', $txCutoff)
                    ->whereNull('paid_at')
                    ->delete();
            }
            $results[] = ['NODERA Pay Gateway & QRIS (Expired/Pending)', "{$deletedNp} baris", 'Dibersihkan (> ' . $daysTransactions . ' hari)'];

            // B. Payment Transactions (TriPay, Midtrans, dll)
            $deletedPt = 0;
            if (Schema::hasTable('payment_transactions')) {
                $deletedPt = PaymentTransaction::whereIn('status', ['expired', 'failed'])
                    ->where('created_at', '<', $txCutoff)
                    ->whereNull('paid_at')
                    ->delete();
            }
            $results[] = ['Payment Gateway (Expired/Failed)', "{$deletedPt} baris", 'Dibersihkan (> ' . $daysTransactions . ' hari)'];

            // C. Unpaid Voucher Orders & Shop Orders
            $deletedVo = 0;
            if (Schema::hasTable('hotspot_voucher_orders')) {
                $deletedVo = HotspotVoucherOrder::whereIn('payment_status', ['expired', 'cancelled', 'unpaid'])
                    ->where('created_at', '<', $txCutoff)
                    ->whereNull('paid_at')
                    ->delete();
            }
            $results[] = ['Hotspot Voucher Orders (Unpaid/Expired)', "{$deletedVo} baris", 'Dibersihkan (> ' . $daysTransactions . ' hari)'];

            $deletedSo = 0;
            if (Schema::hasTable('shop_orders')) {
                $deletedSo = ShopOrder::whereIn('payment_status', ['expired', 'cancelled', 'unpaid', 'pending'])
                    ->where('created_at', '<', $txCutoff)
                    ->whereNull('paid_at')
                    ->delete();
            }
            $results[] = ['Shop Orders (Unpaid/Expired)', "{$deletedSo} baris", 'Dibersihkan (> ' . $daysTransactions . ' hari)'];

            $this->line("  ✓ Transaksi unpaid/expired lama berhasil dirampingkan.");
            $this->line("  🔒 <fg=green>BUKTI TRANSAKSI SAH (PAID / SUCCESS / INVOICES): 100% AMAN & TETAP DISIMPAN.</>");
        } catch (\Throwable $e) {
            $this->error("  ✗ Gagal membersihkan transaksi: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 3. PEMBERSIHAN LOG SISTEM & ERROR AUDIT LOGS
        // -------------------------------------------------------------
        $this->line("\n[3/5] Membersihkan log aktivitas & log audit sistem lama...");
        try {
            $logCutoff = now()->subDays($daysLogs);

            $deletedAudit = 0;
            if (Schema::hasTable('audit_logs')) {
                $deletedAudit = AuditLog::where('created_at', '<', $logCutoff)->delete();
            }
            $results[] = ['Audit Logs (Aktivitas Sistem)', "{$deletedAudit} baris", 'Dibersihkan (> ' . $daysLogs . ' hari)'];

            $deletedClientLog = 0;
            if (Schema::hasTable('client_logs')) {
                $deletedClientLog = ClientLog::where('created_at', '<', $logCutoff)->delete();
            }
            $results[] = ['Client Logs (Browser Errors)', "{$deletedClientLog} baris", 'Dibersihkan (> ' . $daysLogs . ' hari)'];

            $this->line("  ✓ Log audit & browser error lama dibersihkan.");
        } catch (\Throwable $e) {
            $this->error("  ✗ Gagal membersihkan log: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 4. RETENSI DATA STATISTIK TRAFFIC PELANGGAN (CUSTOMER USAGE)
        // -------------------------------------------------------------
        $this->line("\n[4/5] Memeriksa retensi statistik traffic pelanggan (Customer Usage)...");
        try {
            $usageCutoff = now()->subMonths($keepUsageMonths);
            $deletedUsage = 0;

            if (Schema::hasTable('customer_usage')) {
                $deletedUsage = CustomerUsage::where('created_at', '<', $usageCutoff)->delete();
            }

            $results[] = ['Statistik Traffic (Customer Usage)', "{$deletedUsage} baris", 'Disimpan 12 bulan terakhir (lama dibersihkan)'];
            $this->line("  ✓ Data traffic bulanan pelanggan selama {$keepUsageMonths} bulan terakhir dipertahankan.");
        } catch (\Throwable $e) {
            $this->error("  ✗ Gagal memeriksa usage: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 5. OPTIMASI & REKAP
        // -------------------------------------------------------------
        $this->line("\n[5/5] Rekapitulasi Maintenance:");
        $this->table(['Item Pembersihan', 'Jumlah Data', 'Keterangan'], $results);

        $this->newLine();
        $this->info("✅ SYSTEM MAINTENANCE SELESAI! Seluruh database dan router berhasil dirampingkan.");
        Log::info('[SystemMaintenance] Scheduled maintenance successfully executed.', $results);

        return Command::SUCCESS;
    }
}
