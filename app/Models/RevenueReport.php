<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class RevenueReport extends Model
{
    use TenantAware;

    protected $table = 'revenue_reports';

    protected $fillable = [
        'period_year',
        'period_month',
        'total_revenue',
        'total_expenses',
        'profit_loss',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'profit_loss' => 'decimal:2',
        ];
    }

    /**
     * Auto-compute profit_loss when saving.
     */
    protected static function booted()
    {
        static::saving(function ($report) {
            $report->profit_loss = (float) $report->total_revenue - (float) $report->total_expenses;
        });
    }

    /**
     * Get the period label.
     */
    public function getPeriodLabelAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->period_month, 1, $this->period_year));
    }
}
