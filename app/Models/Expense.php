<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'description', 'amount', 'category', 'date', 'notes', 'vendor',
        'receipt_number', 'payment_method', 'recorded_by', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function scopeForPeriod($query, $year, $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }
}
