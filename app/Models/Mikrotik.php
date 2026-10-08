<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Mikrotik extends Model
{
    use TenantAware;

    protected $fillable = [
        'name', 'host', 'port', 'username', 'password', 'is_active',
        'lat', 'lng', 'location',
        'api_mode', 'default_profile', 'default_isolir_profile', 'tenant_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
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
            return Crypt::decryptString($value);
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
                // If it can be decrypted, it's already encrypted
                Crypt::decryptString($value);
                $this->attributes['password'] = $value;
            } catch (\Throwable $e) {
                $this->attributes['password'] = Crypt::encryptString($value);
            }
        }
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'router_id');
    }

    public function odps()
    {
        return $this->hasMany(OdpLocation::class, 'router_id');
    }
}
