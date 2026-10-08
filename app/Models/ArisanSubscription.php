<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanSubscription extends Model
{
    use HasFactory;

    protected $table = 'arisan_subscriptions';

    protected $fillable = [
        'vpn_user_id',
        'subdomain',
        'business_name',
        'price',
        'order_date',
        'expires_at',
        'status',
        'auto_renew',
        'saldo_deducted',
        'admin_password_hash',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'price' => 'float',
        'saldo_deducted' => 'float',
    ];

    public function vpnUser()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function user()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function groups()
    {
        return $this->hasMany(ArisanGroup::class, 'subscription_id');
    }

    public function members()
    {
        return $this->hasMany(ArisanMember::class, 'subscription_id');
    }

    public function cashflows()
    {
        return $this->hasMany(ArisanCashflow::class, 'subscription_id');
    }

    public function paymentSettings()
    {
        return $this->hasMany(ArisanPaymentSetting::class, 'subscription_id');
    }

    public function getUrlAttribute(): string
    {
        $host = request()->getHost();
        $scheme = request()->getScheme() ?: 'https';

        if (app()->environment('local') || $host === 'localhost' || $host === '127.0.0.1' || filter_var($host, FILTER_VALIDATE_IP)) {
            $port = request()->getPort();
            $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';
            return "{$scheme}://{$host}{$portStr}/arisan-app/{$this->subdomain}/";
        }

        $baseDomain = config('arisan.domain', config('app.base_domain', 'dgtlnetsolution.com'));
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
