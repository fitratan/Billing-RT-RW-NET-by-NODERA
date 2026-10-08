<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NoderaPayMerchantTransaction extends Model
{
    use HasFactory;

    protected $table = 'nodera_pay_merchant_transactions';

    protected $fillable = [
        'merchant_id',
        'trx_reference',
        'ref_id',
        'payment_method',
        'gross_amount',
        'midtrans_fee',
        'admin_fee',
        'total_fee',
        'net_amount',
        'customer_name',
        'customer_email',
        'customer_phone',
        'qr_string',
        'qr_image_url',
        'va_number',
        'va_bank',
        'snap_token',
        'snap_redirect_url',
        'status',
        'settlement_status',
        'settlement_due_at',
        'settled_at',
        'midtrans_transaction_id',
        'midtrans_response',
        'callback_url',
        'callback_status',
        'callback_attempts',
        'callback_response',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'gross_amount'      => 'decimal:2',
        'midtrans_fee'      => 'decimal:2',
        'admin_fee'         => 'decimal:2',
        'total_fee'         => 'decimal:2',
        'net_amount'        => 'decimal:2',
        'midtrans_response' => 'array',
        'paid_at'           => 'datetime',
        'settlement_due_at' => 'datetime',
        'settled_at'        => 'datetime',
        'expires_at'        => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(NoderaPayMerchant::class, 'merchant_id');
    }

    public static function generateTrxReference(): string
    {
        return 'NPTRX-' . date('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
