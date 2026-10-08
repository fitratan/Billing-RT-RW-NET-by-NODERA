<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdpLocation extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'name', 'type', 'network_mode', 'lat', 'lng', 'capacity', 'used_ports',
        'parent_odp_id', 'router_id', 'cable_path',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'capacity' => 'integer',
            'used_ports' => 'integer',
        ];
    }

    public function getCablePathAttribute($value)
    {
        if (is_array($value)) return $value;
        if (empty($value) || !is_string($value)) return [];
        try {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setCablePathAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->attributes['cable_path'] = json_encode($value);
        } else {
            $this->attributes['cable_path'] = $value;
        }
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    public function parent()
    {
        return $this->belongsTo(OdpLocation::class, 'parent_odp_id');
    }

    public function children()
    {
        return $this->hasMany(OdpLocation::class, 'parent_odp_id');
    }

    public function onus()
    {
        return $this->hasMany(OnuLocation::class, 'odp_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'odp_id');
    }
}
