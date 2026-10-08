<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class NoderaPayMerchant extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'nodera_pay_merchants';

    protected $fillable = [
        'merchant_code',
        'name',
        'owner_name',
        'email',
        'phone',
        'password',
        'api_key',
        'secret_key',
        'webhook_url',
        'ip_whitelist',
        'balance',
        'clearing_balance',
        'min_withdrawal_threshold',
        'auto_withdrawal_enabled',
        'total_income',
        'total_withdrawn',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'wa_gateway_type',
        'wa_gateway_token',
        'wa_notify_on_payment',
        'payment_channels_config',
        'status',
        'referred_by_partner_id',
        'referral_code_used',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'secret_key',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'clearing_balance' => 'decimal:2',
        'min_withdrawal_threshold' => 'integer',
        'auto_withdrawal_enabled' => 'boolean',
        'total_income' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'wa_notify_on_payment' => 'boolean',
        'payment_channels_config' => 'array',
        'password' => 'hashed',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(NoderaPayMerchantTransaction::class, 'merchant_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(NoderaPayMerchantWithdrawal::class, 'merchant_id');
    }

    public function referredByPartner()
    {
        return $this->belongsTo(ReferralPartner::class, 'referred_by_partner_id');
    }

    /**
     * Generate unique merchant credentials
     */
    public static function generateCredentials(): array
    {
        do {
            $code = 'NP-' . strtoupper(Str::random(8));
        } while (self::where('merchant_code', $code)->exists());

        return [
            'merchant_code' => $code,
            'api_key'       => 'np_live_' . bin2hex(random_bytes(16)),
            'secret_key'    => 'np_sec_' . bin2hex(random_bytes(24)),
        ];
    }
}
