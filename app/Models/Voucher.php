<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'username', 'password', 'profile', 'price', 'time_limit', 'data_limit',
        'comment', 'batch_id', 'router_id', 'created_by', 'used', 'used_at',
        'tenant_id', 'agent_id',
    ];

    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'used_at' => 'datetime',
            'price' => 'float',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
