<?php

namespace App\Jobs;

use App\Models\WaMerchant;
use App\Models\WaMessage;
use App\Models\WhatsappDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendWaGatewayMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    protected int $waMessageId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $waMessageId)
    {
        $this->waMessageId = $waMessageId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $msg = WaMessage::with('merchant', 'device')->find($this->waMessageId);
        if (!$msg || in_array($msg->status, ['sent', 'delivered', 'read', 'cancelled'])) {
            return;
        }

        $merchant = $msg->merchant;
        if (!$merchant) {
            $msg->update([
                'status'        => 'failed',
                'error_message' => 'Merchant akun tidak ditemukan.',
            ]);
            return;
        }

        $device = $msg->device;
        if (!$device) {
            $device = WhatsappDevice::withoutGlobalScopes()
                ->where('merchant_id', $merchant->id)
                ->where('is_default', true)
                ->first()
                ?: WhatsappDevice::withoutGlobalScopes()
                    ->where('merchant_id', $merchant->id)
                    ->where('status', 'CONNECTED')
                    ->first();

            if ($device) {
                $msg->update(['device_id' => $device->id]);
            }
        }

        if (!$device || $device->status !== 'CONNECTED') {
            $msg->update([
                'status'        => 'failed',
                'error_message' => 'Perangkat WhatsApp tidak online atau terputus.',
            ]);
            return;
        }

        $msg->update(['status' => 'processing']);

        $settings = $merchant->gateway_settings;
        $delayMin = (int)($settings['delay_min'] ?? 2);
        $delayMax = (int)($settings['delay_max'] ?? 5);
        $typingSimulation = (bool)($settings['typing_simulation'] ?? true);

        $microserviceUrl = rtrim(config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3000')), '/');
        $masterApiKey = config('services.wa_gateway.api_key', env('WA_GATEWAY_API_KEY', 'nodera_wa_secret_key_2026'));

        $isMedia = ($msg->message_type === 'media' || !empty($msg->media_url));
        $endpoint = $isMedia ? '/api/message/send-media' : '/api/message/send-text';

        $payload = [
            'sessionId'         => $device->session_id,
            'to'                => $msg->recipient,
            'phone'             => $msg->recipient,
            'target'            => $msg->recipient,
            'delay_min'         => $delayMin,
            'delay_max'         => $delayMax,
            'typing_simulation' => $typingSimulation,
            'retry_count'       => 0,
        ];

        if ($isMedia) {
            $payload['mediaUrl'] = $msg->media_url;
            $payload['media_url'] = $msg->media_url;
            $payload['caption'] = $msg->message_content;
            $payload['message'] = $msg->message_content;
            $payload['mediaType'] = $msg->message_type ?: 'image';
            $payload['type'] = $msg->message_type ?: 'image';

            // Direct local base64 fallback for 100% reliable delivery without network fetch failure
            if ($msg->media_url) {
                $relativeStoragePath = strstr($msg->media_url, '/storage/');
                if ($relativeStoragePath) {
                    $cleanPath = ltrim(str_replace('/storage/', '', $relativeStoragePath), '/');
                    $localPath = storage_path('app/public/' . $cleanPath);
                    if (file_exists($localPath)) {
                        $fileData = @file_get_contents($localPath);
                        if ($fileData !== false) {
                            $payload['media_base64'] = base64_encode($fileData);
                            $payload['base64'] = base64_encode($fileData);
                        }
                    }
                }
            }
        } else {
            $payload['message'] = $msg->message_content;
        }

        $urls = array_values(array_unique(array_filter([
            $microserviceUrl,
            'http://127.0.0.1:3022',
            'http://127.0.0.1:3000',
        ])));

        $response = null;
        $lastException = null;

        foreach ($urls as $baseUrl) {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
            try {
                $response = Http::withHeaders([
                    'X-Api-Key' => $masterApiKey,
                ])->timeout(max(40, $delayMax + 25))->post($url, $payload);

                if ($response->successful() || ($response->status() >= 400 && $response->status() < 500)) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if ($response && $response->successful()) {
            $resData = $response->json();
            $serverMsgId = $resData['data']['messageId'] ?? $resData['data']['key']['id'] ?? ('MSG-' . strtoupper(Str::random(12)));
            $msg->update([
                'status'        => 'sent',
                'message_id'    => $serverMsgId,
                'sent_at'       => now(),
                'error_message' => null,
            ]);
            return;
        }

        $errMsg = $response ? ($response->json('error') ?: $response->json('message') ?: $response->json('reason') ?: ('HTTP ' . $response->status())) : ($lastException ? $lastException->getMessage() : 'Gagal terhubung ke WhatsApp Gateway');

        if ($errMsg === 'WhatsApp Gateway not connected.') {
            $errMsg = 'Perangkat WhatsApp tidak terhubung / sesi terputus. Silakan hubungkan ulang di menu Perangkat.';
        }

        $msg->update([
            'status'        => 'failed',
            'error_message' => $errMsg,
        ]);
    }
}
