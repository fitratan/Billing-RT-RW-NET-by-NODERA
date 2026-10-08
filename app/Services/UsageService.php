<?php

namespace App\Services;

use App\Models\CustomerUsage;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * UsageService — bandwidth usage tracking for NODERA ISP customers.
 *
 * Polls MikroTik PPPoE active sessions to track inbound/outbound traffic
 * per customer per month. Handles MikroTik counter resets (reconnect).
 *
 * Reference: billing-rtrw-main/services/usageService.js
 */
class UsageService
{
    private MikrotikService $mikrotik;

    private static array $defaultRouters = [];

    public function __construct(?MikrotikService $mikrotik = null)
    {
        $this->mikrotik = $mikrotik ?? app(MikrotikService::class);
    }

    // ------------------------------------------------------------------
    //  CRUD
    // ------------------------------------------------------------------

    /**
     * Get usage record for a specific customer and period.
     */
    public function getUsage(int $customerId, int $month, int $year): ?CustomerUsage
    {
        return CustomerUsage::where('customer_id', $customerId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();
    }

    /**
     * Update usage with delta values and current MikroTik totals.
     *
     * If no record exists for the current month, creates one.
     * Handles MikroTik counter reset (reconnect) by using total values
     * as delta when the new total is less than the previous total.
     */
    public function updateUsage(
        int $customerId,
        int $deltaIn,
        int $deltaOut,
        int $totalIn,
        int $totalOut,
        ?int $tenantId = null
    ): CustomerUsage {
        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        $usage = $this->getUsage($customerId, $month, $year);

        if ($usage) {
            $usage->increment('bytes_in', $deltaIn);
            $usage->increment('bytes_out', $deltaOut);
            $usage->last_total_bytes_in = $totalIn;
            $usage->last_total_bytes_out = $totalOut;
            $usage->last_update = $now;
            $usage->save();
        } else {
            $usage = CustomerUsage::create([
                'customer_id' => $customerId,
                'tenant_id' => $tenantId,
                'period_month' => $month,
                'period_year' => $year,
                'bytes_in' => $deltaIn,
                'bytes_out' => $deltaOut,
                'last_total_bytes_in' => $totalIn,
                'last_total_bytes_out' => $totalOut,
                'last_update' => $now,
            ]);
        }

        return $usage->fresh();
    }

    /**
     * Get top N customers by total traffic for a given period.
     */
    public function getTopUsage(int $tenantId, int $limit = 10, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?? (int) now()->format('n');
        $year = $year ?? (int) now()->format('Y');

        return CustomerUsage::where('tenant_id', $tenantId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where(function ($q) {
                $q->where('bytes_in', '>', 0)
                  ->orWhere('bytes_out', '>', 0);
            })
            ->orderByRaw('(bytes_in + bytes_out) DESC')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ------------------------------------------------------------------
    //  Polling routines
    // ------------------------------------------------------------------

    /**
     * Poll all active routers and update usage for all active PPPoE sessions.
     *
     * Called every 10 minutes by CronService / Laravel scheduler.
     *
     * Returns array with counts of processed sessions and errors.
     */
    /**
     * Parse MikroTik uptime string (e.g. '2d03:15:20', '1w2d', '3h45m10s', '45s') into total seconds.
     */
    public static function parseUptimeToSeconds(?string $uptime): int
    {
        if (empty($uptime)) {
            return 0;
        }

        $totalSeconds = 0;
        $uptime = trim($uptime);

        if (preg_match('/(?:(\d+)w)?(?:(\d+)d)?(?:(\d{1,2}):(\d{2}):(\d{2}))/', $uptime, $m)) {
            $weeks = !empty($m[1]) ? (int) $m[1] : 0;
            $days = !empty($m[2]) ? (int) $m[2] : 0;
            $hours = !empty($m[3]) ? (int) $m[3] : 0;
            $mins = !empty($m[4]) ? (int) $m[4] : 0;
            $secs = !empty($m[5]) ? (int) $m[5] : 0;
            return ($weeks * 604800) + ($days * 86400) + ($hours * 3600) + ($mins * 60) + $secs;
        }

        if (preg_match_all('/(\d+)([wdhms])/', $uptime, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $val = (int) $match[1];
                $unit = $match[2];
                switch ($unit) {
                    case 'w': $totalSeconds += $val * 604800; break;
                    case 'd': $totalSeconds += $val * 86400; break;
                    case 'h': $totalSeconds += $val * 3600; break;
                    case 'm': $totalSeconds += $val * 60; break;
                    case 's': $totalSeconds += $val; break;
                }
            }
            return $totalSeconds;
        }

        return 0;
    }

    /**
     * Poll a specific router and update usage for all its active PPPoE sessions.
     */
    public function pollRouter(Mikrotik $router): array
    {
        $stats = [
            'sessions_updated' => 0,
            'errors' => 0,
        ];

        try {
            $customers = \App\Models\Customer::withoutGlobalScopes()
                ->where(function ($q) use ($router) {
                    $q->where('router_id', $router->id)
                      ->orWhereNull('router_id');
                })
                ->whereNotNull('pppoe_username')
                ->get()
                ->keyBy(fn($c) => trim($c->pppoe_username));

            if ($customers->isEmpty()) {
                return $stats;
            }

            $customersLower = [];
            foreach ($customers as $uname => $c) {
                $customersLower[strtolower($uname)] = $c;
            }

            $mikrotik = new MikrotikService([
                'host' => $router->host,
                'port' => $router->port ?? 8728,
                'user' => $router->username,
                'pass' => $router->password,
            ]);

            if (!$mikrotik->isConnected()) {
                return $stats;
            }

            // Auto-tanam script on-down disconnect accounting ke semua /ppp/profile MikroTik
            $cacheKey = "mik_pppoe_script_auto_planted_{$router->id}";
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                try {
                    $mikrotik->autoProvisionPppoeAccountingScript($router->id);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 86400); // 24 jam
                } catch (\Throwable $e) {
                    // Ignore auto-provision error
                }
            }

            $actives = $mikrotik->getActivePppoe();
            if (empty($actives)) {
                return $stats;
            }

            // Ambil data interface untuk membaca byte upload/download real dari MikroTik
            $interfaces = [];
            try {
                $interfaces = $mikrotik->query('/interface/print', [
                    '.proplist' => 'name,rx-byte,tx-byte,type',
                ]) ?: [];
            } catch (\Throwable $e) {
                $interfaces = [];
            }

            $ifaceMap = [];
            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? '';
                if ($name) {
                    $ifaceMap[$name] = $iface;
                    $cleanName = trim($name, '<>');
                    $ifaceMap[$cleanName] = $iface;
                }
            }

            $upsertData = [];
            $now = now();
            $nowStr = $now->format('Y-m-d H:i:s');
            $month = (int) $now->format('n');
            $year = (int) $now->format('Y');

            foreach ($actives as $session) {
                $username = trim($session['name'] ?? '');
                if (!$username) {
                    continue;
                }

                $cust = $customers[$username] ?? ($customersLower[strtolower($username)] ?? null);
                if (!$cust) {
                    continue;
                }

                // Baca bytes dari interface dynamic PPPoE (<pppoe-username>)
                $iface = $ifaceMap["<pppoe-{$username}>"] 
                    ?? ($ifaceMap["pppoe-{$username}"] 
                    ?? ($ifaceMap[$username] 
                    ?? ($ifaceMap["<pppoe-" . strtolower($username) . ">"] ?? null)));

                $totalIn = $iface ? (int) ($iface['rx-byte'] ?? 0) : (int) ($session['bytes-in'] ?? 0);
                $totalOut = $iface ? (int) ($iface['tx-byte'] ?? 0) : (int) ($session['bytes-out'] ?? 0);

                $uptimeStr = $session['uptime'] ?? '';
                $uptimeSeconds = self::parseUptimeToSeconds($uptimeStr);

                $currentUsage = $this->getUsage($cust->id, $month, $year);

                $deltaIn = $totalIn;
                $deltaOut = $totalOut;

                if ($currentUsage) {
                    $lastUpdate = $currentUsage->last_update ? \Carbon\Carbon::parse($currentUsage->last_update) : null;
                    $elapsedSeconds = $lastUpdate ? $lastUpdate->diffInSeconds($now) : 999999;

                    // Deteksi sesi baru (reconnect):
                    // Jika total counter sekarang lebih kecil dari last_total, ATAU uptime sesi lebih pendek dari waktu sejak sync terakhir
                    $isNewSession = ($totalIn < (int)$currentUsage->last_total_bytes_in) 
                                 || ($totalOut < (int)$currentUsage->last_total_bytes_out)
                                 || ($uptimeSeconds > 0 && $uptimeSeconds < $elapsedSeconds && $elapsedSeconds < 86400);

                    if ($isNewSession) {
                        // Sesi baru: seluruh byte interface saat ini adalah traffic baru
                        $deltaIn = $totalIn;
                        $deltaOut = $totalOut;
                    } else {
                        // Sesi berjalan: delta adalah selisih byte dari pembacaan sebelumnya
                        $deltaIn = max(0, $totalIn - (int)$currentUsage->last_total_bytes_in);
                        $deltaOut = max(0, $totalOut - (int)$currentUsage->last_total_bytes_out);
                    }
                }

                if ($deltaIn > 0 || $deltaOut > 0 || ($totalIn > 0 && !$currentUsage)) {
                    $upsertData[] = [
                        'customer_id' => $cust->id,
                        'tenant_id' => $cust->tenant_id,
                        'period_month' => $month,
                        'period_year' => $year,
                        'bytes_in' => $currentUsage ? (int)$currentUsage->bytes_in + $deltaIn : $deltaIn,
                        'bytes_out' => $currentUsage ? (int)$currentUsage->bytes_out + $deltaOut : $deltaOut,
                        'last_total_bytes_in' => $totalIn,
                        'last_total_bytes_out' => $totalOut,
                        'last_update' => $nowStr,
                        'created_at' => $nowStr,
                        'updated_at' => $nowStr,
                    ];
                    $stats['sessions_updated']++;
                }
            }

            if (!empty($upsertData)) {
                \App\Models\CustomerUsage::upsert(
                    $upsertData,
                    ['customer_id', 'period_month', 'period_year'],
                    ['bytes_in', 'bytes_out', 'last_total_bytes_in', 'last_total_bytes_out', 'last_update', 'updated_at']
                );
            }
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::error("[UsageService] Failed to poll router {$router->name} ({$router->host}): {$e->getMessage()}");
        }

        return $stats;
    }

    /**
     * Record a PPPoE session disconnect event (e.g. from MikroTik on-down script webhook).
     */
    public function recordSessionDisconnect(string $username, int $rxBytes, int $txBytes, ?int $routerId = null): bool
    {
        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        $cust = \App\Models\Customer::withoutGlobalScopes()
            ->where(function ($q) use ($username) {
                $q->where('pppoe_username', $username)
                  ->orWhere('pppoe_username', strtolower($username));
            })
            ->when($routerId, fn ($q) => $q->where('router_id', $routerId))
            ->first();

        if (!$cust) {
            return false;
        }

        $currentUsage = $this->getUsage($cust->id, $month, $year);

        if ($currentUsage) {
            $deltaIn = max(0, $rxBytes - (int)$currentUsage->last_total_bytes_in);
            $deltaOut = max(0, $txBytes - (int)$currentUsage->last_total_bytes_out);

            if ($rxBytes < (int)$currentUsage->last_total_bytes_in) {
                $deltaIn = $rxBytes;
            }
            if ($txBytes < (int)$currentUsage->last_total_bytes_out) {
                $deltaOut = $txBytes;
            }

            $currentUsage->increment('bytes_in', $deltaIn);
            $currentUsage->increment('bytes_out', $deltaOut);
            $currentUsage->last_total_bytes_in = 0;
            $currentUsage->last_total_bytes_out = 0;
            $currentUsage->last_update = $now;
            $currentUsage->save();
        } else {
            CustomerUsage::create([
                'customer_id' => $cust->id,
                'tenant_id' => $cust->tenant_id,
                'period_month' => $month,
                'period_year' => $year,
                'bytes_in' => $rxBytes,
                'bytes_out' => $txBytes,
                'last_total_bytes_in' => 0,
                'last_total_bytes_out' => 0,
                'last_update' => $now,
            ]);
        }

        return true;
    }

    /**
     * Poll all active routers and update usage for all active PPPoE sessions.
     *
     * Called every 10 minutes by CronService / Laravel scheduler.
     *
     * Returns array with counts of processed sessions and errors.
     */
    public function pollAllRouters(): array
    {
        $stats = [
            'routers_processed' => 0,
            'sessions_updated' => 0,
            'errors' => 0,
        ];

        try {
            $routers = Mikrotik::withoutGlobalScopes()->where('is_active', true)->get();

            if ($routers->isEmpty()) {
                Log::warning('[UsageService] No active routers configured for usage polling.');
                return $stats;
            }

            foreach ($routers as $router) {
                $stats['routers_processed']++;
                $res = $this->pollRouter($router);
                $stats['sessions_updated'] += $res['sessions_updated'] ?? 0;
                $stats['errors'] += $res['errors'] ?? 0;
            }
        } catch (\Exception $e) {
            $stats['errors']++;
            Log::error("[UsageService] pollAllRouters error: {$e->getMessage()}");
        }

        return $stats;
    }

    /**
     * Reset the last tracked byte counters for a customer's current period.
     */
    public function resetUsageCounter(int $customerId): bool
    {
        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        return (bool) CustomerUsage::where('customer_id', $customerId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->update([
                'last_total_bytes_in' => 0,
                'last_total_bytes_out' => 0,
            ]);
    }
}
