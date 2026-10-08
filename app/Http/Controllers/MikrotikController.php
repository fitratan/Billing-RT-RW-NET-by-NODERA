<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MikrotikController extends Controller
{
    public function routers()
    {
        $isSuperadmin = session('admin_role') === 'superadmin';
        $routers = Mikrotik::orderBy('name')->get();

        // Superadmin melihat semua tenant — tampilkan label tenant per router
        $tenantNames = \App\Models\Tenant::pluck('name', 'id');

        return Inertia::render('Admin/MikrotikRouters', [
            'is_superadmin' => $isSuperadmin,
            'tenants' => \App\Models\Tenant::orderBy('name')->get(['id', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            'routers' => $routers->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'host' => $r->host, 'port' => (int) ($r->port ?? 8728),
                'username' => $r->username, 'is_active' => (bool) $r->is_active, 'location' => $r->location,
                'tenant_id' => $r->tenant_id,
                'tenant_name' => $isSuperadmin && $r->tenant_id ? ($tenantNames[$r->tenant_id] ?? null) : null,
                'resource' => $this->fetchResource($r),
            ]),
        ]);
    }

    protected function fetchResource(Mikrotik $router): array
    {
        if (! $router->is_active) {
            return [
                'cpu_load' => null,
                'memory_percent' => null,
                'free_memory' => null,
                'total_memory' => null,
                'uptime' => null,
                'version' => null,
                'board_name' => null,
                'temperature' => null,
                'voltage' => null,
                'online' => false,
            ];
        }

        $host = $router->host;
        $port = (int) ($router->port ?? 8728);
        $cacheKey = "mik_res_" . md5("{$host}:{$port}");
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return array_merge($cached, ['online' => true]);
            }
        }

        try {
            $mik = new MikrotikService($router);
            $res = $mik->getResource();
            if (empty($res)) {
                return [
                    'cpu_load' => null,
                    'memory_percent' => null,
                    'free_memory' => null,
                    'total_memory' => null,
                    'uptime' => null,
                    'version' => null,
                    'board_name' => null,
                    'temperature' => null,
                    'voltage' => null,
                    'online' => false,
                ];
            }

            return array_merge($res, ['online' => true]);
        } catch (\Exception $e) {
            Log::warning("Router resource check gagal: {$router->host} — " . $e->getMessage());
            return [
                'cpu_load' => null,
                'memory_percent' => null,
                'free_memory' => null,
                'total_memory' => null,
                'uptime' => null,
                'version' => null,
                'board_name' => null,
                'temperature' => null,
                'voltage' => null,
                'online' => false,
            ];
        }
    }

    protected function sendTelegramAlert(Mikrotik $router, string $message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID');

        if (!$botToken || !$chatId) return;

        try {
            $isOnline = stripos($message, 'online') !== false;
            $headerEmoji = $isOnline ? '🟢' : '🔴';
            $statusText = $isOnline ? 'ONLINE' : (stripos($message, 'offline') !== false ? 'OFFLINE' : $message);
            $text = "<b>{$headerEmoji} PERINGATAN MIKROTIK — NODERA</b>\n\n"
                . "┌ {$router->name}\n"
                . "├ Status: {$statusText}\n"
                . "├ Host: <code>{$router->host}:{$router->port}</code>\n"
                . "└ Waktu: " . now()->format('Y-m-d H:i:s');

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::warning("Telegram alert error: " . $e->getMessage());
        }
    }

    public function addRouter(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'host' => 'required|max:255',
            'port' => 'required|numeric|min:1|max:65535',
            'username' => 'required|max:255',
            'password' => 'nullable|max:255',
            'default_profile' => 'nullable|max:255',
            'default_isolir_profile' => 'nullable|max:255',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        $isSuperadmin = session('admin_role') === 'superadmin';
        if ($isSuperadmin && empty($request->tenant_id)) {
            return back()->with('error', 'Pilih tenant untuk router ini.')->withInput();
        }
        $tenantId = $isSuperadmin 
            ? (int) $request->tenant_id 
            : (\App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id);

        $host = trim($validated['host']);
        $host = preg_replace('#^https?://#i', '', $host);
        $host = preg_replace('#^ssl://#i', '', $host);
        $host = preg_replace('#^tcp://#i', '', $host);
        $host = rtrim($host, '/');

        $port = (int) $validated['port'];
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && ($port === 8728 || empty($port))) {
                $port = (int) $p;
            }
        }
        $validated['host'] = $host;
        $validated['port'] = $port;
        $validated['username'] = trim($validated['username']);

        // Clear previous cache if any
        \Illuminate\Support\Facades\Cache::forget("mik_cb_down_" . md5("{$host}:{$port}"));
        \Illuminate\Support\Facades\Cache::forget("mik_fail_cnt_" . md5("{$host}:{$port}"));
        \Illuminate\Support\Facades\Cache::forget("mik_res_" . md5("{$host}:{$port}"));

        $router = Mikrotik::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]));

        $syncMode = $request->input('initial_sync_mode', $request->input('sync_customers_type', 'pppoe'));

        try {
            $mik = new MikrotikService($router);
            if ($mik->isConnected()) {
                $res = $mik->getResource(false);
                $version = $res['version'] ?? 'RouterOS';
                $board = $res['board_name'] ?? $res['model'] ?? 'MikroTik';

                $syncDetails = [];

                if ($syncMode !== 'none') {
                    try {
                        $billingCtrl = app(\App\Http\Controllers\BillingController::class);

                        // 1. Sinkronisasi paket profil terlebih dahulu
                        $pkgSyncType = match ($syncMode) {
                            'arp' => 'queue',
                            'all' => 'all',
                            default => 'pppoe',
                        };

                        $reqPkg = new Request([
                            'router_id' => $router->id,
                            'sync_type' => $pkgSyncType,
                        ]);
                        $billingCtrl->syncPackages($reqPkg);
                        $pkgCount = \App\Models\Package::where('router_id', $router->id)
                            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                            ->count();

                        if ($pkgCount > 0) {
                            $pkgLabel = match ($syncMode) {
                                'arp' => 'Profil Antrean / Static',
                                'all' => 'Semua Profil',
                                default => 'Profil PPPoE',
                            };
                            $syncDetails[] = "{$pkgCount} paket ({$pkgLabel})";
                        }

                        // 2. Sinkronisasi pelanggan
                        $custSyncType = match ($syncMode) {
                            'arp' => 'arp',
                            'all' => 'all',
                            default => 'pppoe',
                        };

                        $reqCust = new Request([
                            'router_id' => $router->id,
                            'sync_type' => $custSyncType,
                            'isolation_date' => 20,
                        ]);
                        $billingCtrl->syncCustomers($reqCust);
                        $custCount = \App\Models\Customer::where('router_id', $router->id)
                            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                            ->count();

                        if ($custCount > 0) {
                            $custLabel = match ($syncMode) {
                                'arp' => 'Pelanggan IP Statis / ARP',
                                'all' => 'Semua Pelanggan (PPPoE & Statis)',
                                default => 'Pelanggan PPPoE',
                            };
                            $syncDetails[] = "{$custCount} {$custLabel}";
                        }

                        // 3. Auto-tanam script PPPoE accounting on-down
                        $mik->autoProvisionPppoeAccountingScript($router->id);
                    } catch (\Throwable $se) {
                        \Illuminate\Support\Facades\Log::warning("Auto-sync on add router failed: " . $se->getMessage());
                    }
                }

                $syncMsg = !empty($syncDetails)
                    ? " — Berhasil menyinkronkan " . implode(' & ', $syncDetails) . " otomatis!"
                    : "";

                return redirect()->to('/admin/mikrotik/routers')->with('msg', "Router {$router->name} ({$host}:{$port}) berhasil ditambahkan & TERHUBUNG! [{$board} — {$version}]{$syncMsg}");
            } else {
                $err = $mik->getLastError() ?: 'Koneksi socket timeout / ditolak.';
                return redirect()->to('/admin/mikrotik/routers')->with('msg', "Router {$router->name} tersimpan. Catatan koneksi: {$err}");
            }
        } catch (\Throwable $e) {
            return redirect()->to('/admin/mikrotik/routers')->with('msg', ' Router berhasil ditambahkan');
        }
    }

    public function editRouter(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'host' => 'required|max:255',
            'port' => 'required|numeric|min:1|max:65535',
            'username' => 'required|max:255',
            'password' => 'nullable|max:255',
            'default_profile' => 'nullable|max:255',
            'default_isolir_profile' => 'nullable|max:255',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        $host = trim($validated['host']);
        $host = preg_replace('#^https?://#i', '', $host);
        $host = preg_replace('#^ssl://#i', '', $host);
        $host = preg_replace('#^tcp://#i', '', $host);
        $host = rtrim($host, '/');

        $port = (int) $validated['port'];
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && ($port === 8728 || empty($port))) {
                $port = (int) $p;
            }
        }

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget("mik_cb_down_" . md5("{$host}:{$port}"));
        \Illuminate\Support\Facades\Cache::forget("mik_fail_cnt_" . md5("{$host}:{$port}"));
        \Illuminate\Support\Facades\Cache::forget("mik_res_" . md5("{$host}:{$port}"));

        $data = [
            'name' => $validated['name'],
            'host' => $host,
            'port' => $port,
            'username' => trim($validated['username']),
            'default_profile' => $validated['default_profile'] ?? null,
            'default_isolir_profile' => $validated['default_isolir_profile'] ?? null,
        ];

        // Superadmin bisa pindahkan router antar tenant; tenant admin terkunci ke tenant-nya sendiri
        if (session('admin_role') === 'superadmin' && !empty($request->tenant_id)) {
            $data['tenant_id'] = (int) $request->tenant_id;
        }
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $router = Mikrotik::findOrFail($id);
        $router->fill($data);
        $router->save();

        return redirect()->to('/admin/mikrotik/routers')->with('msg', ' Router berhasil diperbarui');
    }

    public function deleteRouter($id)
    {
        Mikrotik::destroy($id);
        return redirect()->to('/admin/mikrotik/routers')->with('msg', ' Router berhasil dihapus');
    }

    public function testRouter($id)
    {
        $router = Mikrotik::findOrFail($id);
        try {
            $host = $router->host;
            $port = (int) ($router->port ?? 8728);

            // Bersihkan cooldown / circuit breaker agar pengujian langsung dieksekusi secara live
            \Illuminate\Support\Facades\Cache::forget("mik_cb_down_" . md5("{$host}:{$port}"));
            \Illuminate\Support\Facades\Cache::forget("mik_fail_cnt_" . md5("{$host}:{$port}"));
            \Illuminate\Support\Facades\Cache::forget("mik_res_" . md5("{$host}:{$port}"));

            $mik = new MikrotikService($router);
            if ($mik->isConnected()) {
                $res = $mik->getResource(false);
                $version = $res['version'] ?? 'RouterOS';
                $board = $res['board_name'] ?? $res['model'] ?? 'MikroTik';
                return redirect()->to('/admin/mikrotik/routers')->with('msg', "Koneksi ke {$router->name} ({$router->host}:{$router->port}) berhasil! [{$board} — {$version}]");
            }
            return redirect()->to('/admin/mikrotik/routers')->with('error', "Gagal terkoneksi ke {$router->name}: " . ($mik->getLastError() ?: 'Koneksi ditolak / timeout. Pastikan IP service API (port ' . $port . ') aktif di IP > Services.'));
        } catch (\Exception $e) {
            return redirect()->to('/admin/mikrotik/routers')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function testRouterAjax(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $host = trim($request->input('host'));
        $port = (int) $request->input('port', 8728);
        $username = trim($request->input('username'));
        $password = (string) $request->input('password', '');
        $routerId = $request->input('router_id');

        if (empty($password) && !empty($routerId)) {
            $existing = Mikrotik::find($routerId);
            if ($existing) {
                $password = $existing->password;
            }
        }

        $tempRouter = new Mikrotik([
            'name' => 'Temp Test',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
        ]);

        $startTime = microtime(true);
        try {
            \Illuminate\Support\Facades\Cache::forget("mik_cb_down_" . md5("{$host}:{$port}"));
            \Illuminate\Support\Facades\Cache::forget("mik_fail_cnt_" . md5("{$host}:{$port}"));
            \Illuminate\Support\Facades\Cache::forget("mik_res_" . md5("{$host}:{$port}"));

            $mik = new MikrotikService($tempRouter);
            if ($mik->isConnected()) {
                $elapsed = round((microtime(true) - $startTime) * 1000, 1);
                $res = $mik->getResource(false);
                $version = $res['version'] ?? 'RouterOS';
                $board = $res['board_name'] ?? $res['model'] ?? 'MikroTik';
                $uptime = $res['uptime'] ?? '-';
                return response()->json([
                    'success' => true,
                    'latency_ms' => $elapsed,
                    'board_name' => $board,
                    'version' => $version,
                    'uptime' => $uptime,
                    'message' => "Terkoneksi ke {$board} ({$version}) dalam {$elapsed}ms",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $mik->getLastError() ?: "Koneksi ditolak / timeout ke {$host}:{$port}. Pastikan IP service API MikroTik aktif di IP > Services.",
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage(),
            ], 500);
        }
    }

    public function viewRouter($id)
    {
        $router = Mikrotik::findOrFail($id);
        $mik = new MikrotikService($router);

        $users = [];
        $profiles = [];
        $active = [];
        $resource = [];
        $interfaces = [];
        $hotspotUsers = [];
        $hotspotActive = [];
        $arpEntries = [];
        $error = null;

        $isConnected = false;
        try {
            $isConnected = $mik->isConnected();
            if ($isConnected) {
                $resource = $mik->getDetailedResource();
                $interfaces = $mik->getInterfaces();
                $users = $mik->getPppoeSecrets();
                $profiles = $mik->getPppoeProfiles();
                $active = $mik->getActivePppoe();
                $hotspotUsers = $mik->getHotspotUsers();
                $hotspotActive = $mik->getActiveHotspotUsers();
                $arpEntries = $mik->getArpTable();
            } else {
                $error = $mik->getLastError() ?: "Tidak dapat terhubung ke {$router->host}:{$router->port}";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Log::info('MikroTik viewRouter connection notice: ' . $e->getMessage());
        }

        $isDemo = (session('tenant_slug') === 'demo') || ($router->tenant?->slug === 'demo');

        // Fallback simulasi HANYA untuk tenant demo
        if (!$isConnected && $isDemo) {
            $totalMem = 1073741824;
            $freeMem = 773094195;
            $usedMem = $totalMem - $freeMem;
            $totalHdd = 536870912;
            $freeHdd = 461373440;
            $usedHdd = $totalHdd - $freeHdd;

            $resource = [
                'identity' => $router->name ?? 'NODERA-DEMO-ROUTER',
                'cpu_load' => 8,
                'cpu_count' => 4,
                'cpu_frequency' => 1400,
                'cpu_model' => 'AL21400',
                'architecture' => 'arm',
                'board_name' => 'Demo Router (Simulasi)',
                'version' => '7.14.3 (stable)',
                'uptime' => '42d 18:24:10',
                'total_memory' => $totalMem,
                'free_memory' => $freeMem,
                'used_memory' => $usedMem,
                'memory_percent' => (int) round(($usedMem / $totalMem) * 100),
                'total_hdd' => $totalHdd,
                'free_hdd' => $freeHdd,
                'used_hdd' => $usedHdd,
                'hdd_percent' => (int) round(($usedHdd / $totalHdd) * 100),
                'platform' => 'MikroTik',
                'model' => 'Demo Router',
                'serial_number' => 'DEMO708A1B2C3',
                'current_firmware' => '7.14.3',
                'upgrade_firmware' => '7.15.1',
                'temperature' => 41,
                'voltage' => 24.1,
            ];

            $interfaces = [
                ['name' => 'ether1-WAN', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'rx-byte' => 42949672960, 'tx-byte' => 182536110080, 'mac-address' => '48:8F:5A:12:34:01', 'mtu' => '1500', 'comment' => 'Uplink Dedicated (Demo)'],
                ['name' => 'ether2-LAN', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'rx-byte' => 142536110080, 'tx-byte' => 38949672960, 'mac-address' => '48:8F:5A:12:34:02', 'mtu' => '1500', 'comment' => 'LAN Gateway (Demo)'],
            ];
        } elseif (!$isConnected) {
            // Real router offline state
            if (empty($resource)) {
                $resource = [
                    'identity' => $router->name,
                    'cpu_load' => null,
                    'cpu_count' => null,
                    'cpu_frequency' => null,
                    'cpu_model' => null,
                    'architecture' => null,
                    'board_name' => null,
                    'version' => null,
                    'uptime' => null,
                    'total_memory' => null,
                    'free_memory' => null,
                    'used_memory' => null,
                    'memory_percent' => null,
                    'total_hdd' => null,
                    'free_hdd' => null,
                    'used_hdd' => null,
                    'hdd_percent' => null,
                    'platform' => 'MikroTik',
                    'model' => null,
                    'serial_number' => null,
                    'current_firmware' => null,
                    'upgrade_firmware' => null,
                    'temperature' => null,
                    'voltage' => null,
                ];
            }
        }

        // Normalisasi data PPPoE
        $activeNames = array_map(
            fn ($a) => is_array($a) ? ($a['name'] ?? '') : ($a->name ?? ''),
            $active
        );
        $users = array_map(function ($u) use ($activeNames) {
            $name = is_array($u) ? ($u['name'] ?? '-') : ($u->name ?? '-');
            return [
                'name' => $name,
                'profile' => is_array($u) ? ($u['profile'] ?? '-') : ($u->profile ?? '-'),
                'disabled' => (is_array($u) ? ($u['disabled'] ?? 'false') : ($u->disabled ?? 'false')) === 'true',
                'address' => is_array($u) ? ($u['address'] ?? '') : ($u->address ?? ''),
                'caller_id' => is_array($u) ? ($u['caller-id'] ?? '') : ($u->{'caller-id'} ?? ''),
                'comment' => is_array($u) ? ($u['comment'] ?? '') : ($u->comment ?? ''),
                'online' => in_array($name, $activeNames),
            ];
        }, $users);

        $profiles = array_map(
            fn ($p) => [
                'name' => is_array($p) ? ($p['name'] ?? '-') : ($p->name ?? '-'),
                'local_address' => is_array($p) ? ($p['local-address'] ?? '') : ($p->{'local-address'} ?? ''),
                'remote_address' => is_array($p) ? ($p['remote-address'] ?? '') : ($p->{'remote-address'} ?? ''),
                'rate_limit' => is_array($p) ? ($p['rate-limit'] ?? '') : ($p->{'rate-limit'} ?? ''),
            ],
            $profiles
        );

        $active = array_map(
            fn ($a) => [
                'name' => is_array($a) ? ($a['name'] ?? '-') : ($a->name ?? '-'),
                'address' => is_array($a) ? ($a['address'] ?? '') : ($a->address ?? ''),
                'uptime' => is_array($a) ? ($a['uptime'] ?? null) : ($a->uptime ?? null),
                'caller_id' => is_array($a) ? ($a['caller-id'] ?? '') : ($a->{'caller-id'} ?? ''),
                'service' => is_array($a) ? ($a['service'] ?? 'pppoe') : ($a->service ?? 'pppoe'),
            ],
            $active
        );

        $interfaces = array_map(
            fn ($i) => [
                'name' => is_array($i) ? ($i['name'] ?? '-') : ($i->name ?? '-'),
                'type' => is_array($i) ? ($i['type'] ?? 'ether') : ($i->type ?? 'ether'),
                'running' => (is_array($i) ? ($i['running'] ?? 'false') : ($i->running ?? 'false')) === 'true',
                'disabled' => (is_array($i) ? ($i['disabled'] ?? 'false') : ($i->disabled ?? 'false')) === 'true',
                'rx_bytes' => (int) (is_array($i) ? ($i['rx-byte'] ?? 0) : ($i->{'rx-byte'} ?? 0)),
                'tx_bytes' => (int) (is_array($i) ? ($i['tx-byte'] ?? 0) : ($i->{'tx-byte'} ?? 0)),
                'mac_address' => is_array($i) ? ($i['mac-address'] ?? '') : ($i->{'mac-address'} ?? ''),
                'mtu' => is_array($i) ? ($i['mtu'] ?? '') : ($i->mtu ?? ''),
                'comment' => is_array($i) ? ($i['comment'] ?? '') : ($i->comment ?? ''),
            ],
            $interfaces
        );

        return Inertia::render('Admin/MikrotikRouterDetail', [
            'router' => [
                'id' => $router->id,
                'name' => $router->name,
                'host' => $router->host,
                'port' => (int) ($router->port ?? 8728),
                'username' => $router->username,
                'is_active' => (bool) ($router->is_active ?? true),
            ],
            'resource' => $resource,
            'interfaces' => $interfaces,
            'users' => $users,
            'profiles' => $profiles,
            'active' => $active,
            'hotspotUsers' => $hotspotUsers,
            'hotspotActive' => $hotspotActive,
            'arpEntries' => $arpEntries ?? [],
            'error' => $error,
        ]);
    }

    /**
     * Generate a MikroTik config backup (.rsc) derived from the local database
     * for the given router: PPPoE profiles, secrets and hotspot users.
     */
    public function backupRouter($id)
    {
        $router = Mikrotik::findOrFail($id);
        $tenantId = $router->tenant_id ?: session('tenant_id');

        $lines = [];
        $lines[] = '# Backup config MikroTik - ' . $router->name;
        $lines[] = "# Router: {$router->host}:{$router->port}";
        $lines[] = '# Dibuat: ' . now()->format('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = '# ===== PPPoE Profiles =====';

        $profiles = \App\Models\Package::where('router_id', $id)
            ->where('tenant_id', $tenantId)
            ->get();
        $seen = [];
        foreach ($profiles as $p) {
            foreach ([$p->profile_normal, $p->profile_isolir, $p->night_profile_name, $p->fup_profile_name] as $prof) {
                if (!$prof || isset($seen[$prof])) continue;
                $seen[$prof] = true;
                $lines[] = '/ppp profile add name="' . $prof . '"';
            }
        }

        $lines[] = '';
        $lines[] = '# PPPoE Secrets (Pelanggan)';

        $secrets = \App\Models\Customer::with('package')->where('router_id', $id)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('pppoe_username')
            ->get();
        foreach ($secrets as $cust) {
            $profile = $cust->package?->profile_normal ?: 'default';
            $pass = $cust->pppoe_password ?: $cust->pppoe_username;
            $lines[] = '/ppp secret add name="' . $cust->pppoe_username . '" password="' . $pass . '" profile="' . $profile . '" service=pppoe' . ' comment="' . addslashes($cust->name) . '"';
            if ($cust->status === 'isolated') {
                $lines[] = '/ppp secret set [find name="' . $cust->pppoe_username . '"] disabled=yes';
            }
        }

        $lines[] = '';
        $lines[] = '# Hotspot Users (Voucher)';

        $vouchers = \App\Models\Voucher::where('tenant_id', $tenantId)->get();
        foreach ($vouchers as $v) {
            $lines[] = '/ip hotspot user add name="' . $v->username . '" password="' . $v->password . '" profile="' . ($v->profile ?: 'default') . '"';
        }

        $content = implode("\n", $lines) . "\n";
        $filename = 'mikrotik_backup_' . $router->name . '_' . now()->format('Y-m-d_His') . '.rsc';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Real-time live interface traffic monitor for RouterOS
     */
    public function interfaceTraffic(Request $request, $id)
    {
        $router = Mikrotik::findOrFail($id);
        $interface = $request->input('interface', 'all');

        $isDemo = (session('tenant_slug') === 'demo') || ($router->tenant?->slug === 'demo');

        if (!$router->is_active && $isDemo) {
            $rxBps = rand(15000000, 45000000);
            $txBps = rand(5000000, 18000000);
            return response()->json([
                'success' => true,
                'interface' => $interface,
                'rx_bps' => $rxBps,
                'tx_bps' => $txBps,
                'rx_formatted' => $this->formatBps($rxBps),
                'tx_formatted' => $this->formatBps($txBps),
                'timestamp' => now()->format('H:i:s'),
            ]);
        }

        $cacheKey = "mik_traffic_{$id}_" . md5($interface);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json([
                    'success' => false,
                    'error' => $mik->getLastError() ?: 'Koneksi ke router gagal.',
                    'rx_bps' => 0,
                    'tx_bps' => 0,
                    'rx_formatted' => '0 bps',
                    'tx_formatted' => '0 bps',
                    'timestamp' => now()->format('H:i:s'),
                ]);
            }

            $rxBps = 0;
            $txBps = 0;

            if ($interface && $interface !== 'all') {
                $monitor = $mik->query('/interface/monitor-traffic', [
                    'interface' => $interface,
                    'once' => '',
                ]);
                if (!empty($monitor) && isset($monitor[0]['rx-bits-per-second'])) {
                    $rxBps = (int) ($monitor[0]['rx-bits-per-second'] ?? 0);
                    $txBps = (int) ($monitor[0]['tx-bits-per-second'] ?? 0);
                }
            } else {
                // Aggregate across main running interfaces (excluding thousands of individual dynamic pppoe sessions)
                $interfaces = $mik->getInterfaces();
                $mainInterfaces = array_filter($interfaces, function ($iface) {
                    $name = $iface['name'] ?? '';
                    $type = $iface['type'] ?? '';
                    $running = ($iface['running'] ?? 'false') === 'true';
                    $disabled = ($iface['disabled'] ?? 'false') === 'true';
                    // Filter out dynamic pppoe client sub-interfaces from 'all' aggregate
                    $isDynamicClient = str_starts_with($name, '<pppoe-') || $type === 'pppoe-in';
                    return $running && !$disabled && $name && !$isDynamicClient;
                });

                // Fallback to top 10 running if all filtered
                $targetInterfaces = !empty($mainInterfaces) ? $mainInterfaces : array_slice(array_filter($interfaces, fn($i) => ($i['running'] ?? 'false') === 'true'), 0, 10);

                foreach ($targetInterfaces as $iface) {
                    $ifName = $iface['name'] ?? '';
                    if ($ifName) {
                        try {
                            $m = $mik->query('/interface/monitor-traffic', [
                                'interface' => $ifName,
                                'once' => '',
                            ]);
                            if (!empty($m) && isset($m[0]['rx-bits-per-second'])) {
                                $rxBps += (int) ($m[0]['rx-bits-per-second'] ?? 0);
                                $txBps += (int) ($m[0]['tx-bits-per-second'] ?? 0);
                            }
                        } catch (\Throwable $e) {}
                    }
                }
            }

            $payload = [
                'success' => true,
                'interface' => $interface,
                'rx_bps' => $rxBps,
                'tx_bps' => $txBps,
                'rx_formatted' => $this->formatBps($rxBps),
                'tx_formatted' => $this->formatBps($txBps),
                'timestamp' => now()->format('H:i:s'),
            ];

            Cache::put($cacheKey, $payload, 2);

            return response()->json($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'rx_bps' => 0,
                'tx_bps' => 0,
                'rx_formatted' => '0 bps',
                'tx_formatted' => '0 bps',
                'timestamp' => now()->format('H:i:s'),
            ]);
        }
    }

    private function formatBps(int $bps): string
    {
        if ($bps >= 1000000000) {
            return round($bps / 1000000000, 2) . ' Gbps';
        }
        if ($bps >= 1000000) {
            return round($bps / 1000000, 2) . ' Mbps';
        }
        if ($bps >= 1000) {
            return round($bps / 1000, 2) . ' Kbps';
        }
        return $bps . ' bps';
    }
}
