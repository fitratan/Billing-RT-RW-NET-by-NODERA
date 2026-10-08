<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanGroup extends Model
{
    use HasFactory;

    protected $table = 'arisan_groups';

    protected $fillable = [
        'subscription_id',
        'name',
        'period_type',
        'dues_amount',
        'total_slots',
        'admin_fee_per_period',
        'start_date',
        'due_day',
        'draw_day',
        'status',
    ];

    protected $casts = [
        'dues_amount' => 'float',
        'admin_fee_per_period' => 'float',
        'total_slots' => 'integer',
        'due_day' => 'integer',
        'draw_day' => 'integer',
        'start_date' => 'date',
    ];

    public function subscription()
    {
        return $this->belongsTo(ArisanSubscription::class, 'subscription_id');
    }

    public function groupMembers()
    {
        return $this->hasMany(ArisanGroupMember::class, 'group_id');
    }

    public function members()
    {
        return $this->belongsToMany(ArisanMember::class, 'arisan_group_members', 'group_id', 'member_id')
            ->withPivot('slot_number', 'has_won', 'won_period_id')
            ->withTimestamps();
    }

    public function periods()
    {
        return $this->hasMany(ArisanPeriod::class, 'group_id');
    }

    public function cashflows()
    {
        return $this->hasMany(ArisanCashflow::class, 'group_id');
    }

    /**
     * Generate periods for all total_slots in this group.
     */
    public function generatePeriods(): Collection
    {
        $periods = new Collection();
        $startDate = $this->start_date ? Carbon::parse($this->start_date) : Carbon::today();
        $totalSlots = $this->total_slots ?: 10;
        $periodType = strtoupper($this->period_type ?: 'MONTHLY');

        for ($i = 1; $i <= $totalSlots; $i++) {
            if ($periodType === 'MONTHLY') {
                $periodDate = (clone $startDate)->addMonthsNoOverflow($i - 1);
                $dueDay = $this->due_day ?: 10;
                $drawDay = $this->draw_day ?: 15;

                $dueDate = (clone $periodDate)->day(min($dueDay, $periodDate->daysInMonth));
                $drawDate = (clone $periodDate)->day(min($drawDay, $periodDate->daysInMonth));
            } elseif ($periodType === 'WEEKLY') {
                $periodDate = (clone $startDate)->addWeeks($i - 1);
                $dueDate = clone $periodDate;
                $drawDate = (clone $periodDate)->addDays(5);
            } else { // DAILY or custom
                $periodDate = (clone $startDate)->addDays($i - 1);
                $dueDate = clone $periodDate;
                $drawDate = clone $periodDate;
            }

            $period = ArisanPeriod::firstOrCreate(
                [
                    'group_id' => $this->id,
                    'period_number' => $i,
                ],
                [
                    'period_date' => $periodDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'draw_date' => $drawDate->toDateString(),
                    'status' => 'COLLECTING',
                ]
            );

            $periods->push($period);
        }

        return $periods;
    }
}
