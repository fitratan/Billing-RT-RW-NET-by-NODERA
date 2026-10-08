<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnuLocation extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'serial_number', 'name', 'lat', 'lng', 'odp_id', 'customer_id',
        'tenant_id',
    ];

    public function odp()
    {
        return $this->belongsTo(OdpLocation::class, 'odp_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
