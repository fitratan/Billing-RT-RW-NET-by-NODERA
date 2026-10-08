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
     * Check if customer is configured as Static IP
     */
    public static function isStaticCustomer(Customer $customer): bool
    {
        $proto = strtolower(trim((string) ($customer->connection_type ?? '')));
        if ($proto === 'static' || $proto === 'static_ip' || $proto === 'ip_binding') {
            return true;
        }

        return empty($customer->pppoe_username) && !empty($customer->ip_address);
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

        // 1. Update Database status to active
        $customer->update([
            'status' => 'active',
            'updated_at' => now(),
        ]);

        // JIKA PELANGGAN SEBELUMNYA TIDAK TERISOLIR DAN BUKAN DIPAKSA:
        // Jangan ganggu koneksi aktif pelanggan di MikroTik
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
            }
        }

        // Sync with RADIUS server if active
        try {
            $radiusService = app(RadiusService::class);
            $radiusService->unisolateCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] RADIUS un-isolate notice: " . $e->getMessage());
        }

        // 2. Record in Audit Log
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
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System / Webhook Trigger',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] Failed to write AuditLog: " . $e->getMessage());
        }

        Log::info("[IsolationService] Customer #{$customer->id} ({$customer->name}) un-isolated successfully by {$actorName}.");
        return true;
    }

    /**
     * Isolate customer connection across MikroTik & Database
     */
    public function isolateCustomer(Customer|array|int $customerOrId, ?string $actorName = 'System / Auto-Isolir', bool $force = false): bool
    {
        $customer = null;
        if ($customerOrId instanceof Customer) {
            $customer = $customerOrId;
        } elseif (is_numeric($customerOrId)) {
            $customer = Customer::withoutGlobalScopes()->with('package')->find($customerOrId);
        } elseif (is_array($customerOrId) && isset($customerOrId['id'])) {
            $customer = Customer::withoutGlobalScopes()->with('package')->find($customerOrId['id']);
        }

        if (!$customer) {
            return false;
        }

        if (!$customer->relationLoaded('package') && $customer->package_id) {
            $customer->load('package');
        }

        // 1. Cek jika auto_isolir dimatikan pada paket pelanggan (hanya berlaku jika bukan dipaksa manual oleh Admin)
        if (!$force && $customer->package && !empty($customer->package->id) && empty($customer->package->auto_isolir)) {
            Log::info("[IsolationService] Skipping isolation for customer #{$customer->id} ({$customer->name}) because package '{$customer->package->name}' has auto_isolir disabled.");
            return false;
        }

        // 2. Cek jika pelanggan baru dibuat/sync di bulan berjalan pada atau setelah tanggal isolir
        if (!$force && $customer->created_at) {
            $createdAt = \Carbon\Carbon::parse($customer->created_at);
            $isoDay = (int) ($customer->isolation_date ?? 20);
            if ($createdAt->format('Y-m') === now()->format('Y-m') && $createdAt->day >= $isoDay) {
                Log::info("[IsolationService] Skipping auto-isolation for customer #{$customer->id} ({$customer->name}): registered/synced on {$createdAt->format('Y-m-d')} on or after isolation day ({$isoDay}). Auto-isolation starts next month.");
                return false;
            }
        }

        $router = $customer->router_id ? Mikrotik::withoutGlobalScopes()->find($customer->router_id) : null;
        $mikrotikSuccess = false;
        $routerAttempted = false;

        if ($router && $router->is_active) {
            $routerAttempted = true;
            if (!RouterCircuitBreaker::isAvailable($router->id)) {
                Log::warning("[IsolationService] Router #{$router->id} ({$router->name}) circuit is OPEN. Skipping direct connection.");
            } else {
                $mik = new MikrotikService([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '',
                    'port' => (int) $router->port,
                ]);

                if ($mik->isConnected()) {
                    $isStatic = self::isStaticCustomer($customer);
                    $addressList = $this->getIsolirAddressList($customer->tenant_id, $customer->package);

                    if ($isStatic && !empty($customer->ip_address)) {
                        try {
                            // 1. Masukkan IP ke Firewall Address-List Isolir
                            $mik->addAddressList($addressList, $customer->ip_address, "Nodera-Overdue-{$customer->id}");
                            // 2. Pastikan ARP tetap aktif agar user dapat me-load halaman isolir di webproxy gateway
                            $mik->setArpDisabled($customer->ip_address, false);
                            // 3. Batasi kecepatan ke Simple Queue isolir
                            $profileIsolir = $customer->package?->profile_isolir ?? '128k/128k';
                            if (empty($profileIsolir) || strtolower($profileIsolir) === 'isolir') {
                                $profileIsolir = '128k/128k';
                            }
                            $mik->addSimpleQueue("STATIC - " . $customer->name, $customer->ip_address, $profileIsolir, "NODERA Static IP (ISOLIR)");
                            $mikrotikSuccess = true;
                            RouterCircuitBreaker::recordSuccess($router->id);
                        } catch (\Throwable $e) {
                            RouterCircuitBreaker::recordFailure($router->id);
                            Log::error("[IsolationService] Static isolate error: " . $e->getMessage());
                        }
                    } elseif (!empty($customer->pppoe_username)) {
                        try {
                            // Dynamic Address-List Isolation (Anti-PADI Storm)
                            $activeIp = $mik->getActiveSessionIp($customer->pppoe_username) ?: $customer->ip_address;
                            if ($activeIp) {
                                $mik->addAddressList($addressList, $activeIp, "Nodera-Overdue-{$customer->id}");
                            }

                            $profileIsolir = $customer->package?->profile_isolir ?? null;
                            if ($profileIsolir) {
                                $mik->setPppoeUserProfile($customer->pppoe_username, $profileIsolir);
                            } else {
                                $mik->disablePppoeSecret($customer->pppoe_username);
                            }

                            // If no active IP was found in address-list, kick session so they connect isolated
                            if (!$activeIp) {
                                $mik->kickPppoeUser($customer->pppoe_username);
                            }

                            $mikrotikSuccess = true;
                            RouterCircuitBreaker::recordSuccess($router->id);
                        } catch (\Throwable $e) {
                            RouterCircuitBreaker::recordFailure($router->id);
                            Log::error("[IsolationService] PPPoE isolate error: " . $e->getMessage());
                        }
                    }
                } else {
                    RouterCircuitBreaker::recordFailure($router->id);
                    Log::warning("[IsolationService] Cannot connect to router {$router->name} ({$router->host}): " . $mik->getLastError());
                }
            }
        }

        // Sync with RADIUS server if active
        try {
            $radiusService = app(RadiusService::class);
            $radiusService->isolateCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning("[IsolationService] RADIUS isolate notice: " . $e->getMessage());
        }

        // Update database status ONLY if router action succeeded OR if no router is assigned OR forced
        $canUpdateDb = $mikrotikSuccess || !$routerAttempted || $force;

        if ($canUpdateDb) {
            $customer->update([
                'status' => 'isolated',
                'updated_at' => now(),
            ]);
        } else {
            Log::warning("[IsolationService] Router isolation failed for customer #{$customer->id} ({$customer->name}). DB status kept as 'active' for cron retry.");
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
                    'actor' => $actorName,
                    'mikrotik_synced' => $mikrotikSuccess,
                    'db_updated' => $canUpdateDb,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System Auto-Isolir',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return $canUpdateDb;
    }

    /**
     * Get configured isolir firewall address-list name
     */
    public function getIsolirAddressList(?int $tenantId, ?\App\Models\Package $package = null): string
    {
        if ($package && !empty($package->isolir_address_list)) {
            return trim($package->isolir_address_list);
        }

        if (!$tenantId) {
            return 'ISOLIR_LIST';
        }

        try {
            $addon = \App\Models\Addon::where('slug', 'paket_isolir')->first();
            if ($addon) {
                $tenantAddon = \App\Models\TenantAddon::where('tenant_id', $tenantId)
                    ->where('addon_id', $addon->id)
                    ->first();
                if (!empty($tenantAddon?->config['isolir_address_list'])) {
                    return trim($tenantAddon->config['isolir_address_list']);
                }
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return 'ISOLIR_LIST';
    }
}
