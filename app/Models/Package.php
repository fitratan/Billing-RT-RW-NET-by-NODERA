<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'type', 'name', 'price', 'monthly_price', 'semi_annual_price', 'annual_price',
        'profile_normal', 'profile_isolir', 'isolir_address_list',
        'is_active', 'tenant_id', 'router_id',
        'description', 'promo_price', 'promo_cycles', 'prorate_first_invoice',
        'use_ppn', 'ppn_percentage', 'use_uso', 'uso_percentage',
        'admin_fee', 'late_fee', 'materai', 'max_customers', 'max_routers',
        'auto_isolir', 'isolir_interval_months',
        'duration_options',
        'is_popular',
        'use_night_speed', 'night_profile_name',
        'use_fup', 'fup_limit_gb', 'fup_profile_name',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'auto_isolir' => 'boolean',
            'isolir_interval_months' => 'integer',
            'use_night_speed' => 'boolean',
            'use_fup' => 'boolean',
        ];
    }

    public function mikrotik()
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Scope untuk paket internet pelanggan tenant (pppoe, static_ip, dll).
     * Mencegah paket langganan SaaS platform (subscription) bocor ke admin tenant.
     */
    public function scopeForTenantBilling($query, ?int $tenantId = null)
    {
        return $query->where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'));
    }

    /**
     * Scope untuk paket langganan SaaS SuperAdmin (Nodera platform).
     */
    public function scopeForSuperAdminSubscription($query)
    {
        return $query->where('type', 'subscription')->whereNull('tenant_id');
    }

    /**
     * Paket langganan tier "Basic" (max_customers < 1000).
     */
    public function isBasicSubscription(): bool
    {
        return $this->type === 'subscription'
            && (int) $this->max_customers > 0
            && (int) $this->max_customers < 1000;
    }
}
