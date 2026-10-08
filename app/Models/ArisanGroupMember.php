<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanGroupMember extends Model
{
    use HasFactory;

    protected $table = 'arisan_group_members';

    protected $fillable = [
        'group_id',
        'member_id',
        'slot_number',
        'has_won',
        'won_period_id',
    ];

    protected $casts = [
        'slot_number' => 'integer',
        'has_won' => 'boolean',
        'won_period_id' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ArisanGroup::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(ArisanMember::class, 'member_id');
    }

    public function wonPeriod()
    {
        return $this->belongsTo(ArisanPeriod::class, 'won_period_id');
    }

    public function payments()
    {
        return $this->hasMany(ArisanPayment::class, 'group_member_id');
    }

    public function draws()
    {
        return $this->hasMany(ArisanDraw::class, 'winning_group_member_id');
    }
}
