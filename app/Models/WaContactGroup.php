<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaContactGroup extends Model
{
    use HasFactory;

    protected $table = 'wa_contact_groups';

    protected $fillable = [
        'merchant_id',
        'name',
        'color',
        'description',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WaContact::class, 'group_id');
    }
}
