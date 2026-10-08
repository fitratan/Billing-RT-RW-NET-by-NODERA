<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BroadcastWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // unlimited timeout for big broadcasts, or maybe high value

    protected $customers;
    protected string $message;
    protected ?int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection $customers, string $message, ?int $tenantId = null)
    {
        $this->customers = $customers;
        $this->message = $message;
        $this->tenantId = $tenantId ?? ($customers->first()?->tenant_id ?? null);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ws = new \App\Services\WhatsappService($this->tenantId);
        if (!$ws->isEnabled()) {
            \Illuminate\Support\Facades\Log::warning("WhatsApp broadcast aborted: WhatsApp Gateway not configured for tenant [{$this->tenantId}].");
            return;
        }

        $sentWa = 0;
        $failedWa = 0;

        foreach ($this->customers as $index => $c) {
            if (!empty($c->phone)) {
                try {
                    $ws->sendMessage($c->phone, $this->message);
                    $sentWa++;
                } catch (\Exception $e) {
                    $failedWa++;
                }

                // Anti-spam jitter delay between broadcast messages (2-4 seconds)
                if ($index < count($this->customers) - 1) {
                    usleep(random_int(2000000, 4000000));
                }
            }
        }

        \Illuminate\Support\Facades\Log::info("WhatsApp broadcast completed: {$sentWa} sent, {$failedWa} failed.");
    }
}
