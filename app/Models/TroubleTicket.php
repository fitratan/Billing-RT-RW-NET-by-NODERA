<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class TroubleTicket extends Model
{
    use TenantAware;

    protected $fillable = [
        'customer_id', 'customer_name', 'customer_phone', 'description',
        'title', 'notes', 'resolution_notes', 'attachment',
        'priority', 'status', 'assigned_to', 'resolved_by', 'router_id',
        'resolved_at', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
