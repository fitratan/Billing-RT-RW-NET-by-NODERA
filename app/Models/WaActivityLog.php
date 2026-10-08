<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaActivityLog extends Model
{
    use HasFactory;

    protected $table = 'wa_activity_logs';

    protected $fillable = [
        'merchant_id',
        'event',
        'category',
        'description',
        'ip_address',
        'user_agent',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(WaMerchant::class, 'merchant_id');
    }

    /**
     * Record a new activity log
     */
    public static function record(
        int $merchantId,
        string $event,
        string $description,
        string $category = 'general',
        string $status = 'info',
        ?array $metadata = null
    ): self {
        return self::create([
            'merchant_id' => $merchantId,
            'event'       => $event,
            'category'    => $category,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'user_agent'  => substr((string)request()->userAgent(), 0, 250),
            'status'      => $status,
            'metadata'    => $metadata,
        ]);
    }
}
