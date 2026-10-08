<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikhmonSubscription extends Model
{
    protected $fillable = [
        'vpn_user_id', 'tenant_id', 'subdomain', 'ros_version', 'price', 'expires_at',
        'status', 'saldo_deducted', 'order_date', 'last_billed_at',
        'auto_renew', 'notes', 'expired_grace_at', 'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'expires_at' => 'datetime',
            'order_date' => 'datetime',
            'last_billed_at' => 'datetime',
            'saldo_deducted' => 'float',
            'auto_renew' => 'boolean',
            'expired_grace_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
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

        $baseDomain = config('mikhmon.domain', config('app.base_domain'));
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

    public function getSubpathUrlAttribute(): string
    {
        $host = request()->getHost();
        $scheme = request()->getScheme() ?: 'https';
        $port = request()->getPort();
        $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$portStr}/{$this->subdomain}/";
    }

    public function getAdminUrlAttribute(): string
    {
        return rtrim($this->url, '/') . '/login';
    }

    public function getBuyUrlAttribute(): string
    {
        return rtrim($this->url, '/') . '/';
    }
}
