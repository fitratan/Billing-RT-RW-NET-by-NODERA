<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanPeriod extends Model
{
    use HasFactory;

    protected $table = 'arisan_periods';

    protected $fillable = [
        'group_id',
        'period_number',
        'period_date',
        'due_date',
        'draw_date',
        'status',
    ];

    protected $casts = [
        'period_number' => 'integer',
        'period_date' => 'date',
        'due_date' => 'date',
        'draw_date' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(ArisanGroup::class, 'group_id');
    }

    public function payments()
    {
        return $this->hasMany(ArisanPayment::class, 'period_id');
    }

    public function draw()
    {
        return $this->hasOne(ArisanDraw::class, 'period_id');
    }

    /**
     * Determine if all slots/members have paid for this period.
     */
    public function isAllPaid(): bool
    {
        $group = $this->group;
        if (!$group) {
            return false;
        }

        $totalSlots = $group->groupMembers()->count() ?: $group->total_slots;
        if ($totalSlots === 0) {
            return false;
        }

        $paidCount = $this->relationLoaded('payments')
            ? $this->payments->where('status', 'PAID')->count()
            : $this->payments()->where('status', 'PAID')->count();

        return $paidCount >= $totalSlots;
    }

    /**
     * Get the count of paid payments in this period.
     */
    public function getPaidCountAttribute(): int
    {
        return $this->relationLoaded('payments')
            ? $this->payments->where('status', 'PAID')->count()
            : $this->payments()->where('status', 'PAID')->count();
    }
}
