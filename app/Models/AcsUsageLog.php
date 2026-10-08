<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\TenantAware;

class AcsUsageLog extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'acs_usage_logs';

    protected $fillable = [
        'tenant_id',
        'usage_date',
        'total_ont_count',
        'billable_ont_count',
        'rate_applied',
        'amount_deducted',
        'status',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'total_ont_count' => 'integer',
        'billable_ont_count' => 'integer',
        'rate_applied' => 'float',
        'amount_deducted' => 'float',
    ];
}