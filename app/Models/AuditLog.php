<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, TenantAware;

    const UPDATED_AT = null; // Audit logs are append-only; no updated_at needed

    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->old_values = static::sanitizePayload($log->old_values);
            $log->new_values = static::sanitizePayload($log->new_values);
        });
    }

    /**
     * Otomatis mask kredensial dan kunci sensitif agar tidak bocor di log audit
     */
    public static function sanitizePayload($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        $sensitiveKeys = [
            'password', 'portal_password', 'pin', 'token', 'secret',
            'api_key', 'private_key', 'wa_token', 'fcm_token', 'two_factor_secret'
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::sanitizePayload($value);
            } else {
                foreach ($sensitiveKeys as $sensitive) {
                    if (stripos($key, $sensitive) !== false && ! empty($value)) {
                        $data[$key] = '********';
                        break;
                    }
                }
            }
        }

        return $data;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
