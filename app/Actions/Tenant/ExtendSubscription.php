<?php

namespace App\Actions\Tenant;

use App\Models\AuditLog;
use App\Models\Tenant;

class ExtendSubscription
{
    public function execute(Tenant $tenant, int $months): Tenant
    {
        $oldExpiry = $tenant->expired_at ? $tenant->expired_at->copy() : null;
        $newExpiry = $oldExpiry && $oldExpiry > now()
            ? $oldExpiry->copy()->addMonths($months)
            : now()->addMonths($months);

        $tenant->update([
            'expired_at' => $newExpiry,
            'is_active' => true,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription.extended',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'old_values' => ['expired_at' => $oldExpiry?->format('Y-m-d')],
            'new_values' => ['expired_at' => $newExpiry->format('Y-m-d'), 'months' => $months],
        ]);

        return $tenant->fresh();
    }
}
