<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanPayment extends Model
{
    use HasFactory;

    protected $table = 'arisan_payments';

    protected $fillable = [
        'period_id',
        'group_member_id',
        'amount',
        'status',
        'payment_date',
        'payment_method',
        'proof_image',
        'verified_by_admin_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'datetime',
        'verified_by_admin_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(ArisanPeriod::class, 'period_id');
    }

    public function groupMember()
    {
        return $this->belongsTo(ArisanGroupMember::class, 'group_member_id');
    }
}
