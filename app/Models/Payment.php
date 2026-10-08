<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use TenantAware;

    protected $fillable = [
        'invoice_id', 'amount', 'payment_method', 'payment_reference',
        'notes', 'paid_at', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
