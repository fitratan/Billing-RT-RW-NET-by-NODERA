<?php

namespace App\Models;

use App\Models\Traits\TenantAware;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookkeepingSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'vpn_user_id',
        'tenant_id',
        'subdomain',
        'business_name',
        'price',
        'order_date',
        'expires_at',
        'status',
        'auto_renew',
        'saldo_deducted',
        'last_billed_at',
        'expired_grace_at',
        'suspended_at',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'expires_at' => 'datetime',
        'last_billed_at' => 'datetime',
        'expired_grace_at' => 'datetime',
        'suspended_at' => 'datetime',
        'price' => 'float',
        'saldo_deducted' => 'float',
        'auto_renew' => 'boolean',
    ];

    public function vpnUser()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function user()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function getUrlAttribute(): string
    {
        $host = request()->getHost();
        $scheme = request()->getScheme() ?: 'https';

        if (app()->environment('local') || $host === 'localhost' || $host === '127.0.0.1' || filter_var($host, FILTER_VALIDATE_IP)) {
            $port = request()->getPort();
            $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';
            return "{$scheme}://{$host}{$portStr}/{$this->subdomain}/";
        }

        $baseDomain = config('bookkeeping.domain', config('app.base_domain'));
        if (empty($baseDomain) || $baseDomain === 'airnetsolution.com') {
            $hostParts = explode('.', $host);
            if (count($hostParts) >= 2) {
                $baseDomain = implode('.', array_slice($hostParts, -2));
            } else {
                $baseDomain = $host;
            }
        }

        return "{$scheme}://{$this->subdomain}.{$baseDomain}/";
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return now()->greaterThan($this->expires_at);
    }
}
