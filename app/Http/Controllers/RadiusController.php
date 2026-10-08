<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\TenantRadiusSetting;
use App\Services\RadiusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RadiusController extends Controller
{
    public function __construct(
        protected RadiusService $radiusService
    ) {}

    /**
     * Display RADIUS Management Dashboard.
     */
    public function index()
    {
        RadiusService::ensureTablesExist();

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $setting = $this->radiusService->getSetting($tenantId);

        if (!$setting) {
            $setting = new TenantRadiusSetting([
                'tenant_id' => $tenantId,
                'radius_mode' => 'disabled',
                'is_active' => false,
                'remote_db_driver' => 'mysql',
                'remote_db_port' => 3306,
                'remote_db_name' => 'radius',
                'coa_port' => 3799,
                'userman_port' => 8728,
                'auto_sync_on_create' => true,
                'auto_coa_on_isolate' => true,
            ]);
        }

        $nasClients = $this->radiusService->getNasClients($tenantId);
        $sessions = $this->radiusService->getActiveSessions($tenantId, 50);
        $users = $this->radiusService->getUsers($tenantId);
        $totalPppoeCustomers = Customer::where('tenant_id', $tenantId)
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->count();

        // Format dates safely
        $lastSync = $setting->last_sync_at ? Carbon::parse($setting->last_sync_at)->diffForHumans() : null;
        $lastTest = $setting->last_test_at ? Carbon::parse($setting->last_test_at)->diffForHumans() : null;

        return Inertia::render('Admin/Radius', [
            'setting' => [
                'id' => $setting->id,
                'radius_mode' => $setting->radius_mode ?? 'disabled',
                'is_active' => (bool) $setting->is_active,
                'remote_db_driver' => $setting->remote_db_driver ?? 'mysql',
                'remote_db_host' => $setting->remote_db_host ?? '',
                'remote_db_port' => (int) ($setting->remote_db_port ?: 3306),
                'remote_db_name' => $setting->remote_db_name ?? 'radius',
                'remote_db_user' => $setting->remote_db_user ?? '',
                'remote_db_pass' => $setting->remote_db_pass ?? '',
                'nas_ip' => $setting->nas_ip ?? '',
                'nas_secret' => $setting->nas_secret ?? '',
                'coa_port' => (int) ($setting->coa_port ?: 3799),
                'userman_host' => $setting->userman_host ?? '',
                'userman_port' => (int) ($setting->userman_port ?: 8728),
                'userman_user' => $setting->userman_user ?? '',
                'userman_pass' => $setting->userman_pass ?? '',
                'auto_sync_on_create' => (bool) ($setting->auto_sync_on_create ?? true),
                'auto_coa_on_isolate' => (bool) ($setting->auto_coa_on_isolate ?? true),
                'last_sync_at' => $lastSync,
                'last_test_at' => $lastTest,
                'last_test_status' => $setting->last_test_status,
                'last_test_message' => $setting->last_test_message,
            ],
            'nasClients' => $nasClients,
            'sessions' => array_map(function ($s) {
                $sess = (array) $s;
                return [
                    'radacctid' => $sess['radacctid'] ?? null,
                    'username' => $sess['username'] ?? ($sess['name'] ?? '-'),
                    'groupname' => $sess['groupname'] ?? ($sess['group'] ?? '-'),
                    'nasipaddress' => $sess['nasipaddress'] ?? ($sess['nas-ip-address'] ?? '-'),
                    'framedipaddress' => $sess['framedipaddress'] ?? ($sess['address'] ?? ($sess['ip-address'] ?? '-')),
                    'mac_address' => $sess['mac_address'] ?? ($sess['calling-station-id'] ?? '-'),
                    'acctstarttime' => !empty($sess['acctstarttime']) ? Carbon::parse($sess['acctstarttime'])->diffForHumans() : ($sess['from-time'] ?? '-'),
                    'acctsessiontime' => isset($sess['acctsessiontime']) ? (int) $sess['acctsessiontime'] : ($sess['uptime'] ?? null),
                    'acctinputoctets' => isset($sess['acctinputoctets']) ? (int) $sess['acctinputoctets'] : ($sess['download'] ?? null),
                    'acctoutputoctets' => isset($sess['acctoutputoctets']) ? (int) $sess['acctoutputoctets'] : ($sess['upload'] ?? null),
                    'acctterminatecause' => $sess['acctterminatecause'] ?? null,
                ];
            }, $sessions),
            'users' => array_map(function ($u) {
                $usr = (array) $u;
                return [
                    'id' => $usr['id'] ?? ($usr['.id'] ?? null),
                    'username' => $usr['username'] ?? ($usr['name'] ?? '-'),
                    'groupname' => $usr['groupname'] ?? ($usr['group'] ?? '-'),
                ];
            }, $users),
            'stats' => [
                'total_pppoe_customers' => $totalPppoeCustomers,
                'total_radius_users' => count($users),
                'total_active_sessions' => count($sessions),
                'total_nas_clients' => count($nasClients),
            ],
        ]);
    }

    /**
     * Save / Update RADIUS Settings.
     */
    public function saveSettings(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $validated = $request->validate([
            'radius_mode' => 'required|in:disabled,local_db,remote_db,userman_v7',
            'is_active' => 'boolean',
            'remote_db_driver' => 'nullable|string|max:16',
            'remote_db_host' => 'nullable|string|max:255',
            'remote_db_port' => 'nullable|integer',
            'remote_db_name' => 'nullable|string|max:255',
            'remote_db_user' => 'nullable|string|max:255',
            'remote_db_pass' => 'nullable|string',
            'nas_ip' => 'nullable|string|max:255',
            'nas_secret' => 'nullable|string',
            'coa_port' => 'nullable|integer',
            'userman_host' => 'nullable|string|max:255',
            'userman_port' => 'nullable|integer',
            'userman_user' => 'nullable|string|max:255',
            'userman_pass' => 'nullable|string',
            'auto_sync_on_create' => 'boolean',
            'auto_coa_on_isolate' => 'boolean',
        ]);

        $setting = TenantRadiusSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        if (!$setting) {
            $setting = new TenantRadiusSetting();
            $setting->tenant_id = $tenantId;
        }

        $setting->radius_mode = $validated['radius_mode'];
        $setting->is_active = (bool) ($validated['is_active'] ?? false);
        $setting->remote_db_driver = $validated['remote_db_driver'] ?? 'mysql';
        $setting->remote_db_host = $validated['remote_db_host'] ?? null;
        $setting->remote_db_port = (int) ($validated['remote_db_port'] ?? 3306);
        $setting->remote_db_name = $validated['remote_db_name'] ?? 'radius';
        $setting->remote_db_user = $validated['remote_db_user'] ?? null;

        if (isset($validated['remote_db_pass'])) {
            $setting->remote_db_pass = $validated['remote_db_pass'];
        }

        $setting->nas_ip = $validated['nas_ip'] ?? null;
        if (isset($validated['nas_secret'])) {
            $setting->nas_secret = $validated['nas_secret'];
        }
        $setting->coa_port = (int) ($validated['coa_port'] ?? 3799);

        $setting->userman_host = $validated['userman_host'] ?? null;
        $setting->userman_port = (int) ($validated['userman_port'] ?? 8728);
        $setting->userman_user = $validated['userman_user'] ?? null;
        if (isset($validated['userman_pass'])) {
            $setting->userman_pass = $validated['userman_pass'];
        }

        $setting->auto_sync_on_create = (bool) ($validated['auto_sync_on_create'] ?? true);
        $setting->auto_coa_on_isolate = (bool) ($validated['auto_coa_on_isolate'] ?? true);
        $setting->save();

        return redirect()->back()->with('success', 'Pengaturan RADIUS berhasil disimpan.');
    }

    /**
     * Test connection to RADIUS backend.
     */
    public function testConnection()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $result = $this->radiusService->testConnection($tenantId);

        if ($result['status'] === 'ok') {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'] ?? 0,
                'users_count' => $result['users_count'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'latency_ms' => $result['latency_ms'] ?? 0,
        ], 422);
    }

    /**
     * Trigger bulk sync of all PPPoE customers to RADIUS.
     */
    public function syncAll()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $result = $this->radiusService->syncAllCustomers($tenantId);

        if (!empty($result['error'])) {
            return redirect()->back()->with('error', $result['error']);
        }

        return redirect()->back()->with(
            'success',
            "Sinkronisasi selesai. Total: {$result['total']}, Berhasil: {$result['synced']}, Gagal: {$result['failed']}"
        );
    }

    /**
     * Add or update NAS router.
     */
    public function saveNas(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $validated = $request->validate([
            'nasname' => 'required|string|max:128',
            'shortname' => 'nullable|string|max:32',
            'type' => 'nullable|string|max:30',
            'secret' => 'required|string|max:60',
            'description' => 'nullable|string|max:200',
        ]);

        $this->radiusService->saveNasClient($validated, $tenantId);

        return redirect()->back()->with('success', 'Data NAS Router berhasil disimpan.');
    }

    /**
     * Delete NAS router.
     */
    public function deleteNas($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $this->radiusService->deleteNasClient((int) $id, $tenantId);

        return redirect()->back()->with('success', 'NAS Router berhasil dihapus.');
    }

    /**
     * Disconnect session via RFC 3576 CoA / PoD.
     */
    public function disconnectSession(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'nas_ip' => 'nullable|string',
            'framed_ip' => 'nullable|string',
        ]);

        $result = $this->radiusService->sendDisconnectRequest(
            username: $validated['username'],
            nasIp: $validated['nas_ip'] ?? null,
            framedIp: $validated['framed_ip'] ?? null,
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'latency_ms' => $result['latency_ms'] ?? 0,
        ], 422);
    }
}
