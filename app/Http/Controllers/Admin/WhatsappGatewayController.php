<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappDevice;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WhatsappGatewayController extends Controller
{
    private string $microserviceUrl;
    private string $masterApiKey;

    public function __construct()
    {
        $this->microserviceUrl = rtrim(config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3000')), '/');
        $this->masterApiKey = config('services.wa_gateway.api_key', env('WA_GATEWAY_API_KEY', 'nodera_wa_secret_key_2026'));
    }

    /**
     * Display WhatsApp Gateway Device & Session Management Hub.
     */
    public function index()
    {
        $tenantId = TenantScope::currentTenantId();
        
        // 1. Fetch devices from DB
        $devices = WhatsappDevice::orderBy('is_default', 'desc')->latest()->get();

        // 2. Fetch live session statuses from microservice
        $microserviceOnline = false;
        $liveSessions = collect();
        try {
            $resp = Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(3)
                ->get("{$this->microserviceUrl}/api/sessions");

            if ($resp->successful()) {
                $microserviceOnline = true;
                $data = $resp->json('data', []);
                $liveSessions = collect(is_array($data) ? $data : [])->keyBy('id');
            }
        } catch (\Throwable $e) {
            $microserviceOnline = false;
        }

        // 3. Sync live statuses into device list
        $deviceList = $devices->map(function ($d) use ($liveSessions) {
            $sessionsCollect = $liveSessions instanceof \Illuminate\Support\Collection ? $liveSessions : collect($liveSessions);
            $live = $sessionsCollect->get($d->session_id);
            if ($live) {
                $d->live_status = $live['status'] ?? $d->status;
                $d->is_connected = ($live['status'] ?? '') === 'CONNECTED';
                $d->phone_number = $live['phone'] ?? $d->phone_number;
                $d->profile_name = $live['name'] ?? $d->profile_name;
            } else {
                $d->live_status = $d->status;
                $d->is_connected = $d->status === 'CONNECTED';
            }
            return $d;
        });

        // 4. If no devices exist for this tenant, auto-create default session
        if ($deviceList->isEmpty()) {
            $defaultName = $tenantId ? "WhatsApp Tenant {$tenantId}" : "WhatsApp Billing Server";
            $sessionPrefix = $tenantId ? "tenant_{$tenantId}" : "master";
            $defaultSessionId = "wa_{$sessionPrefix}_main";

            $newDev = WhatsappDevice::create([
                'tenant_id'   => $tenantId,
                'session_id'  => $defaultSessionId,
                'name'        => $defaultName,
                'is_default'  => true,
                'status'      => 'DISCONNECTED',
            ]);

            // Trigger microservice initialization
            try {
                Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                    ->post("{$this->microserviceUrl}/api/sessions/create", [
                        'sessionId' => $defaultSessionId,
                        'apiKey'    => $newDev->api_key,
                    ]);
            } catch (\Throwable $e) {}

            $deviceList = collect([$newDev]);
        }

        return Inertia::render('Admin/WhatsappGateway', [
            'devices'            => $deviceList,
            'microserviceOnline' => $microserviceOnline,
            'gatewayUrl'         => $this->microserviceUrl,
            'publicIp'           => request()->server('SERVER_ADDR', '127.0.0.1'),
        ]);
    }

    /**
     * Initialize / Add a new WhatsApp Device Session.
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $tenantId = TenantScope::currentTenantId();
        $tenantPrefix = $tenantId ? "tenant_{$tenantId}" : "master";
        $sessionId = "wa_{$tenantPrefix}_" . bin2hex(random_bytes(4));

        $device = WhatsappDevice::create([
            'tenant_id'   => $tenantId,
            'session_id'  => $sessionId,
            'name'        => $request->name,
            'webhook_url' => $request->webhook_url,
            'status'      => 'INITIALIZING',
        ]);

        try {
            Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(5)
                ->post("{$this->microserviceUrl}/api/sessions/create", [
                    'sessionId'  => $sessionId,
                    'webhookUrl' => $request->webhook_url,
                    'apiKey'     => $device->api_key,
                ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to call WA gateway init session: " . $e->getMessage());
        }

        return redirect()->back()->with('msg', 'Sesi WhatsApp berhasil ditambahkan. Silakan scan QR Code untuk menghubungkan.');
    }

    /**
     * Get live QR Code data URL for scanning.
     */
    public function getQr(string $sessionId)
    {
        try {
            $resp = Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(5)
                ->get("{$this->microserviceUrl}/api/sessions/{$sessionId}/qr");

            if ($resp->successful()) {
                return response()->json($resp->json());
            }

            return response()->json([
                'success' => false,
                'status'  => 'ERROR',
                'message' => 'Gagal mengambil QR code dari server gateway.',
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status'  => 'OFFLINE',
                'message' => 'Server microservice WhatsApp tidak merespons.',
            ], 503);
        }
    }

    /**
     * Request 8-digit Pairing Code for linking by phone number.
     */
    public function requestPairingCode(Request $request, string $sessionId)
    {
        $request->validate([
            'phone' => 'required|string|min:8|max:20',
        ]);

        try {
            $resp = Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(8)
                ->post("{$this->microserviceUrl}/api/sessions/{$sessionId}/pairing-code", [
                    'phone' => $request->phone,
                ]);

            return response()->json($resp->json(), $resp->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Koneksi ke microservice WhatsApp gagal: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Logout / disconnect device.
     */
    public function logout(string $sessionId)
    {
        try {
            Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(5)
                ->post("{$this->microserviceUrl}/api/sessions/{$sessionId}/logout");

            WhatsappDevice::where('session_id', $sessionId)->update([
                'status'       => 'DISCONNECTED',
                'phone_number' => null,
                'profile_name' => null,
            ]);

            return redirect()->back()->with('msg', 'Perangkat WhatsApp berhasil diputus (Logout).');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memutus sesi: ' . $e->getMessage());
        }
    }

    /**
     * Delete device.
     */
    public function delete(string $sessionId)
    {
        try {
            Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(5)
                ->delete("{$this->microserviceUrl}/api/sessions/{$sessionId}");
        } catch (\Throwable $e) {}

        WhatsappDevice::where('session_id', $sessionId)->delete();

        return redirect()->back()->with('msg', 'Perangkat WhatsApp berhasil dihapus.');
    }

    /**
     * Set device as default.
     */
    public function setDefault(string $sessionId)
    {
        $tenantId = TenantScope::currentTenantId();
        WhatsappDevice::where('tenant_id', $tenantId)->update(['is_default' => false]);
        WhatsappDevice::where('session_id', $sessionId)->update(['is_default' => true]);

        return redirect()->back()->with('msg', 'Perangkat utama WhatsApp berhasil diperbarui.');
    }

    /**
     * Send test WhatsApp message.
     */
    public function sendTest(Request $request)
    {
        $request->validate([
            'phone'      => 'required|string|min:8',
            'message'    => 'required|string|max:1000',
            'session_id' => 'nullable|string',
        ]);

        $sessionId = $request->session_id ?: 'default';

        try {
            $resp = Http::withHeaders(['X-Api-Key' => $this->masterApiKey])
                ->timeout(10)
                ->post("{$this->microserviceUrl}/api/send-message", [
                    'session' => $sessionId,
                    'phone'   => $request->phone,
                    'message' => $request->message,
                ]);

            if ($resp->successful()) {
                return redirect()->back()->with('msg', 'Pesan uji coba WhatsApp berhasil dikirim!');
            }

            $err = $resp->json('error', 'Gagal mengirim pesan');
            return redirect()->back()->with('error', "Gagal: {$err}");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Koneksi gagal: ' . $e->getMessage());
        }
    }
}
