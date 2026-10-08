<?php

namespace App\Actions\Tenant;

use App\Models\AuditLog;
use App\Models\Tenant;

class UpdateTenant
{
    public function execute(Tenant $tenant, array $data): Tenant
    {
        $old = $tenant->toArray();
        $tenant->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'tenant.updated',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'old_values' => $old,
            'new_values' => $tenant->toArray(),
        ]);

        return $tenant->fresh();
    }
}
