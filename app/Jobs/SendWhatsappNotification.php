<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Customer $customer;
    protected Invoice $invoice;
    protected string $type; // e.g., 'payment_success', 'invoice_reminder'

    /**
     * Create a new job instance.
     */
    public function __construct(Customer $customer, Invoice $invoice, string $type = 'payment_success')
    {
        $this->customer = $customer;
        $this->invoice = $invoice;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $phone = $this->customer->phone ?? null;
        if (empty($phone)) {
            \Illuminate\Support\Facades\Log::info("[SendWhatsappNotification] Skipped: customer has no phone number", [
                'customer_id' => $this->customer->id,
                'customer_name' => $this->customer->name ?? '',
            ]);
            return;
        }

        $tenantId = $this->customer->tenant_id ?? $this->invoice->tenant_id ?? null;
        $service = new WhatsappService($tenantId);
        if (! $service->isEnabled()) {
            \Illuminate\Support\Facades\Log::info("[SendWhatsappNotification] WhatsApp service not enabled or token empty for tenant " . ($tenantId ?? 'global'));
            return;
        }

        $sent = false;
        $exceptionError = null;
        try {
            $customerData = $this->customer->toArray();
            $invoiceData = $this->invoice->toArray();
            if (empty($invoiceData['package_name']) && $this->customer->package) {
                $invoiceData['package_name'] = $this->customer->package->name;
            }

            switch ($this->type) {
                case 'payment_success':
                    $sent = $service->sendPaymentSuccess($customerData, $invoiceData);
                    break;
                case 'invoice_reminder':
                    $sent = $service->sendInvoiceReminder($this->customer, $this->invoice);
                    break;
                default:
                    $sent = $service->sendPaymentSuccess($customerData, $invoiceData);
            }
        } catch (\Throwable $e) {
            $exceptionError = $e->getMessage();
            \Illuminate\Support\Facades\Log::error("[SendWhatsappNotification] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $sent = false;
        }

        if (! $sent) {
            $lastErr = $service->getLastError() ?: ($exceptionError ? ("Kesalahan sistem: {$exceptionError}") : 'API gateway menolak pesan atau token tidak valid.');
            \Illuminate\Support\Facades\Log::warning("[SendWhatsappNotification] Failed sending to {$phone}: {$lastErr}");
            try {
                \App\Models\AuditLog::create([
                    'tenant_id' => $tenantId,
                    'user_id' => null,
                    'action' => 'whatsapp_failed',
                    'entity_type' => 'invoice',
                    'entity_id' => $this->invoice->id ?? null,
                    'new_values' => [
                        'customer_id' => $this->customer->id,
                        'customer_name' => $this->customer->name,
                        'phone' => $phone,
                        'type' => $this->type,
                        'actor' => 'System WhatsApp Gateway',
                        'reason' => 'Pengiriman notifikasi WhatsApp ke ' . $this->customer->name . ' (' . $phone . ') gagal: ' . $lastErr,
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System Background Job (SendWhatsappNotification)',
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
