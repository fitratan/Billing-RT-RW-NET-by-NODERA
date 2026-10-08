<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onu extends Model
{
    use TenantAware;

    protected $fillable = [
        'serial_number', 'olt_id', 'pon_port', 'onu_index',
        'name', 'customer_id', 'status',
        'rx_power', 'tx_power', 'distance', 'temperature',
        'offline_reason', 'last_online_at', 'last_sync_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'onu_index' => 'integer',
            'customer_id' => 'integer',
            'rx_power' => 'float',
            'tx_power' => 'float',
            'distance' => 'float',
            'temperature' => 'float',
            'last_online_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::saved(function (self $onu) {
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($onu->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");
        });

        static::deleted(function (self $onu) {
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($onu->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");
        });
    }
}
