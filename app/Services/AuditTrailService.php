<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class AuditTrailService
{
    /**
     * Log an activity to the audit trail.
     *
     * @param  string      $action     Action type (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, etc.)
     * @param  string      $entityType Entity type (customer, invoice, package, user, etc.)
     * @param  string|int  $entityId   Entity identifier
     * @param  array|null  $oldValues  Previous state of the entity
     * @param  array|null  $newValues  New state of the entity
     * @return AuditLog
     */
    public function log(
        string $action,
        string $entityType,
        string|int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $data = [
            'user_id'     => Auth::id(),
            'tenant_id'   => session('tenant_id'),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => (string) $entityId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
        ];

        return AuditLog::create($data);
    }

    /**
     * Convenience: Log a CREATE action.
     */
    public function logCreate(string $entityType, string|int $entityId, array $newValues): AuditLog
    {
        return $this->log('CREATE', $entityType, $entityId, null, $newValues);
    }

    /**
     * Convenience: Log an UPDATE action with before/after values.
     */
    public function logUpdate(string $entityType, string|int $entityId, array $oldValues, array $newValues): AuditLog
    {
        return $this->log('UPDATE', $entityType, $entityId, $oldValues, $newValues);
    }

    /**
     * Convenience: Log a DELETE action.
     */
    public function logDelete(string $entityType, string|int $entityId, array $oldValues): AuditLog
    {
        return $this->log('DELETE', $entityType, $entityId, $oldValues, null);
    }

    /**
     * Get audit logs for a specific user.
     *
     * @param  int  $userId
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserLogs(int $userId, int $limit = 50)
    {
        return AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for a specific tenant.
     *
     * @param  int  $tenantId
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTenantLogs(int $tenantId, int $limit = 50)
    {
        return AuditLog::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent audit logs with optional filters.
     *
     * @param  array  $filters  Supported keys: action, entity_type, user_id
     * @param  int    $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFilteredLogs(array $filters = [], int $limit = 50)
    {
        $query = AuditLog::query();

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }
}
