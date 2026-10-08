<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Cashier extends Model
{
    use TenantAware;

    protected $fillable = ['name', 'username', 'password', 'phone', 'is_active', 'opening_balance', 'tenant_id'];
    protected $hidden = ['password'];
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }
}
