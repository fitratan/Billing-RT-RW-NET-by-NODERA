<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    /**
     * Return VAPID public key ke browser agar bisa subscribe.
     */
    public function publicKey()
    {
        $key = config('webpush.vapid.public_key', env('VAPID_PUBLIC_KEY', ''));
        return response()->json(['publicKey' => $key]);
    }

    /**
     * Simpan Web Push subscription dari browser ke database.
     * Dipanggil dari semua portal: admin, kolektor, teknisi, pelanggan.
     */
    public function save(Request $request)
    {
        try {
            $token = $request->input('token', '');
            if (empty($token)) {
                return response()->json(['ok' => false, 'msg' => 'Token kosong'], 400);
            }

            // Deteksi apakah token adalah JSON Web Push subscription atau device token biasa
            $sub = null;
            if (str_starts_with(trim($token), '{')) {
                $sub = json_decode($token, true);
            }

            $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();

            // Tentukan tipe subscriber berdasarkan session aktif
            $subscriberType = 'unknown';
            $subscriberId   = null;

            if (session('collector_id')) {
                $subscriberType = 'collector';
                $subscriberId   = session('collector_id');
                if (!$tenantId) {
                    $tenantId = \App\Models\Collector::withoutGlobalScopes()->find($subscriberId)?->tenant_id;
                }
            } elseif (session('technician_id')) {
                $subscriberType = 'technician';
                $subscriberId   = session('technician_id');
                if (!$tenantId) {
                    $tenantId = \App\Models\User::withoutGlobalScopes()->find($subscriberId)?->tenant_id;
                }
            } elseif (session('customer_id')) {
                $subscriberType = 'customer';
                $subscriberId   = session('customer_id');
                if (!$tenantId) {
                    $tenantId = \App\Models\Customer::withoutGlobalScopes()->find($subscriberId)?->tenant_id;
                }
            } elseif (session('cashier_id')) {
                $subscriberType = 'cashier';
                $subscriberId   = session('cashier_id');
                if (!$tenantId) {
                    $tenantId = \App\Models\Cashier::withoutGlobalScopes()->find($subscriberId)?->tenant_id;
                }
            } elseif (auth()->check()) {
                $user = auth()->user();
                $subscriberType = $user->role ?? 'admin';
                $subscriberId   = $user->id;
                if (!$tenantId) {
                    $tenantId = $user->tenant_id;
                }
            }

            if ($sub && !empty($sub['endpoint'])) {
                // Web Push Subscription — simpan ke tabel push_subscriptions
                PushSubscription::updateOrCreate(
                    ['endpoint' => $sub['endpoint']],
                    [
                        'tenant_id'       => $tenantId,
                        'subscriber_type' => $subscriberType,
                        'subscriber_id'   => $subscriberId,
                        'public_key'      => $sub['keys']['p256dh'] ?? null,
                        'auth_token'      => $sub['keys']['auth'] ?? null,
                        'raw_subscription' => $token,
                        'user_agent'      => $request->userAgent(),
                    ]
                );
            } else {
                // Device token biasa (fallback) — simpan ke kolom fcm_token di model masing-masing
                $this->saveLegacyToken($subscriberType, $subscriberId, $token);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::warning('PushSubscription save failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    private function saveLegacyToken(string $type, ?int $id, string $token): void
    {
        if (!$id) return;

        match ($type) {
            'collector'  => \App\Models\Collector::where('id', $id)->update(['fcm_token' => $token]),
            'technician' => \App\Models\User::where('id', $id)->update(['fcm_token' => $token]),
            'customer'   => \App\Models\Customer::where('id', $id)->update(['fcm_token' => $token]),
            'admin', 'superadmin' => \App\Models\User::where('id', $id)->update(['fcm_token' => $token]),
            default => null,
        };
    }
}
