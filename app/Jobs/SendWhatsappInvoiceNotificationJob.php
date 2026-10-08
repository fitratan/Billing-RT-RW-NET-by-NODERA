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
use Illuminate\Support\Facades\Log;

class SendWhatsappInvoiceNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 15;

    protected Customer $customer;
    protected ?Invoice $invoice;

    public function __construct(Customer $customer, ?Invoice $invoice = null)
    {
        $this->customer = $customer;
        $this->invoice = $invoice;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $phone = $this->customer->phone;
        if (empty($phone)) {
            Log::info("[SendWhatsappInvoiceNotificationJob] Customer #{$this->customer->id} has no phone number. Skipped.");
            return;
        }

        $tenantId = $this->customer->tenant_id ?? $this->invoice?->tenant_id ?? null;
        $waService = new WhatsappService($tenantId);

        if (!$waService->isEnabled() && !$waService->isConfigured()) {
            Log::warning("[SendWhatsappInvoiceNotificationJob] WhatsApp Gateway is not enabled/configured for tenant [{$tenantId}]. Skipped for Customer #{$this->customer->id}.");
            return;
        }

        $invoice = $this->invoice ?? $this->customer->invoices()
            ->where('paid', false)
            ->latest('due_date')
            ->first();

        $amount = $invoice 
            ? 'Rp ' . number_format((float) $invoice->amount, 0, ',', '.') 
            : 'Rp ' . number_format((float) ($this->customer->package?->price ?? 0), 0, ',', '.');

        $dueDate = $invoice && $invoice->due_date 
            ? \Carbon\Carbon::parse($invoice->due_date)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y')
            : now()->setTimezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y');

        $invNumber = $invoice->invoice_number ?? ('INV-' . now()->format('Ym') . '-' . $this->customer->id);

        $appUrl = config('app.url', url('/'));
        $payUrl = $appUrl . '/portal/login';

        $message = "*PEMBERITAHUAN ISOLIR*\n\n"
                 . "Yth. *{$this->customer->name}*,\n\n"
                 . "Layanan internet Anda (*{$this->customer->pppoe_username}*) saat ini dinonaktifkan sementara (isolir) karena telah melewati batas tanggal jatuh tempo.\n\n"
                 . "--------------------------------\n"
                 . "No. Invoice : *{$invNumber}*\n"
                 . "Total       : *{$amount}*\n"
                 . "Jatuh Tempo : *{$dueDate}*\n"
                 . "--------------------------------\n\n"
                 . "Untuk mengaktifkan kembali layanan internet Anda secara otomatis, silakan lakukan pembayaran melalui portal pelanggan:\n"
                 . "{$payUrl}\n\n"
                 . "Abaikan pesan ini jika Anda telah melakukan pembayaran.\n"
                 . "Terima kasih.";

        try {
            $sent = $waService->sendMessage($phone, $message);
            if ($sent) {
                Log::info("[SendWhatsappInvoiceNotificationJob] WhatsApp isolation notice sent to {$phone} for Customer #{$this->customer->id}");
            } else {
                Log::warning("[SendWhatsappInvoiceNotificationJob] WhatsApp Gateway returned false for {$phone}");
            }
        } catch (\Throwable $e) {
            Log::error("[SendWhatsappInvoiceNotificationJob] Failed sending WhatsApp notice to {$phone}: " . $e->getMessage());
            throw $e;
        }
    }
}
