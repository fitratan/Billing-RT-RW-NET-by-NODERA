<?php

namespace App\Jobs;

use App\Events\CustomerIsolatedEvent;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Services\IsolationService;
use App\Services\RouterCircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class IsolateCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 20;

    protected Customer $customer;
    protected bool $force;
    protected ?string $actorName;

    public function __construct(Customer $customer, bool $force = false, ?string $actorName = 'Sistem Auto-Isolir')
    {
        $this->customer = $customer;
        $this->force = $force;
        $this->actorName = $actorName;
        $this->onQueue('isolation');
    }

    public function handle(IsolationService $isolationService): void
    {
        // Reload fresh customer data with relations
        $customer = Customer::withoutGlobalScopes()
            ->with(['package', 'router'])
            ->find($this->customer->id);

        if (!$customer) {
            Log::warning("[IsolateCustomerJob] Customer #{$this->customer->id} not found. Skipped.");
            return;
        }

        // If already isolated or non-active and not forced, skip
        if ($customer->status === 'isolated' && !$this->force) {
            return;
        }

        // Safety Precondition Guard: verify customer still has unpaid overdue invoices
        $hasUnpaidOverdue = $customer->invoices()
            ->where('paid', 0)
            ->where('due_date', '<', now()->format('Y-m-d'))
            ->exists();

        if (!$hasUnpaidOverdue && !$this->force) {
            Log::info("[IsolateCustomerJob] Customer #{$customer->id} ({$customer->name}) has no unpaid overdue invoices. Aborting isolation to prevent false positive.");
            return;
        }

        $routerId = $customer->router_id;
        $router = $customer->router ?? ($routerId ? Mikrotik::withoutGlobalScopes()->find($routerId) : null);

        // 1. Check Circuit Breaker for target router
        if ($router && $router->is_active) {
            if (!RouterCircuitBreaker::isAvailable($router->id)) {
                Log::warning("[IsolateCustomerJob] Circuit OPEN for Router #{$router->id} ({$router->name}). Releasing job for 60s.");
                $this->release(60);
                return;
            }
        }

        $executeIsolation = function () use ($isolationService, $customer, $router) {
            try {
                $isolated = $isolationService->isolateCustomer($customer, $this->actorName, $this->force);

                if ($isolated) {
                    if ($router && $router->is_active) {
                        RouterCircuitBreaker::recordSuccess($router->id);
                    }

                    // 1. Real-time WebSocket Broadcast to Admin Dashboard
                    try {
                        broadcast(new CustomerIsolatedEvent($customer, $this->actorName));
                    } catch (\Throwable $e) {
                        Log::warning("[IsolateCustomerJob] Broadcast event failed: " . $e->getMessage());
                    }

                    // 2. Dispatch Async WhatsApp Notification
                    try {
                        SendWhatsappInvoiceNotificationJob::dispatch($customer)
                            ->onQueue('notifications');
                    } catch (\Throwable $e) {
                        Log::warning("[IsolateCustomerJob] Dispatch WA notification failed: " . $e->getMessage());
                    }

                    Log::info("[IsolateCustomerJob] Customer #{$customer->id} ({$customer->name}) successfully isolated.");
                }
            } catch (\Throwable $e) {
                if ($router && $router->is_active) {
                    RouterCircuitBreaker::recordFailure($router->id);
                }
                Log::error("[IsolateCustomerJob] Failed to isolate customer #{$customer->id}: " . $e->getMessage());
                throw $e;
            }
        };

        // 2. Rate Limiting per Router: Max 5 concurrent requests / second
        if ($router && $router->is_active) {
            try {
                Redis::throttle("rate:router:{$router->id}")
                    ->allow(5)
                    ->every(1)
                    ->then($executeIsolation, function () {
                        // Router queue congested, back off for 2 seconds
                        $this->release(2);
                    });
            } catch (\Throwable $e) {
                // If Redis is not active or driver is database/sync, execute directly
                $executeIsolation();
            }
        } else {
            $executeIsolation();
        }
    }
}
