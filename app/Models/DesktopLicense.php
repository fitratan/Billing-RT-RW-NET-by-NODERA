<?php

namespace App\Models;

use App\Models\Traits\TenantAware;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DesktopLicense extends Model
{
    use HasFactory;

    protected $table = 'desktop_licenses';

    protected $fillable = [
        'tenant_id',
        'vpn_user_id',
        'user_id',
        'license_key',
        'product_name',
        'hwid',
        'device_name',
        'os_info',
        'ip_address',
        'status',
        'activation_count',
        'max_activations',
        'activated_at',
        'expires_at',
        'last_heartbeat_at',
        'features',
        'notes',
    ];

    protected $casts = [
        'activated_at'      => 'datetime',
        'expires_at'        => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'features'          => 'array',
        'activation_count'  => 'integer',
        'max_activations'   => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return Carbon::now()->greaterThan($this->expires_at);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && !$this->isExpired();
    }

    public static function generateKey(string $prefix = 'NDR-MKH'): string
    {
        do {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $part3 = strtoupper(Str::random(4));
            $part4 = strtoupper(Str::random(4));
            $key = "{$prefix}-{$part1}-{$part2}-{$part3}-{$part4}";
        } while (static::where('license_key', $key)->exists());

        return $key;
    }
}
