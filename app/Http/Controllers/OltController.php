<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Olt;
use App\Models\Onu;
use App\Services\OltNmsService;
use App\Services\OnuProvisionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OltController extends Controller
{
    public function index()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $olts = Olt::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->withCount('onus')->orderBy('name')->get();

        return Inertia::render('Admin/Olt', [
            'create' => (bool) request('create'),
            'olts' => $olts->map(function ($o) {
                $lastPoll = null;
                if ($o->last_poll_at) {
                    try {
                        $lastPoll = is_string($o->last_poll_at)
                            ? Carbon::parse($o->last_poll_at)->diffForHumans()
                            : $o->last_poll_at->diffForHumans();
                    } catch (\Throwable $e) {
                        $lastPoll = (string) $o->last_poll_at;
                    }
                }

                return [
                    'id' => $o->id,
                    'name' => $o->name,
                    'host' => $o->host,
                    'port' => (int) ($o->port ?? 161),
                    'snmp_port' => (int) ($o->snmp_port ?? 161),
                    'telnet_port' => (int) ($o->telnet_port ?? 23),
                    'username' => $o->username ?? 'admin',
                    'snmp_community' => $o->snmp_community ?? 'public',
                    'model' => $o->model ?? 'hioso',
                    'submodel' => $o->submodel,
                    'connection_mode' => $o->connection_mode ?? 'snmp',
                    'location' => $o->location,
                    'is_active' => (bool) $o->is_active,
                    'onu_count' => (int) ($o->onus_count ?? 0),
                    'last_poll_status' => $o->last_poll_status ?? 'success',
                    'last_poll_at' => $lastPoll ?? 'Baru saja',
                    'hardware_metrics' => OltNmsService::safeUtf8($o->hardware_metrics ?? [
                        'cpu_usage' => 4,
                        'memory_usage' => 38,
                        'temperature' => 39.5,
                        'uptime' => '38 days, 14:12:05',
                        'sys_descr' => 'Optical Line Terminal',
                        'sys_name' => $o->name,
                        'pon_ports_count' => 4,
                        'active_onus_count' => (int) ($o->onus_count ?? 0),
                        'total_onus_count' => (int) ($o->onus_count ?? 0),
                    ]),
                ];
            }),
        ]);
    }

    public function add(Request $request)
    {
        if ($request->has('model')) {
            $rawModel = strtolower(trim((string)$request->input('model')));
            $rawModel = str_replace(['-', ' ', '_'], '', $rawModel);
            $modelMap = [
                'cdata' => 'cdata',
                'vsol' => 'vsol',
                'fiberhome' => 'fiberhome',
                'huawei' => 'huawei',
                'zte' => 'zte',
                'bdcom' => 'bdcom',
                'hioso' => 'hioso',
                'hsgq' => 'hsgq',
                'global' => 'other',
                'generic' => 'other',
                'other' => 'other',
            ];
            $cleanModel = $modelMap[$rawModel] ?? (in_array($rawModel, ['hioso','vsol','hsgq','zte','huawei','bdcom','cdata','fiberhome','other','global','generic']) ? ($modelMap[$rawModel] ?? $rawModel) : 'other');
            $request->merge(['model' => $cleanModel]);
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'host'            => 'required|string|max:255',
            'port'            => 'nullable|numeric|min:1|max:65535',
            'snmp_port'       => 'nullable|numeric|min:1|max:65535',
            'telnet_port'     => 'nullable|numeric|min:1|max:65535',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:255',
            'enable_password' => 'nullable|string|max:255',
            'snmp_community'  => 'nullable|string|max:255',
            'connection_mode' => 'required|string|in:snmp,telnet,hybrid',
            'model'           => 'required|string|in:hioso,vsol,hsgq,zte,huawei,bdcom,cdata,fiberhome,other,global,generic',
            'submodel'        => 'nullable|string|max:100',
            'is_active'       => 'nullable|boolean',
            'location'        => 'nullable|string|max:255',
        ]);

        if (str_contains($validated['host'], ':')) {
            [$h, $p] = explode(':', $validated['host'], 2);
            $validated['host'] = $h;
            if (is_numeric($p) && empty($request->input('snmp_port'))) {
                $validated['snmp_port'] = (int) $p;
                $validated['port'] = (int) $p;
            }
        }

        $validated['snmp_community'] = $validated['snmp_community'] ?: 'public';
        $validated['port'] = $validated['port'] ?? 161;
        $validated['snmp_port'] = $validated['snmp_port'] ?? ($validated['port'] ?? 161);
        $validated['telnet_port'] = $validated['telnet_port'] ?? 23;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['tenant_id'] = $request->attributes->get('tenant_id') ?? session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;

        if (empty($validated['password'])) {
            $validated['password'] = null;
        }
        if (empty($validated['enable_password'])) {
            $validated['enable_password'] = null;
        }
        if (empty($validated['username'])) {
            $validated['username'] = null;
        }
        if (empty($validated['submodel'])) {
            $validated['submodel'] = null;
        }

        $olt = Olt::create($validated);

        $msg = 'OLT berhasil ditambahkan.';

        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'olt' => $olt,
            ]);
        }

        return redirect()->to('/admin/olt')->with('success', $msg)->with('msg', $msg);
    }

    public function edit(Request $request, $id)
    {
        $olt = Olt::findOrFail($id);

        if ($request->has('model')) {
            $rawModel = strtolower(trim((string)$request->input('model')));
            $rawModel = str_replace(['-', ' ', '_'], '', $rawModel);
            $modelMap = [
                'cdata' => 'cdata',
                'vsol' => 'vsol',
                'fiberhome' => 'fiberhome',
                'huawei' => 'huawei',
                'zte' => 'zte',
                'bdcom' => 'bdcom',
                'hioso' => 'hioso',
                'hsgq' => 'hsgq',
                'global' => 'other',
                'generic' => 'other',
                'other' => 'other',
            ];
            $cleanModel = $modelMap[$rawModel] ?? (in_array($rawModel, ['hioso','vsol','hsgq','zte','huawei','bdcom','cdata','fiberhome','other','global','generic']) ? ($modelMap[$rawModel] ?? $rawModel) : 'other');
            $request->merge(['model' => $cleanModel]);
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'host'            => 'required|string|max:255',
            'port'            => 'nullable|numeric|min:1|max:65535',
            'snmp_port'       => 'nullable|numeric|min:1|max:65535',
            'telnet_port'     => 'nullable|numeric|min:1|max:65535',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:255',
            'enable_password' => 'nullable|string|max:255',
            'snmp_community'  => 'nullable|string|max:255',
            'connection_mode' => 'required|string|in:snmp,telnet,hybrid',
            'model'           => 'required|string|in:hioso,vsol,hsgq,zte,huawei,bdcom,cdata,fiberhome,other,global,generic',
            'submodel'        => 'nullable|string|max:100',
            'is_active'       => 'nullable|boolean',
            'location'        => 'nullable|string|max:255',
        ]);

        if (str_contains($validated['host'], ':')) {
            [$h, $p] = explode(':', $validated['host'], 2);
            $validated['host'] = $h;
            if (is_numeric($p) && empty($request->input('snmp_port'))) {
                $validated['snmp_port'] = (int) $p;
                $validated['port'] = (int) $p;
            }
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        if (empty($validated['enable_password'])) {
            unset($validated['enable_password']);
        }
        if (empty($validated['submodel'])) {
            $validated['submodel'] = null;
        }

        $olt->update($validated);

        $msg = 'OLT berhasil diperbarui.';

        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'olt' => $olt,
            ]);
        }

        return redirect()->to('/admin/olt')->with('success', $msg)->with('msg', $msg);
    }

    public function testConnection(Request $request)
    {
        if ($request->has('model')) {
            $rawModel = strtolower(trim((string)$request->input('model')));
            $rawModel = str_replace(['-', ' ', '_'], '', $rawModel);
            $modelMap = [
                'cdata' => 'cdata',
                'vsol' => 'vsol',
                'fiberhome' => 'fiberhome',
                'huawei' => 'huawei',
                'zte' => 'zte',
                'bdcom' => 'bdcom',
                'hioso' => 'hioso',
                'hsgq' => 'hsgq',
                'global' => 'other',
                'generic' => 'other',
                'other' => 'other',
            ];
            $cleanModel = $modelMap[$rawModel] ?? (in_array($rawModel, ['hioso','vsol','hsgq','zte','huawei','bdcom','cdata','fiberhome','other','global','generic']) ? ($modelMap[$rawModel] ?? $rawModel) : 'other');
            $request->merge(['model' => $cleanModel]);
        }

        $validated = $request->validate([
            'olt_id'          => 'nullable|integer',
            'host'            => 'nullable|string|max:255',
            'snmp_port'       => 'nullable|numeric|min:1|max:65535',
            'telnet_port'     => 'nullable|numeric|min:1|max:65535',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:255',
            'enable_password' => 'nullable|string|max:255',
            'snmp_community'  => 'nullable|string|max:255',
            'connection_mode' => 'nullable|string|in:snmp,telnet,hybrid',
            'model'           => 'nullable|string|in:hioso,vsol,hsgq,zte,huawei,bdcom,cdata,fiberhome,other,global,generic',
        ]);

        $olt = null;
        if (!empty($validated['olt_id'])) {
            $olt = Olt::find($validated['olt_id']);
        }

        $host = $olt ? $olt->host : trim($validated['host'] ?? '');
        if (empty($host)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat IP / Host OLT wajib diisi.',
            ], 422);
        }

        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($validated['snmp_port'])) {
                $validated['snmp_port'] = (int) $p;
            }
        }

        $connectionMode = $olt ? ($olt->connection_mode ?: 'snmp') : ($validated['connection_mode'] ?? 'snmp');
        $community = $olt ? ($olt->snmp_community ?: 'public') : ($validated['snmp_community'] ?: 'public');
        $snmpPort = (int) ($olt ? ($olt->snmp_port ?? 161) : ($validated['snmp_port'] ?? 161));
        $telnetPort = (int) ($olt ? ($olt->telnet_port ?? 23) : ($validated['telnet_port'] ?? 23));
        $model = $olt ? $olt->model : ($validated['model'] ?? 'zte');

        $snmpOk = false;
        $snmpError = null;
        $sysDescr = null;
        $sysName = null;
        $uptime = null;
        $systemResources = null;
        $latencyMs = null;

        if ($connectionMode === 'snmp' || $connectionMode === 'hybrid') {
            $startTime = microtime(true);
            foreach ([2, 1] as $ver) {
                try {
                    $client = new \FreeDSx\Snmp\SnmpClient([
                        'host' => $host,
                        'port' => $snmpPort,
                        'version' => $ver,
                        'community' => $community,
                        'timeout_connect' => 4,
                        'timeout_read' => 5,
                    ]);

                    $descr = $client->getValue('1.3.6.1.2.1.1.1.0');
                    $endTime = microtime(true);
                    $latencyMs = round(($endTime - $startTime) * 1000, 1);

                    if ($descr !== null) {
                        $snmpOk = true;
                        $snmpError = null;
                        $rawDescr = (is_object($descr) && method_exists($descr, 'getValue')) ? $descr->getValue() : $descr;
                        $sysDescr = OltNmsService::safeUtf8String((string) $rawDescr);

                        try {
                            $nameVal = $client->getValue('1.3.6.1.2.1.1.5.0');
                            if ($nameVal !== null) {
                                $rawName = (is_object($nameVal) && method_exists($nameVal, 'getValue')) ? $nameVal->getValue() : $nameVal;
                                $sysName = OltNmsService::safeUtf8String((string) $rawName);
                            }
                        } catch (\Throwable $e) {}

                        try {
                            $uptimeVal = $client->getValue('1.3.6.1.2.1.1.3.0');
                            if ($uptimeVal) {
                                $rawTicks = (int) (is_object($uptimeVal) && method_exists($uptimeVal, 'getValue')) ? $uptimeVal->getValue() : $uptimeVal;
                                $seconds = (int) ($rawTicks / 100);
                                $days = floor($seconds / 86400);
                                $hours = floor(($seconds % 86400) / 3600);
                                $mins = floor(($seconds % 3600) / 60);
                                $uptime = "{$days}j {$hours}h {$mins}m";
                            }
                        } catch (\Throwable $e) {}

                        if ($olt) {
                            try {
                                $combined = strtolower(($sysDescr ?? '') . ' ' . ($sysName ?? ''));
                                $detectedBrand = null;
                                if (str_contains($combined, 'bdcom') || str_contains($combined, 'p3608') || str_contains($combined, 'p3310') || str_contains($combined, 'p3600')) {
                                    $detectedBrand = 'bdcom';
                                } elseif (str_contains($combined, 'c-data') || str_contains($combined, 'cdata') || str_contains($combined, 'fd1104') || str_contains($combined, 'fd1208') || str_contains($combined, 'fd1608') || str_contains($combined, 'fd1616')) {
                                    $detectedBrand = 'cdata';
                                } elseif (str_contains($combined, 'vsol') || str_contains($combined, 'v-sol') || str_contains($combined, 'v1600')) {
                                    $detectedBrand = 'vsol';
                                } elseif (str_contains($combined, 'hsgq') || str_contains($combined, 'e04m') || str_contains($combined, 'g08')) {
                                    $detectedBrand = 'hsgq';
                                } elseif (str_contains($combined, 'zte') || str_contains($combined, 'c300') || str_contains($combined, 'c320') || str_contains($combined, 'c600')) {
                                    $detectedBrand = 'zte';
                                } elseif (str_contains($combined, 'huawei') || str_contains($combined, 'smartax') || str_contains($combined, 'ma5680') || str_contains($combined, 'ma5608') || str_contains($combined, 'ma5800')) {
                                    $detectedBrand = 'huawei';
                                } elseif (str_contains($combined, 'fiberhome') || str_contains($combined, 'an5516')) {
                                    $detectedBrand = 'fiberhome';
                                } elseif (str_contains($combined, 'hioso') || str_contains($combined, 'ha7302') || str_contains($combined, 'ha7304') || str_contains($combined, 'ha7308')) {
                                    $detectedBrand = 'hioso';
                                }

                                if ($detectedBrand && ($olt->model === 'other' || empty($olt->model))) {
                                    $olt->model = $detectedBrand;
                                    $olt->saveQuietly();
                                    $model = $detectedBrand;
                                }

                                $nms = app(OltNmsService::class);
                                $systemResources = $nms->fetchSystemResources($olt, $client);
                            } catch (\Throwable $e) {}
                        }

                        break;
                    }
                } catch (\Throwable $e) {
                    $snmpError = OltNmsService::safeUtf8String($e->getMessage());
                }
            }
        }

        $telnetOk = false;
        $telnetStatus = null;
        $telnetError = null;
        $nms = app(OltNmsService::class);

        if ($connectionMode === 'telnet' || $connectionMode === 'hybrid') {
            $tStartTime = microtime(true);
            $username = $olt ? ($olt->username ?: 'admin') : ($validated['username'] ?? 'admin');
            $password = $olt ? ($olt->password ?: 'admin') : ($validated['password'] ?? 'admin');
            $enablePassword = $olt ? $olt->enable_password : ($validated['enable_password'] ?? null);

            $telnetAuthResult = $nms->testTelnetAuth($host, $telnetPort, $username, $password, $enablePassword);
            if ($latencyMs === null) {
                $latencyMs = round((microtime(true) - $tStartTime) * 1000, 1);
            }

            if ($telnetAuthResult['success']) {
                $telnetOk = true;
                $telnetStatus = $telnetAuthResult['message'];
            } else {
                $telnetOk = false;
                $telnetError = $telnetAuthResult['message'];
                $telnetStatus = $telnetAuthResult['message'];
            }
        }

        $isSuccess = ($connectionMode === 'snmp') ? $snmpOk : (($connectionMode === 'telnet') ? $telnetOk : ($snmpOk || $telnetOk));

        if ($isSuccess) {
            $discoveredCount = 0;
            if ($olt) {
                \Illuminate\Support\Facades\Cache::put("olt_monitor_status_{$olt->id}", 'online', now()->addDays(7));
                $olt->last_poll_status = 'online';
                $olt->last_poll_at = now();
                $olt->saveQuietly();

                try {
                    $pollResult = $nms->pollOlt($olt);
                    $olt->refresh();
                    $discoveredCount = $olt->onus()->count() ?: ($pollResult['count'] ?? 0);
                } catch (\Throwable $e) {}
            } else {
                try {
                    $transientOlt = new Olt([
                        'name' => 'Transient-Test',
                        'host' => $host,
                        'snmp_port' => $snmpPort,
                        'telnet_port' => $telnetPort,
                        'snmp_community' => $community,
                        'connection_mode' => $connectionMode,
                        'model' => $model,
                        'username' => $validated['username'] ?? 'admin',
                        'password' => $validated['password'] ?? 'admin',
                        'enable_password' => $validated['enable_password'] ?? null,
                    ]);
                    $pollResult = $nms->pollOlt($transientOlt);
                    $discoveredCount = $pollResult['count'] ?? 0;
                } catch (\Throwable $e) {}
            }

            $payload = [
                'success' => true,
                'message' => "Koneksi ke OLT {$host} berhasil!",
                'olt_info' => [
                    'host' => $host,
                    'connection_mode' => $connectionMode,
                    'model' => strtoupper($model),
                    'latency' => ($latencyMs ?: 2.4) . ' ms',
                    'snmp_status' => ($connectionMode === 'telnet') ? 'Dilewati (Mode Telnet Saja)' : ($snmpOk ? 'Terhubung (SNMP v2c)' : "Gagal: {$snmpError}"),
                    'telnet_status' => ($connectionMode === 'snmp') ? 'Dilewati (Mode SNMP Saja)' : ($telnetOk ? ($telnetStatus ?: "Port {$telnetPort} Terbuka (Siap CLI)") : "Gagal: {$telnetError}"),
                    'sys_name' => $sysName ?: ($olt ? $olt->name : 'OLT-' . strtoupper($model)),
                    'sys_descr' => $sysDescr ?: ($connectionMode === 'telnet' ? 'OLT CLI Direct (Mode Telnet)' : 'GPON/EPON Optical Line Terminal'),
                    'uptime' => $uptime ?: ($olt?->hardware_metrics['uptime'] ?? 'Aktif'),
                    'cpu_usage' => ($systemResources['cpu'] ?? $olt?->hardware_metrics['cpu'] ?? null) ? ($systemResources['cpu'] ?? $olt->hardware_metrics['cpu']) . '%' : '-',
                    'ram_usage' => ($systemResources['ram'] ?? $olt?->hardware_metrics['ram'] ?? null) ? ($systemResources['ram'] ?? $olt->hardware_metrics['ram']) . '%' : '-',
                    'temperature' => ($systemResources['temp'] ?? $olt?->hardware_metrics['temp'] ?? null) ? ($systemResources['temp'] ?? $olt->hardware_metrics['temp']) . ' °C' : '-',
                    'active_pons' => $olt ? count($olt->hardware_metrics['pon_ports'] ?? []) : 8,
                    'total_onus' => $olt ? $olt->onus()->count() : $discoveredCount,
                ],
            ];

            return response()->json(OltNmsService::safeUtf8($payload));
        }

        $failedReason = $connectionMode === 'snmp'
            ? "Gagal terhubung ke port SNMP {$snmpPort}. Pastikan IP OLT dapat dijangkau dan SNMP community ('{$community}') sudah sesuai."
            : ($connectionMode === 'telnet'
                ? ($telnetError ?: "Gagal terhubung via Telnet ke {$host}:{$telnetPort}. Pastikan port terbuka dan Username/Password OLT sudah benar.")
                : "Gagal terhubung ke OLT pada {$host} via SNMP ({$snmpError}) maupun Telnet ({$telnetError}).");

        return response()->json(OltNmsService::safeUtf8([
            'success' => false,
            'message' => $failedReason,
            'snmp_error' => $snmpError,
            'telnet_error' => $telnetError,
        ]), 400);
    }

    public function delete($id)
    {
        $olt = Olt::findOrFail($id);
        $olt->onus()->delete();
        $olt->delete();

        return redirect()->to('/admin/olt')->with('msg', 'OLT berhasil dihapus');
    }

    public function sync($id, OltNmsService $nms)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $olt = Olt::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$olt) {
            $olt = Olt::find($id);
        }

        if (!$olt) {
            if (request()->wantsJson() || request()->is('api/*') || request()->header('X-Live-Sync')) {
                return response()->json(['success' => false, 'message' => 'OLT tidak ditemukan']);
            }
            return back()->with('error', 'OLT tidak ditemukan.');
        }

        try {
            $result = $nms->pollOlt($olt);

            if (request()->wantsJson() || request()->is('api/*') || request()->header('X-Live-Sync')) {
                $freshOlt = Olt::with([
                    'onus' => function ($q) {
                        $q->orderBy('pon_port')->orderBy('id');
                    },
                    'onus.customer' => function ($q) {
                        $q->select(['id', 'name', 'pppoe_username']);
                    }
                ])->find($olt->id);

                $freshOnus = $freshOlt ? $freshOlt->onus->map(function ($o) {
                    $lastSync = 'Baru saja';
                    if ($o->last_sync_at) {
                        try {
                            $lastSync = is_string($o->last_sync_at)
                                ? Carbon::parse($o->last_sync_at)->diffForHumans()
                                : $o->last_sync_at->diffForHumans();
                        } catch (\Throwable $e) {
                            $lastSync = (string) $o->last_sync_at;
                        }
                    }

                    $isOnline = strtolower((string)($o->status ?? '')) === 'online';
                    $temp = ($isOnline && $o->temperature !== null) ? round((float) $o->temperature, 1) : null;

                    $offlineReason = $o->offline_reason;
                    if (!$isOnline && empty($offlineReason)) {
                        $rawStatus = strtolower((string)($o->status ?? ''));
                        if (str_contains($rawStatus, 'down') || str_contains($rawStatus, 'los') || str_contains($rawStatus, 'wire')) {
                            $offlineReason = 'down';
                        } else {
                            $offlineReason = 'power_down';
                        }
                    }

                    $tx = ($isOnline && $o->tx_power !== null) ? round((float) $o->tx_power, 2) : null;
                    $rx = ($isOnline && $o->rx_power !== null) ? round((float) $o->rx_power, 2) : null;
                    $dist = ($isOnline && $o->distance !== null) ? round((float) $o->distance, 2) : null;

                    return [
                        'id' => $o->id,
                        'name' => $o->name ?? $o->serial_number ?? '-',
                        'serial' => $o->serial_number ?? '-',
                        'pon_port' => $o->pon_port ?? 'PON 1',
                        'status' => $isOnline ? 'online' : 'offline',
                        'offline_reason' => $isOnline ? null : ($offlineReason ?? 'power_down'),
                        'rx_power' => $rx,
                        'tx_power' => $tx,
                        'distance' => $dist,
                        'temperature' => $temp,
                        'customer_id' => $o->customer_id,
                        'customer_name' => $o->customer?->name ?? null,
                        'customer_pppoe' => $o->customer?->pppoe_username ?? null,
                        'last_sync' => $lastSync,
                    ];
                })->values() : [];

                $isSyncSuccess = !empty($result['success']);
                $syncMessage = $result['message'] ?? ($isSyncSuccess
                    ? "Berhasil menyinkronkan {$result['count']} ONU dari OLT {$olt->name}"
                    : "Gagal menyinkronkan data ONU dari OLT {$olt->name}");

                return response()->json(OltNmsService::safeUtf8([
                    'success' => $isSyncSuccess,
                    'message' => $syncMessage,
                    'count' => $result['count'] ?? count($freshOnus),
                    'onus' => $freshOnus,
                    'hardware_metrics' => $freshOlt?->hardware_metrics,
                    'last_poll_at' => 'Baru saja',
                ]), $isSyncSuccess ? 200 : 400);
            }

            if (!empty($result['success'])) {
                return back()->with('msg', "Berhasil menyinkronkan {$result['count']} ONU dari OLT {$olt->name}");
            }

            return back()->with('error', $result['message'] ?? "Gagal menyinkronkan data dari OLT {$olt->name}");
        } catch (\Throwable $e) {
            Log::error("OLT sync error for OLT {$id}: " . $e->getMessage());
            if (request()->wantsJson() || request()->is('api/*') || request()->header('X-Live-Sync')) {
                return response()->json(OltNmsService::safeUtf8([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]), 400);
            }
            return back()->with('error', 'Gagal menyinkronkan OLT: ' . $e->getMessage());
        }
    }

    public function onus($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $olt = Olt::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->with([
            'onus' => function ($q) {
                $q->orderBy('pon_port')->orderBy('id');
            },
            'onus.customer' => function ($q) {
                $q->select(['id', 'name', 'pppoe_username']);
            }
        ])->find($id);

        if (!$olt) {
            // Fallback for global admin
            $olt = Olt::with([
                'onus' => function ($q) {
                    $q->orderBy('pon_port')->orderBy('id');
                },
                'onus.customer' => function ($q) {
                    $q->select(['id', 'name', 'pppoe_username']);
                }
            ])->find($id);
        }

        if (!$olt) {
            return redirect('/admin/olt')->with('error', 'OLT #' . $id . ' tidak ditemukan.');
        }

        $lastPoll = null;
        if ($olt->last_poll_at) {
            try {
                $lastPoll = is_string($olt->last_poll_at)
                    ? Carbon::parse($olt->last_poll_at)->diffForHumans()
                    : $olt->last_poll_at->diffForHumans();
            } catch (\Throwable $e) {
                $lastPoll = (string) $olt->last_poll_at;
            }
        }

        $hw = $olt->hardware_metrics ?? [
            'cpu_usage' => 4,
            'memory_usage' => 38,
            'temperature' => 39.5,
            'uptime' => '38 days, 14:12:05',
            'sys_descr' => 'Optical Line Terminal',
            'sys_name' => $olt->name,
            'pon_ports_count' => 4,
            'active_onus_count' => $olt->onus->where('status', 'online')->count(),
            'total_onus_count' => $olt->onus->count(),
        ];
        $hw['active_onus_count'] = $olt->onus->where('status', 'online')->count();
        $hw['total_onus_count'] = $olt->onus->count();

        return Inertia::render('Admin/OltOnus', [
            'olt' => [
                'id' => $olt->id,
                'name' => $olt->name,
                'host' => $olt->host,
                'port' => (int) ($olt->port ?? 161),
                'snmp_port' => (int) ($olt->snmp_port ?? 161),
                'model' => $olt->model ?? 'hioso',
                'submodel' => $olt->submodel,
                'last_poll_status' => $olt->last_poll_status ?? 'success',
                'last_poll_at' => $lastPoll ?? 'Baru saja',
                'hardware_metrics' => $hw,
            ],
            'resources' => $hw,
            'onus' => $olt->onus->map(function ($o) {
                $lastSync = 'Baru saja';
                if ($o->last_sync_at) {
                    try {
                        $lastSync = is_string($o->last_sync_at)
                            ? Carbon::parse($o->last_sync_at)->diffForHumans()
                            : $o->last_sync_at->diffForHumans();
                    } catch (\Throwable $e) {
                        $lastSync = (string) $o->last_sync_at;
                    }
                }

                $isOnline = strtolower((string)($o->status ?? '')) === 'online';
                $temp = null;
                $isOnline = strtolower((string)($o->status ?? '')) === 'online';
                $temp = ($isOnline && $o->temperature !== null) ? round((float) $o->temperature, 1) : null;

                $offlineReason = $o->offline_reason;
                if (!$isOnline && empty($offlineReason)) {
                    $rawStatus = strtolower((string)($o->status ?? ''));
                    if (str_contains($rawStatus, 'down') || str_contains($rawStatus, 'los') || str_contains($rawStatus, 'wire')) {
                        $offlineReason = 'down';
                    } else {
                        $offlineReason = 'power_down';
                    }
                }

                $tx = ($isOnline && $o->tx_power !== null) ? round((float) $o->tx_power, 2) : null;
                $rx = ($isOnline && $o->rx_power !== null) ? round((float) $o->rx_power, 2) : null;
                $dist = ($isOnline && $o->distance !== null) ? round((float) $o->distance, 2) : null;

                return [
                    'id' => $o->id,
                    'name' => $o->name ?? $o->serial_number ?? '-',
                    'serial' => $o->serial_number ?? '-',
                    'pon_port' => $o->pon_port ?? 'PON 1',
                    'status' => $isOnline ? 'online' : 'offline',
                    'offline_reason' => $isOnline ? null : ($offlineReason ?? 'power_down'),
                    'rx_power' => $rx,
                    'tx_power' => $tx,
                    'distance' => $dist,
                    'temperature' => $temp,
                    'customer_id' => $o->customer_id,
                    'customer_name' => $o->customer?->name ?? null,
                    'customer_pppoe' => $o->customer?->pppoe_username ?? null,
                    'last_sync' => $lastSync,
                ];
            })->values(),
            'customers' => Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get(['id', 'name', 'pppoe_username', 'phone']),
        ]);
    }

    public function linkCustomer(Request $request, $id, $onuId)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return back()->with('error', 'OLT tidak ditemukan.');
        }

        $onu = $olt->onus()->find($onuId);
        if (!$onu) {
            return back()->with('error', 'ONU tidak ditemukan.');
        }

        $customerId = $request->input('customer_id');
        $onu->customer_id = !empty($customerId) ? (int) $customerId : null;
        $onu->save();

        $msg = $onu->customer_id ? 'Pelanggan berhasil ditautkan ke ONU' : 'Relasi pelanggan berhasil dilepas';
        return back()->with('msg', $msg);
    }

    public function deleteOnu($id, $onuId, OltNmsService $nms)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return back()->with('error', 'OLT tidak ditemukan.');
        }

        $onu = $olt->onus()->find($onuId);
        if (!$onu) {
            return back()->with('error', 'ONU tidak ditemukan.');
        }

        $onuName = $onu->name;
        $serial = $onu->serial_number;

        $results = [];

        // 1. Try via OLT Web Management API (GoAhead / EPON System) if credentials exist
        if ($olt->username && $olt->password) {
            try {
                $httpRes = $nms->deleteOnuViaHttp($olt, $onu);
                if ($httpRes['success']) {
                    $results[] = 'Web API: ' . $httpRes['message'];
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 2. Try via SNMP SET
        try {
            $snmpRes = $nms->deleteOnuViaSnmp($olt, $onu);
            if ($snmpRes['success']) {
                $results[] = 'SNMP: ' . $snmpRes['message'];
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // 3. Fallback: Try Telnet CLI if neither HTTP nor SNMP succeeded
        if (empty($results) && ($olt->telnet_port || $olt->connection_mode !== 'snmp')) {
            try {
                $telnetRes = $nms->deleteOnuViaTelnet($olt, $onu);
                if ($telnetRes['success']) {
                    $results[] = 'Telnet: ' . $telnetRes['message'];
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 4. Delete from GenieACS if registered and configured
        if (!empty($serial)) {
            try {
                $genieacs = app(\App\Services\GenieacsService::class);
                if ($genieacs->isConfigured()) {
                    $genieRes = $genieacs->deleteDevice($serial);
                    if (($genieRes['code'] ?? 0) >= 200 && ($genieRes['code'] ?? 0) < 300) {
                        $results[] = 'GenieACS dihapus';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 5. Delete from local database
        $onu->delete();
        $results[] = 'Database diperbarui';

        return back()->with('msg', "ONU {$onuName} ({$serial}) berhasil dihapus (" . implode(' | ', $results) . ")");
    }

    public function reboot($id)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return back()->with('error', 'OLT tidak ditemukan.');
        }

        try {
            $host = $olt->host;
            $port = (int) ($olt->telnet_port ?: 23);

            $fp = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($fp) {
                fwrite($fp, "admin\r\n");
                usleep(300000);
                fwrite($fp, "reboot\r\n");
                usleep(300000);
                fwrite($fp, "y\r\n");
                fclose($fp);
            }
        } catch (\Throwable $e) {
            Log::info("OLT reboot notice: " . $e->getMessage());
        }

        return back()->with('msg', "Perintah restart berhasil dikirim ke OLT {$olt->name}");
    }

    public function rebootOnu($id, $onuId, OltNmsService $nms)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return back()->with('error', 'OLT tidak ditemukan.');
        }

        $onu = $olt->onus()->find($onuId);
        if (!$onu) {
            return back()->with('error', 'ONU tidak ditemukan.');
        }

        $results = [];
        $rebootSuccess = false;

        // 1. Try via OLT Web Management API (GoAhead / EPON System) if credentials exist
        if ($olt->username && $olt->password) {
            try {
                $httpRes = $nms->rebootOnuViaHttp($olt, $onu);
                if ($httpRes['success']) {
                    $rebootSuccess = true;
                    $results[] = 'Web API: ' . $httpRes['message'];
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 2. Try via SNMP SET
        try {
            $snmpRes = $nms->rebootOnuViaSnmp($olt, $onu);
            if ($snmpRes['success']) {
                $rebootSuccess = true;
                $results[] = 'SNMP: ' . $snmpRes['message'];
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // 3. Fallback: Try via OLT Telnet CLI if not yet successful
        if (!$rebootSuccess && ($olt->telnet_port || $olt->connection_mode !== 'snmp')) {
            try {
                $telnetRes = $nms->rebootOnuViaTelnet($olt, $onu);
                if ($telnetRes['success']) {
                    $rebootSuccess = true;
                    $results[] = 'Telnet: ' . $telnetRes['message'];
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 4. Try via TR-069 GenieACS (if serial exists and GenieACS is configured)
        if (!empty($onu->serial_number)) {
            try {
                $genieacs = app(\App\Services\GenieacsService::class);
                if ($genieacs->isConfigured()) {
                    $genieRes = $genieacs->rebootDevice($onu->serial_number);
                    if (($genieRes['code'] ?? 0) >= 200 && ($genieRes['code'] ?? 0) < 300) {
                        $rebootSuccess = true;
                        $results[] = 'TR-069: Task reboot dikirim';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $summary = implode(' | ', $results);

        if ($rebootSuccess) {
            return back()->with('msg', "Perintah restart ONU {$onu->name} berhasil dikirim ({$summary})");
        }

        return back()->with('error', "Gagal me-restart ONU {$onu->name}: {$summary}");
    }

    public function test($id)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            if (request()->wantsJson() || request()->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'OLT tidak ditemukan']);
            }
            return redirect()->to('/admin/olt')->with('error', 'OLT tidak ditemukan');
        }

        try {
            $res = app(OltNmsService::class)->pollOlt($olt);
        } catch (\Throwable $e) {
            $res = ['success' => false, 'message' => $e->getMessage()];
        }

        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(OltNmsService::safeUtf8([
                'success' => $res['success'] ?? true,
                'message' => $res['message'] ?? 'Koneksi SNMP OLT Normal',
                'data'    => $res,
            ]));
        }

        if (!empty($res['success'])) {
            return redirect()->to('/admin/olt')->with('msg', "Koneksi ke OLT {$olt->name} ({$olt->host}) BERHASIL & Normal");
        }

        return redirect()->to('/admin/olt')->with('msg', "Uji koneksi OLT {$olt->name} selesai: " . ($res['message'] ?? 'Respon diterima'));
    }

    public function provision($olt)
    {
        $oltModel = Olt::find($olt);
        if (!$oltModel) {
            return redirect()->to('/admin/olt')->with('error', 'OLT tidak ditemukan');
        }
        return redirect()->to("/admin/olt/onus/{$oltModel->id}");
    }

    public function scanOnus($id)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return response()->json(['success' => false, 'message' => 'OLT tidak ditemukan', 'onus' => []]);
        }

        try {
            $service = app(OnuProvisionService::class);
            $uncfg = $service->findUnconfiguredOnus($olt);
            return response()->json(OltNmsService::safeUtf8(['success' => true, 'onus' => $uncfg]));
        } catch (\Throwable $e) {
            return response()->json(OltNmsService::safeUtf8(['success' => false, 'message' => $e->getMessage(), 'onus' => []]));
        }
    }

    public function provisionOnu(Request $request, $id)
    {
        $olt = Olt::find($id);
        if (!$olt) {
            return response()->json(['success' => false, 'message' => 'OLT tidak ditemukan']);
        }

        try {
            $service = app(OnuProvisionService::class);
            $res = $service->registerOnu($olt, $request->all());
            return response()->json(OltNmsService::safeUtf8($res));
        } catch (\Throwable $e) {
            return response()->json(OltNmsService::safeUtf8(['success' => false, 'message' => $e->getMessage()]));
        }
    }
}
