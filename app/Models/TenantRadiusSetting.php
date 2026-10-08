<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TenantRadiusSetting extends Model
{
    use TenantAware;

    protected $table = 'tenant_radius_settings';

    protected $fillable = [
        'tenant_id',
        'radius_mode',
        'is_active',
        'remote_db_driver',
        'remote_db_host',
        'remote_db_port',
        'remote_db_name',
        'remote_db_user',
        'remote_db_pass',
        'nas_ip',
        'nas_secret',
        'coa_port',
        'userman_host',
        'userman_port',
        'userman_user',
        'userman_pass',
        'auto_sync_on_create',
        'auto_coa_on_isolate',
        'last_sync_at',
        'last_test_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = [
        'remote_db_pass',
        'nas_secret',
        'userman_pass',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'remote_db_port' => 'integer',
            'coa_port' => 'integer',
            'userman_port' => 'integer',
            'auto_sync_on_create' => 'boolean',
            'auto_coa_on_isolate' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_test_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Encrypt & Decrypt Helpers
    public function getRemoteDbPassAttribute($value): ?string
    {
        return $this->decryptValue($value);
    }

    public function setRemoteDbPassAttribute($value): void
    {
        $this->attributes['remote_db_pass'] = $this->encryptValue($value);
    }

    public function getNasSecretAttribute($value): ?string
    {
        return $this->decryptValue($value);
    }

    public function setNasSecretAttribute($value): void
    {
        $this->attributes['nas_secret'] = $this->encryptValue($value);
    }

    public function getUsermanPassAttribute($value): ?string
    {
        return $this->decryptValue($value);
    }

    public function setUsermanPassAttribute($value): void
    {
        $this->attributes['userman_pass'] = $this->encryptValue($value);
    }

    private function decryptValue(?string $value): ?string
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

    private function encryptValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            Crypt::decryptString($value);
            return $value;
        } catch (\Throwable $e) {
            return Crypt::encryptString($value);
        }
    }
}
