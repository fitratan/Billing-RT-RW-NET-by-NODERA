<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'tenant_id',
        'amount',
        'method',       // tripay, midtrans, manual, qris, digiflazz
        'gateway',      // gateway identifier
        'gateway_ref',  // reference from payment gateway
        'status',       // pending, success, failed, expired
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope successful transactions only.
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for a specific period.
     */
    public function scopeForPeriod($query, $year, $month)
    {
        return $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
    }

    /**
     * Scope pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
