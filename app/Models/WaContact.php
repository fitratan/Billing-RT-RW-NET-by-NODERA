<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaContact extends Model
{
    use HasFactory;

    protected $table = 'wa_contacts';

    protected $fillable = [
        'merchant_id',
        'group_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WaContactGroup::class, 'group_id');
    }

    /**
     * Normalize phone numbers (e.g. 08123... -> 628123...)
     */
    public static function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        if (str_starts_with($clean, '08')) {
            $clean = '628' . substr($clean, 2);
        } elseif (str_starts_with($clean, '8') && strlen($clean) >= 9 && strlen($clean) <= 13) {
            $clean = '62' . $clean;
        }

        return $clean;
    }
}
