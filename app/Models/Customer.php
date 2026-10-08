<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory, TenantAware;

    protected $fillable = [
        'name', 'pppoe_username', 'pppoe_password', 'connection_type', 'ip_address', 'mac_address', 'arp_interface', 'auto_arp',
        'code', 'phone', 'email', 'address',
        'package_id', 'odp_id', 'odp_port', 'cable_path', 'lat', 'lng', 'isolation_date', 'whatsapp_lid', 'fcm_token',
        'portal_password', 'status', 'router_id', 'install_date',
        'serial_number', 'promo_cycles_used', 'tenant_id', 'collector_id',
    ];

    protected $hidden = [
        'portal_password',
        'fcm_token',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'install_date' => 'date',
            'auto_arp' => 'boolean',
        ];
    }

    public function getCablePathAttribute($value)
    {
        if (is_array($value)) return $value;
        if (empty($value) || !is_string($value)) return [];
        try {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setCablePathAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->attributes['cable_path'] = json_encode($value);
        } else {
            $this->attributes['cable_path'] = $value;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer) {
            if (empty($customer->code)) {
                $tenantId = $customer->tenant_id ?? 0;
                $prefix = str_pad((string) ($tenantId % 100), 2, '0', STR_PAD_LEFT);
                do {
                    $candidate = $prefix . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                } while (static::withoutGlobalScopes()->where('code', $candidate)->exists());
                $customer->code = $candidate;
            }
            // Pre-computed hash (1x calculate untuk seluruh batch) agar sync & import ratusan pelanggan tidak timeout di BcryptHasher
            if (empty($customer->portal_password)) {
                static $defaultPortalHash = null;
                if (!$defaultPortalHash) {
                    $defaultPortalHash = bcrypt('123456');
                }
                $customer->portal_password = $defaultPortalHash;
            }
        });

        static::saved(function (self $customer) {
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($customer->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");
        });

        static::deleted(function (self $customer) {
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($customer->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");
        });
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collector()
    {
        return $this->belongsTo(Collector::class);
    }

    public function odp()
    {
        return $this->belongsTo(OdpLocation::class, 'odp_id');
    }

    public function onu()
    {
        return $this->hasOne(Onu::class);
    }

    public function isStatic(): bool
    {
        $conn = strtolower((string) ($this->connection_type ?? ''));
        $pppoe = (string) ($this->pppoe_username ?? '');
        $ip = (string) ($this->ip_address ?? '');

        return in_array($conn, ['static', 'arp', 'static_ip', 'ip_static', 'ip_statis'], true)
            || (!empty($ip) && (empty($pppoe) || str_starts_with($pppoe, 'static_') || str_starts_with($pppoe, 'arp_') || $conn === 'static'));
    }
}
