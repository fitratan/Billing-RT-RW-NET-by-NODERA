<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OntDevice;
use App\Models\Customer;
use App\Models\AcsTenantSetting;
use App\Services\GenieAcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class OntDeviceController extends Controller
{
    protected GenieAcsService $acsService;

    public function __construct(GenieAcsService $acsService)
    {
        $this->acsService = $acsService;
    }

    /**
     * Display list of ONT devices for the active tenant
     */
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $tenant = \App\Models\Tenant::find($tenantId);
        $tenantSlug = $tenant?->slug ?: ('isp_' . $tenantId);

        // Auto-initialize tenant ACS settings if not exists
        $acsSettings = AcsTenantSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'is_enabled' => true,
                'connection_mode' => 'cloud',
                'acs_username' => $tenantSlug,
                'acs_password' => bin2hex(random_bytes(8)),
                'server_url' => 'http://acs.dgtlnetsolution.com:7547',
                'nbi_url' => 'http://acs.dgtlnetsolution.com:7557',
                'daily_rate_per_ont' => 33.33,
                'free_tier_quota' => 10,
            ]
        );

        // Auto-upgrade server_url if in Cloud mode and still pointing to IP or acs_username is legacy 'tenant_X'
        $needsSave = false;
        if ($acsSettings->connection_mode === 'cloud') {
            if (str_contains($acsSettings->server_url, '127.0.0.1') || empty($acsSettings->server_url)) {
                $acsSettings->server_url = 'http://acs.dgtlnetsolution.com:7547';
                $acsSettings->nbi_url = 'http://acs.dgtlnetsolution.com:7557';
                $needsSave = true;
            }

            if (empty($acsSettings->acs_username) || str_starts_with($acsSettings->acs_username, 'tenant_')) {
                $acsSettings->acs_username = $tenantSlug;
                $needsSave = true;
            }
        }

        if ($needsSave) {
            $acsSettings->save();
        }

        // Auto-sync from ACS on every page load so user never needs to click manual sync
        try {
            $cacheKey = "acs_sync_tenant_{$tenantId}";
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $this->acsService->syncDevicesFromAcs($tenantId);
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 10);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Auto-sync GenieACS failed: " . $e->getMessage());
        }

        $query = OntDevice::where('tenant_id', $tenantId)->with('customer:id,name,pppoe_username,phone,connection_type');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%")
                  ->orWhere('model_name', 'like', "%{$search}%")
                  ->orWhere('wifi_ssid', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('pppoe_username', 'like', "%{$search}%");
                  });
            });
        }

        // Manufacturer filter
        if ($request->filled('manufacturer') && $request->input('manufacturer') !== 'all') {
            $query->where('manufacturer', $request->input('manufacturer'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'WARNING') {
                $query->where(function($q) {
                    $q->where('rx_power', '<', -24.0)->where('rx_power', '>=', -27.0);
                });
            } elseif ($status === 'CRITICAL') {
                $query->where(function($q) {
                    $q->where('rx_power', '<', -27.0)->orWhere('status', 'CRITICAL');
                });
            } else {
                $query->where('status', $status);
            }
        }

        $devices = $query->orderBy('last_inform_at', 'desc')->paginate(15)->withQueryString();

        // Calculate summary metrics
        $allTenantDevices = OntDevice::where('tenant_id', $tenantId)->get();
        $totalCount = $allTenantDevices->count();
        $onlineCount = $allTenantDevices->where('status', 'ONLINE')->count();
        $warningCount = $allTenantDevices->filter(fn($d) => $d->rx_power !== null && $d->rx_power < -24.0 && $d->rx_power >= -27.0)->count();
        $criticalCount = $allTenantDevices->filter(fn($d) => ($d->rx_power !== null && $d->rx_power < -27.0) || $d->status === 'CRITICAL')->count();
        $offlineCount = $allTenantDevices->where('status', 'OFFLINE')->count();

        // Customer list for quick assignment
        $customers = Customer::where('tenant_id', $tenantId)
            ->select('id', 'name', 'pppoe_username', 'connection_type', 'phone', 'code', 'ip_address')
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                $displayName = !empty($c->name) ? $c->name : (!empty($c->pppoe_username) ? $c->pppoe_username : (!empty($c->code) ? $c->code : ('Pelanggan #' . $c->id)));
                $displayUsername = !empty($c->pppoe_username) ? $c->pppoe_username : (!empty($c->code) ? $c->code : (!empty($c->ip_address) ? $c->ip_address : '-'));
                return [
                    'id' => $c->id,
                    'name' => $displayName,
                    'username' => $displayUsername,
                    'service_type' => $c->connection_type ?? 'PPPoE',
                    'phone' => $c->phone ?? '',
                    'ip_address' => $c->ip_address ?? '',
                ];
            });

        return Inertia::render('Admin/OntDevices', [
            'devices' => $devices,
            'metrics' => [
                'total' => $totalCount,
                'online' => $onlineCount,
                'warning' => $warningCount,
                'critical' => $criticalCount,
                'offline' => $offlineCount,
            ],
            'acsSettings' => $acsSettings,
            'customers' => $customers,
            'filters' => $request->only(['search', 'manufacturer', 'status']),
        ]);
    }

    /**
     * Reboot ONT Device
     */
    public function reboot(Request $request, $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $device = OntDevice::where('tenant_id', $tenantId)->findOrFail($id);

        $success = $this->acsService->rebootDevice($device->serial_number, $tenantId);

        if ($success) {
            return back()->with('success', "Perintah Reboot berhasil dikirim ke ONT {$device->serial_number}.");
        }

        return back()->with('error', "Gagal mengirim perintah reboot ke ONT {$device->serial_number}. Pastikan modem online.");
    }

    /**
     * Update WiFi SSID and Password
     */
    public function updateWifi(Request $request, $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $device = OntDevice::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'wifi_ssid' => 'required|string|max:32',
            'wifi_password' => 'nullable|string|min:8|max:64',
        ]);

        $ssid = $request->input('wifi_ssid');
        $password = $request->input('wifi_password');

        $success = $this->acsService->setWifiConfig($device->serial_number, $ssid, $password, $tenantId);

        if ($success) {
            $device->update([
                'wifi_ssid' => $ssid,
                'wifi_password' => $password ? $password : $device->wifi_password,
            ]);
            return back()->with('success', "Konfigurasi WiFi ONT {$device->serial_number} berhasil diperbarui.");
        }

        return back()->with('error', "Gagal memperbarui WiFi ONT {$device->serial_number}. Pastikan modem merespon.");
    }

    /**
     * Refresh Diagnostics & Optical Parameters
     */
    public function refreshMetrics(Request $request, $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $device = OntDevice::where('tenant_id', $tenantId)->findOrFail($id);

        $success = $this->acsService->refreshParameters($device->serial_number, $tenantId);

        if ($success) {
            return back()->with('success', "Permintaan refresh parameter optik dikirim ke ONT {$device->serial_number}.");
        }

        return back()->with('error', "Gagal merefresh parameter ONT.");
    }

    /**
     * Assign Customer to ONT Device
     */
    public function assignCustomer(Request $request, $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $device = OntDevice::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $device->update([
            'customer_id' => $request->input('customer_id'),
        ]);

        return back()->with('success', "Pelanggan berhasil ditautkan ke ONT {$device->serial_number}.");
    }

    /**
     * Sync newly detected devices from GenieACS
     */
    public function syncAcs(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $result = $this->acsService->syncDevicesFromAcs($tenantId);

        return back()->with('success', "Sinkronisasi selesai. {$result['imported']} perangkat baru diimpor, {$result['updated']} diperbarui.");
    }

    /**
     * Update GenieACS Connection Mode & Settings (Cloud Engine vs Server Mandiri)
     */
    public function updateSettings(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $request->validate([
            'connection_mode' => 'required|in:cloud,self_hosted',
            'server_url'      => 'nullable|string|max:255',
            'nbi_url'         => 'nullable|string|max:255',
            'acs_username'    => 'nullable|string|max:100',
            'acs_password'    => 'nullable|string|max:100',
        ]);

        $mode = $request->input('connection_mode', 'cloud');
        $settings = AcsTenantSetting::firstOrCreate(['tenant_id' => $tenantId]);
        $tenant = \App\Models\Tenant::find($tenantId);
        $tenantSlug = $tenant?->slug ?: ('isp_' . $tenantId);

        if ($mode === 'self_hosted') {
            $serverUrl = $request->input('server_url') ?: 'http://127.0.0.1:7547';
            $nbiUrl = $request->input('nbi_url') ?: 'http://127.0.0.1:7557';
            $acsUsername = $request->input('acs_username', '');
            $acsPassword = $request->input('acs_password', '');
        } else {
            $serverUrl = 'http://acs.dgtlnetsolution.com:7547';
            $nbiUrl = 'http://acs.dgtlnetsolution.com:7557';
            $acsUsername = $tenantSlug;
            $acsPassword = $request->input('acs_password') ?: ($settings->acs_password ?: bin2hex(random_bytes(8)));
        }

        $settings->update([
            'connection_mode' => $mode,
            'is_enabled'      => true,
            'server_url'      => $serverUrl,
            'nbi_url'         => $nbiUrl,
            'acs_username'    => $acsUsername,
            'acs_password'    => $acsPassword,
        ]);

        $modeText = $mode === 'self_hosted' ? 'Server Mandiri (Self-Hosted FREE)' : 'Cloud Managed NODERA (PAYG)';
        return back()->with('success', "Pengaturan mode GenieACS berhasil disimpan ({$modeText}).");
    }
}