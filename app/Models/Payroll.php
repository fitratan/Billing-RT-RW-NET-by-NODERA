<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use TenantAware;

    protected $table = 'payroll';

    protected $fillable = [
        'user_id', 'period_month', 'period_year',
        'salary', 'bonus', 'deductions', 'total', 'paid_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deductions' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
