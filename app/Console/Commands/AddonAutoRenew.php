<?php

namespace App\Console\Commands;

use App\Models\TenantAddon;
use App\Services\AddonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AddonAutoRenew extends Command
{
    protected $signature = 'addons:auto-renew';
    protected $description = 'Auto-renew active tenant add-on subscriptions with balance deduction';

    public function handle(AddonService $addonService): int
    {
        $this->info('Starting Add-on Auto-Renew check...');

        $expiring = TenantAddon::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now()->addHours(24))
            ->with(['tenant', 'addon'])
            ->get();

        $this->info("Found {$expiring->count()} add-on subscriptions expiring soon.");

        foreach ($expiring as $ta) {
            $tenant = $ta->tenant;
            $addon = $ta->addon;

            if (!$tenant || !$addon || !$addon->is_active) {
                continue;
            }

            if (strtolower($addon->billing_cycle ?? 'monthly') === 'lifetime') {
                continue;
            }

            $this->info("Processing auto-renew for Add-on {$addon->name} on Tenant {$tenant->name}...");
            $result = $addonService->purchaseWithBalance($tenant, $addon, true);

            if ($result['success']) {
                $this->info("  -> SUCCESS: Renewed {$addon->name} for {$tenant->name}.");
                Log::info("[AddonAutoRenew] Renewed {$addon->name} for tenant {$tenant->slug}");
            } else {
                $this->warn("  -> FAILED: {$result['error']}");
                Log::warning("[AddonAutoRenew] Failed renew for {$addon->name} on tenant {$tenant->slug}: {$result['error']}");

                if ($ta->isExpired()) {
                    $ta->is_active = false;
                    $ta->status = 'expired';
                    $ta->save();
                }
            }
        }

        $this->info('Add-on Auto-Renew complete.');
        return 0;
    }
}
