<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappDevice extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_devices';

    protected $fillable = [
        'tenant_id',
        'merchant_id',
        'session_id',
        'name',
        'phone_number',
        'profile_name',
        'status',
        'api_key',
        'webhook_url',
        'is_default',
        'last_connected_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id) && TenantScope::currentTenantId()) {
                $model->tenant_id = TenantScope::currentTenantId();
            }
            if (empty($model->session_id)) {
                if ($model->merchant_id) {
                    $prefix = "m{$model->merchant_id}";
                } elseif ($model->tenant_id) {
                    $prefix = "t{$model->tenant_id}";
                } else {
                    $prefix = "master";
                }
                $model->session_id = "dgtl_{$prefix}_" . bin2hex(random_bytes(4));
            }
            if (empty($model->api_key)) {
                $model->api_key = 'dgtl_' . bin2hex(random_bytes(16));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'device_id');
    }
}
