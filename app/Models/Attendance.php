<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use TenantAware;

    protected $table = 'attendance';

    protected $fillable = [
        'user_id', 'collector_id', 'date', 'check_in', 'check_out',
        'notes', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function collector()
    {
        return $this->belongsTo(Collector::class);
    }
}
