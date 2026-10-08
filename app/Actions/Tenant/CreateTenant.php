<?php

namespace App\Actions\Tenant;

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateTenant
{
    public function execute(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $package = isset($data['package_id']) ? Package::find($data['package_id']) : null;
            if (!$package) {
                $package = Package::whereNull('tenant_id')
                    ->where('type', 'subscription')
                    ->where('is_active', true)
                    ->orderBy('monthly_price', 'asc')
                    ->first();
            }

            $defaultMaxCust = (int) (\App\Models\Setting::getValue('default_max_customers') ?: 500);
            $maxCust = $package ? (int) $package->max_customers : (isset($data['max_customers']) ? (int) $data['max_customers'] : $defaultMaxCust);
            $isBasicTrial = $package && $package->isBasicSubscription();
            $duration = (int) ($data['duration'] ?? 1);
            if ($duration <= 0) {
                $duration = 1;
            }

            $maxRouters = (int) ($package->max_routers ?? $data['max_routers'] ?? 5);
            $packageName = $package?->name ?? ($maxCust > 0 ? "Paket {$maxCust} Pelanggan" : "Paket Unlimited");

            $settings = [
                'package_id' => $package?->id,
                'package_name' => $packageName,
                'max_customers' => $maxCust,
                'max_routers' => $maxRouters,
                'subscribed_price' => (int) ($package->monthly_price ?? $package->price ?? 0),
                'subscribed_duration' => $duration,
                'is_trial' => $isBasicTrial,
                'trial_duration' => $isBasicTrial ? $duration : 0,
            ];

            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'max_customers' => $maxCust,
                'max_routers' => $maxRouters,
                'expired_at' => $duration > 0 ? now()->addMonths($duration) : null,
                'trial_ends_at' => $isBasicTrial ? now()->addMonths($duration) : null,
                'settings' => $settings,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'tenant.created',
                'entity_type' => 'tenant',
                'entity_id' => $tenant->id,
                'new_values' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            ]);

            Cache::forget('superadmin.dashboard.stats');
            Cache::forget('superadmin.dashboard.recent_tenants');

            return $tenant;
        });
    }
}
