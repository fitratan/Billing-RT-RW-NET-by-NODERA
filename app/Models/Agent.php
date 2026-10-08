<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use TenantAware;

    protected $table = 'agents';

    protected $fillable = [
        'name', 'username', 'password', 'phone', 'email',
        'commission_type', 'commission_value', 'balance', 'is_active',
        'tenant_id',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
