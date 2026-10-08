<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUsage extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'customer_usage';

    protected $fillable = [
        'customer_id',
        'tenant_id',
        'period_month',
        'period_year',
        'bytes_in',
        'bytes_out',
        'last_total_bytes_in',
        'last_total_bytes_out',
        'last_update',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'last_total_bytes_in' => 'integer',
            'last_total_bytes_out' => 'integer',
            'last_update' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get total usage in gigabytes.
     */
    public function getTotalGbAttribute(): float
    {
        return ($this->bytes_in + $this->bytes_out) / (1024 * 1024 * 1024);
    }

    /**
     * Get download usage in gigabytes (bytes_out = traffic sent from router to client).
     */
    public function getDownloadGbAttribute(): float
    {
        return $this->bytes_out / (1024 * 1024 * 1024);
    }

    /**
     * Get upload usage in gigabytes (bytes_in = traffic received by router from client).
     */
    public function getUploadGbAttribute(): float
    {
        return $this->bytes_in / (1024 * 1024 * 1024);
    }
}
