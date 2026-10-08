<?php

namespace App\Actions\User;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTenantAdmin
{
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $password = $data['password'] ?? substr(md5(uniqid()), 0, 8);

            $username = $data['username'] ?? (strtolower(str_replace(' ', '', $data['name'])) . rand(100, 999));

            $user = User::create([
                'name' => $data['name'],
                'username' => $username,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tenant_id' => $data['tenant_id'],
                'role' => $data['role'],
                'password' => bcrypt($password),
                'is_active' => true,
            ]);

            // Sync with VpnUser for panel access
            try {
                $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($data['tenant_id']);
                $adminEmail = $data['email'] ?? ($username . '@' . ($tenant?->slug ?: 'nodera') . '.local');
                $vpnUser = null;
                if (!empty($data['email']) || !empty($data['phone'])) {
                    $vpnUser = \App\Models\VpnUser::where(function ($q) use ($data) {
                        if (!empty($data['email'])) $q->where('email', $data['email']);
                        if (!empty($data['phone'])) $q->orWhere('phone', $data['phone']);
                    })->first();
                }
                if (!$vpnUser) {
                    $vpnUser = \App\Models\VpnUser::create([
                        'name' => $data['name'],
                        'email' => $adminEmail,
                        'phone' => $data['phone'] ?? null,
                        'password' => bcrypt($password),
                        'saldo' => 0,
                        'bonus_saldo' => 0,
                        'is_active' => true,
                    ]);
                } else {
                    $vpnUser->update([
                        'password' => bcrypt($password),
                        'name' => $data['name'] ?: $vpnUser->name,
                        'phone' => $data['phone'] ?? $vpnUser->phone,
                    ]);
                }
                if ($tenant && empty($tenant->vpn_user_id)) {
                    $tenant->update(['vpn_user_id' => $vpnUser->id]);
                }
            } catch (\Throwable $e) {}

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'admin_tenant.created',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'new_values' => [
                    'tenant_id' => $data['tenant_id'],
                    'name' => $data['name'],
                    'role' => $data['role'],
                ],
            ]);

            return [
                'user' => $user,
                'password' => $password,
            ];
        });
    }
}
