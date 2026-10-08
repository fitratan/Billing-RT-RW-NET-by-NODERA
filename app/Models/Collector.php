<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Collector extends Model
{
    use TenantAware;

    protected $table = 'collectors';

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'username',
        'collection_area', 'commission_type', 'commission_value',
        'balance', 'is_active', 'permissions', 'tenant_id', 'router_id',
    ];

    protected $hidden = ['password'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function router()
    {
        return $this->belongsTo(Mikrotik::class, 'router_id');
    }

    protected static function booted()
    {
        static::updated(function (Collector $collector) {
            if ($collector->wasChanged('name')) {
                $oldName = $collector->getOriginal('name');
                
                // Update all invoices with this collector_id
                \App\Models\Invoice::withoutGlobalScopes()
                    ->where('collector_id', $collector->id)
                    ->update(['processed_by' => $collector->name]);

                // Also update any unlinked invoices that matched the old collector name
                if (!empty($oldName)) {
                    \App\Models\Invoice::withoutGlobalScopes()
                        ->where('tenant_id', $collector->tenant_id)
                        ->where('processed_by', $oldName)
                        ->update([
                            'processed_by' => $collector->name,
                            'collector_id' => $collector->id,
                        ]);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }
}
