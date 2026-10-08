<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanPaymentSetting extends Model
{
    use HasFactory;

    protected $table = 'arisan_payment_settings';

    protected $fillable = [
        'subscription_id',
        'bank_name',
        'account_number',
        'account_holder',
        'qris_image_path',
        'instructions',
    ];

    public function subscription()
    {
        return $this->belongsTo(ArisanSubscription::class, 'subscription_id');
    }
}
