<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NmsTelegramMonitorService
{
    private TenantTelegramService $telegram;

    public function __construct(TenantTelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Run all NMS monitoring checks across active tenants.
     */
    public function runChecks(): void
    {
        $tenants = Tenant::withoutGlobalScopes()->where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            try {
                $config = $this->telegram->getTenantConfig($tenant->id);
                if (!$config['enabled'] || !$config['nms_notif_enabled']) {
                    continue;
                }

                $this->checkTenant($tenant, $config);
            } catch (\Throwable $e) {
                Log::error("NMS Telegram Monitor Error for tenant [{$tenant->id}] {$tenant->name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Perform checks for a single tenant.
     */
    public function checkTenant(Tenant $tenant, array $config): void
    {
        $routers = Mikrotik::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        foreach ($routers as $router) {
            try {
                $this->checkRouterAndServices($tenant, $router, $config);
            } catch (\Throwable $e) {
                Log::warning("NMS check failed for router [{$router->id}] {$router->name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check single router connectivity and its services (PPPoE, Hotspot, ARP).
     */
    private function checkRouterAndServices(Tenant $tenant, Mikrotik $router, array $config): void
    {
        $routerStatusKey = "nms_router_status_{$router->id}";
        $prevRouterStatus = Cache::get($routerStatusKey, null); // 'online', 'offline', or null

        $mik = new MikrotikService($router);
        $isConnected = $mik->isConnected();

        $dateStr = now()->format('Y-m-d');
        $timeStr = now()->format('H:i:s');

        // 1. Router Status Check
        if ($config['nms_router']) {
            if ($isConnected) {
                if ($prevRouterStatus === 'offline') {
                    // RECOVERY
                    $res = $mik->getResource(false);
                    $cpu = $res['cpu_load'] ?? '-';
                    $uptime = $res['uptime'] ?? '-';

                    $msg = "<b>🟢 ROUTER ONLINE — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($router->name) . "\n"
                        . "├ Status: ONLINE\n"
                        . "├ Host: <code>{$router->host}:{$router->port}</code>\n"
                        . "├ CPU: {$cpu}%\n"
                        . "├ Uptime: {$uptime}\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'router');
                }
                Cache::forever($routerStatusKey, 'online');
            } else {
                if ($prevRouterStatus === 'online') {
                    // OFFLINE ALERT
                    $err = $mik->getLastError() ?: 'Connection Timeout / Unreachable';

                    $msg = "<b>🔴 ROUTER OFFLINE — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($router->name) . "\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Host: <code>{$router->host}:{$router->port}</code>\n"
                        . "├ Penyebab: " . htmlspecialchars($err) . "\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'router');
                }
                Cache::forever($routerStatusKey, 'offline');
                return; // Jika router offline, tidak bisa cek PPPoE/Hotspot/ARP
            }
        }

        if (!$isConnected) {
            return;
        }

        // 2. PPPoE Secrets Check
        if ($config['nms_pppoe']) {
            $this->checkPppoeStatus($tenant, $router, $mik);
        }

        // 3. Hotspot Active Check
        if ($config['nms_hotspot']) {
            $this->checkHotspotStatus($tenant, $router, $mik);
        }

        // 4. ARP / IP Binding Check
        if ($config['nms_arp']) {
            $this->checkArpStatus($tenant, $router, $mik);
        }
    }

    /**
     * Check PPPoE online/offline transitions.
     */
    private function checkPppoeStatus(Tenant $tenant, Mikrotik $router, MikrotikService $mik): void
    {
        $pppoeCacheKey = "nms_pppoe_active_{$router->id}";
        $activePpp = $mik->getPppoeActive();

        if (!is_array($activePpp)) {
            return;
        }

        $currentMap = [];
        foreach ($activePpp as $item) {
            $uname = $item['name'] ?? '';
            if ($uname) {
                $callerId = $item['caller-id'] ?? ($item['caller_id'] ?? ($item['callerid'] ?? '-'));
                $currentMap[$uname] = [
                    'address' => $item['address'] ?? '-',
                    'caller_id' => !empty($callerId) ? $callerId : '-',
                    'uptime' => $item['uptime'] ?? '-',
                    'service' => $item['service'] ?? 'pppoe',
                ];
            }
        }

        $prevMap = Cache::get($pppoeCacheKey, null);

        if ($prevMap !== null && is_array($prevMap)) {
            $disconnected = array_diff_key($prevMap, $currentMap);
            $connected = array_diff_key($currentMap, $prevMap);

            $dateStr = now()->format('Y-m-d');
            $timeStr = now()->format('H:i:s');
            $onlineCount = count($currentMap);

            // Notify Disconnected
            if (!empty($disconnected) && count($disconnected) <= 15) {
                foreach ($disconnected as $u => $d) {
                    $msg = "<b>🔴 PPPOE OFFLINE — NODERA</b>\n\n"
                        . "┌ <code>" . htmlspecialchars($u) . "</code>\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ IP: <code>{$d['address']}</code>\n"
                        . "├ CallerID: <code>{$d['caller_id']}</code>\n"
                        . "├ Total Online: {$onlineCount} user\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'pppoe');
                }
            }

            // Notify Connected
            if (!empty($connected) && count($connected) <= 15) {
                foreach ($connected as $u => $d) {
                    $msg = "<b>🟢 PPPOE ONLINE — NODERA</b>\n\n"
                        . "┌ <code>" . htmlspecialchars($u) . "</code>\n"
                        . "├ Status: ONLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ IP: <code>{$d['address']}</code>\n"
                        . "├ CallerID: <code>{$d['caller_id']}</code>\n"
                        . "├ Total Online: {$onlineCount} user\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'pppoe');
                }
            }
        }

        Cache::put($pppoeCacheKey, $currentMap, 86400);
    }

    /**
     * Check Hotspot online/offline transitions.
     */
    private function checkHotspotStatus(Tenant $tenant, Mikrotik $router, MikrotikService $mik): void
    {
        $hsCacheKey = "nms_hs_active_{$router->id}";
        $activeHs = $mik->getHotspotActive();

        if (!is_array($activeHs)) {
            return;
        }

        $currentMap = [];
        foreach ($activeHs as $item) {
            $uname = $item['user'] ?? ($item['name'] ?? '');
            if ($uname) {
                $mac = $item['mac_address'] ?? ($item['mac-address'] ?? ($item['mac'] ?? '-'));
                $currentMap[$uname] = [
                    'address' => $item['address'] ?? '-',
                    'mac' => !empty($mac) ? $mac : '-',
                    'uptime' => $item['uptime'] ?? '-',
                ];
            }
        }

        $prevMap = Cache::get($hsCacheKey, null);

        if ($prevMap !== null && is_array($prevMap)) {
            $disconnected = array_diff_key($prevMap, $currentMap);
            $connected = array_diff_key($currentMap, $prevMap);

            $dateStr = now()->format('Y-m-d');
            $timeStr = now()->format('H:i:s');
            $onlineCount = count($currentMap);

            if (!empty($disconnected) && count($disconnected) <= 10) {
                foreach ($disconnected as $u => $d) {
                    $msg = "<b>🔴 HOTSPOT OFFLINE — NODERA</b>\n\n"
                        . "┌ <code>" . htmlspecialchars($u) . "</code>\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ IP: <code>{$d['address']}</code>\n"
                        . "├ MAC: <code>{$d['mac']}</code>\n"
                        . "├ Total Online: {$onlineCount} user\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'hotspot');
                }
            }

            if (!empty($connected) && count($connected) <= 10) {
                foreach ($connected as $u => $d) {
                    $msg = "<b>🟢 HOTSPOT ONLINE — NODERA</b>\n\n"
                        . "┌ <code>" . htmlspecialchars($u) . "</code>\n"
                        . "├ Status: ONLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ IP: <code>{$d['address']}</code>\n"
                        . "├ MAC: <code>{$d['mac']}</code>\n"
                        . "├ Total Online: {$onlineCount} user\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'hotspot');
                }
            }
        }

        Cache::put($hsCacheKey, $currentMap, 86400);
    }

    /**
     * Check ARP table entries status.
     */
    private function checkArpStatus(Tenant $tenant, Mikrotik $router, MikrotikService $mik): void
    {
        $arpCacheKey = "nms_arp_active_{$router->id}";
        $arpList = $mik->getArpTable();

        if (!is_array($arpList)) {
            return;
        }

        $currentMap = [];
        foreach ($arpList as $item) {
            $ip = $item['address'] ?? '';
            if ($ip && ($item['complete'] ?? 'true') !== 'false') {
                $currentMap[$ip] = [
                    'mac' => $item['mac-address'] ?? '-',
                    'interface' => $item['interface'] ?? '-',
                ];
            }
        }

        $prevMap = Cache::get($arpCacheKey, null);

        if ($prevMap !== null && is_array($prevMap)) {
            $disconnected = array_diff_key($prevMap, $currentMap);
            $connected = array_diff_key($currentMap, $prevMap);

            $dateStr = now()->format('Y-m-d');
            $timeStr = now()->format('H:i:s');

            if (!empty($disconnected) && count($disconnected) <= 10) {
                foreach ($disconnected as $ip => $d) {
                    $msg = "<b>🔴 ARP HOST OFFLINE — NODERA</b>\n\n"
                        . "┌ <code>{$ip}</code>\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ MAC: <code>{$d['mac']}</code>\n"
                        . "├ Interface: " . htmlspecialchars($d['interface']) . "\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'arp');
                }
            }

            if (!empty($connected) && count($connected) <= 10) {
                foreach ($connected as $ip => $d) {
                    $msg = "<b>🟢 ARP HOST ONLINE — NODERA</b>\n\n"
                        . "┌ <code>{$ip}</code>\n"
                        . "├ Status: ONLINE\n"
                        . "├ Router: " . htmlspecialchars($router->name) . "\n"
                        . "├ MAC: <code>{$d['mac']}</code>\n"
                        . "├ Interface: " . htmlspecialchars($d['interface']) . "\n"
                        . "└ Waktu: {$dateStr} {$timeStr}";

                    $this->telegram->sendNmsNotification($tenant->id, $msg, null, 'arp');
                }
            }
        }

        Cache::put($arpCacheKey, $currentMap, 86400);
    }
}
