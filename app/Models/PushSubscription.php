<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id',
        'subscriber_type',
        'subscriber_id',
        'endpoint',
        'public_key',
        'auth_token',
        'raw_subscription',
        'user_agent',
    ];
}
