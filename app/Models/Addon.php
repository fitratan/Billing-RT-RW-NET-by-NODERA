<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'icon',
        'route_name',
        'feature_key',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenantAddons()
    {
        return $this->hasMany(TenantAddon::class);
    }
}
