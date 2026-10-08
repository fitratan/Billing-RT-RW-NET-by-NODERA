<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Onu;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ArpController extends Controller
{
    /**
     * Tampilkan halaman utama ARP Static & Host Monitoring.
     */
    public function index(Request $request, $mode = null)
    {
        @set_time_limit(25);
        $startTime = microtime(true);
        $isTechnician = $request->is('teknisi/*') || $request->is('technician/*') || $mode === 'technician';
        $isCollector = $request->is('kolektor/*') || $request->is('collector/*') || $mode === 'collector';
        $pageMode = $isTechnician ? 'technician' : ($isCollector ? 'collector' : 'admin');

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $routers  = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $defaultRouterId = $routers->first()?->id ? (string) $routers->first()->id : null;
        $routerId = $request->get('router_id', $defaultRouterId);

        $arpEntries = [];
        $interfaces = [];
        $errors     = [];

        if ($routerId === 'all') {
            $targetRouters = $routers;
        } elseif (!empty($routerId)) {
            $targetRouters = $routers->where('id', (int) $routerId);
        } else {
            $targetRouters = collect();
        }

        $forceRefresh = $request->boolean('refresh') || $request->has('refresh');

        // 1. Fetch Customers in Tenant for Correlating IP & MAC
        $cacheKeyMeta = "arp_meta_map_" . ($tenantId ?? 'all');
        $metaMaps = $forceRefresh ? null : Cache::get($cacheKeyMeta);

        if (!$metaMaps) {
            $customers = Customer::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->select(['id', 'name', 'code', 'phone', 'ip_address', 'mac_address', 'arp_interface', 'connection_type', 'package_id', 'status', 'router_id', 'tenant_id'])
                ->with(['package:id,name,price', 'onu:id,customer_id,status,rx_power,pon_port,name,serial_number'])
                ->get();

            $customerByIp = [];
            $customerByMac = [];
            $customerList = [];

            foreach ($customers as $c) {
                $cData = [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'code'            => $c->code,
                    'phone'           => $c->phone,
                    'ip_address'      => $c->ip_address,
                    'mac_address'     => $c->mac_address,
                    'arp_interface'   => $c->arp_interface,
                    'connection_type' => $c->connection_type,
                    'status'          => $c->status,
                    'package_name'    => $c->package?->name,
                    'package_price'   => $c->package?->price,
                    'onu_rx_power'    => $c->onu?->rx_power,
                    'onu_status'      => $c->onu?->status,
                    'router_id'       => $c->router_id,
                ];

                if (!empty($c->ip_address)) {
                    $customerByIp[trim($c->ip_address)] = $cData;
                }
                if (!empty($c->mac_address)) {
                    $cleanMac = strtolower(str_replace([':', '-', '.'], '', trim($c->mac_address)));
                    $customerByMac[$cleanMac] = $cData;
                }

                $customerList[] = [
                    'id'   => $c->id,
                    'name' => $c->name . ($c->ip_address ? " ({$c->ip_address})" : ''),
                    'ip'   => $c->ip_address,
                    'mac'  => $c->mac_address,
                ];
            }

            $metaMaps = [
                'by_ip'         => $customerByIp,
                'by_mac'        => $customerByMac,
                'customers'     => $customerList,
                'raw_customers' => array_values(array_merge(array_values($customerByIp), array_values($customerByMac))),
            ];
            Cache::put($cacheKeyMeta, $metaMaps, 45);
        }

        $customerByIp   = $metaMaps['by_ip'] ?? [];
        $customerByMac  = $metaMaps['by_mac'] ?? [];
        $customerList   = $metaMaps['customers'] ?? [];
        $rawCustomers   = $metaMaps['raw_customers'] ?? [];

        // 2. Query ARP Table & Simple Queues per Target Router
        foreach ($targetRouters as $router) {
            if ((microtime(true) - $startTime) > 12.0) {
                $errors[] = "Query timeout untuk sisa router";
                break;
            }

            try {
                $cacheKey = "arp_router_cache_{$router->id}";
                $routerData = $forceRefresh ? null : Cache::get($cacheKey);

                if (!$routerData) {
                    $mik = new MikrotikService($router);
                    if (!$mik->isConnected()) {
                        $errors[] = "Tidak dapat terhubung ke {$router->name}" . ($mik->getLastError() ? " ({$mik->getLastError()})" : '');
                        continue;
                    }

                    // A. Query /ip/arp/print
                    $rawArps = $mik->query('/ip/arp/print', [
                        '.proplist' => '.id,address,mac-address,interface,comment,disabled,dynamic,complete,invalid',
                    ]);
                    if (!is_array($rawArps)) $rawArps = [];

                    // B. Query /queue/simple/print for rate-limits & bandwidth bytes
                    $rawQueues = [];
                    try {
                        $rawQueues = $mik->query('/queue/simple/print', [
                            '.proplist' => 'name,target,bytes,total-bytes,rate,max-limit,comment,disabled',
                        ]);
                    } catch (\Throwable $e) {}
                    if (!is_array($rawQueues)) $rawQueues = [];

                    // Index queues by target IP
                    $queueByIp = [];
                    foreach ($rawQueues as $q) {
                        $target = $q['target'] ?? '';
                        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $target, $m)) {
                            $targetIp = $m[1];
                            $bytesStr = $q['bytes'] ?? '0/0';
                            $parts = explode('/', $bytesStr);
                            $rx = isset($parts[0]) ? (int) $parts[0] : 0;
                            $tx = isset($parts[1]) ? (int) $parts[1] : 0;

                            $queueByIp[$targetIp] = [
                                'queue_name' => $q['name'] ?? '',
                                'max_limit'  => $q['max-limit'] ?? '',
                                'rate'       => $q['rate'] ?? '',
                                'rx_bytes'   => $rx,
                                'tx_bytes'   => $tx,
                                'total_bytes'=> $rx + $tx,
                                'disabled'   => ($q['disabled'] ?? 'false') === 'true',
                            ];
                        }
                    }

                    // C. Query Interfaces for ARP addition dropdown
                    $rawIfaces = [];
                    try {
                        $rawIfaces = $mik->query('/interface/print', [
                            '.proplist' => 'name,type,disabled,running',
                        ]);
                    } catch (\Throwable $e) {}
                    if (!is_array($rawIfaces)) $rawIfaces = [];

                    $rIfaces = [];
                    foreach ($rawIfaces as $iface) {
                        $iname = $iface['name'] ?? '';
                        if ($iname && ($iface['disabled'] ?? 'false') !== 'true') {
                            $rIfaces[] = [
                                'name'    => $iname,
                                'type'    => $iface['type'] ?? 'ether',
                                'running' => ($iface['running'] ?? 'false') === 'true',
                            ];
                        }
                    }

                    $routerData = [
                        'arps'       => $rawArps,
                        'queue_by_ip'=> $queueByIp,
                        'interfaces' => $rIfaces,
                    ];

                    Cache::put($cacheKey, $routerData, 15);
                }

                $rArps       = $routerData['arps'] ?? [];
                $rQueueByIp  = $routerData['queue_by_ip'] ?? [];
                $rInterfaces = $routerData['interfaces'] ?? [];

                if (empty($interfaces) && !empty($rInterfaces)) {
                    $interfaces = $rInterfaces;
                }

                foreach ($rArps as $arp) {
                    $ip = trim((string) ($arp['address'] ?? ''));
                    $mac = trim((string) ($arp['mac-address'] ?? ''));
                    $iface = trim((string) ($arp['interface'] ?? ''));
                    $comment = trim((string) ($arp['comment'] ?? ''));
                    $isDynamic = ($arp['dynamic'] ?? 'false') === 'true';
                    $isDisabled = ($arp['disabled'] ?? 'false') === 'true';
                    $isComplete = ($arp['complete'] ?? 'true') !== 'false';
                    $isInvalid = ($arp['invalid'] ?? 'false') === 'true';

                    // Correlate with customer
                    $customer = null;
                    if ($ip && isset($customerByIp[$ip])) {
                        $customer = $customerByIp[$ip];
                    } elseif ($mac) {
                        $cleanMac = strtolower(str_replace([':', '-', '.'], '', $mac));
                        if (isset($customerByMac[$cleanMac])) {
                            $customer = $customerByMac[$cleanMac];
                        }
                    }

                    // Correlate with Simple Queue
                    $queue = $ip && isset($rQueueByIp[$ip]) ? $rQueueByIp[$ip] : null;

                    // Online status calculation:
                    // Complete MAC, not disabled, not invalid => Online (active on local network)
                    $isOnline = $isComplete && !$isDisabled && !$isInvalid && (!empty($mac) && $mac !== '00:00:00:00:00:00');

                    $arpEntries[] = [
                        'id'             => $arp['.id'] ?? null,
                        'router_id'      => $router->id,
                        'router_name'    => $router->name,
                        'address'        => $ip,
                        'mac_address'    => $mac,
                        'interface'      => $iface,
                        'comment'        => $comment,
                        'is_dynamic'     => $isDynamic,
                        'is_disabled'    => $isDisabled,
                        'is_complete'    => $isComplete,
                        'is_invalid'     => $isInvalid,
                        'is_online'      => $isOnline,
                        'customer'       => $customer,
                        'queue_name'     => $queue['queue_name'] ?? null,
                        'max_limit'      => $queue['max_limit'] ?? null,
                        'rate'           => $queue['rate'] ?? null,
                        'rx_bytes'       => $queue['rx_bytes'] ?? 0,
                        'tx_bytes'       => $queue['tx_bytes'] ?? 0,
                        'total_bytes'    => $queue['total_bytes'] ?? 0,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = "Gagal memproses {$router->name}: " . $e->getMessage();
                Log::error("[ArpController] Error: " . $e->getMessage());
            }
        }

        // 3. Correlate Static Customers from Database who are currently Offline (not in live ARP table)
        $seenIps = array_flip(array_filter(array_column($arpEntries, 'address')));
        $seenMacs = array_flip(array_map(fn ($m) => strtolower(str_replace([':', '-', '.'], '', $m)), array_filter(array_column($arpEntries, 'mac_address'))));

        foreach ($rawCustomers as $cust) {
            $cIp = trim($cust['ip_address'] ?? '');
            $cMac = strtolower(str_replace([':', '-', '.'], '', trim($cust['mac_address'] ?? '')));

            $matchesRouter = empty($cust['router_id']) || $routerId === 'all' || (int) $cust['router_id'] === (int) $routerId;
            if ($matchesRouter && ($cIp || $cMac)) {
                $ipSeen = $cIp && isset($seenIps[$cIp]);
                $macSeen = $cMac && isset($seenMacs[$cMac]);

                if (!$ipSeen && !$macSeen && (($cust['connection_type'] ?? '') === 'static' || !empty($cust['ip_address']))) {
                    $routerObj = $targetRouters->firstWhere('id', (int) ($cust['router_id'] ?? 0)) ?? $targetRouters->first() ?? $routers->first();
                    $arpEntries[] = [
                        'id'             => null,
                        'router_id'      => $routerObj?->id ?? 0,
                        'router_name'    => $routerObj?->name ?? 'MikroTik',
                        'address'        => $cIp ?: '-',
                        'mac_address'    => $cust['mac_address'] ?: '00:00:00:00:00:00',
                        'interface'      => $cust['arp_interface'] ?: 'ether',
                        'comment'        => 'NODERA-' . $cust['name'],
                        'is_dynamic'     => false,
                        'is_disabled'    => false,
                        'is_complete'    => false,
                        'is_invalid'     => false,
                        'is_online'      => false,
                        'customer'       => $cust,
                        'queue_name'     => null,
                        'max_limit'      => null,
                        'rate'           => null,
                        'rx_bytes'       => 0,
                        'tx_bytes'       => 0,
                        'total_bytes'    => 0,
                    ];
                }
            }
        }

        // Summary Statistics
        $totalArp     = count($arpEntries);
        $onlineEntries = array_values(array_filter($arpEntries, fn ($a) => $a['is_online']));
        $offlineEntries = array_values(array_filter($arpEntries, fn ($a) => !$a['is_online'] && !$a['is_disabled']));
        $staticEntries  = array_values(array_filter($arpEntries, fn ($a) => !$a['is_dynamic']));
        $dynamicEntries = array_values(array_filter($arpEntries, fn ($a) => $a['is_dynamic']));

        $onlineCount  = count($onlineEntries);
        $offlineCount = count($offlineEntries);
        $staticCount  = count($staticEntries);
        $dynamicCount = count($dynamicEntries);
        $disabledCount= count(array_filter($arpEntries, fn ($a) => $a['is_disabled']));
        $linkedCount  = count(array_filter($arpEntries, fn ($a) => !empty($a['customer'])));

        $resolvedTenant = session('tenant_name') ?? 'NODERA Network';

        return Inertia::render('Admin/Arp', [
            'mode'       => $pageMode,
            'routerId'   => $routerId,
            'routers'    => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'users'      => $arpEntries,
            'active'     => $onlineEntries,
            'inactive'   => $offlineEntries,
            'interfaces' => $interfaces,
            'customers'  => $customerList,
            'stats'      => [
                'total'    => $totalArp,
                'online'   => $onlineCount,
                'offline'  => $offlineCount,
                'static'   => $staticCount,
                'dynamic'  => $dynamicCount,
                'disabled' => $disabledCount,
                'linked'   => $linkedCount,
            ],
            'error'      => !empty($errors) ? implode('; ', $errors) : null,
            'companyName'=> 'NODERA Billing',
            'tenantName' => $resolvedTenant,
        ]);
    }

    /**
     * Tambah atau simpan Static ARP baru di MikroTik.
     */
    public function store(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $request->validate([
            'router_id'   => 'required|integer',
            'address'     => 'required|ip',
            'mac_address' => 'required|string',
            'interface'   => 'required|string',
            'comment'     => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer',
        ]);

        $router = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->find($request->router_id);

        if (!$router) {
            return back()->withErrors(['error' => 'Router MikroTik tidak ditemukan atau tidak aktif.']);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return back()->withErrors(['error' => 'Gagal koneksi ke router: ' . $mik->getLastError()]);
            }

            $address = trim($request->address);
            $mac = strtoupper(trim($request->mac_address));
            $iface = trim($request->interface);
            $comment = $request->comment ? trim($request->comment) : 'NODERA-STATIC-' . $address;

            // Add or update ARP entry in MikroTik
            $success = $mik->addArpEntry($address, $mac, $iface, $comment);

            if (!$success) {
                return back()->withErrors(['error' => 'MikroTik menolak penambahan ARP: ' . $mik->getLastError()]);
            }

            // If linked to customer, update customer record and add Simple Queue
            if ($request->customer_id) {
                $cust = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->find($request->customer_id);

                if ($cust) {
                    $cust->update([
                        'ip_address'      => $address,
                        'mac_address'     => $mac,
                        'arp_interface'   => $iface,
                        'connection_type' => 'static',
                        'router_id'       => $router->id,
                    ]);

                    $targetSpeed = ($cust->status === 'isolated')
                        ? ($cust->package?->profile_isolir ?? '128k/128k')
                        : ($cust->package?->profile_normal ?? '10M/10M');
                    if ($targetSpeed === 'isolir' || empty($targetSpeed)) $targetSpeed = '128k/128k';
                    $mik->addSimpleQueue("STATIC - {$cust->name}", $address, $targetSpeed, "NODERA Static IP");
                }
            } else {
                $mik->addSimpleQueue("STATIC - {$address}", $address, '10M/10M', $comment);
            }

            // Clear cache
            Cache::forget("arp_router_cache_{$router->id}");
            Cache::forget("arp_meta_map_" . ($tenantId ?? 'all'));

            return back()->with('success', "Static ARP {$address} ({$mac}) berhasil didaftarkan di {$router->name}.");
        } catch (\Throwable $e) {
            Log::error("[ArpController::store] Error: " . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menambahkan Static ARP: ' . $e->getMessage()]);
        }
    }

    /**
     * Ubah entri Dynamic ARP menjadi Static ARP (Make Static Binding).
     */
    public function makeStatic(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $request->validate([
            'router_id'   => 'required|integer',
            'address'     => 'required|ip',
            'mac_address' => 'required|string',
            'interface'   => 'required|string',
            'comment'     => 'nullable|string|max:100',
        ]);

        $router = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($request->router_id);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json(['success' => false, 'message' => 'Koneksi router gagal: ' . $mik->getLastError()], 500);
            }

            $address = trim($request->address);
            $mac = strtoupper(trim($request->mac_address));
            $iface = trim($request->interface);
            $comment = $request->comment ?: 'NODERA-BINDING-' . $address;

            $success = $mik->addArpEntry($address, $mac, $iface, $comment);

            if ($success) {
                // Also ensure Simple Queue exists if customer matches this IP
                $cust = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('ip_address', $address)
                    ->first();

                if ($cust) {
                    $targetSpeed = ($cust->status === 'isolated')
                        ? ($cust->package?->profile_isolir ?? '128k/128k')
                        : ($cust->package?->profile_normal ?? '10M/10M');
                    if ($targetSpeed === 'isolir' || empty($targetSpeed)) $targetSpeed = '128k/128k';
                    $mik->addSimpleQueue("STATIC - {$cust->name}", $address, $targetSpeed, "NODERA Static IP");
                }

                Cache::forget("arp_router_cache_{$router->id}");
                return response()->json(['success' => true, 'message' => "Entri ARP {$address} berhasil diubah menjadi Static Binding."]);
            }

            return response()->json(['success' => false, 'message' => 'Gagal mengubah ke Static ARP: ' . $mik->getLastError()], 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Aktifkan / Nonaktifkan ARP (Enable / Disable Host Access).
     */
    public function toggle(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $request->validate([
            'router_id' => 'required|integer',
            'id'        => 'nullable|string',
            'address'   => 'required|string',
            'disabled'  => 'required|boolean',
        ]);

        $router = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($request->router_id);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json(['success' => false, 'message' => 'Koneksi router gagal: ' . $mik->getLastError()], 500);
            }

            $target = $request->id ?: $request->address;
            $disabled = (bool) $request->disabled;

            $success = $mik->setArpDisabled($target, $disabled);

            Cache::forget("arp_router_cache_{$router->id}");

            if ($success) {
                $statusText = $disabled ? 'dinonaktifkan (akses diputus)' : 'diaktifkan kembali';
                return response()->json([
                    'success' => true,
                    'message' => "Entri ARP {$request->address} berhasil {$statusText}."
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Gagal mengubah status ARP: ' . $mik->getLastError()], 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Hapus entri ARP dari MikroTik.
     */
    public function delete(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $request->validate([
            'router_id' => 'required|integer',
            'id'        => 'nullable|string',
            'address'   => 'required|string',
        ]);

        $router = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($request->router_id);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan.'], 404);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json(['success' => false, 'message' => 'Koneksi router gagal: ' . $mik->getLastError()], 500);
            }

            $target = $request->id ?: $request->address;
            $success = $mik->removeArpEntry($target);
            if (!empty($request->address)) {
                $mik->removeSimpleQueue($request->address);
            }

            Cache::forget("arp_router_cache_{$router->id}");

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Entri ARP {$request->address} berhasil dihapus dari router."
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Gagal menghapus ARP: ' . $mik->getLastError()], 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ping live check untuk host IP tertentu.
     */
    public function ping(Request $request)
    {
        $request->validate([
            'router_id' => 'required|integer',
            'address'   => 'required|ip',
        ]);

        $user = $request->user();
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? ($user && $user->role !== 'superadmin' ? ($user->tenant_id ?? $user->id) : null);
        $router = Mikrotik::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($request->router_id);

        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan atau tidak memiliki akses.'], 404);
        }

        try {
            $mik = new MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json(['success' => false, 'message' => 'Router tidak terhubung: ' . ($mik->getLastError() ?: 'Koneksi ditolak / timeout')], 500);
            }

            $ip = trim($request->address);
            $count = min(10, max(1, (int)$request->input('count', 4)));
            $rawPing = $mik->query('/ping', [
                'address' => $ip,
                'count'   => $count,
            ]);

            if (!is_array($rawPing) || empty($rawPing)) {
                return response()->json([
                    'success'     => true,
                    'is_alive'    => false,
                    'transmitted' => $count,
                    'received'    => 0,
                    'packet_loss' => 100,
                    'message'     => "Tidak ada respon dari {$ip} (Request Timed Out).",
                    'results'     => array_fill(0, $count, [
                        'seq'    => 1,
                        'host'   => $ip,
                        'status' => 'timeout',
                        'time'   => 'timeout',
                    ]),
                ]);
            }

            $received = 0;
            $formatted = [];

            foreach ($rawPing as $idx => $p) {
                $status = $p['status'] ?? 'ok';
                $time = $p['time'] ?? ($p['avg-rtt'] ?? null);
                $packetLoss = isset($p['packet-loss']) ? (int)$p['packet-loss'] : null;
                $isSuccess = ($status === 'ok' || empty($status) || (isset($p['received']) && (int)$p['received'] > 0) || ($time !== null && $packetLoss !== 100));

                if ($isSuccess && $time !== null) {
                    $received++;
                    $formatted[] = [
                        'seq'     => isset($p['seq']) ? ((int)$p['seq'] + 1) : ($idx + 1),
                        'host'    => $p['host'] ?? $ip,
                        'size'    => $p['size'] ?? 56,
                        'ttl'     => $p['ttl'] ?? 64,
                        'time'    => (string) $time,
                        'status'  => 'success',
                    ];
                } else {
                    $formatted[] = [
                        'seq'     => isset($p['seq']) ? ((int)$p['seq'] + 1) : ($idx + 1),
                        'host'    => $ip,
                        'status'  => 'timeout',
                        'time'    => 'timeout',
                    ];
                }
            }

            $totalTransmitted = count($rawPing) ?: $count;
            $isAlive = $received > 0;

            return response()->json([
                'success'     => true,
                'is_alive'    => $isAlive,
                'transmitted' => $totalTransmitted,
                'received'    => $received,
                'packet_loss' => round((($totalTransmitted - $received) / max(1, $totalTransmitted)) * 100, 1),
                'results'     => $formatted,
                'message'     => $isAlive ? "Host {$ip} aktif ({$received}/{$totalTransmitted} paket diterima)." : "Host {$ip} tidak merespon (100% loss).",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menjalankan ping: ' . $e->getMessage()], 500);
        }
    }
}
