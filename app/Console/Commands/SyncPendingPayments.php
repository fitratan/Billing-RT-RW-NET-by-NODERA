<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\NoderaPayMerchantTransaction;
use App\Models\NoderaPayTransaction;
use App\Models\WaTopup;
use App\Services\NoderaPayEngineService;
use App\Services\WijayaPayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:sync-pending {--hours=24 : Rentang jam transaksi pending yang dicek}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi status transaksi pembayaran pending (Topup, WA, Tagihan) secara otomatis ke upstream payment gateway';

    /**
     * Execute the console command.
     */
    public function handle(NoderaPayEngineService $npEngine): int
    {
        $hours = (int) $this->option('hours') ?: 24;
        $cutoff = now()->subHours($hours);
        $settledCount = 0;

        $this->info("Memulai sinkronisasi transaksi pending dalam {$hours} jam terakhir...");

        $wpSvc = new WijayaPayService(null);
        $wpConfigured = $wpSvc->isConfigured();

        // 1. Sync Pending VPN Topups
        // End payment sync
        return 0;
    }
}