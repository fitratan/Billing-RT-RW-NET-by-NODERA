<?php

namespace App\Jobs;

use App\Models\Mikrotik;
use App\Services\MikrotikService;
use App\Services\RouterCircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncActiveSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    protected ?int $routerId;

    public function __construct(?int $routerId = null)
    {
        $this->routerId = $routerId;
    }

    /**
     * Pull sessions from MikroTik and store in Redis Shadow State.
     */
    public function handle(): void
    {
        $query = Mikrotik::withoutGlobalScopes()->where('is_active', true);
        if ($this->routerId) {
            $query->where('id', $this->routerId);
        }

        $routers = $query->get();

        foreach ($routers as $router) {
            if (!RouterCircuitBreaker::isAvailable($router->id)) {
                continue;
            }

            try {
                $mik = new MikrotikService($router);
                if (!$mik->isConnected()) {
                    RouterCircuitBreaker::recordFailure($router->id);
                    continue;
                }

                // 1. Pull PPPoE Active Sessions
                $pppoeActive = $mik->query('/ppp/active/print', [
                    '.proplist' => 'name,address,uptime,service,caller-id',
                ]);

                // 2. Pull Hotspot Active Sessions
                $hotspotActive = $mik->query('/ip/hotspot/active/print', [
                    '.proplist' => 'user,address,uptime,bytes-in,bytes-out',
                ]);

                // 3. System resource snapshot
                $resource = $mik->getResource(false);

                $tenantId = $router->tenant_id ?? 0;
                $cacheKey = "tenant_{$tenantId}_router_{$router->id}_sessions";

                $payload = [
                    'router_id'      => $router->id,
                    'router_name'    => $router->name,
                    'pppoe_active'   => $pppoeActive,
                    'hotspot_active' => $hotspotActive,
                    'pppoe_count'    => count($pppoeActive),
                    'hotspot_count'  => count($hotspotActive),
                    'resource'       => $resource,
                    'synced_at'      => now()->toIso8601String(),
                ];

                // Cache in Redis with 60s TTL
                Cache::put($cacheKey, $payload, 60);
                Cache::put("mik_shadow_sessions_{$router->id}", $payload, 60);

                RouterCircuitBreaker::recordSuccess($router->id);
            } catch (\Throwable $e) {
                RouterCircuitBreaker::recordFailure($router->id);
                Log::debug("[SyncActiveSessionsJob] Error for router #{$router->id}: " . $e->getMessage());
            }
        }
    }
}
