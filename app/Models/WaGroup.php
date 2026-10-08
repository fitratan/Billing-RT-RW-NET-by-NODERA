<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaGroup extends Model
{
    use HasFactory;

    protected $table = 'wa_groups';

    protected $fillable = [
        'merchant_id',
        'device_id',
        'group_jid',
        'name',
        'participants_count',
        'owner_jid',
        'description',
        'creation_time',
        'last_synced_at',
    ];

    protected $casts = [
        'participants_count' => 'integer',
        'creation_time'      => 'datetime',
        'last_synced_at'     => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WhatsappDevice::class, 'device_id')->withoutGlobalScopes();
    }
}
