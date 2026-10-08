<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaymentGateway extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'payment_gateway_settings';

    protected $fillable = [
        'gateway',
        'config_json',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config_json' => 'array',
        ];
    }

    /**
     * Get a specific gateway's config (cached for 5 minutes).
     */
    public static function getConfig(string $gateway): ?array
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? (session('tenant_id') ? (int) session('tenant_id') : 0);
        $cacheKey = "pg_cfg_{$tenantId}_{$gateway}";

        return Cache::remember($cacheKey, 300, function () use ($gateway, $tenantId) {
            $query = self::withoutGlobalScopes()->where('gateway', $gateway);
            if ($tenantId > 0) {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })->orderByRaw('tenant_id IS NULL ASC');
            } else {
                $query->whereNull('tenant_id');
            }
            $record = $query->first();
            $cfg = $record?->config_json;
            if (is_string($cfg)) {
                $cfg = json_decode($cfg, true) ?? [];
            }
            return is_array($cfg) ? $cfg : null;
        });
    }

    /**
     * Check if a gateway is active (cached for 5 minutes).
     */
    public static function isActive(string $gateway): bool
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? (session('tenant_id') ? (int) session('tenant_id') : 0);
        $cacheKey = "pg_active_{$tenantId}_{$gateway}";

        return Cache::remember($cacheKey, 300, function () use ($gateway, $tenantId) {
            $query = self::withoutGlobalScopes()->where('gateway', $gateway)->where('is_active', true);
            if ($tenantId > 0) {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })->orderByRaw('tenant_id IS NULL ASC');
            } else {
                $query->whereNull('tenant_id');
            }
            return $query->exists();
        });
    }

    /**
     * Update or create a gateway config with cache invalidation.
     */
    public static function setConfig(string $gateway, array $config, bool $active = true): self
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? (session('tenant_id') ? (int) session('tenant_id') : 0);
        Cache::forget("pg_cfg_{$tenantId}_{$gateway}");
        Cache::forget("pg_cfg_0_{$gateway}");
        Cache::forget("pg_active_{$tenantId}_{$gateway}");
        Cache::forget("pg_active_0_{$gateway}");

        $query = self::withoutGlobalScopes()->where('gateway', $gateway);
        if ($tenantId > 0) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $existing = $query->first();

        if ($existing) {
            $existing->update([
                'config_json' => $config,
                'is_active'   => $active,
            ]);
            return $existing;
        }

        return self::withoutGlobalScopes()->create([
            'gateway'     => $gateway,
            'tenant_id'   => $tenantId > 0 ? $tenantId : null,
            'config_json' => $config,
            'is_active'   => $active,
        ]);
    }
}
