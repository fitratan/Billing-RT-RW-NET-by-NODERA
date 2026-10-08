<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    use TenantAware;

    protected $fillable = [
        'name', 'description', 'tenant_id',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}
