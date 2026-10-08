<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Mikrotik;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReprovisionCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // high timeout for sync

    protected $routerId;
    protected $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct($routerId, $tenantId)
    {
        $this->routerId = $routerId;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $router = Mikrotik::withoutGlobalScopes()
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->find($this->routerId) ?? Mikrotik::withoutGlobalScopes()->find($this->routerId);

        if (!$router || !$router->is_active) {
            Log::warning("[ReprovisionCustomersJob] Router tidak ditemukan atau tidak aktif: " . $this->routerId);
            return;
        }

        $mik = new \App\Services\MikrotikService([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password ?? '',
            'port' => (int) ($router->port ?: 8728),
        ]);

        if (!$mik->isConnected()) {
            Log::error("[ReprovisionCustomersJob] Tidak dapat terhubung ke " . $router->name . ": " . $mik->getLastError());
            return;
        }

        $customers = Customer::withoutGlobalScopes()
            ->where(function($q) {
                $q->where('router_id', $this->routerId)->orWhereNull('router_id');
            })
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->with('package')
            ->get();

        $created = 0;
        $existing = 0;

        foreach ($customers as $c) {
            if (empty($c->pppoe_username)) continue;
            $profile = $c->package?->profile_normal ?: $c->package?->name ?: 'default';
            $password = $c->pppoe_password ?: '123456';

            $secret = $mik->getPppoeSecret($c->pppoe_username);
            if (!$secret) {
                if ($mik->addPppoeSecret($c->pppoe_username, $password, $profile, 'pppoe')) {
                    $created++;
                }
            } else {
                $existing++;
            }
        }

        Log::info("[ReprovisionCustomersJob] Sinkronisasi ke router {$router->name} selesai: {$created} secret baru dibuat, {$existing} sudah ada.");
    }
}
