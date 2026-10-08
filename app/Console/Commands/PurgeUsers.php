<?php

namespace App\Console\Commands;

use App\Models\Mikrotik;
use App\Services\MikrotikService;
use App\Services\RouterCircuitBreaker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodera:purge-expired-users {--router= : Optional Router ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge expired hotspot voucher users (uptime >= limit-uptime) to prevent memory & flash wear';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Starting purge of expired hotspot voucher users...");
        Log::info("[PurgeUsers] Starting scheduled purge of expired hotspot users.");

        $routerId = $this->option('router');
        $query = Mikrotik::withoutGlobalScopes()->where('is_active', true);
        if ($routerId) {
            $query->where('id', $routerId);
        }

        $routers = $query->get();
        $totalPurged = 0;

        foreach ($routers as $router) {
            if (!RouterCircuitBreaker::isAvailable($router->id)) {
                $this->warn("Router #{$router->id} ({$router->name}) circuit is OPEN. Skipping.");
                continue;
            }

            try {
                $mik = new MikrotikService($router);
                if (!$mik->isConnected()) {
                    RouterCircuitBreaker::recordFailure($router->id);
                    continue;
                }

                // Query hotspot users
                $users = $mik->query('/ip/hotspot/user/print', [
                    '.proplist' => '.id,name,uptime,limit-uptime,disabled,comment',
                ]);

                $purgedForRouter = 0;
                foreach ($users as $u) {
                    $id = $u['.id'] ?? '';
                    $uptime = $u['uptime'] ?? '';
                    $limitUptime = $u['limit-uptime'] ?? '';
                    $comment = $u['comment'] ?? '';

                    $isExpired = false;

                    if (!empty($limitUptime) && !empty($uptime)) {
                        // Compare uptime strings if limit uptime reached
                        if ($uptime >= $limitUptime) {
                            $isExpired = true;
                        }
                    }

                    if (str_contains(strtolower($comment), 'expired') || str_contains(strtolower($comment), 'habis')) {
                        $isExpired = true;
                    }

                    if ($isExpired && !empty($id)) {
                        $mik->query('/ip/hotspot/user/remove', [
                            '.id' => $id,
                        ]);
                        $purgedForRouter++;
                    }
                }

                RouterCircuitBreaker::recordSuccess($router->id);
                $totalPurged += $purgedForRouter;
                $this->info("Router #{$router->id} ({$router->name}): Purged {$purgedForRouter} expired users.");
            } catch (\Throwable $e) {
                RouterCircuitBreaker::recordFailure($router->id);
                Log::error("[PurgeUsers] Error on router #{$router->id}: " . $e->getMessage());
                $this->error("Error on router #{$router->id}: " . $e->getMessage());
            }
        }

        $this->info("Purge completed. Total {$totalPurged} users removed.");
        Log::info("[PurgeUsers] Completed purge. Total {$totalPurged} expired users removed.");

        return Command::SUCCESS;
    }
}
