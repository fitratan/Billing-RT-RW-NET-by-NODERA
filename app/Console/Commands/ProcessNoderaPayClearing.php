<?php

namespace App\Console\Commands;

use App\Services\NoderaPayEngineService;
use Illuminate\Console\Command;

class ProcessNoderaPayClearing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noderapay:process-clearing {--merchant= : ID Merchant tertentu (opsional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proses auto-kliring dana T+1 transaksi NODERA PAY ke Saldo Tersedia merchant';

    /**
     * Execute the console command.
     */
    public function handle(NoderaPayEngineService $engine): int
    {
        $merchantId = $this->option('merchant') ? (int) $this->option('merchant') : null;

        $this->info('Memulai pemrosesan auto-kliring NODERA PAY (Settlement T+1)...');

        $count = $engine->processPendingClearing($merchantId);

        $this->info("✓ Berhasil memproses kliring untuk {$count} transaksi yang telah jatuh tempo.");

        return 0;
    }
}
