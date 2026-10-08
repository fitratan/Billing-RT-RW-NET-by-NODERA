<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'customer_id', 'customer_name', 'invoice_number', 'amount',
        'due_date', 'paid_at', 'period', 'paid', 'status', 'tenant_id',
        'description', 'payment_method', 'payment_ref',
        'processed_by', 'periods_breakdown', 'accumulated_from',
        'unique_code', 'unique_amount', 'collector_id',
        'telegram_message_id', 'telegram_chat_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid' => 'boolean',
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
