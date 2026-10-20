<?php

namespace App\Console\Commands;

use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Services\IsolationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOverdueIsolation extends Command
{
    protected $signature = 'billing:isolate-overdue 
                            {--force : Paksa isolir tanpa mengecek batas interval paket}
                            {--tenant= : Filter berdasarkan ID Tenant}';

    protected $description = 'Isolir massal pelanggan yang melewati tanggal jatuh tempo dan auto-unisolir bagi yang sudah lunas';

    public function handle(): int
    {
        $today = now()->format('Y-m-d');
        $force = (bool) $this->option('force');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info("⚡ Memulai scanning tagihan jatuh tempo untuk tanggal {$today}...");

        // 1. ISOLIR: Pelanggan aktif yang memiliki tagihan overdue belum lunas
        $query = Customer::withoutGlobalScopes()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            })
            ->whereHas('invoices', function ($q) use ($today) {
                $q->where('paid', 0)
                  ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                  ->where('due_date', '<', $today);
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
                        Log::info("[ProcessOverdueIsolation] Skipping customer #{$customer->id} ({$customer->name}): registered/synced on {$createdAt->format('Y-m-d')} on or after isolation day ({$isoDay}). Auto-isolation starts next month.");
                        continue;
                    }
                }

                // 2. Lewati jika paket mematikan isolir otomatis
                if (!$force && $customer->package && !empty($customer->package->id) && empty($customer->package->auto_isolir)) {
                    Log::info("[ProcessOverdueIsolation] Skipping customer #{$customer->id} ({$customer->name}): package '{$customer->package->name}' has auto_isolir disabled.");
                    continue;
                }

                // 3. Cek interval bulan isolir jika diatur > 1 bulan pada paket
                $intervalMonths = (int) ($customer->package?->isolir_interval_months ?? 1);
                if ($intervalMonths > 1) {
                    $overdueCount = $customer->invoices()
                        ->where('paid', 0)
                        ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                        ->where('due_date', '<', $today)
                        ->count();
                    if ($overdueCount < $intervalMonths) {
                        Log::info("[ProcessOverdueIsolation] Skipping customer #{$customer->id} ({$customer->name}): overdue invoices count ({$overdueCount}) < package interval threshold ({$intervalMonths}).");
                        continue;
                    }
                }

                // 4. Dispatch Job Isolir
                IsolateCustomerJob::dispatch($customer, $force, 'Sistem Cron / Isolir Massal');
                $totalDispatched++;
            }
        });

        $this->info("✅ Berhasil mendispatch {$totalDispatched} job isolir pelanggan!");
        Log::info("[ProcessOverdueIsolation] Dispatched {$totalDispatched} overdue isolation jobs.");

        // 2. AUTO-UNISOLIR: Pelanggan berstatus 'isolated' yang sudah melunasi seluruh tagihan overdue
        $this->info("⚡ Memulai scanning pelanggan terisolir untuk auto-unisolir...");
        $unisolatedQuery = Customer::withoutGlobalScopes()
            ->where('status', 'isolated')
            ->whereDoesntHave('invoices', function ($q) use ($today) {
                $q->where('paid', 0)
                  ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                  ->where('due_date', '<', $today);
            })
            ->with(['router', 'package']);

        if ($tenantId) {
            $unisolatedQuery->where('tenant_id', $tenantId);
        }

        $totalUnisolated = 0;
        $unisolatedQuery->chunkById(200, function ($customers) use (&$totalUnisolated) {
            foreach ($customers as $customer) {
                try {
                    app(IsolationService::class)->unisolateCustomer($customer, 'Sistem Cron / Auto-Unisolir');
                    $totalUnisolated++;
                } catch (\Throwable $e) {
                    Log::error("[ProcessOverdueIsolation] Auto-unisolir failed for customer #{$customer->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("✅ Berhasil memulihkan (unisolir) {$totalUnisolated} pelanggan yang telah melunasi tagihan!");
        Log::info("[ProcessOverdueIsolation] Auto-unisolated {$totalUnisolated} customers without overdue invoices.");

        return Command::SUCCESS;
    }
}
