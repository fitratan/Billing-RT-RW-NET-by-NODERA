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

    protected int $customerId;
    protected bool $force;
    protected ?string $actorName;

    /**
     * Create a new job instance.
     * Supports flexible arguments:
     * - IsolateCustomerJob::dispatch($customer, $force, $actorName)
     * - IsolateCustomerJob::dispatch($customer, $actorName, $force)
     * - IsolateCustomerJob::dispatch($customerId, 'Admin Manual Isolation')
     */
    public function __construct(Customer|int|array $customer, bool|string $forceOrActor = false, bool|string|null $actorOrForce = null)
    {
        if ($customer instanceof Customer) {
            $this->customerId = (int) $customer->id;
        } elseif (is_array($customer)) {
            $this->customerId = (int) ($customer['id'] ?? 0);
        } else {
            $this->customerId = (int) $customer;
        }

        if (is_string($forceOrActor)) {
            $this->actorName = $forceOrActor;
            $this->force = is_bool($actorOrForce) ? $actorOrForce : true;
        } else {
            $this->force = (bool) $forceOrActor;
            $this->actorName = is_string($actorOrForce) ? $actorOrForce : 'Sistem Auto-Isolir';
        }

        $this->onQueue('isolation');
    }

    public function handle(IsolationService $isolationService): void
    {
        // Reload fresh customer data with relations
        $customer = Customer::withoutGlobalScopes()
            ->with(['package', 'router'])
            ->find($this->customerId);

        if (!$customer) {
            Log::warning("[IsolateCustomerJob] Customer #{$this->customerId} not found. Skipped.");
            return;
        }

        // If not forced: skip if package explicitly disabled auto-isolir
        if (!$this->force && $customer->package && !empty($customer->package->id) && empty($customer->package->auto_isolir)) {
            Log::info("[IsolateCustomerJob] Skipping customer #{$customer->id} ({$customer->name}): package auto_isolir is disabled.");
            return;
        }

        // Safety Precondition Guard: verify customer still has unpaid overdue invoices (unless forced)
        if (!$this->force) {
            $hasUnpaidOverdue = $customer->invoices()
                ->where('paid', 0)
                ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                ->where('due_date', '<', now()->format('Y-m-d'))
                ->exists();

            if (!$hasUnpaidOverdue) {
                Log::info("[IsolateCustomerJob] Customer #{$customer->id} ({$customer->name}) has no unpaid overdue invoices. Aborting isolation to prevent false positive.");
                return;
            }
        }

        $routerId = $customer->router_id;
        $router = $customer->router ?? ($routerId ? Mikrotik::withoutGlobalScopes()->find($routerId) : null);

        // 1. Check Circuit Breaker for target router
        if ($router && $router->is_active) {
            if (!RouterCircuitBreaker::isAvailable($router->id)) {
                Log::warning("[IsolateCustomerJob] Circuit OPEN for Router #{$router->id} ({$router->name}).");
                if (config('queue.default') !== 'sync' && method_exists($this, 'release')) {
                    try {
                        $this->release(60);
                        return;
                    } catch (\Throwable $e) {}
                }
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
                        // ignore broadcast errors
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

        // 2. Rate Limiting per Router (only when Redis queue is available and not in sync mode)
        $isSync = config('queue.default') === 'sync';
        if (!$isSync && $router && $router->is_active) {
            try {
                Redis::throttle("rate:router:{$router->id}")
                    ->allow(5)
                    ->every(1)
                    ->then($executeIsolation, function () {
                        if (method_exists($this, 'release')) {
                            $this->release(2);
                        }
                    });
                return;
            } catch (\Throwable $e) {
                // Redis throttle unavailable, fall back to direct execution
            }
        }

        $executeIsolation();
    }
}
