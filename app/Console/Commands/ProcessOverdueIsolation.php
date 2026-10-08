<?php

namespace App\Console\Commands;

use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOverdueIsolation extends Command
{
    protected $signature = 'billing:isolate-overdue 
                            {--force : Paksa isolir tanpa mengecek batas interval paket}
                            {--tenant= : Filter berdasarkan ID Tenant}';

    protected $description = 'Isolir massal pelanggan yang melewati tanggal jatuh tempo via antrian paralel Horizon';

    public function handle(): int
    {
        $today = now()->format('Y-m-d');
        $force = (bool) $this->option('force');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info("⚡ Memulai scanning tagihan jatuh tempo untuk tanggal {$today}...");

        $query = Customer::withoutGlobalScopes()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            })
            ->whereHas('invoices', function ($q) use ($today) {
                $q->where('paid', 0)
                  ->where('due_date', '<', $today)
                  ->whereRaw('invoices.due_date >= DATE(customers.created_at)');
            })
            ->with(['router', 'package']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalDispatched = 0;
        $isoDayDefault = 20;

        $query->chunkById(200, function ($customers) use (&$totalDispatched, $force, $today, $isoDayDefault) {
            foreach ($customers as $customer) {
                // 1. Lewati jika pelanggan baru dibuat/sync di bulan berjalan pada atau setelah tanggal isolir
                $isoDay = (int) ($customer->isolation_date ?: $isoDayDefault);
                if (!$force && $customer->created_at) {
                    $createdAt = \Carbon\Carbon::parse($customer->created_at);
                    if ($createdAt->format('Y-m') === now()->format('Y-m') && $createdAt->day >= $isoDay) {
                        continue;
                    }
                }

                // 2. Lewati jika paket mematikan isolir otomatis
                if (!$force && $customer->package && !empty($customer->package->id) && empty($customer->package->auto_isolir)) {
                    continue;
                }

                // 3. Dispatch Job Isolir ke Redis Queue 'isolation' (Paralel Horizon)
                IsolateCustomerJob::dispatch($customer, $force, 'Sistem Cron / Isolir Massal')
                    ->onQueue('isolation');

                $totalDispatched++;
            }
        });

        $this->info("✅ Berhasil mendispatch {$totalDispatched} job isolir pelanggan ke antrian 'isolation' (Laravel Horizon)!");
        Log::info("[ProcessOverdueIsolation] Dispatched {$totalDispatched} overdue isolation jobs to 'isolation' queue.");

        return Command::SUCCESS;
    }
}
