<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\TenantAware;

class AcsTenantSetting extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'acs_tenant_settings';

    protected $fillable = [
        'tenant_id',
        'is_enabled',
        'connection_mode',
        'acs_username',
        'acs_password',
        'server_url',
        'nbi_url',
        'billing_mode',
        'daily_rate_per_ont',
        'free_tier_quota',
        'active_ont_count',
        'last_billed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'daily_rate_per_ont' => 'float',
        'free_tier_quota' => 'integer',
        'active_ont_count' => 'integer',
        'last_billed_at' => 'datetime',
    ];
}