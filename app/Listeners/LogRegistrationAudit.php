<?php

namespace App\Listeners;

use App\Events\RegistrationApproved;
use App\Models\AuditLog;

class LogRegistrationAudit
{
    public function handle(RegistrationApproved $event): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'registration.approved',
                    'entity_type' => 'registration_request',
                    'entity_id' => $event->registration->id,
                    'new_values' => [
                        'tenant_id' => $event->tenant->id,
                        'slug' => $event->registration->slug,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[LogRegistrationAudit] Failed: ' . $e->getMessage());
        }
    }
}
