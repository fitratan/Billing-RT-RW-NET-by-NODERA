<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoderaPayTransaction extends Model
{
    protected $fillable = [
        'subscription_id',
        'order_id',
        'amount',
        'unique_code',
        'total_amount',
        'dynamic_qris_string',
        'status',
        'paid_at',
        'webhook_status',
        'webhook_response_code',
        'raw_notification',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'float',
            'unique_code'  => 'integer',
            'total_amount' => 'float',
            'paid_at'      => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(NoderaPaySubscription::class, 'subscription_id');
    }
}
