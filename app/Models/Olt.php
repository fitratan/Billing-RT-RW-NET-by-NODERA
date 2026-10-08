<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    use TenantAware;

    protected $fillable = [
        'name', 'host', 'port', 'snmp_port', 'telnet_port',
        'username', 'password', 'enable_password', 'snmp_community',
        'model', 'submodel', 'connection_mode',
        'hardware_metrics', 'last_poll_at', 'last_poll_status',
        'is_active', 'location', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'snmp_port' => 'integer',
            'telnet_port' => 'integer',
            'last_poll_at' => 'datetime',
            'hardware_metrics' => 'array',
        ];
    }

    /**
     * Decrypt password safely with graceful fallback if plaintext.
     */
    public function getPasswordAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Encrypt password on save unless already encrypted or empty.
     */
    public function setPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password'] = null;
        } else {
            try {
                \Illuminate\Support\Facades\Crypt::decryptString($value);
                $this->attributes['password'] = $value;
            } catch (\Throwable $e) {
                $this->attributes['password'] = \Illuminate\Support\Facades\Crypt::encryptString($value);
            }
        }
    }

    /**
     * Decrypt enable_password safely with graceful fallback if plaintext.
     */
    public function getEnablePasswordAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Encrypt enable_password on save unless already encrypted or empty.
     */
    public function setEnablePasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['enable_password'] = null;
        } else {
            try {
                \Illuminate\Support\Facades\Crypt::decryptString($value);
                $this->attributes['enable_password'] = $value;
            } catch (\Throwable $e) {
                $this->attributes['enable_password'] = \Illuminate\Support\Facades\Crypt::encryptString($value);
            }
        }
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class)->withoutGlobalScopes();
    }
}
