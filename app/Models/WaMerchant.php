<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class WaMerchant extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'wa_merchants';

    protected $fillable = [
        'merchant_code',
        'name',
        'owner_name',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'api_key',
        'secret_key',
        'webhook_url',
        'ip_whitelist',
        'plan_type',
        'credit_balance',
        'device_limit',
        'quota_monthly',
        'quota_used_this_month',
        'quota_reset_at',
        'subscription_expires_at',
        'status',
        'referred_by_partner_id',
        'referral_code_used',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'secret_key',
    ];

    protected $casts = [
        'credit_balance' => 'decimal:2',
        'device_limit' => 'integer',
        'quota_monthly' => 'integer',
        'quota_used_this_month' => 'integer',
        'quota_reset_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'array',
    ];

    protected $appends = [
        'gateway_settings',
    ];

    protected static function booted(): void
    {
        static::saved(function (WaMerchant $merchant) {
            $merchant->syncDeviceLimitAndEnforce();
        });
    }

    /**
     * Otomatis sinkronisasi batas perangkat:
     * - Ada Saldo (> 0): Otomatis limit 5 perangkat & Pro status
     * - Saldo Habis (<= 0): Otomatis limit 1 perangkat, dan disconnect 4 perangkat lainnya menyisakan 1 perangkat aktif
     */
    public function syncDeviceLimitAndEnforce(): void
    {
        $hasCredit = ((float)$this->credit_balance > 0);
        $targetLimit = $hasCredit ? 5 : 1;
        $targetPlan = $hasCredit ? 'pro' : 'free';

        if ($this->device_limit !== $targetLimit || $this->plan_type !== $targetPlan) {
            $this->device_limit = $targetLimit;
            $this->plan_type = $targetPlan;
            $this->saveQuietly();
        }

        // Jika saldo habis (<= 0), putuskan 4 perangkat berlebih dan sisakan hanya 1 perangkat default/aktif
        if (!$hasCredit) {
            $devices = WhatsappDevice::withoutGlobalScopes()
                ->where('merchant_id', $this->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            if ($devices->count() > 1) {
                // Perangkat pertama tetap default & aktif
                $primaryDevice = $devices->first();
                if (!$primaryDevice->is_default) {
                    $primaryDevice->updateQuietly(['is_default' => true]);
                }

                // 4 Perangkat lainnya otomatis disconnect jika sedang connected / pairing
                $excessDevices = $devices->slice(1);
                $microserviceUrl = rtrim(config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3000')), '/');
                $masterApiKey = config('services.wa_gateway.api_key', env('WA_GATEWAY_API_KEY', 'nodera_wa_secret_key_2026'));
                $urls = array_values(array_unique(array_filter([
                    $microserviceUrl,
                    'http://127.0.0.1:3022',
                    'http://127.0.0.1:3000',
                ])));

                foreach ($excessDevices as $exDevice) {
                    if ($exDevice->status === 'CONNECTED' || $exDevice->status === 'PAIRING') {
                        foreach ($urls as $baseUrl) {
                            try {
                                \Illuminate\Support\Facades\Http::withHeaders(['X-Api-Key' => $masterApiKey])
                                    ->timeout(3)
                                    ->post("{$baseUrl}/api/session/disconnect/{$exDevice->session_id}");
                            } catch (\Throwable $e) {}
                        }

                        $exDevice->updateQuietly([
                            'status'     => 'DISCONNECTED',
                            'qr_code'    => null,
                            'is_default' => false,
                        ]);

                        try {
                            WaActivityLog::create([
                                'merchant_id' => $this->id,
                                'device_id'   => $exDevice->id,
                                'event_type'  => 'device_disconnected',
                                'description' => "Perangkat {$exDevice->name} otomatis dinonaktifkan karena saldo habis. Batas gratis: 1 perangkat aktif.",
                            ]);
                        } catch (\Throwable $e) {}
                    }
                }

                // Notifikasi downgrade ke tier FREE karena saldo habis
                $tierKey = "wa_merchant_tier_free_{$this->id}_" . now()->format('Y-m-d');
                if (!\Illuminate\Support\Facades\Cache::has($tierKey)) {
                    try {
                        $wa = \App\Services\WhatsappService::forSuperadmin();
                        if ($wa->isEnabled() && !empty($this->phone)) {
                            $wa->sendWaMerchantTierDowngraded($this);
                            \Illuminate\Support\Facades\Cache::put($tierKey, true, now()->addDays(3));
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Peringatan jika saldo hampir habis (sisa <= Rp 5.000)
        $balance = (float) $this->credit_balance;
        if ($balance > 0 && $balance <= 5000) {
            $lowBalKey = "wa_merchant_low_bal_{$this->id}_" . now()->format('Y-m-d');
            if (!\Illuminate\Support\Facades\Cache::has($lowBalKey)) {
                try {
                    $wa = \App\Services\WhatsappService::forSuperadmin();
                    if ($wa->isEnabled() && !empty($this->phone)) {
                        $wa->sendWaMerchantLowBalance($this);
                        \Illuminate\Support\Facades\Cache::put($lowBalKey, true, now()->addDay());
                    }
                } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Get default-merged gateway & anti-spam settings
     */
    public function getGatewaySettingsAttribute(): array
    {
        $raw = $this->settings ?? [];
        $settings = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);

        return array_merge([
            'delay_min'            => 2,
            'delay_max'            => 5,
            'typing_simulation'    => true,
            'batch_delay_count'    => 20,
            'batch_delay_seconds'  => 15,
            'retry_count'          => 2,
            'queue_strategy'       => 'fifo',
            'auto_read_incoming'   => true,
        ], $settings);
    }

    public function devices()
    {
        return $this->hasMany(WhatsappDevice::class, 'merchant_id');
    }

    public function referredByPartner()
    {
        return $this->belongsTo(ReferralPartner::class, 'referred_by_partner_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'merchant_id');
    }

    public function topups(): HasMany
    {
        return $this->hasMany(WaTopup::class, 'merchant_id');
    }

    /**
     * Check if merchant can send message based on balance OR free monthly quota
     * Text message: Rp 30
     * Media message: Rp 50
     * Free quota: 500 messages/month (with watermark)
     */
    public function canSendMessage(string $type = 'text'): bool
    {
        $cost = ($type === 'media') ? 50.00 : 30.00;
        if ((float)$this->credit_balance >= $cost) {
            return true;
        }
        return $this->quota_used_this_month < $this->quota_monthly;
    }

    /**
     * Determine if outgoing message requires watermark
     * Free messages without credit balance will include watermark
     */
    public function requiresWatermark(string $type = 'text'): bool
    {
        $cost = ($type === 'media') ? 50.00 : 30.00;
        return (float)$this->credit_balance < $cost;
    }

    /**
     * Check if merchant is on active Pro plan (compatibility helper)
     */
    public function isPro(): bool
    {
        return $this->plan_type === 'pro';
    }

    /**
     * Get unit cost per message
     */
    public function getMessageCost(string $type = 'text'): float
    {
        return ($type === 'media') ? 50.00 : 30.00;
    }

    /**
     * Generate unique merchant credentials
     */
    public static function generateCredentials(): array
    {
        do {
            $code = 'WAG-' . strtoupper(Str::random(8));
        } while (self::where('merchant_code', $code)->exists());

        return [
            'merchant_code' => $code,
            'api_key'       => 'dgtl_' . bin2hex(random_bytes(16)),
            'secret_key'    => 'dgtl_sec_' . bin2hex(random_bytes(24)),
        ];
    }
}
