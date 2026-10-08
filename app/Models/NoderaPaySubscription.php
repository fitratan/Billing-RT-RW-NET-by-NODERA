<?php

namespace App\Models;

use App\Models\Traits\TenantAware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoderaPaySubscription extends Model
{
    protected $fillable = [
        'vpn_user_id',
        'tenant_id',
        'package_type',
        'mikhmon_subscription_id',
        'vpn_account_id',
        'name',
        'qris_image_path',
        'qris_raw_string',
        'merchant_name',
        'merchant_city',
        'nmid',
        'webhook_url',
        'api_key',
        'secret_key',
        'price',
        'qris_timeout_minutes',
        'enable_dynamic_qris',
        'enable_unique_code',
        'status',
        'expires_at',
        'saldo_deducted',
        'order_date',
        'last_billed_at',
        'auto_renew',
        'notes',
        'last_device_ping_at',
        'last_device_name',
        'last_device_battery',
        'last_device_ip',
    ];

    protected function casts(): array
    {
        return [
            'price'                => 'float',
            'qris_timeout_minutes' => 'integer',
            'enable_dynamic_qris'  => 'boolean',
            'enable_unique_code'   => 'boolean',
            'saldo_deducted'       => 'float',
            'expires_at'           => 'datetime',
            'order_date'           => 'datetime',
            'last_billed_at'       => 'datetime',
            'last_device_ping_at'  => 'datetime',
            'auto_renew'           => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function mikhmon(): BelongsTo
    {
        return $this->belongsTo(MikhmonSubscription::class, 'mikhmon_subscription_id');
    }

    public function vpnAccount(): BelongsTo
    {
        return $this->belongsTo(VpnAccount::class, 'vpn_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(NoderaPayTransaction::class, 'subscription_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
