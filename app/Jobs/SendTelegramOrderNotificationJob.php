<?php

namespace App\Jobs;

use App\Models\ShopOrder;
use App\Services\TenantTelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected int $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = ShopOrder::withoutGlobalScopes()->find($this->orderId);
        if (!$order) {
            Log::warning("SendTelegramOrderNotificationJob: Order #{$this->orderId} not found.");
            return;
        }

        try {
            $telegramService = app(TenantTelegramService::class);
            $telegramService->sendOrderNotification($order);
        } catch (\Throwable $e) {
            Log::error("SendTelegramOrderNotificationJob failed for Order #{$this->orderId}: " . $e->getMessage());
        }
    }
}
