<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = [
        'vpn_user_id', 'name', 'slug', 'domain', 'email', 'phone', 'is_active', 'auto_renew', 'settings', 'max_customers', 'max_routers', 'expired_at',
        'trial_ends_at', 'logo', 'address', 'db_host', 'db_name',
    ];

    protected function casts(): array
    {
        return [
            'vpn_user_id' => 'integer',
            'is_active' => 'boolean',
            'auto_renew' => 'boolean',
            'settings' => 'array',
            'expired_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'max_customers' => 'integer',
            'max_routers' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Tenant $tenant) {
            \Illuminate\Support\Facades\Cache::forget("tenant:slug:{$tenant->slug}");
            \Illuminate\Support\Facades\Cache::forget("tenant_meta:slug:{$tenant->slug}");
        });

        static::deleted(function (Tenant $tenant) {
            \Illuminate\Support\Facades\Cache::forget("tenant:slug:{$tenant->slug}");
            \Illuminate\Support\Facades\Cache::forget("tenant_meta:slug:{$tenant->slug}");
        });
    }

    public function vpnUser()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function mikrotiks()
    {
        return $this->hasMany(Mikrotik::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function isExpired(): bool
    {
        if (!$this->expired_at) return false;
        return now()->gt($this->expired_at);
    }

    public function getMaxCustomersAttribute(): int
    {
        if (array_key_exists('max_customers', $this->attributes) && $this->attributes['max_customers'] !== null) {
            return (int) $this->attributes['max_customers'];
        }
        if (isset($this->settings['max_customers']) && $this->settings['max_customers'] !== null) {
            return (int) $this->settings['max_customers'];
        }
        $defaultMax = \App\Models\Setting::getValue('default_max_customers');
        return ($defaultMax !== null && $defaultMax !== '') ? (int) $defaultMax : 500;
    }

    public function getMaxRoutersAttribute(): int
    {
        if (array_key_exists('max_routers', $this->attributes) && $this->attributes['max_routers'] !== null) {
            return (int) $this->attributes['max_routers'];
        }
        if (isset($this->settings['max_routers']) && $this->settings['max_routers'] !== null) {
            return (int) $this->settings['max_routers'];
        }
        return 5;
    }

    public function getAutoRenewAttribute(): bool
    {
        if (array_key_exists('auto_renew', $this->attributes) && $this->attributes['auto_renew'] !== null) {
            return (bool) $this->attributes['auto_renew'];
        }
        return (bool) ($this->settings['auto_renew'] ?? false);
    }

    protected $appends = ['package_name', 'subdomain'];

    public function getSubdomainAttribute(): string
    {
        return $this->slug ?? '';
    }

    public function getPackageNameAttribute(): string
    {
        if (!empty($this->settings['package_name'])) {
            return (string) $this->settings['package_name'];
        }
        if (!empty($this->settings['package_id'])) {
            $pkg = Package::find($this->settings['package_id']);
            if ($pkg) return $pkg->name;
        }
        $maxCust = $this->max_customers;
        if ($maxCust > 0) {
            $matching = Package::where('type', 'subscription')->where('max_customers', $maxCust)->first();
            if ($matching) {
                return $matching->name;
            }
            return "Paket {$maxCust} Pelanggan";
        }
        return "Paket Unlimited";
    }
}
