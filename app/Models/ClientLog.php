<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class ClientLog extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id',
        'level',
        'message',
        'stack',
        'source',
        'role',
        'route',
        'url',
        'user_agent',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
