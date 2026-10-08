<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\TenantAware;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TenantAware;

    protected $fillable = [
        'username', 'password', 'name', 'email', 'google_id', 'avatar', 'phone', 'role', 'permissions',
        'whatsapp_lid', 'fcm_token', 'is_active', 'last_login', 'tenant_id', 'router_id',
        'two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'remember_token',
        'fcm_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'last_login' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(TroubleTicket::class, 'assigned_to');
    }

    public function technicianRouters()
    {
        return $this->belongsToMany(Mikrotik::class, 'technician_routers', 'user_id', 'router_id')
            ->withTimestamps();
    }

    public function scopeTechnicians($query)
    {
        return $query->where('role', 'technician');
    }
}
