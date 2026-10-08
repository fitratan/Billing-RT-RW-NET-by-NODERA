<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanDraw extends Model
{
    use HasFactory;

    protected $table = 'arisan_draws';

    protected $fillable = [
        'period_id',
        'winning_group_member_id',
        'prize_amount',
        'draw_timestamp',
        'draw_seed_hash',
        'disbursement_status',
        'disbursement_proof',
    ];

    protected $casts = [
        'prize_amount' => 'float',
        'draw_timestamp' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(ArisanPeriod::class, 'period_id');
    }

    public function winningGroupMember()
    {
        return $this->belongsTo(ArisanGroupMember::class, 'winning_group_member_id');
    }
}
