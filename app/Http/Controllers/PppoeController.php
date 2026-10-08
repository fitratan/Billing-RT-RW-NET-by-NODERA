<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Onu;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PppoeController extends Controller
{
    public function index(Request $request)
    {
        @set_time_limit(25);
        $startTime = microtime(true);
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $routers  = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('is_active', true)->orderBy('name')->get();

        // Default ke router pertama yang aktif untuk respons instan (<100ms)
        $defaultRouterId = $routers->first()?->id ? (string) $routers->first()->id : null;
        $routerId = $request->get('router_id', $defaultRouterId);

        $users       = [];
        $active      = [];
        $inactive    = [];
        $ifaceByName = [];
        $errors      = [];

        if ($routerId === 'all') {
            $targetRouters = $routers;
        } elseif (!empty($routerId)) {
            $targetRouters = $routers->where('id', (int) $routerId);
        } else {
            $targetRouters = collect();
        }

        $forceRefresh = $request->boolean('refresh') || $request->has('refresh');

        foreach ($targetRouters as $router) {
            if ((microtime(true) - $startTime) > 12.0) {
                $errors[] = "Query timeout untuk sisa router";
                break;
            }
            try {
                $cacheKey = "pppoe_router_cache_{$router->id}";
                $routerData = $forceRefresh ? null : \Illuminate\Support\Facades\Cache::get($cacheKey);

                if (!$routerData) {
                    $mik = new MikrotikService($router);
                    if (!$mik->isConnected()) {
                        $errors[] = "Tidak dapat terhubung ke {$router->name}" . ($mik->getLastError() ? " ({$mik->getLastError()})" : '');
                        continue;
                    }

                    // 1. Ambil Secrets dengan proplist ringkas (1 query hemat)
                    $rUsers = $mik->query('/ppp/secret/print', [
                        '.proplist' => 'name,password,profile,disabled,last-logged-out,comment,remote-address,service',
                    ]);
                    if (!is_array($rUsers)) $rUsers = [];

                    // 2. Ambil Active Sessions dengan proplist ringkas
                    $rActive = $mik->query('/ppp/active/print', [
                        '.proplist' => 'name,service,caller-id,address,uptime,session-id,radius',
                    ]);
                    if (!is_array($rActive)) $rActive = [];

                    // 3. Hitung Inactive di PHP tanpa re-query ulang ke RouterOS
                    $activeNames = array_column($rActive, 'name');
                    $activeNamesMap = array_flip(array_filter($activeNames));
                    $rInactive = array_values(array_filter($rUsers, function ($s) use ($activeNamesMap) {
                        $uname = $s['name'] ?? '';
                        return !isset($activeNamesMap[$uname]) && ($s['disabled'] ?? 'false') !== 'true';
                    }));

                    // 4. Query interface bandwidth ringkas
                    $rIfaces = [];
                    try {
                        $ifaces = $mik->query('/interface/print', [
                            '.proplist' => 'name,rx-byte,tx-byte',
                        ]);
                        if (is_array($ifaces)) {
                            foreach ($ifaces as $iface) {
                                $iname = $iface['name'] ?? '';
                                if ($iname) {
                                    $rIfaces[$iname] = $iface;
                                    $rIfaces[strtolower(trim($iname))] = $iface;
                                    $cleanIface = trim($iname, '<> ');
                                    $rIfaces[$cleanIface] = $iface;
                                    $rIfaces[strtolower($cleanIface)] = $iface;
                                    if (str_starts_with(strtolower($cleanIface), 'pppoe-')) {
                                        $subName = substr($cleanIface, 6);
                                        $rIfaces[$subName] = $iface;
                                        $rIfaces[strtolower($subName)] = $iface;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {}

                    $routerData = [
                        'users' => $rUsers,
                        'active' => $rActive,
                        'inactive' => $rInactive,
                        'ifaces' => $rIfaces,
                    ];

                    // Cache data selama 20 detik untuk performa super cepat & mencegah network congestion
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $routerData, 20);
                }

                $rUsers = $routerData['users'] ?? [];
                $rActive = $routerData['active'] ?? [];
                $rInactive = $routerData['inactive'] ?? [];
                if (!empty($routerData['ifaces'])) {
                    $ifaceByName = array_merge($ifaceByName, $routerData['ifaces']);
                }

                // Tag masing-masing record dengan router_id & router_name
                foreach ($rUsers as &$u) {
                    $u['router_id'] = $router->id;
                    $u['router_name'] = $router->name;
                }
                unset($u);

                foreach ($rActive as &$a) {
                    $a['router_id'] = $router->id;
                    $a['router_name'] = $router->name;
                }
                unset($a);

                foreach ($rInactive as &$inact) {
                    $inact['router_id'] = $router->id;
                    $inact['router_name'] = $router->name;
                }
                unset($inact);

                $users = array_merge($users, $rUsers);
                $active = array_merge($active, $rActive);
                $inactive = array_merge($inactive, $rInactive);
            } catch (\Throwable $e) {
                $errors[] = "Error {$router->name}: " . $e->getMessage();
                Log::error("PPPoE error for {$router->name}: " . $e->getMessage());
            }
        }

        $error = !empty($errors) ? implode(' | ', $errors) : null;

        // Load customers & their linked ONUs dengan caching 45s untuk efisiensi polling realtime
        $cacheKeyMeta = "pppoe_meta_map_" . ($tenantId ?? 'all');
        $metaMaps = $forceRefresh ? null : \Illuminate\Support\Facades\Cache::get($cacheKeyMeta);

        if (!$metaMaps) {
            $customers = Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->select(['id', 'name', 'phone', 'pppoe_username', 'tenant_id'])
                ->with(['onu' => fn ($q) => $q->select(['id', 'customer_id', 'status', 'rx_power', 'tx_power', 'pon_port', 'name', 'olt_id', 'serial_number'])])
                ->get();
            $customerMap = [];
            foreach ($customers as $c) {
                $onu = $c->onu;
                $data = [
                    'customer_id'   => $c->id,
                    'customer_name' => $c->name,
                    'phone'         => $c->phone,
                    'onu_status'    => $onu?->status,
                    'onu_rx_power'  => $onu?->rx_power ? (float) $onu->rx_power : null,
                    'onu_tx_power'  => $onu?->tx_power ? (float) $onu->tx_power : null,
                    'onu_pon'       => $onu?->pon_port,
                    'onu_name'      => $onu?->name,
                    'olt_name'      => null,
                    'onu_serial'    => $onu?->serial_number,
                ];

                if (!empty($c->pppoe_username)) {
                    $customerMap[strtolower(trim($c->pppoe_username))] = $data;
                }
                if (!empty($c->name)) {
                    $customerMap[strtolower(trim($c->name))] = $data;
                }
            }

            // Also check if any ONU name matches PPPoE username directly
            $unlinkedOnus = Onu::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->with('olt')->whereNull('customer_id')->get();
            $onuDirectMap = [];
            foreach ($unlinkedOnus as $o) {
                if (!empty($o->name)) {
                    $onuDirectMap[strtolower(trim($o->name))] = [
                        'customer_id'   => null,
                        'customer_name' => null,
                        'phone'         => null,
                        'onu_status'    => $o->status,
                        'onu_rx_power'  => $o->rx_power ? (float) $o->rx_power : null,
                        'onu_tx_power'  => $o->tx_power ? (float) $o->tx_power : null,
                        'onu_pon'       => $o->pon_port,
                        'onu_name'      => $o->name,
                        'olt_name'      => $o->olt?->name,
                        'onu_serial'    => $o->serial_number,
                    ];
                }
            }

            $metaMaps = [
                'customers' => $customerMap,
                'onus'      => $onuDirectMap,
            ];
            \Illuminate\Support\Facades\Cache::put($cacheKeyMeta, $metaMaps, 45);
        }

        $customerMap = $metaMaps['customers'] ?? [];
        $onuDirectMap = $metaMaps['onus'] ?? [];

        $resolveMeta = function (string $username) use ($customerMap, $onuDirectMap) {
            $key = strtolower(trim($username));
            return $customerMap[$key] ?? $onuDirectMap[$key] ?? null;
        };

        $resolveTraffic = function (string $username) use ($ifaceByName) {
            $ifaceRow = $ifaceByName[$username] ?? null;
            if (!$ifaceRow && !empty($ifaceByName)) {
                foreach ($ifaceByName as $iname => $row) {
                    if (stripos($iname, $username) !== false) {
                        $ifaceRow = $row;
                        break;
                    }
                }
            }

            $rx = $ifaceRow ? (int)($ifaceRow['rx-byte'] ?? 0) : 0;
            $tx = $ifaceRow ? (int)($ifaceRow['tx-byte'] ?? 0) : 0;
            $total = $rx + $tx;

            return [
                'bytes_in'    => $rx,
                'bytes_out'   => $tx,
                'total_bytes' => $total,
            ];
        };

        return Inertia::render('Admin/Pppoe', [
            'routerId' => $routerId,
            'error'    => $error,
            'routers'  => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'users'    => collect($users)->map(function ($u) use ($resolveMeta, $resolveTraffic) {
                $username = $u['name'] ?? '';
                $meta     = $resolveMeta($username);
                $traffic  = $resolveTraffic($username);

                return [
                    'name'            => $username,
                    'router_id'       => $u['router_id'] ?? null,
                    'router_name'     => $u['router_name'] ?? null,
                    'customer_name'   => $meta['customer_name'] ?? null,
                    'customer_id'     => $meta['customer_id'] ?? null,
                    'phone'           => $meta['phone'] ?? null,
                    'onu_status'      => $meta['onu_status'] ?? null,
                    'onu_rx_power'    => $meta['onu_rx_power'] ?? null,
                    'onu_pon'         => $meta['onu_pon'] ?? null,
                    'onu_name'        => $meta['onu_name'] ?? null,
                    'olt_name'        => $meta['olt_name'] ?? null,
                    'password'        => $u['password'] ?? '',
                    'profile'         => $u['profile'] ?? '',
                    'disabled'        => ($u['disabled'] ?? '') === 'true',
                    'last_logged_out' => $u['last-logged-out'] ?? null,
                    'bytes_in'        => $traffic['bytes_in'],
                    'bytes_out'       => $traffic['bytes_out'],
                    'total_bytes'     => $traffic['total_bytes'],
                ];
            })->values(),
            'active' => collect($active)->map(function ($a) use ($resolveMeta, $resolveTraffic) {
                $username = $a['name'] ?? '';
                $meta     = $resolveMeta($username);
                $traffic  = $resolveTraffic($username);

                return [
                    'name'          => $username,
                    'router_id'     => $a['router_id'] ?? null,
                    'router_name'   => $a['router_name'] ?? null,
                    'customer_name' => $meta['customer_name'] ?? null,
                    'customer_id'   => $meta['customer_id'] ?? null,
                    'phone'         => $meta['phone'] ?? null,
                    'onu_status'    => $meta['onu_status'] ?? null,
                    'onu_rx_power'  => $meta['onu_rx_power'] ?? null,
                    'onu_pon'       => $meta['onu_pon'] ?? null,
                    'onu_name'      => $meta['onu_name'] ?? null,
                    'olt_name'      => $meta['olt_name'] ?? null,
                    'address'       => $a['address'] ?? '',
                    'uptime'        => $a['uptime'] ?? '',
                    'bytes_in'      => $traffic['bytes_in'],
                    'bytes_out'     => $traffic['bytes_out'],
                    'total_bytes'   => $traffic['total_bytes'],
                ];
            })->values(),
            'inactive' => collect($inactive)->map(function ($i) use ($resolveMeta, $resolveTraffic) {
                $username = $i['name'] ?? '';
                $meta     = $resolveMeta($username);
                $traffic  = $resolveTraffic($username);

                return [
                    'name'            => $username,
                    'router_id'       => $i['router_id'] ?? null,
                    'router_name'     => $i['router_name'] ?? null,
                    'customer_name'   => $meta['customer_name'] ?? null,
                    'customer_id'     => $meta['customer_id'] ?? null,
                    'phone'           => $meta['phone'] ?? null,
                    'onu_status'      => $meta['onu_status'] ?? null,
                    'onu_rx_power'    => $meta['onu_rx_power'] ?? null,
                    'onu_pon'         => $meta['onu_pon'] ?? null,
                    'onu_name'        => $meta['onu_name'] ?? null,
                    'olt_name'        => $meta['olt_name'] ?? null,
                    'profile'         => $i['profile'] ?? '',
                    'last_logged_out' => $i['last-logged-out'] ?? null,
                    'bytes_in'        => $traffic['bytes_in'],
                    'bytes_out'       => $traffic['bytes_out'],
                    'total_bytes'     => $traffic['total_bytes'],
                ];
            })->values(),
        ]);
    }

    /**
     * Kick / Disconnect an active PPPoE session from MikroTik.
     */
    public function kick(Request $request)
    {
        $username = $request->input('username');
        $routerId = $request->input('router_id');

        if (!$username || !$routerId) {
            return back()->with('error', 'Username atau router tidak valid');
        }

        $router = Mikrotik::find($routerId);
        if (!$router) {
            return back()->with('error', 'Router tidak ditemukan');
        }

        $mik = new MikrotikService([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password ?? '',
            'port' => (int) $router->port,
        ]);

        try {
            if (!$mik->isConnected()) {
                return back()->with('error', 'Tidak dapat terhubung ke ' . $router->name);
            }

            $mik->kickPppoeUser($username);

            // Invalidate PPPoE router cache immediately
            \Illuminate\Support\Facades\Cache::forget("pppoe_router_cache_{$router->id}");

            return back()->with('success', "Sesi PPPoE {$username} berhasil diputus (klien akan reconnect otomatis).");
        } catch (\Exception $e) {
            Log::error("Failed to kick PPPoE user {$username}: " . $e->getMessage());
            return back()->with('error', 'Gagal memutus sesi: ' . $e->getMessage());
        }
    }

    /**
     * Delete a PPPoE secret directly from MikroTik.
     */
    public function deleteSecret(Request $request)
    {
        $username = $request->input('username');
        $routerId = $request->input('router_id');

        if (!$username || !$routerId) {
            return back()->with('error', 'Username atau router tidak valid');
        }

        $router = Mikrotik::find($routerId);
        if (!$router) {
            return back()->with('error', 'Router tidak ditemukan');
        }

        $mik = new MikrotikService([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password ?? '',
            'port' => (int) $router->port,
        ]);

        try {
            if (!$mik->isConnected()) {
                return back()->with('error', 'Tidak dapat terhubung ke ' . $router->name);
            }

            $deleted = $mik->deletePppoeSecret($username);
            $mik->kickPppoeUser($username);

            // Invalidate PPPoE router cache & metadata cache immediately
            \Illuminate\Support\Facades\Cache::forget("pppoe_router_cache_{$router->id}");
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($router->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");

            if ($deleted) {
                return back()->with('success', "Secret PPPoE {$username} berhasil dihapus dari router {$router->name}.");
            }

            return back()->with('warning', "Secret PPPoE {$username} tidak ditemukan di router {$router->name}.");
        } catch (\Exception $e) {
            Log::error("Failed to delete PPPoE secret {$username}: " . $e->getMessage());
            return back()->with('error', 'Gagal menghapus secret: ' . $e->getMessage());
        }
    }
}
