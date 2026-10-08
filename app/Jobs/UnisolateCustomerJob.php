<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\IsolationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UnisolateCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;
    public int $timeout = 25;

    protected int $customerId;
    protected ?string $actorName;
    protected bool $force;

    public function __construct(Customer|int $customer, ?string $actorName = 'Sistem Auto-Unisolir (Payment Gateway)', bool $force = false)
    {
        $this->customerId = is_numeric($customer) ? (int) $customer : (int) $customer->id;
        $this->actorName = $actorName;
        $this->force = $force;
        $this->onQueue('isolation');
    }

    public function handle(IsolationService $isolationService): void
    {
        $customer = Customer::withoutGlobalScopes()
            ->with(['package', 'router'])
            ->find($this->customerId);

        if (!$customer) {
            Log::warning("[UnisolateCustomerJob] Customer #{$this->customerId} not found. Skipped.");
            return;
        }

        // Precondition guard: check if customer still has overdue unpaid invoices
        $hasUnpaidOverdue = $customer->invoices()
            ->where('paid', false)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->format('Y-m-d'))
            ->exists();

        if ($hasUnpaidOverdue && !$this->force) {
            Log::info("[UnisolateCustomerJob] Customer #{$this->customerId} still has overdue unpaid invoices. Unisolir aborted.");
            return;
        }

        try {
            $isolationService->unisolateCustomer($customer, $this->actorName, $this->force);
            Log::info("[UnisolateCustomerJob] Customer #{$this->customerId} ({$customer->name}) successfully unisolated by {$this->actorName}.");
        } catch (\Throwable $e) {
            Log::error("[UnisolateCustomerJob] Unisolir failed for customer #{$this->customerId}: " . $e->getMessage());
            throw $e;
        }
    }
}
