<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\BillingEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes per chunk batch

    protected array $customerIds;
    protected string $period;

    /**
     * Create a new job instance.
     */
    public function __construct(array $customerIds, string $period)
    {
        $this->customerIds = $customerIds;
        $this->period = $period;
    }

    /**
     * Execute the job.
     */
    public function handle(BillingEngineService $billingEngine): void
    {
        if (empty($this->customerIds)) {
            return;
        }

        $customers = Customer::withoutGlobalScopes()
            ->with('package')
            ->whereIn('id', $this->customerIds)
            ->where('status', 'active')
            ->get();

        $generated = 0;
        foreach ($customers as $customer) {
            try {
                $invoice = $billingEngine->generateSingleCustomerInvoice($customer, $this->period);
                if ($invoice) {
                    $generated++;
                }
            } catch (\Throwable $e) {
                Log::error("[GenerateInvoiceBatchJob] Failed for customer #{$customer->id}: " . $e->getMessage());
            }
        }

        Log::info("[GenerateInvoiceBatchJob] Completed batch for " . count($customers) . " customers ({$generated} invoices created).");
    }
}
