<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVouchersToMikrotikJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // High timeout for batch sync

    protected $routerId;
    protected $payloads;

    /**
     * Create a new job instance.
     */
    public function __construct($routerId, array $payloads)
    {
        $this->routerId = $routerId;
        $this->payloads = $payloads;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $router = \App\Models\Mikrotik::find($this->routerId);
        if (!$router) {
            \Illuminate\Support\Facades\Log::warning("SyncVouchersToMikrotikJob: Router ID {$this->routerId} not found.");
            return;
        }

        try {
            $mikrotikService = new \App\Services\MikrotikService($router);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SyncVouchersToMikrotikJob: Connect failed - " . $e->getMessage());
            return;
        }

        $created = 0;
        $failed = 0;

        foreach ($this->payloads as $p) {
            try {
                $ok = $mikrotikService->addHotspotUser(
                    $p['username'],
                    $p['password'],
                    $p['profile'],
                    $p['time_limit'] ?? '',
                    $p['data_limit_bytes'] ?? 0,
                    $p['server'] ?? 'all',
                    $p['comment'] ?? ''
                );
                
                if ($ok) {
                    $created++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                \Illuminate\Support\Facades\Log::error("SyncVouchersToMikrotikJob: Add user failed - " . $e->getMessage());
            }
        }

        \Illuminate\Support\Facades\Log::info("SyncVouchersToMikrotikJob completed: {$created} created, {$failed} failed on Router ID {$this->routerId}.");
    }
}
