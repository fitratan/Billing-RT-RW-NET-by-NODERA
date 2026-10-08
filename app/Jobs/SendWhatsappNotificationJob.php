<?php

namespace App\Jobs;

use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsappNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // Retry after 30s if failed

    protected string $phone;
    protected string $message;
    protected ?int $tenantId;

    public function __construct(string $phone, string $message, ?int $tenantId = null)
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->tenantId = $tenantId;
    }

    public function handle(?WhatsappService $waService = null): void
    {
        $service = $this->tenantId ? new WhatsappService($this->tenantId) : ($waService ?? new WhatsappService());

        if (!$service->isConfigured()) {
            $reason = $service->getLastError() ?: 'Token belum diisi atau dinonaktifkan.';
            Log::warning("[SendWhatsappNotificationJob] WhatsApp Gateway is not configured for tenant [{$this->tenantId}]: {$reason}. Skipped.");
            return;
        }

        try {
            $sent = $service->sendMessage($this->phone, $this->message);
            if ($sent) {
                Log::info('[SendWhatsappNotificationJob] Message successfully sent', ['phone' => $this->phone, 'tenant_id' => $this->tenantId]);
            } else {
                $errReason = $service->getLastError() ?: 'Gateway menolak pengiriman.';
                Log::error('[SendWhatsappNotificationJob] Failed to send WhatsApp message', [
                    'phone' => $this->phone,
                    'tenant_id' => $this->tenantId,
                    'error' => $errReason,
                ]);
                try {
                    \App\Models\AuditLog::create([
                        'tenant_id' => $this->tenantId ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? null,
                        'user_id' => null,
                        'action' => 'whatsapp_failed',
                        'entity_type' => 'customer',
                        'entity_id' => null,
                        'new_values' => [
                            'phone' => $this->phone,
                            'actor' => 'System WhatsApp Gateway',
                            'reason' => 'Pengiriman pesan WhatsApp ke ' . $this->phone . ' gagal: ' . $errReason,
                        ],
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'System Background Job (SendWhatsappNotificationJob)',
                    ]);
                } catch (\Throwable $e2) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            Log::error('[SendWhatsappNotificationJob] Exception during WhatsApp send', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
            ]);
            try {
                \App\Models\AuditLog::create([
                    'tenant_id' => \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? null,
                    'user_id' => null,
                    'action' => 'whatsapp_failed',
                    'entity_type' => 'customer',
                    'entity_id' => null,
                    'new_values' => [
                        'phone' => $this->phone,
                        'actor' => 'System WhatsApp Gateway',
                        'reason' => 'Exception gateway WhatsApp: ' . $e->getMessage(),
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System Background Job (SendWhatsappNotificationJob)',
                ]);
            } catch (\Throwable $e3) {
                // ignore
            }
            throw $e;
        }
    }
}
