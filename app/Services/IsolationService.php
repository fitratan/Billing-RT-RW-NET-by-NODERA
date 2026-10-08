<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;

class IsolationService
{
    /**
     * Check whether customer uses Static IP connection mode
     */
    public static function isStaticCustomer(Customer|array|int $customerOrId): bool
    {
        $customer = null;
        if ($customerOrId instanceof Customer) {
            $customer = $customerOrId;
        } elseif (is_numeric($customerOrId)) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId);
        } elseif (is_array($customerOrId) && isset($customerOrId['id'])) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId['id']);
        }

        if (!$customer) {
            return false;
        }

        $isStatic = (bool) ($customer->is_static ?? false);
        $connType = strtolower(trim((string) ($customer->connection_type ?? '')));
        $hasStaticType = in_array($connType, ['static', 'static_ip', 'ip_static', 'dhcp', 'hotspot_static'], true);
        $hasNoPppoe = empty($customer->pppoe_username) && !empty($customer->ip_address);

        return $isStatic || $hasStaticType || $hasNoPppoe;
    }

    /**
     * Un-isolate customer connection across MikroTik & Database
     */
    public function unisolateCustomer(Customer|array|int $customerOrId, ?string $actorName = 'System / Webhook', bool $force = false): bool
    {
        $customer = null;
        if ($customerOrId instanceof Customer) {
            $customer = $customerOrId;
        } elseif (is_numeric($customerOrId)) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId);
        } elseif (is_array($customerOrId) && isset($customerOrId['id'])) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId['id']);
        }

        if (!$customer) {
            Log::warning("[IsolationService] Customer not found for un-isolation.");
            return false;
        }

        $wasIsolated = ($customer->status === 'isolated');

        // If customer was not isolated and not forced, nothing to do
        if (!$wasIsolated && !$force) {
            return true;
        }

        $router = $customer->router_id ? Mikrotik::withoutGlobalScopes()->find($customer->router_id) : null;
        $mikrotikSuccess = false;

        if ($router && $router->is_active) {
            $mik = new MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) $router->port,
            ]);

            if ($mik->isConnected()) {
                $isStatic = self::isStaticCustomer($customer);

                if ($isStatic && !empty($customer->ip_address)) {
                    // Static IP: enable ARP, remove from address list and restore Simple Queue
                    try {
                        $addressList = $this->getIsolirAddressList($customer->tenant_id, $customer->package);
                        $mik->removeAddressList($addressList, $customer->ip_address);
                        if ($addressList !== 'ISOLIR_LIST') {
                            $mik->removeAddressList('ISOLIR_LIST', $customer->ip_address);
                        }
                        $mik->setArpDisabled($customer->ip_address, false);
                        $profileNormal = $customer->package?->profile_normal ?? '10M/10M';
                        $mik->addSimpleQueue("STATIC - " . $customer->name, $customer->ip_address, $profileNormal, "NODERA Static IP");
                        $mikrotikSuccess = true;
                    } catch (\Throwable $e) {
                        Log::error("[IsolationService] Static un-isolate error: " . $e->getMessage());
                    }
                } elseif (!empty($customer->pppoe_username)) {
                    // PPPoE: Remove active session IP from address list (Instant Online!), restore secret profile
                    try {
                        $addressList = $this->getIsolirAddressList($customer->tenant_id, $customer->package);
                        $activeIp = $mik->getActiveSessionIp($customer->pppoe_username) ?: $customer->ip_address;
                        if ($activeIp) {
                            $mik->removeAddressList($addressList, $activeIp);
                            if ($addressList !== 'ISOLIR_LIST') {
                                $mik->removeAddressList('ISOLIR_LIST', $activeIp);
                            }
                        }

                        $mik->enablePppoeSecret($customer->pppoe_username);
                        $profileNormal = $customer->package?->profile_normal ?? null;
                        if ($profileNormal) {
                            $mik->setPppoeUserProfile($customer->pppoe_username, $profileNormal);
                        }

                        // If user has no active IP, kick session to reconnect with normal profile
                        if (!$activeIp) {
                            $mik->kickPppoeUser($customer->pppoe_username);
                        }
                        $mikrotikSuccess = true;
                    } catch (\Throwable $e) {
                        Log::error("[IsolationService] PPPoE un-isolate error: " . $e->getMessage());
                    }
                }
            } else {
                Log::warning("[IsolationService] Router #{$router->id} ({$router->name}) unreachable during un-isolation for Customer #{$customer->id}.");
            }
        } else {
            // Customer has no active router assigned
            $mikrotikSuccess = true;
        }

        // Sync with RADIUS server if active
        try {
            $radiusService = app(RadiusService::class);
            $radiusService->unisolateCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] RADIUS un-isolate notice: " . $e->getMessage());
        }

        // Update Database status ONLY on router success OR manual force override
        if ($mikrotikSuccess || $force) {
            $customer->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);
        }

        // Record in Audit Log
        try {
            AuditLog::create([
                'tenant_id' => $customer->tenant_id,
                'user_id' => auth()->id() ?? null,
                'action' => 'customer_unisolated',
                'entity_type' => 'customer',
                'entity_id' => $customer->id,
                'new_values' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'pppoe_username' => $customer->pppoe_username,
                    'router_id' => $customer->router_id,
                    'actor' => $actorName,
                    'mikrotik_synced' => $mikrotikSuccess,
                    'status_updated' => ($mikrotikSuccess || $force),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System / Webhook Trigger',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] Failed to write AuditLog: " . $e->getMessage());
        }

        if (!$mikrotikSuccess && !$force) {
            Log::warning("[IsolationService] Un-isolation incomplete for Customer #{$customer->id} (Router offline/unsynced). Status kept isolated for automatic retry.");
            return false;
        }

        Log::info("[IsolationService] Customer #{$customer->id} ({$customer->name}) un-isolated successfully by {$actorName}.");
        return true;
    }

    /**
     * Isolate customer connection across MikroTik & Database
     */
    public function isolateCustomer(Customer|array|int $customerOrId, ?string $actorName = 'System / Webhook', bool $force = false): bool
    {
        $customer = null;
        if ($customerOrId instanceof Customer) {
            $customer = $customerOrId;
        } elseif (is_numeric($customerOrId)) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId);
        } elseif (is_array($customerOrId) && isset($customerOrId['id'])) {
            $customer = Customer::withoutGlobalScopes()->find($customerOrId['id']);
        }

        if (!$customer) {
            Log::warning("[IsolationService] Customer not found for isolation.");
            return false;
        }

        $router = $customer->router_id ? Mikrotik::withoutGlobalScopes()->find($customer->router_id) : null;
        $mikrotikSuccess = false;

        if ($router && $router->is_active) {
            $mik = new MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) $router->port,
            ]);

            if ($mik->isConnected()) {
                $isStatic = self::isStaticCustomer($customer);

                if ($isStatic && !empty($customer->ip_address)) {
                    // Static IP Mode: add to address list and disable ARP
                    try {
                        $addressList = $this->getIsolirAddressList($customer->tenant_id, $customer->package);
                        $mik->addAddressList($addressList, $customer->ip_address, "NODERA Isolir - " . $customer->name);
                        $mik->setArpDisabled($customer->ip_address, true);
                        $mikrotikSuccess = true;
                    } catch (\Throwable $e) {
                        Log::error("[IsolationService] Static isolate error: " . $e->getMessage());
                    }
                } elseif (!empty($customer->pppoe_username)) {
                    // PPPoE Mode: add to isolir address list, change secret profile and kick
                    try {
                        $addressList = $this->getIsolirAddressList($customer->tenant_id, $customer->package);
                        $activeIp = $mik->getActiveSessionIp($customer->pppoe_username) ?: $customer->ip_address;
                        if ($activeIp) {
                            $mik->addAddressList($addressList, $activeIp, "NODERA Isolir - " . $customer->name);
                        }

                        $profileIsolir = $customer->package?->profile_isolir ?? 'ISOLIR';
                        $mik->setPppoeUserProfile($customer->pppoe_username, $profileIsolir);
                        $mik->kickPppoeUser($customer->pppoe_username);
                        $mikrotikSuccess = true;
                    } catch (\Throwable $e) {
                        Log::error("[IsolationService] PPPoE isolate error: " . $e->getMessage());
                    }
                }
            } else {
                Log::warning("[IsolationService] Router #{$router->id} ({$router->name}) unreachable during isolation for Customer #{$customer->id}.");
            }
        } else {
            // Customer has no active router assigned
            $mikrotikSuccess = true;
        }

        // Sync with RADIUS server if active
        try {
            $radiusService = app(RadiusService::class);
            $radiusService->isolateCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] RADIUS isolate notice: " . $e->getMessage());
        }

        // Update Database status ONLY on router success OR manual force override
        if ($mikrotikSuccess || $force) {
            $customer->update([
                'status' => 'isolated',
                'updated_at' => now(),
            ]);
        }

        // Record in Audit Log
        try {
            AuditLog::create([
                'tenant_id' => $customer->tenant_id,
                'user_id' => auth()->id() ?? null,
                'action' => 'customer_isolated',
                'entity_type' => 'customer',
                'entity_id' => $customer->id,
                'new_values' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'pppoe_username' => $customer->pppoe_username,
                    'router_id' => $customer->router_id,
                    'actor' => $actorName,
                    'mikrotik_synced' => $mikrotikSuccess,
                    'status_updated' => ($mikrotikSuccess || $force),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System / Webhook Trigger',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] Failed to write AuditLog: " . $e->getMessage());
        }

        if (!$mikrotikSuccess && !$force) {
            Log::warning("[IsolationService] Isolation incomplete for Customer #{$customer->id} (Router offline/unsynced). Status kept active for automatic retry.");
            return false;
        }

        Log::info("[IsolationService] Customer #{$customer->id} ({$customer->name}) isolated successfully by {$actorName}.");
        return true;
    }

    /**
     * Resolve isolir address list name for tenant
     */
    private function getIsolirAddressList(?int $tenantId, $package = null): string
    {
        if ($package && !empty($package->isolir_address_list)) {
            return trim($package->isolir_address_list);
        }

        return 'ISOLIR_LIST';
    }
}
