<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    use HasFactory;

    protected $table = 'wa_messages';

    protected $fillable = [
        'merchant_id',
        'device_id',
        'message_id',
        'recipient',
        'message_type',
        'message_content',
        'media_url',
        'status',
        'error_message',
        'cost',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WhatsappDevice::class, 'device_id');
    }
}
