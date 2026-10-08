<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IspLicense extends Model
{
    use HasFactory;

    protected $fillable = [
        'vpn_user_id',
        'license_key',
        'client_name',
        'client_email',
        'client_phone',
        'domain',
        'server_ip',
        'hardware_id',
        'package_type',
        'price',
        'max_customers',
        'max_routers',
        'status',
        'expires_at',
        'last_heartbeat_at',
        'last_heartbeat_ip',
        'notes',
    ];

    public function vpnUser()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    protected $casts = [
        'expires_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'max_customers' => 'integer',
        'max_routers' => 'integer',
    ];

    public static function generateLicenseKey(string $prefix = 'NDR-ISP'): string
    {
        do {
            $p1 = strtoupper(Str::random(4));
            $p2 = strtoupper(Str::random(4));
            $p3 = strtoupper(Str::random(4));
            $p4 = strtoupper(Str::random(4));
            $key = "{$prefix}-{$p1}-{$p2}-{$p3}-{$p4}";
        } while (self::where('license_key', $key)->exists());

        return $key;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && !$this->isExpired();
    }
}
