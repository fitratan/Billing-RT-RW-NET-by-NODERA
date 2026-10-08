<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class TenantAddon extends Model
{
    use TenantAware;

    protected $table = 'tenant_addons';
    protected $fillable = [
        'tenant_id',
        'addon_id',
        'is_active',
        'status',
        'config',
        'auto_renew',
        'expired_at',
        'unique_code',
        'total_amount',
        'dynamic_qris_string',
        'expires_at',
        'paid_at',
        'last_billed_at',
        'telegram_chat_id',
        'telegram_message_id',
    ];

    protected $casts = [
        'config'         => 'array',
        'is_active'      => 'boolean',
        'auto_renew'     => 'boolean',
        'expired_at'     => 'datetime',
        'expires_at'     => 'datetime',
        'paid_at'        => 'datetime',
        'last_billed_at' => 'datetime',
        'total_amount'   => 'decimal:2',
        'unique_code'    => 'integer',
    ];

    public function isPaid(): bool
    {
        return $this->status === 'approved' || $this->paid_at !== null;
    }

    public function isExpired(): bool
    {
        if ($this->expired_at === null) {
            // Lifetime
            return false;
        }

        return $this->expired_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->status === 'approved' || $this->status === 'active') && !$this->isExpired();
    }

    /**
     * Check if a tenant has valid access to a specific Addon feature.
     * Returns true if:
     * 1. The Addon does not exist or is inactive in platform (open feature)
     * 2. The Addon price is 0 (free feature)
     * 3. The tenant has an active, approved, non-expired TenantAddon record
     */
    public static function hasAccess(int|Tenant|null $tenant, string $addonSlug): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        if (!$tenantId) {
            return false;
        }

        $addon = Addon::where('slug', $addonSlug)->first();
        if (!$addon || !$addon->is_active || (float) $addon->price <= 0) {
            return true; // Not configured as paid addon, unrestricted
        }

        $tenantAddon = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('addon_id', $addon->id)
            ->first();

        return $tenantAddon ? $tenantAddon->isValid() : false;
    }

    public static function dismissTelegramNotification(self $ta): void
    {
        if (empty($ta->telegram_message_id) || empty($ta->telegram_chat_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $telegram->editMessageReplyMarkup(
                    (string) $ta->telegram_chat_id,
                    (int) $ta->telegram_message_id,
                    ['inline_keyboard' => []]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[TenantAddon] Failed to dismiss Telegram keyboard: ' . $e->getMessage());
        }
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
