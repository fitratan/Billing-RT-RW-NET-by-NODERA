<?php

namespace App\Models;

use App\Models\Traits\TenantAware;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_partner_id',
        'vpn_user_id',
        'vpn_topup_request_id',
        'wa_merchant_id',
        'nodera_pay_merchant_id',
        'tenant_id',
        'topup_amount',
        'commission_rate',
        'commission_amount',
        'source_platform',
        'source_id',
        'source_invoice',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'topup_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function partner()
    {
        return $this->belongsTo(ReferralPartner::class, 'referral_partner_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function referredWaMerchant()
    {
        return $this->belongsTo(WaMerchant::class, 'wa_merchant_id');
    }

    public function referredGatewayMerchant()
    {
        return $this->belongsTo(NoderaPayMerchant::class, 'nodera_pay_merchant_id');
    }

    public function referredTenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function topupRequest()
    {
        return $this->belongsTo(VpnTopupRequest::class, 'vpn_topup_request_id');
    }
}
