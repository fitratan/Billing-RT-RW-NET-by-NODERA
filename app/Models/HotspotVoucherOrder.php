<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotspotVoucherOrder extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'hotspot_voucher_orders';

    protected $fillable = [
        'order_number',
        'tenant_id',
        'router_id',
        'package_id',
        'package_name',
        'customer_name',
        'customer_phone',
        'amount',
        'unique_code',
        'total_amount',
        'payment_method',
        'payment_channel',
        'payment_status',
        'gateway_reference',
        'gateway_checkout_url',
        'gateway_payload',
        'voucher_id',
        'voucher_code',
        'voucher_password',
        'voucher_profile',
        'voucher_timelimit',
        'voucher_datalimit',
        'connect_url',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'unique_code' => 'float',
            'total_amount' => 'float',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
