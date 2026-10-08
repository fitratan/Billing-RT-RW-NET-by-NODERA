<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanCashflow extends Model
{
    use HasFactory;

    protected $table = 'arisan_cashflows';

    protected $fillable = [
        'subscription_id',
        'group_id',
        'type',
        'category',
        'amount',
        'transaction_date',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'date',
    ];

    public function subscription()
    {
        return $this->belongsTo(ArisanSubscription::class, 'subscription_id');
    }

    public function group()
    {
        return $this->belongsTo(ArisanGroup::class, 'group_id');
    }
}
