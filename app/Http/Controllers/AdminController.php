<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Setting;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Services\GenieacsService;
use App\Services\MikrotikService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminController extends Controller
{
    protected MikrotikService $mikrotik;
    protected GenieacsService $genieacs;

    public function __construct(MikrotikService $mikrotik, GenieacsService $genieacs)
    {
        $this->mikrotik = $mikrotik;
        $this->genieacs = $genieacs;
    }

    public function index()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        // Total Devices (GenieACS)
        try {
            $acsData = Cache::remember("genieacs_devices_{$tenantId}", 60, fn() => $this->genieacs->getDevices());
            if (($acsData['code'] ?? 0) === 200 && is_array($acsData['body'])) {
                $totalDevices = count($acsData['body']);
            } else {
                $totalDevices = DB::table('onu_locations')->when($tenantId, fn($q) => $q->where('onu_locations.tenant_id', $tenantId))->count();
            }
        } catch (\Exception $e) {
            $totalDevices = 0;
        }

        // Online PPPoE & Hotspot (MikroTik)
        $onlinePppoe = 0;
        $onlineHotspot = 0;
        $mikrotikConnected = false;

        $activeRouter = \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->first();

        if ($activeRouter) {
            try {
                $service = new MikrotikService($activeRouter);
                if ($service->isConnected()) {
                    $mikrotikConnected = true;
                    $pppActiveList = $service->query('/ppp/active/print');
                    $onlinePppoe = is_array($pppActiveList) ? count($pppActiveList) : 0;
                    $hotspotActiveList = $service->query('/ip/hotspot/active/print');
                    $onlineHotspot = is_array($hotspotActiveList) ? count($hotspotActiveList) : 0;
                }
            } catch (\Exception $e) {
                Log::error('MikroTik query error: ' . $e->getMessage());
            }
        } else {
            $onlinePppoe = \App\Models\Customer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'active')->count();
        }

        // Pending Invoices
        $pendingInvoices = \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'pending')->count();

        // Today's Revenue (SARGable Index Range)
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayRevenue = (float) \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('paid', 1)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $todayVoucher = (float) \App\Models\Voucher::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('used', true)
            ->where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('used_at', [$todayStart, $todayEnd])
                  ->orWhere(fn($sub) => $sub->whereNull('used_at')->whereBetween('created_at', [$todayStart, $todayEnd]));
            })
            ->sum('price');
        $todayRevenue += $todayVoucher;

        // Pending Tickets
        $pendingTickets = TroubleTicket::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->whereIn('status', ['pending', 'in_progress'])->count();

        $stats = compact('totalDevices', 'onlinePppoe', 'onlineHotspot', 'pendingInvoices', 'todayRevenue', 'pendingTickets', 'mikrotikConnected');

        return view('mobile.dashboard', ['stats' => $stats, 'invoices' => []]);
    }

    public function analytics()
    {
        $currentMonth = now()->format('Y-m');
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $revenueThisMonth = (float) \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('paid', 1)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $voucherThisMonth = (float) \App\Models\Voucher::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('used', true)
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('used_at', [$monthStart, $monthEnd])
                  ->orWhere(fn($sub) => $sub->whereNull('used_at')->whereBetween('created_at', [$monthStart, $monthEnd]));
            })
            ->sum('price');

        $revenueThisMonth += $voucherThisMonth;

        $paidInvoices = \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('paid', 1)->count();
        $unpaidInvoices = \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('paid', 0)->count();
        $totalCustomers = \App\Models\Customer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();

        $recentPayments = \App\Models\Invoice::with('customer')
            ->select('invoices.*', 'customers.name as customer_name')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id', 'left')
            ->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('invoices.paid', 1)
            ->orderBy('invoices.paid_at', 'DESC')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Analytics', [
            'revenueThisMonth' => (float) $revenueThisMonth,
            'paidInvoices' => (int) $paidInvoices,
            'unpaidInvoices' => (int) $unpaidInvoices,
            'totalCustomers' => (int) $totalCustomers,
            'recentPayments' => $recentPayments->map(fn ($p) => ['id' => $p->id, 'invoice_number' => $p->invoice_number ?? '', 'amount' => (float) $p->amount, 'customer_name' => $p->customer_name ?? $p->customer?->name, 'created_at' => $p->created_at?->toIso8601String()]),
        ]);
    }

    public function genieacs()
    {
        $genieacsUrl = \App\Models\Setting::getValue('GENIEACS_URL', '');
        $genieacsUsername = \App\Models\Setting::getValue('GENIEACS_USERNAME', '');
        $genieacsPassword = \App\Models\Setting::getValue('GENIEACS_PASSWORD', '');
        $genieacsToken = \App\Models\Setting::getValue('GENIEACS_TOKEN', '');

        $configured = !empty($genieacsUrl);
        $devices = [];
        $connectionError = null;

        if ($configured) {
            $forceRefresh = request()->boolean('refresh') || request()->has('refresh');
            $result = $this->genieacs->getDevices(!$forceRefresh);
            $devices = $result['summaries'] ?? [];
            $connectionError = $result['error'] ?? null;
        }

        $total = count($devices);
        $online = count(array_filter($devices, fn($d) => !empty($d['online'])));
        $offline = $total - $online;

        return Inertia::render('Admin/Genieacs', [
            'devices' => $devices,
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'configured' => $configured,
            'genieacsUrl' => $genieacsUrl ?: '',
            'genieacsUsername' => $genieacsUsername ?: '',
            'genieacsPassword' => $genieacsPassword ?: '',
            'genieacsToken' => $genieacsToken ?: '',
            'connectionError' => $connectionError,
        ]);
    }

    public function genieacsDevice(Request $request)
    {
        $serial = $request->get('serial');
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi']);
        }
        $device = $this->genieacs->getDevice($serial);
        if (!empty($device)) {
            return response()->json(['success' => true, 'device' => $device]);
        }
        return response()->json(['success' => false, 'message' => 'Perangkat tidak ditemukan di GenieACS'], 404);
    }

    public function genieacsReboot(Request $request)
    {
        $serial = $request->input('serial');
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }
        $result = $this->genieacs->rebootDevice($serial);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Perintah reboot berhasil dikirim ke perangkat via TR-069' : ($result['error'] ?? 'Gagal mengirim perintah reboot ke GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function genieacsRefresh(Request $request)
    {
        $serial = $request->input('serial');
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }
        $result = $this->genieacs->refreshParameters($serial);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Task refresh parameter berhasil dikirim ke perangkat' : ($result['error'] ?? 'Gagal merefresh parameter perangkat di GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function genieacsFactoryReset(Request $request)
    {
        $serial = $request->input('serial');
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }
        $result = $this->genieacs->factoryReset($serial);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Perintah factory reset telah dikirim ke perangkat' : ($result['error'] ?? 'Gagal mengirim factory reset ke GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function genieacsWifi(Request $request)
    {
        $serial = $request->input('serial');
        $ssid = $request->input('ssid', '');
        $password = $request->input('password', '');

        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }

        $result = $this->genieacs->setWifi($serial, $ssid, $password);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Pengaturan Wi-Fi berhasil diperbarui via TR-069' : ($result['error'] ?? 'Gagal memperbarui pengaturan Wi-Fi di GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function genieacsPppoe(Request $request)
    {
        $serial = $request->input('serial');
        $username = $request->input('username', '');
        $password = $request->input('password', '');

        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }

        $result = $this->genieacs->setPppoe($serial, $username, $password);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Pengaturan akun PPPoE berhasil dikirim ke perangkat via TR-069' : ($result['error'] ?? 'Gagal mengirim akun PPPoE ke GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function genieacsDelete(Request $request)
    {
        $serial = $request->input('serial');
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat wajib diisi'], 400);
        }
        $result = $this->genieacs->deleteDevice($serial);
        $isSuccess = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Perangkat berhasil dihapus dari GenieACS' : ($result['error'] ?? 'Gagal menghapus perangkat dari GenieACS'),
        ], $isSuccess ? 200 : 400);
    }

    public function clearMapCache(): void
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            \Illuminate\Support\Facades\Cache::forget("admin_map_payload_view_{$tenantId}");
            \Illuminate\Support\Facades\Cache::forget("admin_map_payload_view_");
            \Illuminate\Support\Facades\Cache::forget("admin_map_payload_view_null");
        } catch (\Throwable $e) {}
    }

    public function map()
    {
        try {
            $forceRefresh = request()->boolean('refresh') || request()->has('refresh');
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $cacheKey = "admin_map_payload_view_{$tenantId}";

            if (!$forceRefresh && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $payload = \Illuminate\Support\Facades\Cache::get($cacheKey);
            } else {
                $payload = $this->buildMapPayload();
                \Illuminate\Support\Facades\Cache::put($cacheKey, $payload, 20);
            }

            return Inertia::render('Admin/Map', $payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("AdminController@map exception: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return Inertia::render('Admin/Map', [
                'routers' => [],
                'odps' => [],
                'onusMarkers' => [],
                'odpMarkers' => [],
                'customerMarkers' => [],
                'allCustomers' => [],
                'connections' => [],
                'create' => (bool) request('create'),
            ]);
        }
    }

    public function mapData()
    {
        try {
            $forceRefresh = request()->boolean('refresh') || request()->has('refresh');
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $cacheKey = "admin_map_payload_view_{$tenantId}";

            if (!$forceRefresh && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $payload = \Illuminate\Support\Facades\Cache::get($cacheKey);
            } else {
                $payload = $this->buildMapPayload();
                \Illuminate\Support\Facades\Cache::put($cacheKey, $payload, 20);
            }

            return response()->json(array_merge(['success' => true, 'timestamp' => now()->format('H:i:s')], $payload));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("AdminController@mapData exception: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function buildMapPayload(): array
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $hasOdpTable = \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('odp_locations'), fn() => \Illuminate\Support\Facades\Schema::hasTable('odp_locations'));
        $hasOnuTable = \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('onu_locations'), fn() => \Illuminate\Support\Facades\Schema::hasTable('onu_locations'));
        $hasOdpCol = \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('customers' . 'odp_id'), fn() => \Illuminate\Support\Facades\Schema::hasColumn('customers', 'odp_id'));
        $hasCablePathCol = \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('customers' . 'cable_path'), fn() => \Illuminate\Support\Facades\Schema::hasColumn('customers', 'cable_path'));
        $hasParentOdpCol = $hasOdpTable && \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('odp_locations' . 'parent_odp_id'), fn() => \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'parent_odp_id'));
        $hasRouterCol = $hasOdpTable && \Illuminate\Support\Facades\Cache::rememberForever('schema_check_' . md5('odp_locations' . 'router_id'), fn() => \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'router_id'));

        $onus = $hasOnuTable
            ? \App\Models\OnuLocation::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->with('customer', 'odp')->get()
            : collect();
        
        if ($hasOdpTable) {
            $odpRelations = [];
            if ($hasParentOdpCol) {
                $odpRelations[] = 'parent';
            }
            if ($hasRouterCol) {
                $odpRelations[] = 'router';
            }

            $odpQuery = \App\Models\OdpLocation::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->with($odpRelations);
            $counts = [];
            if ($hasOnuTable) {
                $counts[] = 'onus';
            }
            if ($hasOdpCol) {
                $counts[] = 'customers';
            }
            if (!empty($counts)) {
                $odpQuery->withCount($counts);
            }
            $odps = $odpQuery->orderBy('name')->get();
        } else {
            $odps = collect();
        }

        // 1. Gather live network telemetry across active tenant routers (Top Bandwidth Engine)
        $routers = \App\Models\Mikrotik::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->withCount('customers')->where('is_active', true)->get();
        if ($routers->isEmpty()) {
            $routers = \App\Models\Mikrotik::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->withCount('customers')->get();
        }

        $activeSessions = [];
        $secretMap = [];
        $interfaceTraffic = [];
        $sessionsByIp = [];
        $sessionsByMac = [];
        $sessionsByName = [];
        $queuesByTargetIp = [];
        $queuesByName = [];
        $arpMap = [];
        $reachableRouterIds = [];
        $forceRefresh = request()->boolean('refresh') || request()->has('refresh');

        foreach ($routers as $router) {
            if (!$router->is_active || empty($router->host)) {
                continue;
            }

            $cacheKey = "mik_map_tel_{$router->id}";
            $routerTel = null;
            if (!$forceRefresh && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $routerTel = \Illuminate\Support\Facades\Cache::get($cacheKey);
            }

            if (!is_array($routerTel)) {
                try {
                    $mik = new \App\Services\MikrotikService($router);
                    if ($mik->isConnected()) {
                        $rIfaces = [];
                        $ifaceByName = [];
                        $ifaces = $mik->query('/interface/print', ['.proplist' => 'name,rx-byte,tx-byte']);
                        if (is_array($ifaces)) {
                            foreach ($ifaces as $iface) {
                                $iname = $iface['name'] ?? '';
                                if ($iname) {
                                    $ifaceByName[$iname] = $iface;
                                    $ifaceByName[strtolower(trim($iname))] = $iface;
                                    $cleanIface = trim($iname, '<> ');
                                    $ifaceByName[$cleanIface] = $iface;
                                    $ifaceByName[strtolower($cleanIface)] = $iface;
                                    if (str_starts_with(strtolower($cleanIface), 'pppoe-')) {
                                        $subName = substr($cleanIface, 6);
                                        $ifaceByName[$subName] = $iface;
                                        $ifaceByName[strtolower($subName)] = $iface;
                                    }
                                    $rIfaces[$iname] = $iface;
                                }
                            }
                        }

                        $rQueuesByName = [];
                        $rQueuesByTargetIp = [];
                        try {
                            $queues = $mik->query('/queue/simple/print', ['.proplist' => 'name,target,bytes']);
                            if (is_array($queues)) {
                                foreach ($queues as $q) {
                                    $qName = $q['name'] ?? '';
                                    $target = $q['target'] ?? '';
                                    $bytesStr = $q['bytes'] ?? '0/0';
                                    $parts = explode('/', $bytesStr);
                                    $qRx = isset($parts[0]) ? (int) $parts[0] : 0;
                                    $qTx = isset($parts[1]) ? (int) $parts[1] : 0;
                                    $qInfo = [
                                        'name' => $qName,
                                        'target' => $target,
                                        'rx' => $qRx,
                                        'tx' => $qTx,
                                        'total' => $qRx + $qTx,
                                    ];
                                    if ($qName) $rQueuesByName[strtolower(trim($qName))] = $qInfo;
                                    if ($target) {
                                        $cleanTargetIp = explode('/', $target)[0];
                                        $rQueuesByTargetIp[$cleanTargetIp] = $qInfo;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        $rArpMap = [];
                        try {
                            $arps = $mik->query('/ip/arp/print', ['.proplist' => 'address,mac-address']);
                            if (is_array($arps)) {
                                foreach ($arps as $arp) {
                                    $aIp = $arp['address'] ?? '';
                                    $aMac = $arp['mac-address'] ?? '';
                                    if ($aIp) $rArpMap[$aIp] = $aMac;
                                }
                            }
                        } catch (\Throwable $e) {}

                        $rActiveSessions = [];
                        $actives = $mik->query('/ppp/active/print');
                        if (is_array($actives)) {
                            foreach ($actives as $act) {
                                $uname = $act['name'] ?? '';
                                $ip = $act['address'] ?? '';
                                $mac = $act['caller-id'] ?? '';
                                $uptime = $act['uptime'] ?? '-';
                                $service = $act['service'] ?? 'pppoe';

                                $ifaceRow = $ifaceByName[$uname] ?? ($ifaceByName[strtolower(trim($uname))] ?? null);
                                if (!$ifaceRow) {
                                    foreach ($ifaceByName as $iname => $row) {
                                        if (stripos($iname, $uname) !== false) {
                                            $ifaceRow = $row;
                                            break;
                                        }
                                    }
                                }

                                $rx = $ifaceRow ? (int) ($ifaceRow['rx-byte'] ?? 0) : 0;
                                $tx = $ifaceRow ? (int) ($ifaceRow['tx-byte'] ?? 0) : 0;
                                if ($rx === 0 && $tx === 0 && $ip && isset($rQueuesByTargetIp[$ip])) {
                                    $rx = $rQueuesByTargetIp[$ip]['rx'];
                                    $tx = $rQueuesByTargetIp[$ip]['tx'];
                                }

                                $sessionObj = [
                                    'type' => 'pppoe',
                                    'name' => $uname,
                                    'address' => $ip,
                                    'caller-id' => $mac,
                                    'uptime' => $uptime,
                                    'service' => $service,
                                    'rx_bytes' => $rx,
                                    'tx_bytes' => $tx,
                                    'total_bytes' => $rx + $tx,
                                    'router_id' => $router->id,
                                    'router_name' => $router->name,
                                ];
                                if ($uname) {
                                    $rActiveSessions['by_name'][$uname] = $sessionObj;
                                    $rActiveSessions['by_name'][strtolower(trim($uname))] = $sessionObj;
                                }
                                if ($ip) $rActiveSessions['by_ip'][$ip] = $sessionObj;
                                if ($mac) {
                                    $cleanMac = strtolower(str_replace([':', '-', '.'], '', $mac));
                                    $rActiveSessions['by_mac'][$cleanMac] = $sessionObj;
                                }
                            }
                        }

                        $rSecretMap = [];
                        $secrets = $mik->query('/ppp/secret/print');
                        if (is_array($secrets)) {
                            foreach ($secrets as $sec) {
                                $uname = $sec['name'] ?? '';
                                if ($uname) {
                                    $rSecretMap[$uname] = $sec;
                                    $rSecretMap[strtolower(trim($uname))] = $sec;
                                }
                            }
                        }

                        try {
                            $hotspots = $mik->query('/ip/hotspot/active/print');
                            if (is_array($hotspots)) {
                                foreach ($hotspots as $hs) {
                                    $user = $hs['user'] ?? '';
                                    $ip = $hs['address'] ?? '';
                                    $mac = $hs['mac-address'] ?? '';
                                    $uptime = $hs['uptime'] ?? '-';
                                    $rx = (int) ($hs['bytes-in'] ?? 0);
                                    $tx = (int) ($hs['bytes-out'] ?? 0);

                                    $sessionObj = [
                                        'type' => 'hotspot',
                                        'name' => $user,
                                        'address' => $ip,
                                        'caller-id' => $mac,
                                        'mac-address' => $mac,
                                        'uptime' => $uptime,
                                        'service' => 'hotspot',
                                        'rx_bytes' => $rx,
                                        'tx_bytes' => $tx,
                                        'total_bytes' => $rx + $tx,
                                        'router_id' => $router->id,
                                        'router_name' => $router->name,
                                    ];
                                    if ($user) {
                                        $rActiveSessions['by_name'][$user] = $sessionObj;
                                        $rActiveSessions['by_name'][strtolower(trim($user))] = $sessionObj;
                                        $rActiveSessions['by_name']['hs:' . $user] = $sessionObj;
                                    }
                                    if ($ip) $rActiveSessions['by_ip'][$ip] = $sessionObj;
                                    if ($mac) {
                                        $cleanMac = strtolower(str_replace([':', '-', '.'], '', $mac));
                                        $rActiveSessions['by_mac'][$cleanMac] = $sessionObj;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        $routerTel = [
                            'reachable' => true,
                            'interfaceTraffic' => $rIfaces,
                            'queuesByName' => $rQueuesByName,
                            'queuesByTargetIp' => $rQueuesByTargetIp,
                            'arpMap' => $rArpMap,
                            'activeSessions' => $rActiveSessions,
                            'secretMap' => $rSecretMap,
                        ];

                        \Illuminate\Support\Facades\Cache::put($cacheKey, $routerTel, 30);
                    }
                } catch (\Throwable $e) {}
            }

            if (is_array($routerTel) && !empty($routerTel['reachable'])) {
                $reachableRouterIds[$router->id] = true;
                if (!empty($routerTel['interfaceTraffic'])) {
                    $interfaceTraffic[$router->id] = $routerTel['interfaceTraffic'];
                }
                if (!empty($routerTel['queuesByName'])) {
                    $queuesByName[$router->id] = $routerTel['queuesByName'];
                    foreach ($routerTel['queuesByName'] as $k => $v) $queuesByName['all'][$k] = $v;
                }
                if (!empty($routerTel['queuesByTargetIp'])) {
                    $queuesByTargetIp[$router->id] = $routerTel['queuesByTargetIp'];
                    foreach ($routerTel['queuesByTargetIp'] as $k => $v) $queuesByTargetIp['all'][$k] = $v;
                }
                if (!empty($routerTel['arpMap'])) {
                    $arpMap[$router->id] = $routerTel['arpMap'];
                    foreach ($routerTel['arpMap'] as $k => $v) $arpMap['all'][$k] = $v;
                }
                if (!empty($routerTel['activeSessions'])) {
                    $aSess = $routerTel['activeSessions'];
                    if (!empty($aSess['by_name'])) {
                        foreach ($aSess['by_name'] as $k => $v) {
                            $activeSessions[$router->id][$k] = $v;
                            $sessionsByName['all'][$k] = $v;
                        }
                    }
                    if (!empty($aSess['by_ip'])) {
                        foreach ($aSess['by_ip'] as $k => $v) {
                            $sessionsByIp[$router->id][$k] = $v;
                            $sessionsByIp['all'][$k] = $v;
                        }
                    }
                    if (!empty($aSess['by_mac'])) {
                        foreach ($aSess['by_mac'] as $k => $v) {
                            $sessionsByMac[$router->id][$k] = $v;
                            $sessionsByMac['all'][$k] = $v;
                        }
                    }
                }
                if (!empty($routerTel['secretMap'])) {
                    foreach ($routerTel['secretMap'] as $k => $v) {
                        $secretMap[$router->id][$k] = $v;
                        $secretMap['all'][$k] = $v;
                    }
                }
            }
        }

        // Router Marker Data
        $routerList = $routers->map(function ($r) use ($activeSessions) {
            $activeCount = isset($activeSessions[$r->id]) ? count($activeSessions[$r->id]) : 0;
            return [
                'id' => $r->id,
                'name' => $r->name,
                'host' => $r->host,
                'port' => $r->port,
                'is_active' => (bool) $r->is_active,
                'lat' => $r->lat ? (float) $r->lat : null,
                'lng' => $r->lng ? (float) $r->lng : null,
                'location' => $r->location ?? 'NOC / Server Utama',
                'active_sessions_count' => $activeCount,
                'customers_count' => (int) ($r->customers_count ?? 0),
            ];
        });

        // 2. Fetch all customers and calculate online/offline status per ODP (Memory & Column Optimized)
        $customerSelect = [
            'id', 'name', 'code', 'phone', 'email', 'address', 'pppoe_username',
            'ip_address', 'mac_address', 'status', 'isolation_date', 'lat', 'lng',
            'package_id', 'router_id', 'tenant_id'
        ];
        if ($hasOdpCol) {
            $customerSelect[] = 'odp_id';
            $customerSelect[] = 'odp_port';
        }
        if ($hasCablePathCol) {
            $customerSelect[] = 'cable_path';
        }

        $customerRelations = [
            'package:id,name,price',
            'router:id,name,host',
            'onu:id,customer_id,olt_id,serial_number,rx_power,tx_power,pon_port',
            'onu.olt:id,name'
        ];
        if ($hasOdpCol) {
            $customerRelations[] = 'odp:id,name,lat,lng,capacity';
        }

        $allCustomersRaw = \App\Models\Customer::withoutGlobalScopes()
            ->select($customerSelect)
            ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
            ->with($customerRelations)
            ->orderBy('name')
            ->get();

        $odpTotalClients = [];
        $odpOnlineClients = [];
        $customerOnlineMap = [];

        foreach ($allCustomersRaw as $c) {
            $rId = $c->router_id;
            $username = $c->pppoe_username ? trim($c->pppoe_username) : '';
            $unameLower = strtolower($username);
            $cIp = $c->ip_address ? trim($c->ip_address) : '';

            $active = null;
            if ($username) {
                $active = $activeSessions[$rId][$username] ?? ($activeSessions[$rId][$unameLower] ?? ($sessionsByName['all'][$username] ?? ($sessionsByName['all'][$unameLower] ?? null)));
            }
            if (!$active && $cIp) {
                $active = $sessionsByIp[$rId][$cIp] ?? ($sessionsByIp['all'][$cIp] ?? null);
            }
            if (!$active && $c->code) {
                $active = $sessionsByName['all'][strtolower(trim($c->code))] ?? null;
            }
            if (!$active && $c->name) {
                $active = $sessionsByName['all'][strtolower(trim($c->name))] ?? null;
            }

            $isRouterReachable = !empty($rId) && !empty($reachableRouterIds[$rId]);
            $isOnline = $isRouterReachable && !empty($active) && ($c->status === 'active');
            $customerOnlineMap[$c->id] = $isOnline;

            if ($hasOdpCol && !empty($c->odp_id)) {
                $odpTotalClients[$c->odp_id] = ($odpTotalClients[$c->odp_id] ?? 0) + 1;
                if ($isOnline) {
                    $odpOnlineClients[$c->odp_id] = ($odpOnlineClients[$c->odp_id] ?? 0) + 1;
                }
            }
        }

        // 3. Build ODP / ODC / HTB / Switch map
        $odpMap = [];
        $odpList = $odps->map(function ($o) use (&$odpMap, $odpTotalClients, $odpOnlineClients) {
            $used = (int) ($o->customers_count ?? 0);
            $capacity = (int) ($o->capacity ?? 8);
            $type = $o->type ?? 'odp';
            $netMode = $o->network_mode ?? ($type === 'htb' || $type === 'switch' ? 'lan' : 'pon');
            $totalClients = $odpTotalClients[$o->id] ?? $used;
            $onlineClients = $odpOnlineClients[$o->id] ?? 0;

            $item = [
                'id' => $o->id,
                'name' => $o->name,
                'type' => $type,
                'network_mode' => $netMode,
                'lat' => (float) ($o->lat ?? 0),
                'lng' => (float) ($o->lng ?? 0),
                'onus_count' => (int) ($o->onus_count ?? 0),
                'customers_count' => $used,
                'used_ports' => $used,
                'capacity' => $capacity,
                'available_ports' => max(0, $capacity - $used),
                'parent_odp_id' => $o->parent_odp_id ?? null,
                'parent_name' => $o->parent?->name ?? null,
                'router_id' => $o->router_id ?? null,
                'router_name' => $o->router?->name ?? null,
                'cable_path' => $o->cable_path ?? [],
                'total_clients' => $totalClients,
                'online_clients' => $onlineClients,
                'is_full' => $used >= $capacity,
                'is_critical' => $capacity > 0 && ($used / $capacity) >= 0.75 && ($used < $capacity),
                'is_available' => $used < $capacity,
            ];
            $odpMap[$o->id] = $item;
            return $item;
        });

        // 4. Build Customer Markers and Drop Wire Lines (Matched with TopBandwidth Telemetry)
        $customerMarkers = [];
        $connections = [];
        $customerTelemetryMap = [];

        foreach ($allCustomersRaw as $c) {
            $cLat = (float) ($c->lat ?? 0);
            $cLng = (float) ($c->lng ?? 0);

            $rId = $c->router_id;
            $username = $c->pppoe_username ? trim($c->pppoe_username) : '';
            $unameLower = strtolower($username);
            $cCode = $c->code ? trim($c->code) : '';
            $cCodeLower = strtolower($cCode);
            $cName = $c->name ? trim($c->name) : '';
            $cNameLower = strtolower($cName);
            $cIp = $c->ip_address ? trim($c->ip_address) : '';
            $cMac = $c->mac_address ? strtolower(str_replace([':', '-', '.'], '', $c->mac_address)) : '';

            // 1. Cari Live Session aktif persis seperti Top Bandwidth
            $active = null;

            // a. Prioritas 1: Cocokkan PPPoE username
            if ($username) {
                if ($rId && isset($activeSessions[$rId][$username])) {
                    $active = $activeSessions[$rId][$username];
                } elseif ($rId && isset($activeSessions[$rId][$unameLower])) {
                    $active = $activeSessions[$rId][$unameLower];
                } elseif (isset($sessionsByName['all'][$username])) {
                    $active = $sessionsByName['all'][$username];
                } elseif (isset($sessionsByName['all'][$unameLower])) {
                    $active = $sessionsByName['all'][$unameLower];
                }
            }

            // b. Prioritas 2: Cocokkan dengan IP di database
            if (!$active && $cIp) {
                if ($rId && isset($sessionsByIp[$rId][$cIp])) {
                    $active = $sessionsByIp[$rId][$cIp];
                } elseif (isset($sessionsByIp['all'][$cIp])) {
                    $active = $sessionsByIp['all'][$cIp];
                }
            }

            // c. Prioritas 3: Cocokkan dengan ID / Kode Pelanggan
            if (!$active && $cCode) {
                if ($rId && isset($activeSessions[$rId][$cCode])) {
                    $active = $activeSessions[$rId][$cCode];
                } elseif (isset($sessionsByName['all'][$cCodeLower])) {
                    $active = $sessionsByName['all'][$cCodeLower];
                }
            }

            // d. Prioritas 4: Cocokkan dengan Nama Pelanggan
            if (!$active && $cName) {
                if ($rId && isset($activeSessions[$rId][$cName])) {
                    $active = $activeSessions[$rId][$cName];
                } elseif (isset($sessionsByName['all'][$cNameLower])) {
                    $active = $sessionsByName['all'][$cNameLower];
                }
            }

            // e. Prioritas 5: Cocokkan dengan MAC Address
            if (!$active && $cMac) {
                if ($rId && isset($sessionsByMac[$rId][$cMac])) {
                    $active = $sessionsByMac[$rId][$cMac];
                } elseif (isset($sessionsByMac['all'][$cMac])) {
                    $active = $sessionsByMac['all'][$cMac];
                }
            }

            // 2. Ambil Secret konfigurasi jika ada
            $secret = null;
            if ($username) {
                if ($rId && isset($secretMap[$rId][$username])) {
                    $secret = $secretMap[$rId][$username];
                } elseif ($rId && isset($secretMap[$rId][$unameLower])) {
                    $secret = $secretMap[$rId][$unameLower];
                } elseif (isset($secretMap['all'][$unameLower])) {
                    $secret = $secretMap['all'][$unameLower];
                }
            }

            // 3. Tentukan IP Address & MAC Address Real-Time
            $realIp = !empty($active['address'])
                ? $active['address']
                : (!empty($secret['remote-address'])
                    ? $secret['remote-address']
                    : ($cIp ?: null));

            $realMac = !empty($active['caller-id'])
                ? $active['caller-id']
                : (!empty($active['mac-address'])
                    ? $active['mac-address']
                    : (!empty($secret['caller-id'])
                        ? $secret['caller-id']
                        : (!empty($secret['last-caller-id'])
                            ? $secret['last-caller-id']
                            : ($realIp && isset($arpMap['all'][$realIp])
                                ? $arpMap['all'][$realIp]
                                : ($c->mac_address ?: null)))));

            // 4. Hitung Trafik Bandwidth Data (Rx, Tx, Total) dari Top Bandwidth
            $rxBytes = 0;
            $txBytes = 0;

            if ($active) {
                $rxBytes = (int) ($active['rx_bytes'] ?? 0);
                $txBytes = (int) ($active['tx_bytes'] ?? 0);
            }

            // Jika belum dapat bytes dari live session, coba cek Simple Queue atau Interface langsung
            if ($rxBytes === 0 && $txBytes === 0) {
                if ($realIp && isset($queuesByTargetIp['all'][$realIp])) {
                    $rxBytes = (int) ($queuesByTargetIp['all'][$realIp]['rx'] ?? 0);
                    $txBytes = (int) ($queuesByTargetIp['all'][$realIp]['tx'] ?? 0);
                } elseif ($unameLower && isset($queuesByName['all'][$unameLower])) {
                    $rxBytes = (int) ($queuesByName['all'][$unameLower]['rx'] ?? 0);
                    $txBytes = (int) ($queuesByName['all'][$unameLower]['tx'] ?? 0);
                }
            }

            // 5. Tentukan Status Online & Uptime
            $liveOnline = !empty($active);
            $routerReachable = !empty($rId) && !empty($reachableRouterIds[$rId]);
            $isOnline = $routerReachable && $liveOnline && ($c->status === 'active');

            if (!empty($active['uptime'])) {
                $uptime = $active['uptime'];
            } else {
                $uptime = '-';
            }

            $service = $active['service'] ?? ($active['type'] ?? ($c->connection_type ?? 'pppoe'));
            $profile = $secret['profile'] ?? ($c->package?->name ?? 'Default');

            $cOdpId = $hasOdpCol ? $c->odp_id : null;
            $cOdpName = ($hasOdpCol && $c->odp) ? $c->odp->name : null;
            $cOdpPort = $hasOdpCol ? $c->odp_port : null;
            $cCablePath = $hasCablePathCol ? ($c->cable_path ?? []) : [];

            $customerTelemetryMap[$c->id] = [
                'ip_address' => $realIp ?? '-',
                'mac_address' => $realMac ?? '-',
                'is_online' => $isOnline,
                'uptime' => $uptime,
                'rx_bytes' => $this->formatBytes($rxBytes),
                'tx_bytes' => $this->formatBytes($txBytes),
                'total_bytes' => $this->formatBytes($rxBytes + $txBytes),
            ];

            if ($cLat != 0 && $cLng != 0) {
                $customerMarkers[] = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'pppoe_username' => $c->pppoe_username,
                    'ip_address' => $realIp ?? '-',
                    'mac_address' => $realMac ?? '-',
                    'connection_type' => strtoupper($service),
                    'uptime' => $uptime,
                    'is_online' => $isOnline,
                    'mikrotik_reachable' => $routerReachable,
                    'rx_bytes' => $this->formatBytes($rxBytes),
                    'tx_bytes' => $this->formatBytes($txBytes),
                    'total_bytes' => $this->formatBytes($rxBytes + $txBytes),
                    'rx_bytes_raw' => $rxBytes,
                    'tx_bytes_raw' => $txBytes,
                    'profile' => $profile,
                    'remote_address' => $secret['remote-address'] ?? $realIp ?? '-',
                    'local_address' => $secret['local-address'] ?? ($c->router?->host ?? '-'),
                    'router_id' => $c->router_id,
                    'router_name' => $c->router?->name ?? 'MikroTik Gateway',
                    'router_ip' => $c->router?->host ?? '-',
                    'phone' => $c->phone,
                    'email' => $c->email,
                    'address' => $c->address,
                    'package_id' => $c->package_id,
                    'package_name' => $c->package?->name,
                    'package_price' => (float) ($c->package?->price ?? 0),
                    'status' => $liveOnline ? 'active' : ($c->status ?? 'isolated'),
                    'isolation_date' => $c->isolation_date ?? '20',
                    'unpaid_invoices' => (int) ($c->unpaid_invoices ?? 0),
                    'lat' => $cLat,
                    'lng' => $cLng,
                    'odp_id' => $cOdpId,
                    'odp_name' => $cOdpName,
                    'odp_port' => $cOdpPort,
                    'cable_path' => $cCablePath,
                    'rx_power' => ($c->onu && $c->onu->rx_power !== null) ? (string) $c->onu->rx_power : null,
                    'tx_power' => ($c->onu && $c->onu->tx_power !== null) ? (string) $c->onu->tx_power : null,
                    'onu_serial' => $c->onu?->serial_number ?? $c->serial_number ?? null,
                    'olt_name' => $c->onu?->olt?->name ?? null,
                    'pon_port' => $c->onu?->pon_port ?? null,
                ];

                // Drop wire cable line to customer
                if ($cOdpId && isset($odpMap[$cOdpId])) {
                    $odp = $odpMap[$cOdpId];
                    if ($odp['lat'] && $odp['lng']) {
                        $connections[] = [
                            'id' => "line-c-{$c->id}",
                            'category' => 'drop_wire',
                            'source_type' => $odp['type'] ?? 'odp',
                            'source_id' => $odp['id'],
                            'source_name' => $odp['name'],
                            'target_type' => 'customer',
                            'target_id' => $c->id,
                            'target_name' => $c->name,
                            'odp_id' => $odp['id'],
                            'odp_name' => $odp['name'],
                            'customer_id' => $c->id,
                            'customer_name' => $c->name,
                            'customer_pppoe' => $c->pppoe_username,
                            'status' => $isOnline ? 'active' : ($c->status ?? 'isolated'),
                            'is_connected' => $isOnline,
                            'odp_lat' => (float) $odp['lat'],
                            'odp_lng' => (float) $odp['lng'],
                            'target_lat' => $cLat,
                            'target_lng' => $cLng,
                            'odp_port' => $cOdpPort,
                            'waypoints' => $cCablePath,
                        ];
                    }
                }
            }
        }

        // 5. Generate Inter-Node Feeder / Distribution & Backbone Lines
        foreach ($odps as $o) {
            $totalUnderChild = $odpTotalClients[$o->id] ?? 0;
            $onlineUnderChild = $odpOnlineClients[$o->id] ?? 0;

            // Feeder Line: Parent ODC / ODP / HTB ➔ Child ODP / HTB
            if (!empty($o->parent_odp_id) && isset($odpMap[$o->parent_odp_id])) {
                $parent = $odpMap[$o->parent_odp_id];
                if ($parent['lat'] && $parent['lng'] && $o->lat && $o->lng) {
                    $isFeederCut = ($totalUnderChild > 0 && $onlineUnderChild === 0);
                    $isFeederConnected = !$isFeederCut;
                    $status = $isFeederCut ? 'cut' : ($totalUnderChild === 0 ? 'standby' : 'active');

                    $connections[] = [
                        'id' => "line-feeder-p{$parent['id']}-c{$o->id}",
                        'category' => 'feeder',
                        'source_type' => $parent['type'] ?? 'odc',
                        'source_id' => $parent['id'],
                        'source_name' => $parent['name'],
                        'target_type' => $o->type ?? 'odp',
                        'target_id' => $o->id,
                        'target_name' => $o->name,
                        'source_lat' => (float) $parent['lat'],
                        'source_lng' => (float) $parent['lng'],
                        'target_lat' => (float) $o->lat,
                        'target_lng' => (float) $o->lng,
                        'odp_id' => $parent['id'],
                        'odp_name' => $parent['name'],
                        'customer_id' => null,
                        'customer_name' => "{$parent['name']} ➔ {$o->name}",
                        'customer_pppoe' => "Feeder Trunk ({$onlineUnderChild}/{$totalUnderChild} Online)",
                        'status' => $status,
                        'is_connected' => $isFeederConnected,
                        'odp_lat' => (float) $parent['lat'],
                        'odp_lng' => (float) $parent['lng'],
                        'waypoints' => $o->cable_path ?? [],
                        'online_clients' => $onlineUnderChild,
                        'total_clients' => $totalUnderChild,
                    ];
                }
            } elseif (!empty($o->router_id) || empty($o->parent_odp_id)) {
                // Backbone Line: Router NOC ➔ ODC / HTB Utama
                $targetRouterId = !empty($o->router_id) ? $o->router_id : ($routerList->first()['id'] ?? null);
                $routerObj = $targetRouterId ? $routerList->firstWhere('id', $targetRouterId) : null;
                if (!$routerObj && $routerList->isNotEmpty()) {
                    $routerObj = $routerList->first();
                }

                if ($routerObj && !empty($routerObj['lat']) && !empty($routerObj['lng']) && $o->lat && $o->lng) {
                    $isBackboneCut = ($totalUnderChild > 0 && $onlineUnderChild === 0);
                    $isBackboneConnected = !$isBackboneCut;
                    $status = $isBackboneCut ? 'cut' : ($totalUnderChild === 0 ? 'standby' : 'active');

                    $connections[] = [
                        'id' => "line-backbone-r{$routerObj['id']}-o{$o->id}",
                        'category' => 'backbone',
                        'source_type' => 'router',
                        'source_id' => $routerObj['id'],
                        'source_name' => $routerObj['name'],
                        'target_type' => $o->type ?? 'odc',
                        'target_id' => $o->id,
                        'target_name' => $o->name,
                        'source_lat' => (float) $routerObj['lat'],
                        'source_lng' => (float) $routerObj['lng'],
                        'target_lat' => (float) $o->lat,
                        'target_lng' => (float) $o->lng,
                        'odp_id' => $o->id,
                        'odp_name' => $routerObj['name'],
                        'customer_id' => null,
                        'customer_name' => "{$routerObj['name']} ➔ {$o->name}",
                        'customer_pppoe' => "Backbone NOC ({$onlineUnderChild}/{$totalUnderChild} Online)",
                        'status' => $status,
                        'is_connected' => $isBackboneConnected,
                        'odp_lat' => (float) $routerObj['lat'],
                        'odp_lng' => (float) $routerObj['lng'],
                        'waypoints' => $o->cable_path ?? [],
                        'online_clients' => $onlineUnderChild,
                        'total_clients' => $totalUnderChild,
                    ];
                }
            }
        }

        $onusMarkers = $onus->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'serial' => $o->serial_number,
            'lat' => (float) $o->lat,
            'lng' => (float) $o->lng,
            'odp' => $o->odp?->name,
            'odp_id' => $o->odp_id,
            'customer' => $o->customer?->name,
            'customer_id' => $o->customer_id,
        ]);

        $allCustomers = $allCustomersRaw->map(function ($c) use ($hasOdpCol, $hasCablePathCol, $customerTelemetryMap) {
            $telemetry = $customerTelemetryMap[$c->id] ?? [];
            return [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'pppoe_username' => $c->pppoe_username,
                'ip_address' => $telemetry['ip_address'] ?? ($c->ip_address ?: '-'),
                'mac_address' => $telemetry['mac_address'] ?? ($c->mac_address ?: '-'),
                'is_online' => $telemetry['is_online'] ?? false,
                'uptime' => $telemetry['uptime'] ?? '-',
                'rx_bytes' => $telemetry['rx_bytes'] ?? '0 B',
                'tx_bytes' => $telemetry['tx_bytes'] ?? '0 B',
                'total_bytes' => $telemetry['total_bytes'] ?? '0 B',
                'address' => $c->address,
                'phone' => $c->phone,
                'odp_id' => $hasOdpCol ? $c->odp_id : null,
                'odp_name' => ($hasOdpCol && $c->odp) ? $c->odp->name : null,
                'odp_port' => $hasOdpCol ? $c->odp_port : null,
                'cable_path' => $hasCablePathCol ? ($c->cable_path ?? []) : [],
                'lat' => (float) ($c->lat ?? 0),
                'lng' => (float) ($c->lng ?? 0),
                'status' => $c->status,
                'package_name' => $c->package?->name,
                'router_id' => $c->router_id,
                'router_name' => $c->router?->name ?? 'MikroTik Gateway',
            ];
        });

        $authUser = auth()->user();
        $isAdminRole = session('superadmin_logged_in') || session('admin_logged_in') || ($authUser && in_array(strtolower((string) $authUser->role), ['admin', 'superadmin', 'operator', 'owner']));
        $isStaffReadOnly = session('technician_logged_in') || session('collector_logged_in') || ($authUser && in_array(strtolower((string) $authUser->role), ['technician', 'teknisi', 'collector', 'kolektor', 'cashier', 'kasir', 'customer', 'pelanggan']));
        $readOnly = !$isAdminRole || $isStaffReadOnly;

        return [
            'routers' => $routerList instanceof \Illuminate\Support\Collection ? $routerList->values()->all() : array_values((array) $routerList),
            'odps' => $odpList instanceof \Illuminate\Support\Collection ? $odpList->values()->all() : array_values((array) $odpList),
            'onusMarkers' => $onusMarkers instanceof \Illuminate\Support\Collection ? $onusMarkers->values()->all() : array_values((array) $onusMarkers),
            'odpMarkers' => $odpList instanceof \Illuminate\Support\Collection ? $odpList->values()->all() : array_values((array) $odpList),
            'customerMarkers' => array_values($customerMarkers),
            'allCustomers' => $allCustomers instanceof \Illuminate\Support\Collection ? $allCustomers->values()->all() : array_values((array) $allCustomers),
            'connections' => array_values($connections),
            'create' => (bool) request('create'),
            'readOnly' => (bool) $readOnly,
        ];
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function updateCoords(Request $request)
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $type = $request->input('type');
            $id = $request->input('id');
            $rawLat = $request->input('lat');
            $rawLng = $request->input('lng');
            $cleanLat = is_string($rawLat) ? str_replace(',', '.', trim($rawLat)) : $rawLat;
            $cleanLng = is_string($rawLng) ? str_replace(',', '.', trim($rawLng)) : $rawLng;
            $lat = ($cleanLat !== null && $cleanLat !== '' && $cleanLat !== 'null' && is_numeric($cleanLat)) ? (float) $cleanLat : null;
            $lng = ($cleanLng !== null && $cleanLng !== '' && $cleanLng !== 'null' && is_numeric($cleanLng)) ? (float) $cleanLng : null;

            if ($type === 'router') {
                $router = \App\Models\Mikrotik::withoutGlobalScopes()->find($id);

                if ($router) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('mikrotiks', 'lat')) {
                        \Illuminate\Support\Facades\Schema::table('mikrotiks', function ($table) {
                            $table->decimal('lat', 10, 7)->nullable()->after('password');
                        });
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('mikrotiks', 'lng')) {
                        \Illuminate\Support\Facades\Schema::table('mikrotiks', function ($table) {
                            $table->decimal('lng', 10, 7)->nullable()->after('lat');
                        });
                    }

                    $updateData = [];
                    if ($tenantId && (empty($router->tenant_id) || $router->tenant_id == 0)) {
                        $updateData['tenant_id'] = $tenantId;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('mikrotiks', 'lat')) {
                        $updateData['lat'] = $lat;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('mikrotiks', 'lng')) {
                        $updateData['lng'] = $lng;
                    }

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        \Illuminate\Support\Facades\DB::table('mikrotiks')->where('id', $id)->update($updateData);
                        $router->refresh();
                    }

                    \Illuminate\Support\Facades\Cache::forget("router_map_{$router->id}");
                    $this->clearMapCache();

                    return response()->json([
                        'success' => true,
                        'message' => "Posisi Router NOC \"{$router->name}\" berhasil dipasang.",
                        'router' => [
                            'id' => $router->id,
                            'name' => $router->name,
                            'host' => $router->host,
                            'lat' => $lat,
                            'lng' => $lng,
                            'location' => $router->location ?? 'NOC / Server Utama',
                        ],
                    ]);
                }
                return response()->json(['success' => false, 'message' => 'Router tidak ditemukan.'], 404);
            } elseif ($type === 'customer') {
                $hasOdpCol = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'odp_id');
                $customerRelations = ['package'];
                if ($hasOdpCol) {
                    $customerRelations[] = 'odp';
                }
                $cust = \App\Models\Customer::withoutGlobalScopes()->with($customerRelations)->find($id);

                if ($cust) {
                    $updateData = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'updated_at' => now(),
                    ];
                    if ($tenantId && (empty($cust->tenant_id) || $cust->tenant_id == 0)) {
                        $updateData['tenant_id'] = $tenantId;
                    }
                    if ($hasOdpCol && $request->has('odp_id')) {
                        $odpVal = $request->input('odp_id');
                        $updateData['odp_id'] = (!empty($odpVal) && $odpVal !== 'null') ? (int) $odpVal : null;
                    }
                    if ($hasOdpCol && $request->has('odp_port')) {
                        $portVal = $request->input('odp_port');
                        $updateData['odp_port'] = (!empty($portVal) && $portVal !== 'null') ? (int) $portVal : null;
                    }
                    if ($request->has('address')) {
                        $updateData['address'] = $request->input('address');
                    }

                    // Validasi bentrok port ODP: pastikan port tidak dipakai pelanggan lain pada ODP yang sama
                    $targetOdpId = array_key_exists('odp_id', $updateData) ? $updateData['odp_id'] : $cust->odp_id;
                    $targetOdpPort = array_key_exists('odp_port', $updateData) ? $updateData['odp_port'] : $cust->odp_port;

                    if (!empty($targetOdpId) && !empty($targetOdpPort)) {
                        $existingPortCust = \App\Models\Customer::withoutGlobalScopes()
                            ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                            ->where('odp_id', $targetOdpId)
                            ->where('odp_port', $targetOdpPort)
                            ->where('id', '!=', $id)
                            ->first();

                        if ($existingPortCust) {
                            return response()->json([
                                'success' => false,
                                'message' => "Port {$targetOdpPort} pada ODP ini sudah digunakan oleh pelanggan '{$existingPortCust->name}'. Silakan pilih port lain.",
                            ], 422);
                        }
                    }

                    \Illuminate\Support\Facades\DB::table('customers')->where('id', $id)->update($updateData);
                    $cust->refresh();
                    $cust->load($customerRelations);

                    return response()->json([
                        'success' => true,
                        'message' => "Data pelanggan {$cust->name} berhasil diperbarui.",
                        'customer' => [
                            'id' => $cust->id,
                            'name' => $cust->name,
                            'code' => $cust->code,
                            'pppoe_username' => $cust->pppoe_username,
                            'ip_address' => $cust->ip_address ?: '-',
                            'mac_address' => $cust->mac_address ?: '-',
                            'connection_type' => strtoupper($cust->connection_type ?? 'PPPOE'),
                            'uptime' => 'Online',
                            'is_online' => true,
                            'rx_bytes' => '0 B',
                            'tx_bytes' => '0 B',
                            'total_bytes' => '0 B',
                            'rx_bytes_raw' => 0,
                            'address' => $cust->address,
                            'phone' => $cust->phone,
                            'email' => $cust->email,
                            'package_id' => $cust->package_id,
                            'package_name' => $cust->package?->name,
                            'package_price' => (float) ($cust->package?->price ?? 0),
                            'status' => 'active',
                            'isolation_date' => $cust->isolation_date ?? '20',
                            'unpaid_invoices' => 0,
                            'lat' => $lat,
                            'lng' => $lng,
                            'odp_id' => $cust->odp_id,
                            'odp_name' => $cust->odp?->name,
                            'odp_port' => $cust->odp_port,
                            'odp_lat' => (float) ($cust->odp?->lat ?? 0),
                            'odp_lng' => (float) ($cust->odp?->lng ?? 0),
                            'cable_path' => $cust->cable_path ?? [],
                            'rx_power' => ($cust->onu && $cust->onu->rx_power !== null) ? (string) $cust->onu->rx_power : null,
                            'tx_power' => ($cust->onu && $cust->onu->tx_power !== null) ? (string) $cust->onu->tx_power : null,
                            'onu_serial' => $cust->onu?->serial_number ?? $cust->serial_number ?? null,
                            'olt_name' => $cust->onu?->olt?->name ?? null,
                            'pon_port' => $cust->onu?->pon_port ?? null,
                        ],
                    ]);
                }
            } elseif ($type === 'odp') {
                $odp = \App\Models\OdpLocation::withoutGlobalScopes()->with(['parent', 'router'])->find($id);

                if ($odp) {
                    $updateData = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'updated_at' => now(),
                    ];
                    if ($tenantId && (empty($odp->tenant_id) || $odp->tenant_id == 0)) {
                        $updateData['tenant_id'] = $tenantId;
                    }
                    if ($request->has('type') && \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'type')) {
                        $updateData['type'] = $request->input('type');
                    }
                    if ($request->has('network_mode') && \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'network_mode')) {
                        $updateData['network_mode'] = $request->input('network_mode');
                    }
                    if ($request->has('parent_odp_id') && \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'parent_odp_id')) {
                        $updateData['parent_odp_id'] = $request->input('parent_odp_id') ?: null;
                    }
                    if ($request->has('router_id') && \Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'router_id')) {
                        $updateData['router_id'] = $request->input('router_id') ?: null;
                    }
                    if ($request->has('capacity')) {
                        $updateData['capacity'] = (int) $request->input('capacity');
                    }

                    \Illuminate\Support\Facades\DB::table('odp_locations')->where('id', $id)->update($updateData);
                    $odp->refresh();
                    $odp->load(['parent', 'router']);
                    $this->clearMapCache();

                    return response()->json([
                        'success' => true,
                        'message' => "Titik Distribusi {$odp->name} berhasil diperbarui.",
                        'odp' => $odp,
                    ]);
                }
            }

            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Gagal update koordinat: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui posisi: ' . $e->getMessage()], 500);
        }
    }

    public function updateCablePath(Request $request)
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $customerId = $request->input('customer_id');
            $odpId = $request->input('odp_id');
            $waypoints = $request->input('waypoints', []);

            $cleanWaypoints = [];
            if (is_array($waypoints)) {
                foreach ($waypoints as $pt) {
                    if (is_array($pt) && count($pt) >= 2) {
                        $cleanWaypoints[] = [
                            (float) number_format((float) $pt[0], 6, '.', ''),
                            (float) number_format((float) $pt[1], 6, '.', ''),
                        ];
                    }
                }
            }

            if ($odpId) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'cable_path')) {
                    \Illuminate\Support\Facades\Schema::table('odp_locations', function ($table) {
                        $table->json('cable_path')->nullable()->after('capacity');
                    });
                }

                $odp = \App\Models\OdpLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($odpId);
                if (!$odp) {
                    return response()->json(['success' => false, 'message' => 'Titik distribusi tidak ditemukan.'], 404);
                }
                $odp->update(['cable_path' => $cleanWaypoints]);
                $this->clearMapCache();
                return response()->json([
                    'success' => true,
                    'message' => "Jalur rute kabel feeder {$odp->name} berhasil disimpan.",
                    'cable_path' => $cleanWaypoints,
                ]);
            }

            if ($customerId) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('customers', 'cable_path')) {
                    \Illuminate\Support\Facades\Schema::table('customers', function ($table) {
                        $table->json('cable_path')->nullable()->after('address');
                    });
                }

                $cust = \App\Models\Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($customerId);
                if (!$cust) {
                    return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 404);
                }

                $cust->update(['cable_path' => $cleanWaypoints]);
                $this->clearMapCache();

                return response()->json([
                    'success' => true,
                    'message' => "Jalur kabel pelanggan {$cust->name} berhasil disimpan.",
                    'cable_path' => $cleanWaypoints,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Target kabel tidak valid.'], 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Gagal update jalur kabel: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan jalur kabel: ' . $e->getMessage()], 500);
        }
    }

    public function pingCustomer(Request $request)
    {
        $customerId = $request->input('customer_id');
        $ip = $request->input('ip');
        $customer = null;

        if ($customerId) {
            $customer = \App\Models\Customer::withoutGlobalScopes()->with('router')->find($customerId);
        }

        $tenantId = $customer?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        // 1. Kumpulkan semua router kandidat milik tenant
        $candidateRouters = [];
        if ($customer && $customer->router && $customer->router->is_active) {
            $candidateRouters[] = $customer->router;
        }

        $tenantRouters = \App\Models\Mikrotik::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->get();

        foreach ($tenantRouters as $tr) {
            if (!in_array($tr->id, array_column($candidateRouters, 'id'))) {
                $candidateRouters[] = $tr;
            }
        }

        if (empty($candidateRouters)) {
            $candidateRouters = \App\Models\Mikrotik::withoutGlobalScopes()->where('is_active', true)->get()->all();
        }

        $parseTime = function ($val): ?float {
            if ($val === null || $val === '') return null;
            $str = trim((string) $val);

            // Format HH:MM:SS.mmmmmm e.g. "00:00:00.002", "00:00:00.000350"
            if (preg_match('/^(?:(\d+):)?(\d+):([0-9.]+)$/', $str, $m)) {
                $sec = (float) $m[3];
                $min = (int) $m[2];
                $hr = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
                $totalMs = ($hr * 3600 + $min * 60 + $sec) * 1000;
                return round($totalMs, 3);
            }

            // Composite format e.g. "1s200ms", "5ms350us", "350us", "1.5ms"
            $totalMs = 0;
            $hasMatch = false;

            if (preg_match('/([0-9.]+)\s*s(?![a-z])/i', $str, $m)) {
                $totalMs += (float) $m[1] * 1000;
                $hasMatch = true;
            }
            if (preg_match('/([0-9.]+)\s*ms/i', $str, $m)) {
                $totalMs += (float) $m[1];
                $hasMatch = true;
            }
            if (preg_match('/([0-9.]+)\s*(?:us|µs)/i', $str, $m)) {
                $totalMs += (float) $m[1] / 1000;
                $hasMatch = true;
            }

            if ($hasMatch) {
                return round($totalMs, 3);
            }

            if (is_numeric($str)) {
                return round((float) $str, 3);
            }

            return null;
        };

        // 2. Cari sesi aktif pelanggan di seluruh router kandidat
        $matchedRouter = null;
        $matchedMik = null;
        $activeSession = null;
        $sessionType = 'pppoe';

        $cUser = $customer?->pppoe_username ? trim($customer->pppoe_username) : '';
        $cUserLower = strtolower($cUser);
        $cIp = (!empty($ip) && $ip !== '-') ? trim($ip) : ($customer?->ip_address ? trim($customer->ip_address) : '');
        $cMac = $customer?->mac_address ? strtolower(str_replace([':', '-', '.'], '', $customer->mac_address)) : '';
        $cCode = $customer?->code ? strtolower(trim($customer->code)) : '';
        $cName = $customer?->name ? strtolower(trim($customer->name)) : '';

        foreach ($candidateRouters as $r) {
            try {
                $mik = new \App\Services\MikrotikService($r);
                if (!$mik->isConnected()) {
                    continue;
                }

                // Cek PPP Active
                $actives = $mik->query('/ppp/active/print');
                if (is_array($actives)) {
                    foreach ($actives as $act) {
                        $uname = $act['name'] ?? '';
                        $uLower = strtolower(trim($uname));
                        $actIp = $act['address'] ?? '';
                        $actMac = !empty($act['caller-id']) ? strtolower(str_replace([':', '-', '.'], '', $act['caller-id'])) : '';

                        $isMatch = ($cUser && ($uLower === $cUserLower || $uname === $cUser))
                            || ($cIp && $actIp === $cIp)
                            || ($cMac && $actMac === $cMac)
                            || ($cCode && $uLower === $cCode)
                            || ($cName && $uLower === $cName);

                        if ($isMatch) {
                            $matchedRouter = $r;
                            $matchedMik = $mik;
                            $activeSession = $act;
                            $sessionType = $act['service'] ?? 'pppoe';
                            if ($actIp) {
                                $cIp = $actIp;
                                if ($customer && $customer->ip_address !== $actIp) {
                                    $customer->update(['ip_address' => $actIp]);
                                }
                            }
                            break 2;
                        }
                    }
                }

                // Cek Hotspot Active
                $hotspots = $mik->query('/ip/hotspot/active/print');
                if (is_array($hotspots)) {
                    foreach ($hotspots as $hs) {
                        $user = $hs['user'] ?? '';
                        $uLower = strtolower(trim($user));
                        $hsIp = $hs['address'] ?? '';
                        $hsMac = !empty($hs['mac-address']) ? strtolower(str_replace([':', '-', '.'], '', $hs['mac-address'])) : '';

                        $isMatch = ($cUser && ($uLower === $cUserLower || $user === $cUser))
                            || ($cIp && $hsIp === $cIp)
                            || ($cMac && $hsMac === $cMac)
                            || ($cCode && $uLower === $cCode)
                            || ($cName && $uLower === $cName);

                        if ($isMatch) {
                            $matchedRouter = $r;
                            $matchedMik = $mik;
                            $activeSession = $hs;
                            $sessionType = 'hotspot';
                            if ($hsIp) {
                                $cIp = $hsIp;
                                if ($customer && $customer->ip_address !== $hsIp) {
                                    $customer->update(['ip_address' => $hsIp]);
                                }
                            }
                            break 2;
                        }
                    }
                }

                // Simpan router pertama yang terkoneksi sebagai router default bila sesi offline
                if (!$matchedMik) {
                    $matchedRouter = $r;
                    $matchedMik = $mik;
                }
            } catch (\Throwable $e) {
                // Lanjutkan ke router berikutnya
            }
        }

        $targetIp = $cIp ?: ($customer?->ip_address ?: null);

        // 3. Jalankan Ping dari MikroTik router
        if ($matchedMik && !empty($targetIp) && $targetIp !== '-' && $targetIp !== '127.0.0.1') {
            try {
                $pingRows = $matchedMik->query('/ping', [
                    'address' => $targetIp,
                    'count' => '4',
                ]);

                \Illuminate\Support\Facades\Log::info("[PING DEBUG] Router: {$matchedRouter->name}, IP: {$targetIp}, Customer: {$customer?->name}, Rows: " . json_encode($pingRows));

                if (!empty($pingRows)) {
                    $latencies = [];
                    $received = 0;
                    $sent = 0;
                    $summaryLoss = null;

                    foreach ($pingRows as $row) {
                        if (!is_array($row)) continue;

                        if (isset($row['sent']) && isset($row['received'])) {
                            $sent = max($sent, (int) $row['sent']);
                            $received = max($received, (int) $row['received']);
                            if (isset($row['packet-loss'])) {
                                $summaryLoss = (float) str_replace('%', '', (string) $row['packet-loss']);
                            }
                            if (isset($row['avg-rtt']) && empty($latencies)) {
                                $avgT = $parseTime($row['avg-rtt']);
                                if ($avgT !== null) $latencies[] = $avgT;
                            }
                            continue;
                        }

                        $sent++;
                        $timeFound = null;
                        foreach (['time', 'rtt', 'avg-rtt', 'round-trip-time'] as $tk) {
                            if (isset($row[$tk])) {
                                $timeFound = $parseTime($row[$tk]);
                                if ($timeFound !== null) break;
                            }
                        }

                        $statusStr = isset($row['status']) ? strtolower((string)$row['status']) : '';
                        $isTimeout = in_array($statusStr, ['timeout', 'timed out', 'host unreachable', 'net unreachable']);

                        if ($timeFound !== null && !$isTimeout) {
                            $latencies[] = $timeFound;
                            $received++;
                        }
                    }

                    if ($sent === 0) {
                        $sent = count($pingRows) ?: 4;
                    }

                    if ($received > 0 && !empty($latencies)) {
                        $min = min($latencies);
                        $max = max($latencies);
                        $avg = round(array_sum($latencies) / count($latencies), 2);
                        $loss = $summaryLoss !== null ? $summaryLoss : ($sent > 0 ? round((($sent - $received) / $sent) * 100) : 0);

                        $jitter = 0.5;
                        if (count($latencies) > 1) {
                            $diffs = [];
                            for ($i = 1; $i < count($latencies); $i++) {
                                $diffs[] = abs($latencies[$i] - $latencies[$i - 1]);
                            }
                            $jitter = round(array_sum($diffs) / count($diffs), 2);
                        }

                        $quality = 'Sangat Stabil (Optimal)';
                        if ($loss > 20 || $jitter > 15 || $avg > 80) {
                            $quality = 'Tinggi / Tidak Stabil';
                        } elseif ($loss > 0 || $jitter > 4 || $avg > 40) {
                            $quality = 'Cukup Baik';
                        }

                        return response()->json([
                            'success' => true,
                            'ip' => $targetIp,
                            'target' => ($customer?->name ?? 'Pelanggan') . ' (' . ($customer?->pppoe_username ?: $targetIp) . ')',
                            'transmitted' => $sent,
                            'received' => $received,
                            'loss_percent' => $loss,
                            'min_ms' => $min,
                            'avg_ms' => $avg,
                            'max_ms' => $max,
                            'jitter_ms' => $jitter,
                            'samples' => $latencies,
                            'quality' => $quality,
                            'status' => 'ONLINE',
                            'source' => 'MikroTik Gateway (' . $matchedRouter->name . ')',
                            'timestamp' => now()->format('H:i:s'),
                        ]);
                    } else {
                        // Sesi aktif di MikroTik tetapi ICMP Echo Request diblokir oleh ONT/CPE
                        $sessionUptime = $activeSession['uptime'] ?? null;
                        $qualityMsg = $activeSession
                            ? 'Sesi ' . strtoupper($sessionType) . ' Aktif' . ($sessionUptime ? " ({$sessionUptime})" : '') . ' · ICMP Ping diblokir Firewall/ONT Pelanggan'
                            : 'Host Tidak Merespon (RTO) · Pelanggan Offline';

                        return response()->json([
                            'success' => true,
                            'ip' => $targetIp,
                            'target' => ($customer?->name ?? 'Pelanggan') . ' (' . ($customer?->pppoe_username ?: $targetIp) . ')',
                            'transmitted' => $sent ?: 4,
                            'received' => 0,
                            'loss_percent' => 100,
                            'min_ms' => 0,
                            'avg_ms' => 0,
                            'max_ms' => 0,
                            'jitter_ms' => 0,
                            'samples' => [],
                            'quality' => $qualityMsg,
                            'status' => $activeSession ? 'ONLINE' : 'RTO',
                            'source' => 'MikroTik Gateway (' . ($matchedRouter?->name ?? 'Router') . ')',
                            'timestamp' => now()->format('H:i:s'),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("MikroTik Direct Ping Exception: " . $e->getMessage());
            }
        }

        if (empty($targetIp) || $targetIp === '-' || $targetIp === '127.0.0.1') {
            return response()->json([
                'success' => false,
                'ip' => '-',
                'target' => $customer?->name ?? 'Pelanggan',
                'transmitted' => 4,
                'received' => 0,
                'loss_percent' => 100,
                'min_ms' => 0,
                'avg_ms' => 0,
                'max_ms' => 0,
                'jitter_ms' => 0,
                'samples' => [],
                'quality' => 'IP Pelanggan Belum Terdeteksi / Sesi Offline di MikroTik',
                'status' => 'OFFLINE',
                'timestamp' => now()->format('H:i:s'),
            ]);
        }

        // 4. Fallback ke ICMP Ping server lokal bila router tidak dapat dihubungi
        $count = 4;
        $escapedIp = escapeshellarg($targetIp);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWindows 
            ? "ping -n {$count} -w 1000 {$escapedIp}" 
            : "ping -c {$count} -W 1 {$escapedIp} 2>&1";

        $output = [];
        $returnVar = 0;
        @exec($cmd, $output, $returnVar);

        $latencies = [];
        foreach ($output as $line) {
            if (preg_match('/time[=<]([0-9.]+)\s*ms/i', $line, $matches)) {
                $latencies[] = (float) $matches[1];
            }
        }

        $received = count($latencies);
        $loss = $count > 0 ? round((($count - $received) / $count) * 100) : 100;

        if ($received > 0) {
            $min = min($latencies);
            $max = max($latencies);
            $avg = round(array_sum($latencies) / $received, 2);

            $jitter = 0.5;
            if ($received > 1) {
                $diffs = [];
                for ($i = 1; $i < $received; $i++) {
                    $diffs[] = abs($latencies[$i] - $latencies[$i - 1]);
                }
                $jitter = round(array_sum($diffs) / count($diffs), 2);
            }

            $quality = 'Sangat Stabil (Optimal)';
            if ($jitter > 8 || $avg > 80) {
                $quality = 'Tinggi / Tidak Stabil';
            } elseif ($jitter > 4 || $avg > 40) {
                $quality = 'Cukup Baik';
            }

            return response()->json([
                'success' => true,
                'ip' => $targetIp,
                'target' => $customer?->name ?? $targetIp,
                'transmitted' => $count,
                'received' => $received,
                'loss_percent' => $loss,
                'min_ms' => $min,
                'avg_ms' => $avg,
                'max_ms' => $max,
                'jitter_ms' => $jitter,
                'samples' => $latencies,
                'quality' => $quality,
                'status' => 'ONLINE',
                'source' => 'ICMP Local Server',
                'timestamp' => now()->format('H:i:s'),
            ]);
        } else {
            return response()->json([
                'success' => true,
                'ip' => $targetIp,
                'target' => $customer?->name ?? $targetIp,
                'transmitted' => $count,
                'received' => 0,
                'loss_percent' => 100,
                'min_ms' => 0,
                'avg_ms' => 0,
                'max_ms' => 0,
                'jitter_ms' => 0,
                'samples' => [],
                'quality' => 'Host Tidak Merespon (RTO)',
                'status' => 'RTO',
                'source' => 'ICMP Local Server',
                'timestamp' => now()->format('H:i:s'),
            ]);
        }
    }

    public function odp()
    {
        return redirect('/admin/map');
    }

    public function addOdp(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'type' => 'nullable|string',
                'network_mode' => 'nullable|string',
                'lat' => 'nullable',
                'lng' => 'nullable',
                'capacity' => 'nullable|integer|min:1|max:256',
                'parent_odp_id' => 'nullable',
                'router_id' => 'nullable',
                'cable_path' => 'nullable',
            ]);

            $type = in_array(strtolower($validated['type'] ?? ''), ['odc', 'odp', 'odp_modular', 'odp_ratio', 'ratio', 'modular', 'htb', 'switch', 'olt', 'server'])
                ? strtolower($validated['type'])
                : 'odp';

            $netMode = in_array(strtolower($validated['network_mode'] ?? ''), ['pon', 'lan'])
                ? strtolower($validated['network_mode'])
                : ($type === 'htb' || $type === 'switch' ? 'lan' : 'pon');

            $rawLat = $request->input('lat');
            $rawLng = $request->input('lng');
            $cleanLat = is_string($rawLat) ? str_replace(',', '.', trim($rawLat)) : $rawLat;
            $cleanLng = is_string($rawLng) ? str_replace(',', '.', trim($rawLng)) : $rawLng;
            $lat = is_numeric($cleanLat) ? (float) $cleanLat : -6.200000;
            $lng = is_numeric($cleanLng) ? (float) $cleanLng : 106.816666;
            $capacity = !empty($validated['capacity']) ? (int) $validated['capacity'] : ($type === 'odc' ? 24 : 8);

            // Verifikasi parent_odp_id ada di database untuk mencegah MySQL foreign key violation crash
            $parentId = null;
            $rawParent = $request->input('parent_odp_id');
            if (!empty($rawParent) && $rawParent !== 'null' && is_numeric($rawParent)) {
                $candParent = (int) $rawParent;
                if ($candParent > 0 && \App\Models\OdpLocation::where('id', $candParent)->exists()) {
                    $parentId = $candParent;
                }
            }

            // Verifikasi router_id ada di database untuk mencegah foreign key violation crash
            $routerId = null;
            $rawRouter = $request->input('router_id');
            if (!empty($rawRouter) && $rawRouter !== 'null' && is_numeric($rawRouter)) {
                $candRouter = (int) $rawRouter;
                if ($candRouter > 0 && \App\Models\Mikrotik::where('id', $candRouter)->exists()) {
                    $routerId = $candRouter;
                }
            }
            if ($parentId === null && $routerId === null) {
                $routerId = \App\Models\Mikrotik::value('id');
            }

            $cablePath = null;
            if ($request->has('cable_path')) {
                $rawCable = $request->input('cable_path');
                $cablePath = is_array($rawCable) ? $rawCable : (is_string($rawCable) ? json_decode($rawCable, true) : null);
            }

            $insertData = [
                'name' => trim($validated['name']),
                'lat' => $lat,
                'lng' => $lng,
                'capacity' => $capacity,
                'used_ports' => 0,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'type')) {
                $insertData['type'] = $type;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'network_mode')) {
                $insertData['network_mode'] = $netMode;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'parent_odp_id')) {
                $insertData['parent_odp_id'] = $parentId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'router_id')) {
                $insertData['router_id'] = $routerId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'tenant_id')) {
                $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
                if ($tenantId) {
                    $insertData['tenant_id'] = $tenantId;
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'cable_path') && $cablePath !== null) {
                $insertData['cable_path'] = $cablePath;
            }

            $odp = \App\Models\OdpLocation::create($insertData);
            $odp->load(['parent', 'router']);
            $this->clearMapCache();

            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json([
                    'success' => true,
                    'message' => 'Titik distribusi jaringan (' . strtoupper($type) . ' - ' . $odp->name . ') berhasil ditambahkan.',
                    'odp' => [
                        'id' => $odp->id,
                        'name' => $odp->name,
                        'type' => $odp->type ?? $type,
                        'network_mode' => $odp->network_mode ?? $netMode,
                        'lat' => (float) ($odp->lat ?? 0),
                        'lng' => (float) ($odp->lng ?? 0),
                        'onus_count' => 0,
                        'customers_count' => 0,
                        'used_ports' => 0,
                        'capacity' => (int) ($odp->capacity ?? $capacity),
                        'available_ports' => (int) ($odp->capacity ?? $capacity),
                        'parent_odp_id' => $odp->parent_odp_id,
                        'parent_name' => $odp->parent?->name,
                        'router_id' => $odp->router_id,
                        'router_name' => $odp->router?->name,
                        'cable_path' => $odp->cable_path ?? [],
                        'total_clients' => 0,
                        'online_clients' => 0,
                        'is_full' => false,
                        'is_critical' => false,
                        'is_available' => true,
                    ],
                ]);
            }

            return back()->with('msg', 'Titik distribusi jaringan (' . strtoupper($type) . ' - ' . $odp->name . ') berhasil ditambahkan.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json(['success' => false, 'message' => 'Data tidak valid: ' . implode(' ', $ve->validator->errors()->all())], 422);
            }
            return back()->withErrors($ve->validator)->with('error', 'Data titik ODP tidak valid. ' . implode(' ', $ve->validator->errors()->all()));
        } catch (\Throwable $e) {
            Log::error('Gagal menambahkan titik ODP: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json(['success' => false, 'message' => 'Gagal menyimpan titik distribusi: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menyimpan titik distribusi: ' . $e->getMessage());
        }
    }

    public function editOdp(Request $request, $id)
    {
        try {
            $odp = \App\Models\OdpLocation::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'type' => 'nullable|string',
                'network_mode' => 'nullable|string',
                'lat' => 'nullable',
                'lng' => 'nullable',
                'capacity' => 'nullable|integer|min:1|max:256',
                'parent_odp_id' => 'nullable',
                'router_id' => 'nullable',
                'cable_path' => 'nullable',
            ]);

            $type = in_array(strtolower($validated['type'] ?? ''), ['odc', 'odp', 'odp_modular', 'odp_ratio', 'ratio', 'modular', 'htb', 'switch', 'olt', 'server'])
                ? strtolower($validated['type'])
                : ($odp->type ?? 'odp');

            $netMode = in_array(strtolower($validated['network_mode'] ?? ''), ['pon', 'lan'])
                ? strtolower($validated['network_mode'])
                : ($odp->network_mode ?? ($type === 'htb' || $type === 'switch' ? 'lan' : 'pon'));

            $rawLat = $request->input('lat');
            $rawLng = $request->input('lng');
            $cleanLat = is_string($rawLat) ? str_replace(',', '.', trim($rawLat)) : $rawLat;
            $cleanLng = is_string($rawLng) ? str_replace(',', '.', trim($rawLng)) : $rawLng;
            $lat = is_numeric($cleanLat) ? (float) $cleanLat : $odp->lat;
            $lng = is_numeric($cleanLng) ? (float) $cleanLng : $odp->lng;
            $capacity = !empty($validated['capacity']) ? (int) $validated['capacity'] : $odp->capacity;

            $parentId = null;
            $rawParent = $request->input('parent_odp_id');
            if (!empty($rawParent) && $rawParent !== 'null' && is_numeric($rawParent)) {
                $candParent = (int) $rawParent;
                if ($candParent > 0 && $candParent !== (int) $id && \App\Models\OdpLocation::where('id', $candParent)->exists()) {
                    $parentId = $candParent;
                }
            }

            $routerId = null;
            $rawRouter = $request->input('router_id');
            if (!empty($rawRouter) && $rawRouter !== 'null' && is_numeric($rawRouter)) {
                $candRouter = (int) $rawRouter;
                if ($candRouter > 0 && \App\Models\Mikrotik::where('id', $candRouter)->exists()) {
                    $routerId = $candRouter;
                }
            }
            if ($parentId === null && $routerId === null) {
                $routerId = \App\Models\Mikrotik::value('id');
            }

            $updateData = [
                'name' => trim($validated['name']),
                'lat' => $lat,
                'lng' => $lng,
                'capacity' => $capacity,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'type')) {
                $updateData['type'] = $type;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'network_mode')) {
                $updateData['network_mode'] = $netMode;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'parent_odp_id')) {
                $updateData['parent_odp_id'] = $parentId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'router_id')) {
                $updateData['router_id'] = $routerId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'cable_path') && $request->has('cable_path')) {
                $rawCable = $request->input('cable_path');
                $updateData['cable_path'] = is_array($rawCable) ? $rawCable : (is_string($rawCable) ? json_decode($rawCable, true) : null);
            }

            $odp->update($updateData);
            $odp->refresh();
            $odp->load(['parent', 'router']);
            $this->clearMapCache();

            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data titik distribusi (' . strtoupper($type) . ') berhasil diperbarui.',
                    'odp' => [
                        'id' => $odp->id,
                        'name' => $odp->name,
                        'type' => $odp->type ?? $type,
                        'network_mode' => $odp->network_mode ?? $netMode,
                        'lat' => (float) ($odp->lat ?? 0),
                        'lng' => (float) ($odp->lng ?? 0),
                        'capacity' => (int) ($odp->capacity ?? $capacity),
                        'parent_odp_id' => $odp->parent_odp_id,
                        'parent_name' => $odp->parent?->name,
                        'router_id' => $odp->router_id,
                        'router_name' => $odp->router?->name,
                        'cable_path' => $odp->cable_path ?? [],
                    ],
                ]);
            }

            return back()->with('msg', 'Data titik distribusi (' . strtoupper($type) . ') berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json(['success' => false, 'message' => 'Data tidak valid: ' . implode(' ', $ve->validator->errors()->all())], 422);
            }
            return back()->withErrors($ve->validator)->with('error', 'Data titik ODP tidak valid. ' . implode(' ', $ve->validator->errors()->all()));
        } catch (\Throwable $e) {
            Log::error('Gagal memperbarui titik ODP: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json(['success' => false, 'message' => 'Gagal memperbarui titik distribusi: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal memperbarui titik distribusi: ' . $e->getMessage());
        }
    }

    public function deleteOdp(Request $request, $id)
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $odp = \App\Models\OdpLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($id);
            if (!$odp) {
                if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                    return response()->json(['success' => false, 'message' => 'Titik ODP tidak ditemukan atau sudah dihapus.'], 404);
                }
                return back()->with('error', 'Titik ODP tidak ditemukan atau sudah dihapus.');
            }

            $odpId = $odp->id;

            // 1. Lepaskan relasi children ODP
            if (\Illuminate\Support\Facades\Schema::hasColumn('odp_locations', 'parent_odp_id')) {
                \App\Models\OdpLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('parent_odp_id', $odpId)->update(['parent_odp_id' => null]);
            }

            // 2. Lepaskan relasi pelanggan
            if (\Illuminate\Support\Facades\Schema::hasTable('customers') && \Illuminate\Support\Facades\Schema::hasColumn('customers', 'odp_id')) {
                $custUpdate = ['odp_id' => null];
                if (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'odp_port')) {
                    $custUpdate['odp_port'] = null;
                }
                \App\Models\Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('odp_id', $odpId)->update($custUpdate);
            }

            // 3. Lepaskan relasi ONU locations
            if (\Illuminate\Support\Facades\Schema::hasTable('onu_locations') && \Illuminate\Support\Facades\Schema::hasColumn('onu_locations', 'odp_id')) {
                \App\Models\OnuLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('odp_id', $odpId)->update(['odp_id' => null]);
            }

            $odpName = $odp->name;
            $odp->delete();
            $this->clearMapCache();

            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json([
                    'success' => true,
                    'message' => "Titik ODP / Distribusi \"{$odpName}\" berhasil dihapus.",
                ]);
            }

            return back()->with('msg', "Titik ODP / Distribusi \"{$odpName}\" berhasil dihapus.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal menghapus titik ODP: ' . $e->getMessage(), ['exception' => $e]);
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus titik ODP: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menghapus titik ODP: ' . $e->getMessage());
        }
    }

    public function mikrotik()
    {
        $users = [];
        $profiles = [];
        $active = [];
        $error = null;

        try {
            if (!$this->mikrotik->isConnected()) {
                $error = 'Tidak dapat terhubung ke MikroTik. Silakan cek konfigurasi di Pengaturan.';
            } else {
                $users = $this->mikrotik->getPppoeSecrets();
                $profiles = $this->mikrotik->getPppoeProfiles();
                $active = $this->mikrotik->getActivePppoe();
            }
        } catch (\Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            session()->flash('error', $error);
            Log::error('MikroTik error: ' . $e->getMessage());
        }

        return view('admin.mikrotik', compact('users', 'profiles', 'active', 'error'));
    }

    public function mikrotikProfiles()
    {
        $profiles = [];
        try {
            $profiles = $this->mikrotik->getPppoeProfiles();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return Inertia::render('Admin/MikrotikProfiles', [
            'profiles' => collect($profiles)->map(fn ($p) => is_array($p) ? $p : (is_object($p) ? (array) $p : ['name' => $p])),
        ]);
    }

    public function hotspotProfiles()
    {
        $profiles = [];
        try {
            $profiles = $this->mikrotik->getHotspotProfiles();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return view('admin.hotspot_profiles', compact('profiles'));
    }

    public function mikrotikAction(Request $request)
    {
        $json = $request->json()->all();
        $action = $json['action'] ?? '';

        // Resolve router: pakai router_id dari request; fallback router aktif
        // pertama tenant. Dulu pakai $this->mikrotik (config global per-tenant
        // yang ga pernah ke-set) → SEMUA aksi gagal "tidak dikonfigurasi".
        $router = null;
        if (!empty($json['router_id'])) {
            $router = \App\Models\Mikrotik::withoutGlobalScopes()->find($json['router_id']);
        }
        if (!$router) {
            $router = \App\Models\Mikrotik::where('is_active', true)->first();
        }
        if ($router) {
            $this->mikrotik = new MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) ($router->port ?: 8728),
            ]);
        } else {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan — tambah router dulu.']);
        }

        switch ($action) {
            case 'add_pppoe':
                $username = $json['username'] ?? '';
                $password = $json['password'] ?? '';
                $profile = $json['profile'] ?? 'default';

                if (empty($username) || empty($password)) {
                    return response()->json(['success' => false, 'message' => 'Username dan password wajib diisi']);
                }

                $result = $this->mikrotik->addPppoeSecret($username, $password, $profile);

                if ($result) {
                    return response()->json([
                        'success' => true,
                        'message' => "PPPoE user '{$username}' berhasil ditambahkan dengan profile '{$profile}'"
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menambahkan user: ' . $this->mikrotik->getLastError()
                    ]);
                }

            case 'add_hotspot':
                $username = $json['username'] ?? '';
                $password = $json['password'] ?? '';
                $profile = $json['profile'] ?? 'default';
                $limit_uptime = $json['limit_uptime'] ?? '';

                if (empty($username) || empty($password)) {
                    return response()->json(['success' => false, 'message' => 'Username dan password wajib diisi']);
                }

                $result = $this->mikrotik->addHotspotUser($username, $password, $profile, $limit_uptime);

                if ($result) {
                    return response()->json([
                        'success' => true,
                        'message' => "Hotspot user '{$username}' berhasil ditambahkan"
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menambahkan user: ' . $this->mikrotik->getLastError()
                    ]);
                }

            case 'edit_pppoe':
                $username = $json['username'] ?? '';
                $password = $json['password'] ?? '';
                $profile = $json['profile'] ?? 'default';

                if (empty($username)) {
                    return response()->json(['success' => false, 'message' => 'Username tidak valid']);
                }

                $data = ['profile' => $profile];
                if (!empty($password)) {
                    $data['password'] = $password;
                }

                $result = $this->mikrotik->updatePppoeSecret($username, $data);

                if ($result) {
                    $msg = "PPPoE user '{$username}' berhasil diupdate";
                    if (!empty($password)) {
                        $msg .= " (password diubah)";
                    }
                    return response()->json(['success' => true, 'message' => $msg]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'delete_pppoe':
                $username = $json['username'] ?? '';

                if (empty($username)) {
                    return response()->json(['success' => false, 'message' => 'Username tidak valid']);
                }

                $result = $this->mikrotik->deletePppoeSecret($username);

                if ($result) {
                    return response()->json([
                        'success' => true,
                        'message' => "PPPoE user '{$username}' berhasil dihapus dari MikroTik"
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menghapus user: ' . $this->mikrotik->getLastError()
                    ]);
                }

            case 'edit_hotspot':
                $username = $json['username'] ?? '';
                $password = $json['password'] ?? '';
                $profile = $json['profile'] ?? 'default';
                $limit_uptime = $json['limit_uptime'] ?? '';

                if (empty($username)) {
                    return response()->json(['success' => false, 'message' => 'Username tidak valid']);
                }

                $data = ['profile' => $profile];
                if (!empty($password)) {
                    $data['password'] = $password;
                }
                if (!empty($limit_uptime)) {
                    $data['limit_uptime'] = $limit_uptime;
                }

                $result = $this->mikrotik->updateHotspotUser($username, $data);

                if ($result) {
                    $msg = "Hotspot user '{$username}' berhasil diupdate";
                    if (!empty($password)) {
                        $msg .= " (password diubah)";
                    }
                    return response()->json(['success' => true, 'message' => $msg]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'toggle_pppoe':
            case 'toggle_hotspot':
                $username = $json['username'] ?? '';
                $enabled = $json['enabled'] ?? true;

                if ($action === 'toggle_pppoe') {
                    $result = $enabled ? $this->mikrotik->enablePppoe($username) : $this->mikrotik->disablePppoe($username);
                } else {
                    $result = true;
                }

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Status user '{$username}' berhasil diubah"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'add_pppoe_profile':
                $name = $json['name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile wajib diisi']);
                }

                $rateLimit = $json['rate_limit'] ?? '';
                $localAddress = $json['local_address'] ?? '';
                $remoteAddress = $json['remote_address'] ?? '';

                $result = $this->mikrotik->addPppoeProfile($name, $rateLimit, $localAddress, $remoteAddress);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile PPPoE '{$name}' berhasil ditambahkan"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'edit_pppoe_profile':
                $name = $json['name'] ?? '';
                $originalName = $json['original_name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile tidak valid']);
                }

                $data = [];
                if (isset($json['rate_limit'])) $data['rate_limit'] = $json['rate_limit'];
                if (isset($json['local_address'])) $data['local_address'] = $json['local_address'];
                if (isset($json['remote_address'])) $data['remote_address'] = $json['remote_address'];
                if ($name !== $originalName) $data['name'] = $name;

                $result = $this->mikrotik->updatePppoeProfile($originalName, $data);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile PPPoE '{$name}' berhasil diupdate"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'delete_pppoe_profile':
                $name = $json['name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile tidak valid']);
                }

                $result = $this->mikrotik->deletePppoeProfile($name);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile PPPoE '{$name}' berhasil dihapus"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'add_hotspot_profile':
                $name = $json['name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile wajib diisi']);
                }

                $sharedUsers = (int)($json['shared_users'] ?? 1);
                $rateLimit = $json['rate_limit'] ?? '';

                $result = $this->mikrotik->addHotspotProfile($name, $sharedUsers, $rateLimit);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile Hotspot '{$name}' berhasil ditambahkan"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'edit_hotspot_profile':
                $name = $json['name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile tidak valid']);
                }

                $data = [];
                if (isset($json['shared_users'])) $data['shared_users'] = (int)$json['shared_users'];
                if (isset($json['rate_limit'])) $data['rate_limit'] = $json['rate_limit'];
                if (isset($json['original_name']) && $name !== $json['original_name']) {
                    $data['name'] = $name;
                }

                $result = $this->mikrotik->updateHotspotProfile($name, $data);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile Hotspot '{$name}' berhasil diupdate"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'delete_hotspot_profile':
                $name = $json['name'] ?? '';

                if (empty($name)) {
                    return response()->json(['success' => false, 'message' => 'Nama profile tidak valid']);
                }

                $result = $this->mikrotik->deleteHotspotProfile($name);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "Profile Hotspot '{$name}' berhasil dihapus"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            case 'generate_vouchers':
                $vouchers = $json['vouchers'] ?? [];

                if (empty($vouchers)) {
                    return response()->json(['success' => false, 'message' => 'Tidak ada voucher untuk disimpan']);
                }

                $count = 0;
                $failed = 0;

                foreach ($vouchers as $v) {
                    $result = $this->mikrotik->addHotspotUser($v['username'], $v['password'], $v['profile'], $v['limit_uptime'] ?? '');
                    if ($result) {
                        $count++;
                    } else {
                        $failed++;
                    }
                }

                $message = "Berhasil menyimpan {$count} voucher ke MikroTik";
                if ($failed > 0) {
                    $message .= ", {$failed} gagal";
                }

                return response()->json([
                    'success' => $count > 0,
                    'message' => $message
                ]);

            case 'delete_hotspot_user':
                $username = $json['username'] ?? '';

                if (empty($username)) {
                    return response()->json(['success' => false, 'message' => 'Username tidak valid']);
                }

                $result = $this->mikrotik->deleteHotspotUser($username);

                if ($result) {
                    return response()->json(['success' => true, 'message' => "User Hotspot '{$username}' berhasil dihapus"]);
                } else {
                    return response()->json(['success' => false, 'message' => $this->mikrotik->getLastError()]);
                }

            default:
                return response()->json(['success' => false, 'message' => 'Action tidak dikenal']);
        }
    }

    public function hotspot(Request $request)
    {
        $routers = Mikrotik::where('is_active', true)->orderBy('name')->get();
        $routerId = $request->get('router_id', $routers->first()?->id);
        $users = [];
        $profiles = [];
        $active = [];
        $error = null;

        if ($routerId) {
            $router = Mikrotik::find($routerId);
            if ($router) {
                $forceRefresh = $request->boolean('refresh') || $request->has('refresh');
                $cacheKey = "mikrotik_hotspot_data_{$routerId}";

                if ($forceRefresh) {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }

                $cached = \Illuminate\Support\Facades\Cache::remember($cacheKey, 15, function () use ($router) {
                    $mik = new MikrotikService([
                        'host' => $router->host,
                        'user' => $router->username,
                        'pass' => $router->password ?? '',
                        'port' => (int) $router->port,
                    ]);
                    try {
                        if (!$mik->isConnected()) {
                            return ['error' => 'Tidak dapat terhubung ke ' . $router->name, 'users' => [], 'profiles' => [], 'active' => []];
                        }
                        return [
                            'users' => $mik->getHotspotUsers(),
                            'profiles' => $mik->getHotspotProfiles(),
                            'active' => $mik->getActiveHotspotUsers(),
                            'error' => null,
                        ];
                    } catch (\Exception $e) {
                        Log::error('Hotspot error: ' . $e->getMessage());
                        return ['error' => 'Error: ' . $e->getMessage(), 'users' => [], 'profiles' => [], 'active' => []];
                    }
                });

                $users = $cached['users'] ?? [];
                $profiles = $cached['profiles'] ?? [];
                $active = $cached['active'] ?? [];
                $error = $cached['error'] ?? null;
            }
        }

        return Inertia::render('Admin/Hotspot', [
            'routerId' => $routerId,
            'error' => $error,
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'profiles' => collect($profiles)->map(fn ($p) => is_array($p) ? ($p['name'] ?? $p) : (is_object($p) ? ($p->name ?? $p) : $p)),
            'users' => collect($users)->map(fn ($u) => $u),
            'active' => collect($active)->map(fn ($a) => $a),
        ]);
    }

    public function voucher(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        // 1. Ambil router
        $routers = \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        $selectedRouterId = $request->input('router_id') ? (int) $request->input('router_id') : ($routers->first()->id ?? null);
        $activeRouter = $routers->firstWhere('id', $selectedRouterId);

        $profiles = [];
        $servers = [];
        $mikrotikUsers = [];
        $mikrotikError = null;

        if ($activeRouter) {
            try {
                $service = new \App\Services\MikrotikService($activeRouter);
                $profiles = $service->getHotspotProfiles();
                $servers = $service->getHotspotServers();
                $mikrotikUsers = $service->getHotspotUsers();
            } catch (\Exception $e) {
                $mikrotikError = $e->getMessage();
                Log::error('Hotspot voucher load error: ' . $e->getMessage());
            }
        }

        // 2. Ambil list voucher dari database lokal
        $vouchersQuery = \App\Models\Voucher::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->latest();
        if ($request->filled('batch')) {
            $vouchersQuery->where('batch_id', $request->batch);
        }
        if ($request->filled('profile')) {
            $vouchersQuery->where('profile', $request->profile);
        }
        if ($selectedRouterId) {
            $vouchersQuery->where('router_id', $selectedRouterId);
        }
        $localVouchers = $vouchersQuery->limit(200)->get();

        // 3. Ambil paket voucher
        $packages = DB::table('voucher_packages')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        // 4. Ambil list batch unik
        $batches = \App\Models\Voucher::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->select('batch_id', DB::raw('COUNT(*) as total_vouchers'), DB::raw('MAX(created_at) as created_at'), DB::raw('MAX(profile) as profile'))
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Voucher', [
            'create' => (bool) $request->create,
            'routers' => $routers,
            'selectedRouterId' => $selectedRouterId,
            'profiles' => collect($profiles)->map(fn ($p) => is_array($p) ? ($p['name'] ?? $p) : (is_object($p) ? ($p->name ?? $p) : $p))->values(),
            'servers' => collect($servers)->map(fn ($s) => is_array($s) ? ($s['name'] ?? $s) : (is_object($s) ? ($s->name ?? $s) : $s))->values(),
            'vouchers' => $localVouchers,
            'mikrotikUsers' => collect($mikrotikUsers)->map(fn ($v) => $v)->values(),
            'packages' => $packages,
            'batches' => $batches,
            'mikrotikError' => $mikrotikError,
        ]);
    }

    public function generateVouchers(Request $request)
    {
        $data = $request->validate([
            'router_id' => 'nullable|integer',
            'server' => 'nullable|string|max:50',
            'profile' => 'required|string|max:100',
            'quantity' => 'required|integer|min:1|max:500',
            'user_mode' => 'required|in:vc,up',
            'code_length' => 'required|integer|min:3|max:12',
            'prefix' => 'nullable|string|max:20',
            'char_pattern' => 'required|in:num,lower,upper,mix,mix1',
            'time_limit' => 'nullable|string|max:30',
            'data_limit_value' => 'nullable|numeric|min:0',
            'data_limit_unit' => 'nullable|in:MB,GB',
            'price' => 'nullable|numeric|min:0',
            'comment' => 'nullable|string|max:100',
        ]);

        $quantity = (int) $data['quantity'];
        $routerId = $data['router_id'] ?? null;
        $activeRouter = $routerId ? \App\Models\Mikrotik::find($routerId) : \App\Models\Mikrotik::first();

        // Hitung data limit dalam bytes jika diisi
        $dataLimitBytes = 0;
        $dataLimitFormatted = null;
        if (!empty($data['data_limit_value']) && (float) $data['data_limit_value'] > 0) {
            $val = (float) $data['data_limit_value'];
            $unit = $data['data_limit_unit'] ?? 'MB';
            $dataLimitFormatted = $val . ' ' . $unit;
            $dataLimitBytes = $unit === 'GB' ? (int) ($val * 1024 * 1024 * 1024) : (int) ($val * 1024 * 1024);
        }

        $timeLimit = !empty($data['time_limit']) ? trim($data['time_limit']) : null;
        $price = !empty($data['price']) ? (float) $data['price'] : 0;
        $server = !empty($data['server']) ? $data['server'] : 'all';
        $prefix = !empty($data['prefix']) ? trim($data['prefix']) : '';
        $codeLength = (int) ($data['code_length'] ?? 6);
        $charPattern = $data['char_pattern'] ?? 'mix1';
        $userMode = $data['user_mode'] ?? 'vc';

        $batchId = 'VCH-' . date('Ymd-His') . '-' . mt_rand(100, 999);
        $customComment = !empty($data['comment']) ? trim($data['comment']) : $batchId;

        $created = 0;
        $failed = 0;

        // Karakter generator acak ala Mikhmon (menghindari karakter rancu 0/O, 1/I)
        $chars = match ($charPattern) {
            'num' => '23456789',
            'lower' => 'abcdefghjkmnpqrstuvwxyz',
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'mix' => 'abcdefghjkmnpqrstuvwxyz23456789',
            'mix1' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
            default => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        };
        $charLen = strlen($chars);

        $vouchersToInsert = [];
        $mikrotikPayloads = [];

        for ($i = 0; $i < $quantity; $i++) {
            $randomCode = '';
            for ($c = 0; $c < $codeLength; $c++) {
                $randomCode .= $chars[random_int(0, $charLen - 1)];
            }
            $username = $prefix . $randomCode;

            if ($userMode === 'vc') {
                $password = $username;
            } else {
                $randPass = '';
                for ($p = 0; $p < max(4, $codeLength - 1); $p++) {
                    $randPass .= $chars[random_int(0, $charLen - 1)];
                }
                $password = $randPass;
            }

            $vouchersToInsert[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $data['profile'],
                'price' => $price,
                'time_limit' => $timeLimit,
                'data_limit' => $dataLimitFormatted,
                'comment' => $customComment,
                'batch_id' => $batchId,
                'router_id' => $activeRouter?->id,
                'created_by' => auth()->id(),
                'used' => false,
                'tenant_id' => session('tenant_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $mikrotikPayloads[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $data['profile'],
                'time_limit' => $timeLimit ?? '',
                'data_limit_bytes' => $dataLimitBytes,
                'server' => $server,
                'comment' => $customComment
            ];
        }

        // Batch insert
        foreach (array_chunk($vouchersToInsert, 500) as $chunk) {
            \App\Models\Voucher::insert($chunk);
        }

        // Push ke MikroTik Router via Job
        if ($activeRouter) {
            \App\Jobs\SyncVouchersToMikrotikJob::dispatch($activeRouter->id, $mikrotikPayloads)->onQueue('default');
        }

        $msg = "Berhasil membuat {$quantity} voucher (Batch: {$batchId}) dan sedang disinkronisasi ke MikroTik.";

        return redirect()->to('/admin/voucher?batch=' . urlencode($batchId))->with('msg', $msg);
    }

    public function voucherDelete($id)
    {
        $voucher = \App\Models\Voucher::where('id', $id)->first();
        if ($voucher) {
            if ($voucher->router_id) {
                $router = \App\Models\Mikrotik::find($voucher->router_id);
                if ($router) {
                    try {
                        $service = new \App\Services\MikrotikService($router);
                        $service->deleteHotspotUser($voucher->username);
                    } catch (\Exception $e) {
                        // ignore error on mikrotik delete
                    }
                }
            }
            $voucher->delete();
        }
        return redirect()->back()->with('msg', 'Voucher berhasil dihapus.');
    }

    public function voucherDeleteBatch($batch_id)
    {
        $vouchers = \App\Models\Voucher::where('batch_id', $batch_id)->get();
        $grouped = $vouchers->groupBy('router_id');
        $idsToDelete = $vouchers->pluck('id')->toArray();

        foreach ($grouped as $router_id => $routerVouchers) {
            if ($router_id) {
                $router = \App\Models\Mikrotik::find($router_id);
                if ($router) {
                    try {
                        $service = new \App\Services\MikrotikService($router);
                        foreach ($routerVouchers as $v) {
                            $service->deleteHotspotUser($v->username);
                        }
                    } catch (\Exception $e) {}
                }
            }
        }

        if (!empty($idsToDelete)) {
            \App\Models\Voucher::whereIn('id', $idsToDelete)->delete();
        }

        return redirect()->to('/admin/voucher')->with('msg', 'Batch voucher berhasil dihapus.');
    }

    public function trouble()
    {
        $tickets = collect();
        $technicians = collect();
        $customers = collect();
        $routers = collect();
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        try {
            $tickets = TroubleTicket::with('router')->select('trouble_tickets.*', 'users.name as technician_name', 'resolver.name as resolver_name')
                ->leftJoin('users', 'users.id', '=', 'trouble_tickets.assigned_to')
                ->leftJoin('users as resolver', 'resolver.id', '=', 'trouble_tickets.resolved_by')
                ->when($tenantId, fn ($q) => $q->where('trouble_tickets.tenant_id', $tenantId))
                ->orderByRaw("CASE WHEN trouble_tickets.status = 'pending' THEN 1 WHEN trouble_tickets.status = 'in_progress' THEN 2 WHEN trouble_tickets.status = 'resolved' THEN 3 WHEN trouble_tickets.status = 'closed' THEN 4 ELSE 5 END")
                ->orderBy('created_at', 'DESC')
                ->get();
            $technicians = User::whereIn('role', ['technician', 'teknisi'])
                ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->get();
            $customers = \App\Models\Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
            $routers = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return Inertia::render('Admin/Trouble', [
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'customer_id' => $t->customer_id,
                'customer_name' => $t->customer_name,
                'customer_phone' => $t->customer_phone,
                'assigned_to' => $t->assigned_to ? (int) $t->assigned_to : null,
                'technician_name' => $t->technician_name,
                'router_name' => $t->router?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'technicians' => $technicians->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'customers' => $customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
        ]);
    }

    public function createTicket(Request $request)
    {
        // Clean empty string values to null before validation so numeric validation doesn't fail
        $request->merge([
            'customer_id' => $request->customer_id ?: null,
            'assigned_to' => $request->assigned_to ?: null,
            'technician_id' => $request->technician_id ?: null,
            'router_id' => $request->router_id ?: null,
            'title' => $request->title ?: null,
        ]);

        $validated = $request->validate([
            'title' => 'nullable|string|max:150',
            'customer_id' => 'nullable|numeric',
            'description' => 'required|min:3',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|numeric',
            'technician_id' => 'nullable|numeric',
            'router_id' => 'nullable|numeric',
        ]);

        $customer = $request->customer_id ? \App\Models\Customer::find($request->customer_id) : null;
        $assignedTo = $request->assigned_to ?: $request->technician_id;
        $title = $request->title ?: ($customer ? "Gangguan - {$customer->name}" : "Laporan Gangguan");
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $ticket = TroubleTicket::create([
            'title' => $title,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? 'Umum',
            'customer_phone' => $customer?->phone ?? '',
            'description' => $request->description,
            'priority' => $request->priority,
            'assigned_to' => !empty($assignedTo) ? (int) $assignedTo : null,
            'router_id' => $request->router_id ?: ($customer?->router_id ?? null),
            'status' => !empty($assignedTo) ? 'in_progress' : 'pending',
            'tenant_id' => $tenantId,
        ]);

        $ticketId = $ticket->id;

        // Dispatch Push Notification
        try {
            app(\App\Services\PushNotificationService::class)->notifyTicketCreated($ticket);
        } catch (\Exception $e) {
            Log::error('Failed to send push notification on ticket creation: ' . $e->getMessage());
        }

        // Notify via WhatsApp
        try {
            $ws = new WhatsappService();

            if (!empty($assignedTo)) {
                // PERSONAL NOTIFICATION to Assigned Technician
                $technician = User::find($assignedTo);
                if ($technician && !empty($technician->phone)) {
                    $msg = "*TUGAS BARU (TIKET GANGGUAN)*\n\n";
                    $msg .= "Halo {$technician->name},\n";
                    $msg .= "Admin telah memberikan tugas baru kepada Anda:\n\n";
                    $msg .= "--------------------------------\n";
                    $msg .= "ID Tiket : #{$ticketId}\n";
                    $msg .= "Judul : {$title}\n";
                    $msg .= "Pelanggan : " . ($customer->name ?? 'N/A') . "\n";
                    $msg .= "Alamat : " . ($customer->address ?? '-') . "\n";
                    $msg .= "Keluhan : " . $request->description . "\n";
                    $msg .= "Prioritas : " . strtoupper($request->priority) . "\n";
                    $msg .= "--------------------------------\n\n";
                    $msg .= "Silakan cek dashboard untuk detail lebih lanjut.";

                    $ws->sendMessage($technician->phone, $msg);
                }
            } else {
                // BROADCAST to All Technicians
                $technicians = User::whereIn('role', ['technician', 'teknisi'])
                    ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                    ->get();
                foreach ($technicians as $tech) {
                    if (!empty($tech->phone)) {
                        $msg = "*LAPORAN GANGGUAN BARU*\n\n";
                        $msg .= "Halo {$tech->name},\n";
                        $msg .= "Ada laporan gangguan baru (Belum ada teknisi):\n\n";
                        $msg .= "--------------------------------\n";
                        $msg .= "ID Tiket : #{$ticketId}\n";
                        $msg .= "Judul : {$title}\n";
                        $msg .= "Pelanggan : " . ($customer->name ?? 'N/A') . "\n";
                        $msg .= "Keluhan : " . $request->description . "\n";
                        $msg .= "--------------------------------\n\n";
                        $msg .= "Mohon koordinasi dengan admin.";

                        $ws->sendMessage($tech->phone, $msg);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket notification: ' . $e->getMessage());
        }

        $successMsg = ' Tiket berhasil dibuat' . (!empty($assignedTo) ? ' & Ditugaskan' : '');
        return redirect()->to('/admin/trouble')->with('msg', $successMsg);
    }

    public function updateTicket(Request $request, $id)
    {
        $request->validate([
            'description' => 'required|string|min:3',
            'priority' => 'required|in:low,medium,high,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        TroubleTicket::where('id', $id)->update([
            'description' => $request->description,
            'priority' => $request->priority,
            'notes' => $request->notes,
            'updated_at' => now(),
        ]);

        return redirect()->to('/admin/trouble')->with('msg', ' Tiket berhasil diupdate');
    }

    public function assignTicket(Request $request, $id)
    {
        $assignedTo = $request->input('assigned_to') ?: $request->input('technician_id');

        if (empty($assignedTo)) {
            return redirect()->back()->with('error', 'Pilih teknisi terlebih dahulu.');
        }

        TroubleTicket::where('id', $id)->update([
            'assigned_to' => $assignedTo,
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);

        // Notify Technician via WhatsApp
        try {
            $ticket = TroubleTicket::with('customer')
                ->select('trouble_tickets.*')
                ->where('trouble_tickets.id', $id)
                ->first();

            $technician = User::find($assignedTo);

            if ($technician && !empty($technician->phone)) {
                $ws = new WhatsappService();
                $msg = "*TUGAS BARU (TIKET GANGGUAN)*\n\n";
                $msg .= "Halo {$technician->name},\n";
                $msg .= "Anda mendapat tugas baru untuk menangani tiket berikut:\n\n";
                $msg .= "--------------------------------\n";
                $msg .= "ID Tiket : #{$id}\n";
                $msg .= "Judul : " . ($ticket->title ?? 'Tiket Gangguan') . "\n";
                $msg .= "Pelanggan : {$ticket->customer?->name}\n";
                $msg .= "Alamat : {$ticket->customer?->address}\n";
                $msg .= "Keluhan : {$ticket->description}\n";
                $msg .= "Prioritas : " . strtoupper($ticket->priority ?? 'NORMAL') . "\n";
                $msg .= "--------------------------------\n\n";
                $msg .= "Silakan cek dashboard teknisi untuk detail lebih lanjut.\n";
                $msg .= "Terima kasih.";

                $ws->sendMessage($technician->phone, $msg);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify technician: ' . $e->getMessage());
        }

        return redirect()->to('/admin/trouble')->with('msg', ' Tiket berhasil di-assign & Notifikasi terkirim');
    }

    public function takeTicket($id)
    {
        TroubleTicket::where('id', $id)->update([
            'assigned_to' => auth()->id(),
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);

        return redirect()->to('/admin/trouble')->with('msg', ' Tiket diambil dan sedang diproses');
    }

    public function closeTicket(Request $request, $id)
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $data = [
            'status' => 'closed',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'resolution_notes' => $request->post('resolution_notes') ?? 'Ditutup oleh Admin',
            'updated_at' => now(),
        ];

        TroubleTicket::where('id', $id)->update($data);

        // Notify Customer
        try {
            $ticket = TroubleTicket::with('customer')
                ->select('trouble_tickets.*')
                ->where('trouble_tickets.id', $id)
                ->first();

            if ($ticket && $ticket->customer && !empty($ticket->customer->phone)) {
                $ws = new WhatsappService();
                $msg = "*LAPORAN SELESAI*\n\n";
                $msg .= "Yth. {$ticket->customer->name},\n";
                $msg .= "Laporan gangguan Anda dengan ID #{$id} telah ditandai sebagai *SELESAI* oleh Admin.\n\n";
                $msg .= "--------------------------------\n";
                $msg .= "Keterangan: " . $data['resolution_notes'] . "\n";
                $msg .= "--------------------------------\n\n";
                $msg .= "Terima kasih telah menggunakan layanan kami.";

                $ws->sendMessage($ticket->customer->phone, $msg);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin resolution notify: ' . $e->getMessage());
        }

        return redirect()->to('/admin/trouble')->with('msg', ' Tiket berhasil ditutup & Notifikasi terkirim');
    }

    public function handleCommand(Request $request)
    {
        $cmd = $request->post('command');
        $parts = explode(' ', trim($cmd), 2);
        $action = strtoupper($parts[0] ?? '');
        $arg    = $parts[1] ?? null;

        $response = '';
        switch ($action) {
            case 'REBOOT':
                $res = $this->genieacs->rebootDevice($arg);
                $response = ($res['code'] ?? 0) === 200 ? ' Reboot command sent' : ' Failed to send reboot';
                break;
            case 'PPPOE-ON':
                $this->mikrotik->enablePppoe($arg);
                $response = ' PPPoE enabled';
                break;
            case 'PPPOE-OFF':
                $this->mikrotik->disablePppoe($arg);
                $response = ' PPPoE disabled';
                break;
            default:
                $response = ' Unknown command';
        }

        return response()->json(['msg' => $response]);
    }

    public function update()
    {
        // Get current version from a file
        $versionFile = base_path('version.txt');
        $currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'Unknown';

        // Get last backup info
        $backupDir = base_path('backups');
        $lastBackup = null;
        if (is_dir($backupDir)) {
            $backups = glob($backupDir . '/backup_*.zip');
            if (!empty($backups)) {
                usort($backups, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $lastBackup = [
                    'file' => basename($backups[0]),
                    'date' => date('Y-m-d H:i:s', filemtime($backups[0])),
                    'size' => round(filesize($backups[0]) / 1024 / 1024, 2) . ' MB'
                ];
            }
        }

        // Check if update.php exists in root folder
        $updateFileExists = file_exists(base_path('update.php'));

        $data = compact('currentVersion', 'lastBackup', 'updateFileExists');
        $data['githubRepo'] = 'alijayanet/gembok-php';
        $data['githubBranch'] = 'main';

        return view('admin.update', $data);
    }

    public function runUpdate()
    {
        $updateFile = base_path('update.php');

        if (!file_exists($updateFile)) {
            return response()->json([
                'success' => false,
                'message' => 'File update.php tidak ditemukan'
            ]);
        }

        return response()->json([
            'success' => true,
            'redirect' => url('update.php')
        ]);
    }

    public function systemNotifications()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $notifications = collect();

        try {
            // 1. Paid Invoices
            $paidInvoices = \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('paid', 1)
                ->with('customer:id,name,phone')
                ->latest('paid_at')
                ->take(25)
                ->get();

            foreach ($paidInvoices as $inv) {
                $customerName = $inv->customer?->name ?? $inv->customer_name ?? 'Pelanggan';
                $notifications->push([
                    'id' => 'inv-paid-' . $inv->id,
                    'category' => 'billing',
                    'type' => 'success',
                    'title' => 'Pembayaran Tagihan Diterima',
                    'description' => 'Invoice ' . $inv->invoice_number . ' a.n ' . $customerName . ' telah dibayar lunas sebesar Rp ' . number_format((float) $inv->amount, 0, ',', '.'),
                    'actor' => $inv->payment_method ? strtoupper((string) $inv->payment_method) : 'Kasir POS / Auto Gateway',
                    'action_url' => '/admin/billing/invoices',
                    'action_label' => 'Lihat Invoice',
                    'created_at' => ($inv->paid_at ?? $inv->updated_at ?? $inv->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications paid invoices error: ' . $e->getMessage());
        }

        try {
            // 2. Pending Invoices (Due/Unpaid)
            $pendingInvoices = \App\Models\Invoice::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('paid', 0)
                ->with('customer:id,name,phone')
                ->latest('created_at')
                ->take(15)
                ->get();

            foreach ($pendingInvoices as $inv) {
                $customerName = $inv->customer?->name ?? $inv->customer_name ?? 'Pelanggan';
                $notifications->push([
                    'id' => 'inv-pend-' . $inv->id,
                    'category' => 'billing',
                    'type' => 'warning',
                    'title' => 'Tagihan Jatuh Tempo / Belum Lunas',
                    'description' => 'Invoice ' . $inv->invoice_number . ' a.n ' . $customerName . ' sebesar Rp ' . number_format((float) $inv->amount, 0, ',', '.') . ' membutuhkan tindak lanjut penagihan.',
                    'actor' => 'Sistem Billing',
                    'action_url' => '/admin/billing/invoices?status=unpaid',
                    'action_label' => 'Proses Tagihan',
                    'created_at' => ($inv->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications pending invoices error: ' . $e->getMessage());
        }

        try {
            // 3. New Customers
            $recentCustomers = \App\Models\Customer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->latest('created_at')
                ->take(20)
                ->get();

            foreach ($recentCustomers as $cust) {
                $code = $cust->code ?? 'PLG';
                $notifications->push([
                    'id' => 'cust-' . $cust->id,
                    'category' => 'customer',
                    'type' => 'info',
                    'title' => 'Pelanggan Baru Terdaftar',
                    'description' => 'Pelanggan ' . $cust->name . ' (' . $code . ') telah didaftarkan dan diaktifkan pada sistem.',
                    'actor' => 'Admin / Registrasi',
                    'action_url' => '/admin/billing/customers',
                    'action_label' => 'Data Pelanggan',
                    'created_at' => ($cust->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications customers error: ' . $e->getMessage());
        }

        try {
            // 4. Trouble Tickets
            $recentTickets = \App\Models\TroubleTicket::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->latest('created_at')
                ->take(20)
                ->get();

            foreach ($recentTickets as $tkt) {
                $isPending = in_array(strtolower((string) $tkt->status), ['pending', 'open', 'in_progress']);
                $notifications->push([
                    'id' => 'tkt-' . $tkt->id,
                    'category' => 'trouble',
                    'type' => $isPending ? 'warning' : 'success',
                    'title' => 'Tiket Komplain: ' . ($tkt->title ?? $tkt->description ?? 'Laporan Gangguan'),
                    'description' => 'Laporan pelanggan ' . ($tkt->customer_name ?? 'Pelanggan') . ' (Status: ' . ucfirst((string) $tkt->status) . ')',
                    'actor' => 'Helpdesk & Teknisi',
                    'action_url' => '/admin/trouble',
                    'action_label' => 'Buka Helpdesk',
                    'created_at' => ($tkt->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications tickets error: ' . $e->getMessage());
        }

        try {
            // 5. Audit Logs (Aktivitas Admin, Auto-Isolir, & Notifikasi Gateway)
            $auditLogs = \App\Models\AuditLog::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->with('user:id,name')
                ->latest('created_at')
                ->take(50)
                ->get();

            foreach ($auditLogs as $log) {
                $actionRaw = (string) $log->action;
                $actionClean = ucwords(str_replace(['_', '-'], ' ', $actionRaw));
                $newVals = is_array($log->new_values) ? $log->new_values : (json_decode($log->new_values ?? '[]', true) ?: []);
                $actor = $newVals['actor'] ?? ($log->user?->name ?? 'Sistem');
                $customerName = $newVals['customer_name'] ?? ($newVals['name'] ?? null);

                $title = 'Log Aktivitas: ' . $actionClean;
                $desc = ($log->user?->name ?? $actor) . ' melakukan ' . strtolower($actionClean) . ' pada modul ' . ($log->entity_type ? class_basename((string) $log->entity_type) : 'Sistem');
                $type = 'info';
                $url = null;
                $label = null;

                if (str_contains(strtolower($actionRaw), 'isolated') && !str_contains(strtolower($actionRaw), 'unisolated')) {
                    $title = 'Otomatis Isolir: ' . ($customerName ?: 'Pelanggan');
                    $type = 'danger';
                    $desc = "Layanan pelanggan {$customerName} " . (!empty($newVals['pppoe_username']) ? "({$newVals['pppoe_username']}) " : "") . "dinonaktifkan / dipindah ke profil isolir oleh {$actor}.";
                    $url = $customerName ? '/admin/billing/customers?search=' . urlencode($customerName) : '/admin/billing/customers';
                    $label = 'Lihat Pelanggan';
                } elseif (str_contains(strtolower($actionRaw), 'unisolated')) {
                    $title = 'Buka Isolir: ' . ($customerName ?: 'Pelanggan');
                    $type = 'success';
                    $desc = "Layanan pelanggan {$customerName} telah diaktifkan kembali oleh {$actor}.";
                    $url = $customerName ? '/admin/billing/customers?search=' . urlencode($customerName) : '/admin/billing/customers';
                    $label = 'Lihat Pelanggan';
                } elseif (str_contains(strtolower($actionRaw), 'whatsapp') || str_contains(strtolower($actionRaw), 'wa_failed')) {
                    $title = 'WhatsApp Gateway: Gagal Terkirim';
                    $type = 'warning';
                    $desc = $newVals['reason'] ?? ('Gagal mengirim pesan WhatsApp ke nomor ' . ($newVals['phone'] ?? '-') . ($customerName ? " ({$customerName})" : ''));
                    $url = '/admin/billing/invoices';
                    $label = 'Cek Tagihan';
                }

                $notifications->push([
                    'id' => 'audit-' . $log->id,
                    'category' => 'system',
                    'type' => $type,
                    'title' => $title,
                    'description' => $desc,
                    'actor' => $actor,
                    'action_url' => $url,
                    'action_label' => $label,
                    'created_at' => ($log->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications audit logs error: ' . $e->getMessage());
        }

        try {
            // 6. System Announcements (No tenant_id column on announcements)
            $announcements = \App\Models\Announcement::where('is_active', true)
                ->latest('created_at')
                ->take(10)
                ->get();

            foreach ($announcements as $ann) {
                $notifications->push([
                    'id' => 'ann-' . $ann->id,
                    'category' => 'announcement',
                    'type' => $ann->type === 'warning' ? 'warning' : ($ann->type === 'maintenance' ? 'danger' : 'info'),
                    'title' => 'Pengumuman: ' . $ann->title,
                    'description' => $ann->content,
                    'actor' => 'Pusat NODERA / Superadmin',
                    'action_url' => null,
                    'action_label' => null,
                    'created_at' => ($ann->published_at ?? $ann->created_at ?? now())->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SystemNotifications announcements error: ' . $e->getMessage());
        }

        // Sort all by created_at desc
        $feed = $notifications->sortByDesc(fn ($item) => strtotime((string) $item['created_at']))->values();

        $stats = [
            'total_notif' => $feed->count(),
            'total_billing' => $feed->where('category', 'billing')->count(),
            'total_trouble' => $feed->where('category', 'trouble')->count(),
            'total_system' => $feed->where('category', 'system')->count(),
        ];

        return Inertia::render('Admin/Notifications', [
            'notifications' => $feed,
            'stats' => $stats,
        ]);
    }

    public function broadcast()
    {
        $tenantId = $this->resolveCurrentTenantId();
        $custQuery = DB::table('customers');
        if ($tenantId !== null) {
            $custQuery->where('tenant_id', $tenantId);
        } else {
            $custQuery->whereNull('tenant_id');
        }
        $customers = $custQuery->orderBy('name')->get(['id', 'name', 'phone']);

        if ($tenantId !== null) {
            $globalTpls = DB::table('whatsapp_templates')->whereNull('tenant_id')->get()->keyBy('name');
            $tenantTpls = DB::table('whatsapp_templates')->where('tenant_id', $tenantId)->get()->keyBy('name');
            $templates = $globalTpls->merge($tenantTpls)->values();
        } else {
            $templates = DB::table('whatsapp_templates')->whereNull('tenant_id')->orderBy('name')->get();
        }

        // Cek siapa yang punya VAPID subscription aktif
        $subQuery = DB::table('push_subscriptions')->where('subscriber_type', 'customer');
        if ($tenantId !== null) {
            $subQuery->where('tenant_id', $tenantId);
        } else {
            $subQuery->whereNull('tenant_id');
        }
        $subscribedCustomerIds = $subQuery
            ->pluck('subscriber_id')
            ->unique()
            ->toArray();

        $notifSettings = [
            'reminder_enabled' => Setting::getValue('NOTIF_REMINDER_ENABLED', '1') === '1',
            'reminder_channel' => Setting::getValue('NOTIF_REMINDER_CHANNEL', 'all'),
            'isolir_enabled'   => Setting::getValue('NOTIF_ISOLIR_ENABLED', '1') === '1',
            'isolir_channel'   => Setting::getValue('NOTIF_ISOLIR_CHANNEL', 'all'),
        ];

        $tenantId = $this->resolveCurrentTenantId();
        if ($tenantId !== null) {
            $waSettings = [
                'provider'     => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->where('tenant_id', $tenantId)->value('value') ?? 'fonnte',
                'api_url'      => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->where('tenant_id', $tenantId)->value('value') ?? 'https://api.fonnte.com/send',
                'token'        => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->where('tenant_id', $tenantId)->value('value') ?? '',
                'sender_phone' => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->where('tenant_id', $tenantId)->value('value') ?? '',
                'auto_typing'  => (Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_AUTO_TYPING')->where('tenant_id', $tenantId)->value('value') ?? '1') === '1',
            ];
        } else {
            $waSettings = [
                'provider'     => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->whereNull('tenant_id')->value('value') ?? 'fonnte',
                'api_url'      => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->whereNull('tenant_id')->value('value') ?? 'https://api.fonnte.com/send',
                'token'        => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->whereNull('tenant_id')->value('value') ?? '',
                'sender_phone' => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->whereNull('tenant_id')->value('value') ?? '',
                'auto_typing'  => (Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_AUTO_TYPING')->whereNull('tenant_id')->value('value') ?? '1') === '1',
            ];
        }

        return Inertia::render('Admin/Broadcast', [
            'customers' => $customers->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'phone'    => $c->phone,
                'has_push' => in_array($c->id, $subscribedCustomerIds),
            ]),
            'templates'     => $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'message' => $t->message ?? '']),
            'notifSettings' => $notifSettings,
            'waSettings'    => $waSettings,
        ]);
    }

    public function saveWaSettings(Request $request)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $data = $request->validate([
            'provider'     => 'nullable|string|max:50',
            'api_url'      => 'nullable|string|max:255',
            'token'        => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:50',
            'auto_typing'  => 'nullable|boolean',
        ]);

        Setting::setValue('WHATSAPP_PROVIDER', $data['provider'] ?? 'fonnte', $tenantId);
        Setting::setValue('WHATSAPP_API_URL', $data['api_url'] ?? 'https://api.fonnte.com/send', $tenantId);
        Setting::setValue('WHATSAPP_TOKEN', $data['token'] ?? '', $tenantId);
        Setting::setValue('WHATSAPP_SENDER_PHONE', $data['sender_phone'] ?? '', $tenantId);
        Setting::setValue('WHATSAPP_AUTO_TYPING', !empty($data['auto_typing']) ? '1' : '0', $tenantId);

        return redirect()->back()->with('msg', 'Pengaturan WhatsApp Gateway API berhasil disimpan.');
    }

    public function testWhatsappMessage(Request $request)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $data = $request->validate([
            'phone'   => 'required|string|min:8|max:20',
            'message' => 'required|string|min:3',
        ]);

        try {
            $ws = new \App\Services\WhatsappService($tenantId);
            if (!$ws->isEnabled()) {
                if (!$request->header('X-Inertia') && $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'WhatsApp Gateway belum aktif / Token API belum diisi di Pengaturan.'], 422);
                }
                return redirect()->back()->with('error', 'WhatsApp Gateway belum aktif / Token API belum diisi.');
            }

            $ok = $ws->sendMessage($data['phone'], $data['message']);
            if ($ok) {
                if (!$request->header('X-Inertia') && $request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => 'Pesan uji coba WhatsApp berhasil dikirim ke ' . $data['phone']]);
                }
                return redirect()->back()->with('msg', 'Pesan uji coba WhatsApp berhasil dikirim ke ' . $data['phone']);
            } else {
                $err = $ws->getLastError() ?: 'Pastikan token dan nomor tujuan valid.';
                if (!$request->header('X-Inertia') && $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Gagal mengirim pesan WhatsApp: ' . $err], 422);
                }
                return redirect()->back()->with('error', 'Gagal mengirim pesan WhatsApp: ' . $err);
            }
        } catch (\Throwable $e) {
            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Error pengiriman WhatsApp: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error pengiriman WhatsApp: ' . $e->getMessage());
        }
    }

    public function broadcastSend(Request $request)
    {
        $request->validate([
            'message' => 'required|min:3',
            'channel' => 'required|in:wa,push,all',
            'target' => 'required|in:all,customers,selected',
            'customer_ids' => 'nullable',
            'push_title' => 'nullable|string',
        ]);

        $message = $request->message;
        $channel = $request->input('channel', 'all');
        $pushTitle = $request->input('push_title') ?: 'Pengumuman Admin';
        $tenantId = session('tenant_id');
        $sentPush = 0;

        $query = DB::table('customers')->where('tenant_id', $tenantId);
        if ($request->target === 'selected' && !empty($request->customer_ids)) {
            $ids = is_array($request->customer_ids) ? $request->customer_ids : explode(',', $request->customer_ids);
            $query->whereIn('id', $ids);
        }

        $customers = $query->get();

        // 1. WhatsApp Broadcast
        if (in_array($channel, ['wa', 'all'])) {
            \App\Jobs\BroadcastWhatsappJob::dispatch($customers, $message, (int) $tenantId)->onQueue('default');
        }

        // 2. Push Notification Broadcast (VAPID — bekerja meski app ditutup)
        if (in_array($channel, ['push', 'all'])) {
            try {
                $pushService = app(\App\Services\PushNotificationService::class);

                if ($request->target === 'selected' && !empty($request->customer_ids)) {
                    // Kirim ke pelanggan tertentu satu per satu
                    $ids = is_array($request->customer_ids) ? $request->customer_ids : explode(',', $request->customer_ids);
                    foreach ($ids as $cid) {
                        $sentPush += $pushService->sendVapidToSubscribers('customer', (int) $cid, $pushTitle, $message, ['url' => '/portal']);
                    }
                } else {
                    // Kirim ke semua pelanggan tenant
                    $sentPush = $pushService->sendVapidToTenantSubscribers((int) $tenantId, 'customer', $pushTitle, $message, ['url' => '/portal']);
                }
            } catch (\Exception $e) {
                \Log::error("Push notification broadcast error: " . $e->getMessage());
            }
        }

        $summary = [];
        if (in_array($channel, ['wa', 'all'])) {
            $summary[] = "WA broadcast sedang diproses di background";
        }
        if (in_array($channel, ['push', 'all'])) {
            $summary[] = "Push: {$sentPush} terkirim";
        }

        return redirect()->back()->with('msg', 'Broadcast sedang diproses! ' . implode(', ', $summary));
    }

    public function testPushNotification(Request $request)
    {
        $request->validate([
            'role_target' => 'required|in:technician,collector,customer',
            'title'       => 'nullable|string',
            'body'        => 'nullable|string',
        ]);

        $tenantId   = session('tenant_id');
        $target     = $request->role_target;
        $title      = $request->title ?: 'Test Push Notification';
        $body       = $request->body  ?: 'Ini adalah tes notifikasi push dari Admin. Notifikasi berfungsi!';
        $pushService = app(\App\Services\PushNotificationService::class);

        $targetLabel = match($target) {
            'technician' => 'Teknisi',
            'collector'  => 'Kolektor',
            default      => 'Pelanggan',
        };

        $url = match($target) {
            'technician' => '/teknisi/dashboard',
            'collector'  => '/kolektor/dashboard',
            default      => '/portal',
        };

        // Hitung total subscription untuk peran ini
        $query = DB::table('push_subscriptions')->where('subscriber_type', $target);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $totalDevices = $query->count();

        if ($totalDevices === 0) {
            return redirect()->back()->with('error',
                "Belum ada perangkat {$targetLabel} yang terdaftar untuk push notifikasi. "
                . "Pastikan {$targetLabel} sudah buka aplikasi dan izinkan notifikasi di browser."
            );
        }

        // Kirim via VAPID (bekerja meski app ditutup)
        $sentCount = $pushService->sendVapidToSubscribers($target, null, $title, $body, ['url' => $url]);

        return redirect()->back()->with('msg',
            "Test push notifikasi terkirim ke {$sentCount} dari {$totalDevices} perangkat {$targetLabel}."
        );
    }

    public function saveNotifSettings(Request $request)
    {
        $tenantId = $this->resolveCurrentTenantId();

        if ($request->has('reminder_enabled')) {
            $val = $request->boolean('reminder_enabled') ? '1' : '0';
            Setting::setValue('NOTIF_REMINDER_ENABLED', $val, $tenantId);
            Setting::setValue('INVOICE_REMINDER_AUTO', $val, $tenantId);
        }
        if ($request->has('reminder_days')) {
            $days = $request->input('reminder_days');
            if (is_array($days)) {
                $days = implode(',', array_filter($days, fn($v) => is_numeric($v)));
            }
            Setting::setValue('INVOICE_REMINDER_DAYS', (string) $days, $tenantId);
        }
        if ($request->has('isolir_enabled')) {
            Setting::setValue('NOTIF_ISOLIR_ENABLED', $request->boolean('isolir_enabled') ? '1' : '0', $tenantId);
        }

        $reminderDaysSetting = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_DAYS')->value('value') ?? '3,1,0';
        $reminderDays = array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $reminderDaysSetting)), fn($v) => is_numeric($v))));
        if (empty($reminderDays)) {
            $reminderDays = [3, 1, 0];
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengaturan notifikasi otomatis berhasil diperbarui!',
                'settings' => [
                    'reminder_enabled' => Setting::getValue('NOTIF_REMINDER_ENABLED', '1') === '1',
                    'reminder_days'    => $reminderDays,
                    'isolir_enabled'   => Setting::getValue('NOTIF_ISOLIR_ENABLED', '1') === '1',
                ],
            ]);
        }

        return redirect()->back()->with('msg', 'Pengaturan notifikasi otomatis berhasil diperbarui!');
    }

    public function testAndSaveWaSettings(Request $request)
    {
        $data = $request->validate([
            'provider'     => 'nullable|string|max:50',
            'api_url'      => 'nullable|string|max:255',
            'token'        => 'required|string|max:255',
            'sender_phone' => 'nullable|string|max:50',
            'auto_typing'  => 'nullable|boolean',
        ]);

        $provider = $data['provider'] ?? 'fonnte';
        $apiUrl = $data['api_url'] ?: 'https://api.fonnte.com/send';
        $token = trim($data['token']);
        $senderPhone = $data['sender_phone'] ?? '';
        $autoTyping = !empty($data['auto_typing']) ? '1' : '0';

        $isConnect = false;
        $errorMessage = '';

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 18,
                'connect_timeout' => 8,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            if (str_contains($apiUrl, 'fonnte.com') || $provider === 'fonnte') {
                $res = $client->post('https://api.fonnte.com/device', [
                    'headers' => [
                        'Authorization' => $token,
                    ],
                ]);
                $body = (string) $res->getBody();
                $json = json_decode($body, true);
                if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
                    if (isset($json['status']) && $json['status'] === false) {
                        $errorMessage = $json['reason'] ?? 'Token Fonnte tidak valid atau device belum terdaftar.';
                    } else {
                        $isConnect = true;
                    }
                } else {
                    $errorMessage = 'Server Fonnte mengembalikan status HTTP ' . $res->getStatusCode();
                }
            } elseif (str_contains($apiUrl, 'mhwa.biz.id') || $provider === 'mhwa') {
                $sessionId = trim($senderPhone);
                $sessionId = !empty($sessionId) ? $sessionId : 'default';
                $baseUrl = preg_replace('#/api/.*$#', '', $apiUrl) ?: 'https://mhwa.biz.id';

                // 1. Cek langsung via official endpoint GET /api/devices
                $devRes = $client->get("{$baseUrl}/api/devices", [
                    'headers' => [
                        'x-api-key' => $token,
                    ],
                ]);
                $devBody = (string) $devRes->getBody();
                $devJson = json_decode($devBody, true);

                if ($devRes->getStatusCode() === 200 && is_array($devJson) && isset($devJson['data']) && is_array($devJson['data'])) {
                    $devices = $devJson['data'];
                    $targetDevice = null;
                    foreach ($devices as $d) {
                        if (($d['session_id'] ?? '') === $sessionId || ($d['device_name'] ?? '') === $sessionId) {
                            $targetDevice = $d;
                            break;
                        }
                    }

                    if (!$targetDevice && count($devices) === 1 && $sessionId === 'default') {
                        $targetDevice = $devices[0];
                    }

                    if ($targetDevice) {
                        $devStatus = strtolower((string) ($targetDevice['status'] ?? ''));
                        if (in_array($devStatus, ['connected', 'active', 'authenticated', 'ready', 'online'])) {
                            $isConnect = true;
                        } else {
                            $errorMessage = "Sesi '{$targetDevice['device_name']}' ({$targetDevice['session_id']}) terputus (Status: {$devStatus}) di MHWA. Silakan scan QR di dashboard mhwa.biz.id.";
                        }
                    } else {
                        $availableList = array_map(fn($d) => ($d['device_name'] ?? '') . ' [' . ($d['session_id'] ?? '') . ']', $devices);
                        $availStr = !empty($availableList) ? implode(', ', $availableList) : 'Belum ada device';
                        $errorMessage = "Session ID '{$sessionId}' tidak ditemukan di akun MHWA Anda. Device terdaftar: {$availStr}.";
                    }
                } else {
                    // Fallback kirim ping test
                    $res = $client->post($apiUrl, [
                        'headers' => [
                            'x-api-key'    => $token,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'session_id' => $sessionId,
                            'to'         => '628123456789',
                            'message'    => 'NODERA Test Ping',
                        ],
                    ]);
                    $body = (string) $res->getBody();
                    $json = json_decode($body, true);
                    if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
                        $isConnect = true;
                    } else {
                        $errorMessage = $json['error'] ?? $json['message'] ?? 'Gagal memverifikasi API MHWA';
                    }
                }
            } elseif (str_contains($apiUrl, 'kirimi.id') || $provider === 'kirimi') {
                $userCode = null;
                $deviceId = null;
                $secret = $token;

                $senderPhoneRaw = trim($senderPhone);
                if (str_contains($senderPhoneRaw, ':')) {
                    [$uCode, $dId] = explode(':', $senderPhoneRaw, 2);
                    $userCode = trim($uCode);
                    $deviceId = trim($dId);
                } elseif (str_contains($senderPhoneRaw, '|')) {
                    [$uCode, $dId] = explode('|', $senderPhoneRaw, 2);
                    $userCode = trim($uCode);
                    $deviceId = trim($dId);
                } else {
                    $deviceId = $senderPhoneRaw;
                }

                if (empty($userCode) && str_contains($token, ':')) {
                    [$uCode, $sec] = explode(':', $token, 2);
                    $userCode = trim($uCode);
                    $secret = trim($sec);
                } elseif (empty($userCode) && str_contains($token, '|')) {
                    [$uCode, $sec] = explode('|', $token, 2);
                    $userCode = trim($uCode);
                    $secret = trim($sec);
                }

                // Kirimi.id: Gunakan endpoint device-status untuk validasi instan tanpa lock pengiriman pesan
                $statusUrl = 'https://api.kirimi.id/v1/device-status';
                try {
                    $res = $client->post($statusUrl, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept'       => 'application/json',
                        ],
                        'json' => [
                            'user_code' => $userCode ?: 'default',
                            'device_id' => $deviceId ?: 'default',
                            'secret'    => $secret,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    $res = $client->post($apiUrl, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept'       => 'application/json',
                        ],
                        'json' => [
                            'user_code' => $userCode ?: 'default',
                            'device_id' => $deviceId ?: 'default',
                            'secret'    => $secret,
                            'receiver'  => '6281234567890',
                            'message'   => 'NODERA Test Ping',
                        ],
                    ]);
                }

                $body = (string) $res->getBody();
                $json = json_decode($body, true);
                if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
                    if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false')) {
                        $rawMsg = $json['message'] ?? 'Gagal memverifikasi API Kirimi.id';
                        $rawMsgLower = strtolower($rawMsg);
                        $errorMessage = match(true) {
                            str_contains($rawMsgLower, 'user not found') || str_contains($rawMsgLower, 'invalid secret') => 'User Code atau Secret Key Kirimi.id tidak valid. Pastikan format Nomor Pengirim adalah UserCode:DeviceID (misal: ' . ($userCode ?: 'KMxxx') . ':' . ($deviceId ?: 'D-xxx') . ').',
                            str_contains($rawMsgLower, 'device not found') => 'Device ID (' . ($deviceId ?: '-') . ') tidak ditemukan pada akun Kirimi.id Anda.',
                            str_contains($rawMsgLower, 'disconnected') || str_contains($rawMsgLower, 'not connected') => 'Perangkat WhatsApp Kirimi.id terputus (Status: Disconnected / Belum Scan QR di dashboard Kirimi.id).',
                            default => $rawMsg,
                        };
                    } else {
                        $isConnect = true;
                    }
                } else {
                    $errorMessage = $json['message'] ?? 'Gagal memverifikasi API Kirimi.id (HTTP ' . $res->getStatusCode() . ')';
                }
            } else {
                $res = $client->post($apiUrl, [
                    'headers' => [
                        'Authorization' => $token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'token' => $token,
                        'ping' => 'test',
                    ],
                ]);
                if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 400) {
                    $isConnect = true;
                } else if ($res->getStatusCode() === 401 || $res->getStatusCode() === 403) {
                    $errorMessage = 'Token otentikasi ditolak (HTTP ' . $res->getStatusCode() . '). Pastikan Token API Anda benar.';
                } else {
                    $isConnect = true;
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $code = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
            $body = $e->getResponse() ? (string)$e->getResponse()->getBody() : '';
            $json = json_decode($body, true);
            $rawError = $json['error'] ?? $json['reason'] ?? $json['message'] ?? '';

            if (stripos($rawError, 'perangkat tidak ditemukan') !== false || stripos($rawError, 'bukan milik') !== false) {
                $errorMessage = "MHWA Gateway: Perangkat tidak ditemukan atau bukan milik Anda! Pastikan 'Session ID Perangkat' sama persis dengan yang ada di dashboard mhwa.biz.id (contoh: dev_21_1789530016696, pastikan tidak kelebihan underscore) dan status perangkat Connected.";
            } elseif (stripos($rawError, 'tidak terdaftar di whatsapp') !== false || stripos($rawError, 'pesan masuk antrean') !== false) {
                // API key & Session ID perangkat terbukti valid dan terhubung ke WhatsApp
                $isConnect = true;
            } elseif ($code === 401 || $code === 403 || stripos($rawError, 'unauthorized') !== false || stripos($rawError, 'api key') !== false) {
                $errorMessage = "API Key / Token tidak valid atau telah kedaluwarsa (HTTP {$code}).";
            } elseif ($code === 400 && (str_contains($apiUrl, 'mhwa.biz.id') || $provider === 'mhwa')) {
                // If MHWA returned 400 with parameter validation error or queue status
                $errorMessage = $rawError ?: "Gagal memverifikasi API MHWA (HTTP {$code})";
            } else {
                $errorMessage = $rawError ?: "Gagal memverifikasi API (HTTP {$code}): " . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $errorMessage = "Koneksi ke gateway gagal: " . $e->getMessage();
        }

        if (!$isConnect) {
            return response()->json([
                'success' => false,
                'message' => $errorMessage ?: 'Gagal terhubung ke WhatsApp Gateway. Periksa kembali URL dan Token API.',
            ], 422);
        }

        $tenantId = $this->resolveCurrentTenantId();
        Setting::setValue('WHATSAPP_PROVIDER', $provider, $tenantId);
        Setting::setValue('WHATSAPP_API_URL', $apiUrl, $tenantId);
        Setting::setValue('WHATSAPP_TOKEN', $token, $tenantId);
        Setting::setValue('WHATSAPP_SENDER_PHONE', $senderPhone, $tenantId);
        Setting::setValue('WHATSAPP_AUTO_TYPING', $autoTyping, $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Koneksi WhatsApp Gateway berhasil diverifikasi dan disimpan!',
            'settings' => [
                'provider' => $provider,
                'api_url' => $apiUrl,
                'token' => $token,
                'sender_phone' => $senderPhone,
                'auto_typing' => $autoTyping === '1',
                'is_configured' => true,
            ],
        ]);
    }

    public function testAndSaveGenieacsSettings(Request $request)
    {
        $data = $request->validate([
            'url'      => 'required|string|max:255',
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'token'    => 'nullable|string|max:255',
        ]);

        $url = rtrim(trim($data['url']), '/');
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $token = trim($data['token'] ?? '');

        $isConnect = false;
        $errorMessage = '';
        $workingUrl = $url;

        $candidates = [
            $url . '/devices?projection=_id&limit=2',
            $url . '/api/devices?projection=_id&limit=2',
        ];

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $dockerHost = str_replace(['localhost', '127.0.0.1'], '172.17.0.1', $url);
            $candidates[] = $dockerHost . '/devices?projection=_id&limit=2';
            $candidates[] = $dockerHost . '/api/devices?projection=_id&limit=2';

            $internalHost = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $url);
            $candidates[] = $internalHost . '/devices?projection=_id&limit=2';
            $candidates[] = $internalHost . '/api/devices?projection=_id&limit=2';
        }

        $headers = ['Accept' => 'application/json'];
        if ($username && $password) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
        } elseif ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $client = new \GuzzleHttp\Client([
            'timeout' => 8,
            'connect_timeout' => 3,
            'verify' => false,
        ]);

        foreach ($candidates as $candidateUrl) {
            try {
                $res = $client->get($candidateUrl, ['headers' => $headers]);
                $statusCode = $res->getStatusCode();
                if ($statusCode >= 200 && $statusCode < 400) {
                    $isConnect = true;
                    // If candidate worked via docker host, adjust working url
                    if (str_contains($candidateUrl, '172.17.0.1') && (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1'))) {
                        $workingUrl = str_replace(['localhost', '127.0.0.1'], '172.17.0.1', $url);
                    }
                    break;
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $code = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                if ($code === 401 || $code === 403) {
                    $errorMessage = "Otentikasi ditolak oleh GenieACS (HTTP {$code}). Silakan periksa Username / Password / Token.";
                    break;
                }
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }

        if (!$isConnect) {
            return response()->json([
                'success' => false,
                'message' => $errorMessage ?: "Gagal terhubung ke URL GenieACS ({$url}). Pastikan IP dan Port NBI (biasanya 7557) dapat dijangkau.",
            ], 422);
        }

        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        $toSave = [
            'GENIEACS_URL' => $workingUrl,
            'GENIEACS_USERNAME' => $username,
            'GENIEACS_PASSWORD' => $password,
            'GENIEACS_TOKEN' => $token,
        ];

        foreach ($toSave as $key => $val) {
            \App\Models\Setting::setValue($key, (string) $val, $tenantId);
        }

        // Invalidate cache
        $this->genieacs->clearDeviceCache();
        \Illuminate\Support\Facades\Cache::forget("genieacs_devices_{$tenantId}");

        return response()->json([
            'success' => true,
            'message' => 'Koneksi GenieACS TR-069 NBI berhasil diverifikasi dan disimpan!',
            'settings' => $toSave,
        ]);
    }

    private function resolveCurrentTenantId(): ?int
    {
        $rawTenant = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!empty($rawTenant) && is_numeric($rawTenant) && (int) $rawTenant > 0) {
            if (\App\Models\Tenant::withoutGlobalScopes()->where('id', (int) $rawTenant)->exists()) {
                return (int) $rawTenant;
            }
        }
        return null;
    }

    public static function getDefaultWhatsappTemplates(): array
    {
        return [
            // ── 1. BILLING & ISOLIR PELANGGAN ISP ──
            [
                'name'           => 'Tagihan Baru',
                'category'       => 'billing',
                'category_label' => 'Billing Pelanggan',
                'description'    => 'Dikirim saat tagihan internet bulanan pelanggan baru saja diterbitkan.',
                'message'        => "*TAGIHAN INTERNET BARU*\n{perusahaan}\n\nYth. {nama},\nTagihan internet Anda untuk periode *{periode}* telah terbit dengan rincian:\n\n--------------------------------\nNo. Invoice : {invoice}\nPaket       : {paket}\nServer/NOC  : {router}\nTotal Bayar : *Rp {jumlah}*\nJatuh Tempo : {jatuh_tempo}\n--------------------------------\n\nCek Tagihan & Struk Digital:\n{link_struk}\n\nLogin Portal Pelanggan:\n{login_url}\n\nMohon lakukan pembayaran sebelum tanggal jatuh tempo untuk menghindari isolir otomatis.\nTerima kasih telah berlangganan bersama {perusahaan}.",
                'variables'      => ['{nama}', '{perusahaan}', '{periode}', '{invoice}', '{paket}', '{router}', '{jumlah}', '{jatuh_tempo}', '{link_struk}', '{login_url}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Pengingat Tagihan',
                'category'       => 'billing',
                'category_label' => 'Billing Pelanggan',
                'description'    => 'Dikirim sebagai reminder tagihan jatuh tempo (H-3, H-1, Hari H) kepada pelanggan yang belum lunas.',
                'message'        => "*PENGINGAT TAGIHAN INTERNET*\n{perusahaan}\n\nYth. {nama},\n\nKami menginformasikan bahwa tagihan layanan internet Anda saat ini *belum dibayar*:\n\n--------------------------------\nNo. Invoice : {invoice}\nPaket       : {paket}\nPeriode     : {periode}\nTotal Bayar : *Rp {jumlah}*\nJatuh Tempo : {jatuh_tempo}\n--------------------------------\n\nRincian Tagihan & Pembayaran:\n{link_struk}\n\nPortal Layanan:\n{login_url}\n\nMohon segera lakukan pembayaran sebelum tanggal jatuh tempo untuk menghindari penghentian/isolir layanan otomatis.\nAbaikan pesan ini jika Anda sudah melakukan pembayaran.\nTerima kasih.",
                'variables'      => ['{nama}', '{perusahaan}', '{invoice}', '{paket}', '{periode}', '{jumlah}', '{jatuh_tempo}', '{link_struk}', '{login_url}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Bukti Pembayaran Lunas',
                'category'       => 'billing',
                'category_label' => 'Billing Pelanggan',
                'description'    => 'Dikirim saat tagihan internet pelanggan berhasil diverifikasi / lunas (kwitansi digital).',
                'message'        => "*BUKTI PEMBAYARAN LUNAS*\n{perusahaan}\n\nYth. {nama},\nTerima kasih, pembayaran tagihan internet Anda telah kami terima dan diverifikasi.\n\n--------------------------------\nNo. Invoice : {invoice}\nStatus      : *LUNAS*\nPaket       : {paket}\nJumlah      : Rp {jumlah}\nWaktu Bayar : {waktu}\n--------------------------------\n\nLihat & Unduh Struk Resmi:\n{link_struk}\n\nAkses Portal Pelanggan:\n{login_url}\n\nLayanan internet Anda ({paket}) pada router {router} aktif tanpa kendala. Terima kasih atas kepercayaan Anda bersama {perusahaan}.",
                'variables'      => ['{nama}', '{perusahaan}', '{invoice}', '{paket}', '{jumlah}', '{waktu}', '{link_struk}', '{login_url}', '{router}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Pemberitahuan Isolir',
                'category'       => 'billing',
                'category_label' => 'Billing Pelanggan',
                'description'    => 'Dikirim otomatis saat sistem mengisolir koneksi pelanggan yang menunggak tagihan.',
                'message'        => "*PEMBERITAHUAN ISOLIR LAYANAN*\n{perusahaan}\n\nYth. {nama},\nKami memberitahukan bahwa akses layanan internet ({paket}) pada router {router} saat ini *Terisolir (Non-Aktif)* karena melewati batas waktu pembayaran jatuh tempo.\n\n--------------------------------\nStatus      : TERISOLIR (OFF)\nAkses Portal: {login_url}\n--------------------------------\n\nMohon segera lakukan pembayaran tagihan Anda agar koneksi internet dapat aktif kembali secara otomatis.\nAbaikan pemberitahuan ini jika Anda sudah menyelesaikan pembayaran.\nPusat Bantuan & Layanan: {perusahaan}",
                'variables'      => ['{nama}', '{perusahaan}', '{paket}', '{router}', '{login_url}'],
                'is_active'      => true,
            ],

            // ── 2. SAAS ISP ONBOARDING & SUBSCRIPTION ──
            [
                'name'           => 'Pendaftaran Baru',
                'category'       => 'saas',
                'category_label' => 'SaaS Onboarding',
                'description'    => 'Dikirim saat calon tenant mendaftar paket SaaS Cloud ISP Billing (menunggu pembayaran).',
                'message'        => "*PENDAFTARAN NODERA DITERIMA*\n{perusahaan}\n\nHalo {nama},\nTerima kasih telah mendaftar di {perusahaan}! Permintaan pendaftaran instansi Anda telah berhasil kami terima dengan rincian:\n\n--------------------------------\nInstansi / ISP : {nama_instansi}\nSubdomain      : {subdomain}\nPaket          : {paket} ({durasi} Bulan)\nTotal Tagihan  : *Rp {total_tagihan}*\nMetode Bayar   : {metode_bayar}\nStatus         : *MENUNGGU PEMBAYARAN*\n--------------------------------\n\nPetunjuk Pembayaran:\nSilakan selesaikan pembayaran sesuai nominal di atas. Jika sudah melakukan pembayaran, akun Anda akan segera diverifikasi dan diaktifkan oleh admin.\n\nCek Status & Detail Pendaftaran:\n{link_status}\n\nTerima kasih telah bergabung bersama {perusahaan}!",
                'variables'      => ['{nama}', '{nama_instansi}', '{perusahaan}', '{subdomain}', '{paket}', '{durasi}', '{total_tagihan}', '{metode_bayar}', '{link_status}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Pendaftaran Disetujui',
                'category'       => 'saas',
                'category_label' => 'SaaS Onboarding',
                'description'    => 'Dikirim saat admin menyetujui pendaftaran tenant baru (berisi kredensial & link login tenant).',
                'message'        => "*AKUN INSTANSI TELAH AKTIF!*\n{perusahaan}\n\nHalo {nama},\nKabar baik! Pendaftaran instansi Anda telah diverifikasi dan disetujui oleh admin. Akun Anda telah aktif dan siap digunakan.\n\n--------------------------------\nInstansi  : {nama_instansi}\nLogin URL : {login_url}\nUsername  : {username}\nPassword  : {password}\nPaket     : {paket} ({durasi} Bulan)\n--------------------------------\n\nSilakan login melalui link di atas untuk mulai mengelola router MikroTik, pelanggan, dan voucher WiFi Anda.\n\nTerima kasih telah bergabung bersama {perusahaan}!",
                'variables'      => ['{nama}', '{nama_instansi}', '{perusahaan}', '{login_url}', '{username}', '{password}', '{paket}', '{durasi}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'SaaS Subscription Reminder',
                'category'       => 'saas',
                'category_label' => 'SaaS Onboarding',
                'description'    => 'Pengingat masa aktif langganan Cloud SaaS ISP Billing tenant akan berakhir (H-7, H-3, H-1).',
                'message'        => "*PENGINGAT MASA AKTIF LANGGANAN CLOUD SAAS*\n{perusahaan}\n\nHalo Admin *{nama_instansi}*,\nMasa aktif langganan Cloud SaaS Billing Anda akan berakhir dalam *{sisa_hari}* (pada {expires_at}).\n\n--------------------------------\nInstance   : {nama_instansi} ({slug})\nMasa Aktif : s/d {expires_at}\n--------------------------------\n\nUntuk memastikan sistem billing, isolir otomatis, dan voucher hotspot pelanggan Anda tetap berjalan tanpa gangguan, pastikan saldo akun Anda mencukupi atau lakukan perpanjangan di:\n{link_perpanjang}\n\nTerima kasih atas kepercayaan Anda bermitra dengan {perusahaan}!",
                'variables'      => ['{nama_instansi}', '{slug}', '{sisa_hari}', '{expires_at}', '{link_perpanjang}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'SaaS Subscription Renewed',
                'category'       => 'saas',
                'category_label' => 'SaaS Onboarding',
                'description'    => 'Dikirim saat langganan Cloud SaaS ISP Billing tenant berhasil diperpanjang otomatis dari saldo.',
                'message'        => "*PERPANJANGAN LANGGANAN CLOUD SAAS BERHASIL*\n{perusahaan}\n\nHalo Admin *{nama_instansi}*,\nLangganan Cloud SaaS Billing Anda telah berhasil diperpanjang!\n\n--------------------------------\nInstance       : {nama_instansi} ({slug})\nBiaya          : Rp {nominal}\nMasa Aktif Baru: s/d *{expires_at}*\nStatus         : *AKTIF*\n--------------------------------\n\nSeluruh layanan operasional billing dan isolir pelanggan berjalan normal.\nTerima kasih telah mempercayakan sistem Anda pada {perusahaan}!",
                'variables'      => ['{nama_instansi}', '{slug}', '{nominal}', '{expires_at}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'SaaS Subscription Expired',
                'category'       => 'saas',
                'category_label' => 'SaaS Onboarding',
                'description'    => 'Dikirim saat masa aktif langganan Cloud SaaS tenant habis dan belum diperpanjang.',
                'message'        => "*LANGGANAN CLOUD SAAS TELAH KEDALUWARSA*\n{perusahaan}\n\nHalo Admin *{nama_instansi}*,\nMasa aktif langganan Cloud SaaS Billing Anda (*{slug}*) telah habis pada {expires_at}.\n\n--------------------------------\nInstance    : {nama_instansi}\nStatus      : *NONAKTIF (EXPIRED)*\nKedaluwarsa : {expires_at}\n--------------------------------\n\nUntuk mengaktifkan kembali akses panel admin dan pemrosesan billing pelanggan Anda, silakan lakukan perpanjangan di:\n{link_perpanjang}\n\nTerima kasih.",
                'variables'      => ['{nama_instansi}', '{slug}', '{expires_at}', '{link_perpanjang}', '{perusahaan}'],
                'is_active'      => true,
            ],

            // ── 3. MEMBER PANEL & DEPOSIT SALDO ──
            [
                'name'           => 'Member Baru',
                'category'       => 'member',
                'category_label' => 'Deposit & Saldo',
                'description'    => 'Selamat datang kepada user baru yang mendaftar di portal Member / VPN Remote.',
                'message'        => "*SELAMAT DATANG DI {perusahaan}*\n\nHalo {nama},\nAkun member Anda telah berhasil didaftarkan di portal {perusahaan}.\n\n--------------------------------\nNama   : {nama}\nEmail  : {email}\nNo. WA : {phone}\nSaldo  : Rp {saldo}\n--------------------------------\n\nAkses Dashboard Member:\n{login_url}\n\nMelalui portal ini, Anda dapat mengelola layanan VPN Remote, Mikhmon Online, Top-up saldo, dan layanan lainnya.\n\nTerima kasih telah bergabung bersama kami!",
                'variables'      => ['{nama}', '{email}', '{phone}', '{saldo}', '{login_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Topup Pending',
                'category'       => 'member',
                'category_label' => 'Deposit & Saldo',
                'description'    => 'Petunjuk pembayaran & konfirmasi saat member membuat permintaan deposit/top-up saldo.',
                'message'        => "*PERMINTAAN TOP-UP SALDO*\n{perusahaan}\n\nHalo {nama},\nPermintaan deposit saldo Anda telah dibuat dengan rincian:\n\n--------------------------------\nNo. Invoice  : {invoice}\nNominal      : *Rp {nominal}*\nMetode Bayar : {metode_bayar}\nStatus       : *MENUNGGU PEMBAYARAN*\nWaktu        : {waktu}\n--------------------------------\n\nSilakan lakukan pembayaran sesuai nominal yang tertera. Setelah pembayaran berhasil, saldo akan otomatis bertambah ke akun Anda.\n\nCek Status Deposit:\n{topup_url}\n\nTerima kasih telah menggunakan layanan {perusahaan}!",
                'variables'      => ['{nama}', '{invoice}', '{nominal}', '{metode_bayar}', '{waktu}', '{topup_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Topup Berhasil',
                'category'       => 'member',
                'category_label' => 'Deposit & Saldo',
                'description'    => 'Dikirim saat deposit saldo member telah berhasil diverifikasi dan saldo bertambah.',
                'message'        => "*TOP-UP SALDO BERHASIL!*\n{perusahaan}\n\nHalo {nama},\nDeposit saldo Anda telah berhasil diverifikasi dan ditambahkan ke akun Anda!\n\n--------------------------------\nNo. Invoice    : {invoice}\nNominal Masuk  : *Rp {nominal}*\nMetode Bayar   : {metode_bayar}\nTotal Saldo    : *Rp {saldo_sekarang}*\nStatus         : *BERHASIL / LUNAS*\nWaktu          : {waktu}\n--------------------------------\n\nSaldo Anda sudah aktif dan dapat digunakan untuk VPN Remote, Mikhmon Online, maupun perpanjangan layanan lainnya.\n\nAkses Dashboard:\n{login_url}\n\nTerima kasih atas kepercayaan Anda bersama {perusahaan}!",
                'variables'      => ['{nama}', '{invoice}', '{nominal}', '{metode_bayar}', '{saldo_sekarang}', '{waktu}', '{login_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Topup Ditolak',
                'category'       => 'member',
                'category_label' => 'Deposit & Saldo',
                'description'    => 'Pemberitahuan saat permintaan deposit saldo ditolak oleh admin beserta alasannya.',
                'message'        => "*PERMINTAAN TOP-UP DITOLAK*\n{perusahaan}\n\nHalo {nama},\nMohon maaf, permintaan deposit saldo Anda dengan No. Invoice *{invoice}* sebesar *Rp {nominal}* belum dapat disetujui.\n\nCatatan Admin: {alasan}\n\nJika Anda sudah melakukan pembayaran, silakan hubungi tim support kami dengan melampirkan bukti transfer yang valid.\nTerima kasih.",
                'variables'      => ['{nama}', '{invoice}', '{nominal}', '{alasan}', '{perusahaan}'],
                'is_active'      => true,
            ],

            // ── 4. CLOUD VPN REMOTE ──
            [
                'name'           => 'VPN Created',
                'category'       => 'vpn',
                'category_label' => 'Cloud VPN Remote',
                'description'    => 'Detail akun, port remote, dan kredensial saat akun VPN baru berhasil dibuat/aktif.',
                'message'        => "*AKUN VPN REMOTE BERHASIL DIAKTIFKAN*\n{perusahaan}\n\nHalo {nama},\nLayanan VPN Remote Anda telah berhasil dibuat dan langsung aktif!\n\n--------------------------------\nPaket       : {paket}\nServer      : {server_name}\nHost/Domain : {host}\nUsername    : *{username}*\nPassword    : *{password}*\nProtokol    : {protocol}\nIP Static   : {ip_static}\nMasa Aktif  : s/d {expires_at}\n--------------------------------\nAkses Port Remote :\n{ports}\n--------------------------------\n\nLihat detail akun & script MikroTik :\n{detail_url}\n\nTerima kasih telah menggunakan layanan {perusahaan}!",
                'variables'      => ['{nama}', '{paket}', '{server_name}', '{host}', '{username}', '{password}', '{protocol}', '{ip_static}', '{expires_at}', '{ports}', '{detail_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'VPN Renewed',
                'category'       => 'vpn',
                'category_label' => 'Cloud VPN Remote',
                'description'    => 'Konfirmasi perpanjangan masa aktif VPN remote berhasil (otomatis potong saldo / manual).',
                'message'        => "*PERPANJANGAN VPN REMOTE BERHASIL*\n{perusahaan}\n\nHalo {nama},\nMasa aktif akun VPN Remote *{username}* telah berhasil diperpanjang!\n\n--------------------------------\nAkun VPN    : {username}\nPaket       : {paket}\nBiaya       : *Rp {nominal}*\nMasa Aktif  : s/d *{expires_at}*\nSisa Saldo  : Rp {saldo_sekarang}\n--------------------------------\n\nTerima kasih telah berlangganan di {perusahaan}!",
                'variables'      => ['{nama}', '{username}', '{paket}', '{nominal}', '{expires_at}', '{saldo_sekarang}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'VPN Expiring Reminder',
                'category'       => 'vpn',
                'category_label' => 'Cloud VPN Remote',
                'description'    => 'Pengingat masa aktif VPN Remote akan berakhir dalam H-3 dan H-1.',
                'message'        => "*PENGINGAT MASA AKTIF VPN REMOTE*\n{perusahaan}\n\nHalo {nama},\nMasa aktif akun VPN Remote *{username}* akan berakhir dalam *{sisa_hari}* (pada {expires_at}).\n\n--------------------------------\nAkun VPN       : {username}\nPaket          : {paket}\nMasa Aktif     : {expires_at}\nAuto-Renew     : {auto_renew}\nSaldo Anda     : Rp {saldo_sekarang}\n--------------------------------\n\nPastikan saldo Anda mencukupi untuk perpanjangan otomatis atau perpanjang secara manual melalui tautan berikut:\n{link_perpanjang}\n\nTerima kasih.",
                'variables'      => ['{nama}', '{username}', '{paket}', '{sisa_hari}', '{expires_at}', '{auto_renew}', '{saldo_sekarang}', '{link_perpanjang}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'VPN Expired',
                'category'       => 'vpn',
                'category_label' => 'Cloud VPN Remote',
                'description'    => 'Pemberitahuan bahwa akun VPN telah kedaluwarsa dan dinonaktifkan (masa tenggang 7 hari).',
                'message'        => "*LAYANAN VPN REMOTE TELAH KEDALUWARSA*\n{perusahaan}\n\nHalo {nama},\nMasa aktif akun VPN Remote *{username}* telah habis pada {expires_at} dan saat ini dinonaktifkan.\n\n--------------------------------\nAkun VPN   : {username}\nStatus     : *EXPIRED (NONAKTIF)*\nKedaluwarsa: {expires_at}\n--------------------------------\n\nUntuk mengaktifkan kembali koneksi remote router Anda, silakan top up saldo dan perpanjang akun melalui:\n{link_perpanjang}\n\nAkun yang tidak diperpanjang dalam 7 hari akan dihapus permanen dari server.\nTerima kasih.",
                'variables'      => ['{nama}', '{username}', '{expires_at}', '{link_perpanjang}', '{perusahaan}'],
                'is_active'      => true,
            ],

            // ── 5. MIKHMON ONLINE ──
            [
                'name'           => 'Mikhmon Created',
                'category'       => 'mikhmon',
                'category_label' => 'Mikhmon Online',
                'description'    => 'Informasi login & URL web saat server Mikhmon Online baru berhasil dibuat.',
                'message'        => "*LAYANAN MIKHMON ONLINE BERHASIL DIAKTIFKAN*\n{perusahaan}\n\nHalo {nama},\nInstance Mikhmon Online Anda telah berhasil dibuat dan siap digunakan!\n\n--------------------------------\nSubdomain  : *{subdomain}*\n🌐 URL Web : {url}\n👤 User    : *nodera*\n🔑 Pass    : *nodera*\nRouterOS   : v{ros_version}\nMasa Aktif : s/d {expires_at}\n--------------------------------\n\nSilakan login ke link di atas untuk menghubungkan router MikroTik dan mulai mencetak voucher hotspot.\n\nTerima kasih telah menggunakan {perusahaan}!",
                'variables'      => ['{nama}', '{subdomain}', '{url}', '{ros_version}', '{expires_at}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Mikhmon Renewed',
                'category'       => 'mikhmon',
                'category_label' => 'Mikhmon Online',
                'description'    => 'Konfirmasi masa aktif Mikhmon Online berhasil diperpanjang otomatis dari saldo.',
                'message'        => "*PERPANJANGAN MIKHMON ONLINE BERHASIL*\n{perusahaan}\n\nHalo {nama},\nMasa aktif layanan Mikhmon Online *{subdomain}* telah berhasil diperpanjang!\n\n--------------------------------\nSubdomain   : {subdomain}\n🌐 URL Web  : {url}\nBiaya       : *Rp {nominal}*\nMasa Aktif  : s/d *{expires_at}*\nSisa Saldo  : Rp {saldo_sekarang}\n--------------------------------\n\nTerima kasih telah berlangganan di {perusahaan}!",
                'variables'      => ['{nama}', '{subdomain}', '{url}', '{nominal}', '{expires_at}', '{saldo_sekarang}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Mikhmon Expired',
                'category'       => 'mikhmon',
                'category_label' => 'Mikhmon Online',
                'description'    => 'Pemberitahuan masa aktif server Mikhmon telah kedaluwarsa (data aman selama 7 hari tenggang).',
                'message'        => "*LAYANAN MIKHMON ONLINE TELAH KEDALUWARSA*\n{perusahaan}\n\nHalo {nama},\nMasa aktif server Mikhmon Online Anda (*{subdomain}*) telah berakhir pada {expires_at}.\n\n--------------------------------\nSubdomain   : {subdomain}\nStatus      : *EXPIRED (NONAKTIF)*\nKedaluwarsa : {expires_at}\n--------------------------------\n\nUntuk mengaktifkan kembali server Mikhmon Anda, silakan lakukan perpanjangan di:\n{link_perpanjang}\n\nData voucher dan sesi Anda tetap tersimpan selama masa tenggang 7 hari.\nTerima kasih.",
                'variables'      => ['{nama}', '{subdomain}', '{expires_at}', '{link_perpanjang}', '{perusahaan}'],
                'is_active'      => true,
            ],

            // ── 6. MITRA REFERRAL & KOMISI ──
            [
                'name'           => 'Referral Disetujui',
                'category'       => 'referral',
                'category_label' => 'Mitra Referral',
                'description'    => 'Pemberitahuan pengajuan mitra referral disetujui (dapat link & kode referral).',
                'message'        => "*SELAMAT! KEMITRAAN REFERRAL ANDA DISETUJUI*\n{perusahaan}\n\nHalo {nama},\nPengajuan Anda sebagai Mitra Program Referral {perusahaan} telah *DISETUJUI*!\n\n--------------------------------\nKode Referral : *{kode_referral}*\nKomisi Anda   : *{rate}%* dari setiap topup downline\n🔗 Link Promo : {link_referral}\n--------------------------------\n\nBagikan link atau kode referral Anda kepada rekan ISP / Teknisi Jaringan. Setiap kali member yang Anda ajak melakukan deposit, komisi otomatis masuk ke saldo mitra Anda!\n\nPantau statistik & komisi Anda di:\n{portal_url}\n\nSukses selalu bersama {perusahaan}!",
                'variables'      => ['{nama}', '{kode_referral}', '{rate}', '{link_referral}', '{portal_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Komisi Referral Diterima',
                'category'       => 'referral',
                'category_label' => 'Mitra Referral',
                'description'    => 'Notifikasi real-time saat komisi masuk ketika downline berhasil melakukan top-up saldo.',
                'message'        => "*KOMISI REFERRAL DITERIMA !*\n{perusahaan}\n\nHalo {nama_mitra},\nKabar gembira! Anda baru saja mendapatkan komisi dari aktivitas deposit downline Anda:\n\n--------------------------------\nDownline       : {downline}\nTop-Up         : Rp {nominal_topup}\nRate Komisi    : {rate}%\nKomisi Masuk   : *+Rp {nominal_komisi}*\nSaldo Komisi   : *Rp {saldo_komisi}*\n--------------------------------\n\nKomisi dapat ditarik ke rekening bank / e-wallet atau langsung dikonversi ke saldo utama Anda.\n\nCek saldo komisi Anda di:\n{portal_url}\n\nTerima kasih atas kerja sama Anda!",
                'variables'      => ['{nama_mitra}', '{downline}', '{nominal_topup}', '{rate}', '{nominal_komisi}', '{saldo_komisi}', '{portal_url}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Pencairan Referral Selesai',
                'category'       => 'referral',
                'category_label' => 'Mitra Referral',
                'description'    => 'Pemberitahuan saat penarikan saldo komisi referral telah berhasil ditransfer oleh admin.',
                'message'        => "*PENCAIRAN KOMISI REFERRAL TELAH DITRANSFER*\n{perusahaan}\n\nHalo {nama_mitra},\nPermintaan penarikan dana komisi referral Anda telah berhasil diproses dan ditransfer!\n\n--------------------------------\nJumlah Dana   : *Rp {nominal}*\nBank / Tujuan : {bank}\nNo. Rekening  : *{rekening}*\nAtas Nama     : {atas_nama}\nStatus        : *SELESAI / DITRANSFER*\nCatatan       : {catatan}\n--------------------------------\n\nSilakan cek mutasi rekening Anda. Terima kasih atas kerja sama Anda yang luar biasa bersama {perusahaan}!",
                'variables'      => ['{nama_mitra}', '{nominal}', '{bank}', '{rekening}', '{atas_nama}', '{catatan}', '{perusahaan}'],
                'is_active'      => true,
            ],
            [
                'name'           => 'Penarikan Referral Ditolak',
                'category'       => 'referral',
                'category_label' => 'Mitra Referral',
                'description'    => 'Pemberitahuan saat penarikan dana komisi ditolak (saldo dikembalikan ke akun mitra).',
                'message'        => "*PENARIKAN KOMISI REFERRAL DITOLAK*\n{perusahaan}\n\nHalo {nama_mitra},\nPermintaan penarikan komisi referral Anda sebesar *Rp {nominal}* tidak dapat diproses.\n\n--------------------------------\nAlasan Penolakan : {alasan}\nStatus Dana      : *Dikembalikan ke Saldo Komisi*\n--------------------------------\n\nSaldo komisi telah dikembalikan ke akun Anda. Silakan periksa kembali nomor rekening / informasi pembayaran dan ajukan ulang.\n\nTerima kasih.",
                'variables'      => ['{nama_mitra}', '{nominal}', '{alasan}', '{perusahaan}'],
                'is_active'      => true,
            ],
        ];
    }

    public function whatsappTemplates()
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        $defaultTemplates = self::getDefaultWhatsappTemplates();

        // Fetch DB templates: Global templates merged with Tenant overrides
        $dbTemplates = collect();
        try {
            if ($tenantId !== null) {
                $globalTpls = DB::table('whatsapp_templates')->whereNull('tenant_id')->get()->keyBy('name');
                $tenantTpls = DB::table('whatsapp_templates')->where('tenant_id', $tenantId)->get()->keyBy('name');
                $dbTemplates = $globalTpls->merge($tenantTpls);
            } else {
                $dbTemplates = DB::table('whatsapp_templates')
                    ->whereNull('tenant_id')
                    ->get()
                    ->keyBy('name');
            }
        } catch (\Throwable $e) {}

        $templates = [];
        foreach ($defaultTemplates as $idx => $dt) {
            $cat = $dt['category'] ?? 'general';
            // If NOT superadmin and in tenant context, show billing and general templates
            if (!$isSuperadmin && !in_array($cat, ['billing', 'general'])) {
                continue;
            }

            $dbTpl = $dbTemplates->get($dt['name']);
            $templates[] = [
                'id'             => $dbTpl->id ?? ($idx + 1),
                'name'           => $dt['name'],
                'category'       => $cat,
                'category_label' => $dt['category_label'] ?? 'Umum',
                'description'    => $dt['description'] ?? '',
                'variables'      => $dt['variables'] ?? [],
                'message'        => $dbTpl->message ?? $dt['message'],
                'is_active'      => (bool) ($dbTpl->is_active ?? true),
            ];
        }

        $reminderDaysSetting = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_DAYS')->value('value') ?? '3,1,0';
        $reminderDays = array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $reminderDaysSetting)), fn($v) => is_numeric($v))));
        if (empty($reminderDays)) {
            $reminderDays = [3, 1, 0];
        }

        $notifSettings = [
            'reminder_enabled' => (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_AUTO')->value('value') ?? Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'NOTIF_REMINDER_ENABLED')->value('value') ?? '1') === '1',
            'reminder_days'    => $reminderDays,
            'isolir_enabled'   => Setting::getValue('NOTIF_ISOLIR_ENABLED', '1') === '1',
        ];

        if ($tenantId !== null) {
            $waSettings = [
                'provider'      => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->where('tenant_id', $tenantId)->value('value') ?? 'fonnte',
                'api_url'       => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->where('tenant_id', $tenantId)->value('value') ?? 'https://api.fonnte.com/send',
                'token'         => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->where('tenant_id', $tenantId)->value('value') ?? '',
                'sender_phone'  => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->where('tenant_id', $tenantId)->value('value') ?? '',
                'auto_typing'   => (Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_AUTO_TYPING')->where('tenant_id', $tenantId)->value('value') ?? '1') === '1',
                'is_configured' => !empty(Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->where('tenant_id', $tenantId)->value('value')),
            ];
        } else {
            $waSettings = [
                'provider'      => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->whereNull('tenant_id')->value('value') ?? 'fonnte',
                'api_url'       => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->whereNull('tenant_id')->value('value') ?? 'https://api.fonnte.com/send',
                'token'         => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->whereNull('tenant_id')->value('value') ?? '',
                'sender_phone'  => Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->whereNull('tenant_id')->value('value') ?? '',
                'auto_typing'   => (Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_AUTO_TYPING')->whereNull('tenant_id')->value('value') ?? '1') === '1',
                'is_configured' => !empty(Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->whereNull('tenant_id')->value('value')),
            ];
        }

        $view = $isSuperadmin ? 'Superadmin/WhatsappTemplates' : 'Admin/WhatsappTemplates';
        
        $initialDeviceStatus = null;
        try {
            $waSvc = new WhatsappService($tenantId, false, $isSuperadmin && empty($tenantId));
            $initialDeviceStatus = $waSvc->checkDeviceStatus();
        } catch (\Throwable $e) {
            $initialDeviceStatus = [
                'is_configured' => $waSettings['is_configured'] ?? false,
                'is_connected'  => false,
                'status'        => 'disconnected',
                'status_label'  => 'Terputus / Belum Dicek',
                'provider'      => strtoupper($waSettings['provider'] ?? 'NONE'),
                'sender_id'     => $waSettings['sender_phone'] ?? '-',
                'details'       => 'Belum dapat memeriksa status perangkat.',
                'checked_at'    => now()->format('H:i:s, d M Y'),
            ];
        }

        return Inertia::render($view, [
            'templates'     => $templates,
            'is_superadmin' => $isSuperadmin,
            'is_locked'     => false,
            'notifSettings' => $notifSettings,
            'waSettings'    => $waSettings,
            'deviceStatus'  => $initialDeviceStatus,
        ]);
    }

    /**
     * Probe live WhatsApp device / session status
     */
    public function checkWhatsappDeviceStatus(Request $request)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        $service = new WhatsappService($tenantId, false, $isSuperadmin && empty($tenantId));
        $status = $service->checkDeviceStatus();

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    public function whatsappTemplateToggle(Request $request, $id)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        $reqName = trim((string) $request->input('name', ''));
        $hasExplicit = $request->has('is_active');
        $explicitVal = $request->boolean('is_active');

        // Case 1: Superadmin platform context (tenant_id IS NULL)
        if ($isSuperadmin && empty($tenantId)) {
            $row = DB::table('whatsapp_templates')
                ->whereNull('tenant_id')
                ->where(function ($q) use ($id, $reqName) {
                    $q->where('id', $id);
                    if (!empty($reqName)) {
                        $q->orWhere('name', $reqName);
                    }
                })
                ->first();

            if ($row) {
                $next = $hasExplicit ? $explicitVal : !(bool) $row->is_active;
                DB::table('whatsapp_templates')
                    ->where('id', $row->id)
                    ->update(['is_active' => $next, 'updated_at' => now()]);

                return response()->json([
                    'success'   => true,
                    'id'        => $row->id,
                    'is_active' => (bool) $next,
                    'message'   => 'Status template berhasil diperbarui.',
                ]);
            }

            // Create global entry if not existing in DB yet
            $defaultTemplates = self::getDefaultWhatsappTemplates();
            $tplName = $reqName;
            $tplMessage = '';
            foreach ($defaultTemplates as $idx => $dt) {
                if ($dt['name'] === $reqName || ($idx + 1) == $id) {
                    $tplName = $dt['name'];
                    $tplMessage = $dt['message'];
                    break;
                }
            }

            $next = $hasExplicit ? $explicitVal : false;
            $newId = DB::table('whatsapp_templates')->insertGetId([
                'name'       => $tplName ?: ('Template ' . $id),
                'message'    => $tplMessage ?: 'Format pesan',
                'tenant_id'  => null,
                'is_active'  => $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success'   => true,
                'id'        => $newId,
                'is_active' => (bool) $next,
                'message'   => 'Status template berhasil diperbarui.',
            ]);
        }

        // Case 2: Tenant Admin or Impersonated Tenant Context
        $existingTenantTpl = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($id, $reqName) {
                $q->where('id', $id);
                if (!empty($reqName)) {
                    $q->orWhere('name', $reqName);
                }
            })
            ->first();

        if ($existingTenantTpl) {
            $next = $hasExplicit ? $explicitVal : !(bool) $existingTenantTpl->is_active;
            DB::table('whatsapp_templates')
                ->where('id', $existingTenantTpl->id)
                ->update(['is_active' => $next, 'updated_at' => now()]);

            return response()->json([
                'success'   => true,
                'id'        => $existingTenantTpl->id,
                'is_active' => (bool) $next,
                'message'   => 'Status template berhasil diperbarui.',
            ]);
        }

        // Tenant does not have a custom row yet. Find base message.
        $baseTpl = DB::table('whatsapp_templates')
            ->whereNull('tenant_id')
            ->where(function ($q) use ($id, $reqName) {
                $q->where('id', $id);
                if (!empty($reqName)) {
                    $q->orWhere('name', $reqName);
                }
            })
            ->first();

        $tplName = $reqName ?: ($baseTpl->name ?? null);
        $tplMessage = $baseTpl->message ?? null;

        if (!$tplName || !$tplMessage) {
            foreach (self::getDefaultWhatsappTemplates() as $idx => $dt) {
                if ($dt['name'] === $tplName || ($idx + 1) == $id || (!empty($reqName) && $dt['name'] === $reqName)) {
                    $tplName = $dt['name'];
                    $tplMessage = $dt['message'];
                    break;
                }
            }
        }

        $currentDefault = $baseTpl ? (bool) $baseTpl->is_active : true;
        $next = $hasExplicit ? $explicitVal : !$currentDefault;

        $newId = DB::table('whatsapp_templates')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => $tplName ?: ('Template ' . $id),
            'message'    => $tplMessage ?: 'Format pesan',
            'is_active'  => $next,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $newId,
            'is_active' => (bool) $next,
            'message'   => 'Status template berhasil diperbarui.',
        ]);
    }

    public function whatsappTemplateSave(Request $request, $id = null)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        $reqId = $id ?? $request->input('id');
        $name = trim((string) $request->input('name', ''));
        $message = trim((string) $request->input('message', ''));
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        if (empty($message)) {
            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Format pesan template tidak boleh kosong.'], 422);
            }
            return redirect()->back()->with('error', 'Format pesan template tidak boleh kosong.');
        }

        // Auto-resolve template name from ID if not provided
        if (empty($name) && !empty($reqId)) {
            $foundDb = DB::table('whatsapp_templates')->where('id', $reqId)->first();
            if ($foundDb && !empty($foundDb->name)) {
                $name = $foundDb->name;
            } else {
                foreach (self::getDefaultWhatsappTemplates() as $idx => $dt) {
                    if (($idx + 1) == $reqId || (string) $reqId === (string) ($idx + 1)) {
                        $name = $dt['name'];
                        break;
                    }
                }
            }
        }

        if (empty($name)) {
            $name = 'Template ' . ($reqId ?: rand(100, 999));
        }

        if ($isSuperadmin && empty($tenantId)) {
            // Superadmin platform global save (tenant_id = null)
            $existing = DB::table('whatsapp_templates')
                ->whereNull('tenant_id')
                ->where(function ($q) use ($reqId, $name) {
                    if (!empty($reqId)) {
                        $q->where('id', $reqId);
                    }
                    $q->orWhere('name', $name);
                })
                ->first();

            if ($existing) {
                DB::table('whatsapp_templates')
                    ->where('id', $existing->id)
                    ->update([
                        'message'    => $message,
                        'is_active'  => $isActive,
                        'updated_at' => now(),
                    ]);
                $savedId = $existing->id;
            } else {
                $savedId = DB::table('whatsapp_templates')->insertGetId([
                    'name'       => $name,
                    'message'    => $message,
                    'tenant_id'  => null,
                    'is_active'  => $isActive,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => true, 'id' => $savedId, 'message' => 'Template pesan global berhasil disimpan oleh SuperAdmin.']);
            }
            return redirect()->back()->with('msg', 'Template pesan global berhasil disimpan oleh SuperAdmin.');
        }

        // Tenant custom template save (tenant_id = $tenantId)
        $existing = DB::table('whatsapp_templates')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($reqId, $name) {
                if (!empty($reqId)) {
                    $q->where('id', $reqId);
                }
                $q->orWhere('name', $name);
            })
            ->first();

        if ($existing) {
            DB::table('whatsapp_templates')
                ->where('id', $existing->id)
                ->update([
                    'message'    => $message,
                    'is_active'  => $isActive,
                    'updated_at' => now(),
                ]);
            $savedId = $existing->id;
        } else {
            $savedId = DB::table('whatsapp_templates')->insertGetId([
                'tenant_id'  => $tenantId,
                'name'       => $name,
                'message'    => $message,
                'is_active'  => $isActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!$request->header('X-Inertia') && $request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $savedId, 'message' => 'Template pesan berhasil disimpan.']);
        }

        return redirect()->back()->with('msg', 'Template pesan berhasil disimpan.');
    }

    public function whatsappTemplateDelete($id)
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        if ($isSuperadmin && empty($tenantId)) {
            DB::table('whatsapp_templates')->whereNull('tenant_id')->where('id', $id)->delete();
        } elseif ($tenantId) {
            DB::table('whatsapp_templates')->where('tenant_id', $tenantId)->where('id', $id)->delete();
        }

        if (!request()->header('X-Inertia') && request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Template berhasil dikembalikan ke standar.']);
        }
        return redirect()->back()->with('msg', 'Template berhasil dikembalikan ke standar.');
    }

    public function whatsappTemplateResetDefaults()
    {
        $tenantId = $this->resolveCurrentTenantId();
        $authUser = auth()->user();
        $isSuperadmin = session('admin_role') === 'superadmin'
            || session('superadmin_logged_in')
            || ($authUser && (strtolower((string) $authUser->role) === 'superadmin' || ($authUser->is_superadmin ?? false)));

        if ($isSuperadmin && empty($tenantId)) {
            DB::table('whatsapp_templates')->whereNull('tenant_id')->delete();
        } elseif ($tenantId) {
            DB::table('whatsapp_templates')->where('tenant_id', $tenantId)->delete();
        }

        if (!request()->header('X-Inertia') && request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Semua template pesan berhasil di-reset ke standar bawaan sistem.',
            ]);
        }

        return redirect()->back()->with('msg', 'Semua template pesan berhasil di-reset ke standar bawaan sistem.');
    }

    public function mikrotikDisplay()
    {
        $routers = \App\Models\Mikrotik::withCount('customers')
            ->where('tenant_id', session('tenant_id'))
            ->orderBy('name')->get();
        return Inertia::render('Admin/MikrotikDisplay', [
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'host' => $r->host, 'customers_count' => (int) ($r->customers_count ?? 0)]),
        ]);
    }

    public function collectorPayments()
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        $payments = DB::table('invoices')
            ->join('collectors', 'invoices.collector_id', '=', 'collectors.id')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('invoices.paid', true)
            ->whereNotNull('invoices.collector_id')
            ->select('invoices.*', 'collectors.name as collector_name', 'customers.name as customer_name')
            ->orderBy('invoices.paid_at', 'desc')
            ->limit(100)->get();
        return Inertia::render('Admin/CollectorPayments', [
            'payments' => $payments->map(fn ($p) => ['id' => $p->id, 'invoice_number' => $p->invoice_number ?? '', 'customer_name' => $p->customer_name ?? $p->customer?->name, 'amount' => (float) $p->amount, 'paid_at' => $p->paid_at?->toIso8601String(), 'collector' => $p->collector?->name]),
        ]);
    }


    public function semuaFitur()
    {
        return Inertia::render('Admin/SemuaFitur', [
            'tenantName' => session('tenant_name', 'NODERA'),
            'adminName' => session('admin_name', 'Admin'),
            'mikhmon' => [
                'price' => (float) config('mikhmon.monthly_price'),
                'url' => config('app.panel_url', 'https://panel.dgtlnetsolution.com') . '/mikhmon',
            ],
        ]);
    }

    public function promoslides()
    {
        $slides = collect();
        try {
            $slides = DB::table('promo_slides')
                ->where('tenant_id', session('tenant_id'))
                ->orderBy('id')->get();
        } catch (\Exception $e) {
            Log::error('promoslides: ' . $e->getMessage());
        }
        return Inertia::render('Admin/PromoSlides', [
            'create' => (bool) request('create'),
            'slides' => $slides->map(fn ($s) => ['id' => $s->id, 'title' => $s->title ?? '', 'image' => $s->image ?? null, 'is_active' => (bool) ($s->is_active ?? true), 'sort_order' => (int) ($s->sort_order ?? 0)]),
        ]);
    }

    public function promoslidesStore(Request $request)
    {
        $request->validate([
            'title' => 'required|max:255',
            'image' => 'required|image|max:2048',
            'link' => 'nullable|url|max:500',
        ]);

        $path = $request->file('image')->store('promo_slides', 'public');

        DB::table('promo_slides')->insert([
            'title' => $request->title,
            'image' => $path,
            'link' => $request->link,
            'sort_order' => 0,
            'is_active' => true,
            'tenant_id' => session('tenant_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('msg', 'Slide berhasil ditambahkan');
    }

    public function promoslidesDelete($id)
    {
        DB::table('promo_slides')->where('id', $id)->where('tenant_id', session('tenant_id'))->delete();
        return redirect()->back()->with('msg', 'Slide berhasil dihapus');
    }

    public function sidebarSettings()
    {
        $tenantId = session('tenant_id');
        $all = DB::table('sidebar_settings')->where('tenant_id', $tenantId)->pluck('is_visible', 'menu_key');
        return Inertia::render('Admin/SidebarSettings', ['settings' => $all->toArray()]);
    }

    public function sidebarSettingsSave(Request $request)
    {
        $tenantId = session('tenant_id');
        $menus = $request->input('menu', []);
        $allKeys = ['dashboard','analytics','billing','customers','packages','invoices','mikrotik','pppoe','hotspot','olt','genieacs','map','trouble','employees','inventory','attendance','payroll','finance','broadcast','voucher'];

        DB::table('sidebar_settings')->where('tenant_id', $tenantId)->delete();
        foreach ($allKeys as $k) {
            DB::table('sidebar_settings')->insert([
                'tenant_id' => $tenantId, 'menu_key' => $k,
                'is_visible' => in_array($k, $menus) ? 1 : 0,
                'sort_order' => array_search($k, $allKeys),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return redirect()->back()->with('msg', 'Pengaturan sidebar disimpan');
    }

    public function dashboardMenuSettingsSave(Request $request)
    {
        $tenantId = session('tenant_id');
        $menus = $request->input('menu', []);
        $allKeys = ['customers','invoices','packages','collectors','bayar-kolektor','payment-gateway','qris','promo-slides','finance','expenses','payroll','analytics','mikrotik','pppoe','hotspot','voucher','olt','top-bandwidth','trouble','broadcast','employees','attendance','inventory'];

        DB::table('dashboard_menu_settings')->where('tenant_id', $tenantId)->delete();
        $order = 0;
        foreach ($menus as $k) {
            if (!in_array($k, $allKeys)) continue;
            DB::table('dashboard_menu_settings')->insert([
                'tenant_id' => $tenantId, 'menu_key' => $k, 'sort_order' => $order++,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return redirect()->back()->with('msg', 'Menu dashboard disimpan');
    }

    public function printPayslip(Request $request)
    {
        $payroll = null;
        if ($request->has('id')) {
            $payroll = \App\Models\Payroll::find($request->id);
        }
        return view('admin.print_payslip', compact('payroll'));
    }

    public function printVouchers(Request $request)
    {
        $tenantId = session('tenant_id');
        $query = \App\Models\Voucher::query();

        if ($request->filled('ids')) {
            $ids = explode(',', $request->ids);
            $query->whereIn('id', $ids);
        } elseif ($request->filled('batch')) {
            $query->where('batch_id', $request->batch);
        } elseif ($request->filled('profile')) {
            $query->where('profile', $request->profile);
        } else {
            $query->latest()->limit(50);
        }

        $vouchers = $query->get();
        $routerId = $vouchers->first()?->router_id ?? null;
        $activeRouter = $routerId 
            ? \App\Models\Mikrotik::find($routerId) 
            : \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->first();

        $hotspotName = \App\Models\Setting::getValue('HOTSPOT_NAME', session('tenant_name') ?? ($activeRouter?->name ?? 'NODERA HOTSPOT'));
        $dnsName = \App\Models\Setting::getValue('HOTSPOT_DNS', null);

        if (!$dnsName && $activeRouter) {
            try {
                $service = new \App\Services\MikrotikService($activeRouter);
                $serverProfiles = $service->execute('/ip/hotspot/profile/print');
                foreach ($serverProfiles as $sp) {
                    if (!empty($sp['dns-name'])) {
                        $dnsName = $sp['dns-name'];
                        break;
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        if (!$dnsName) {
            $dnsName = 'nodera.login';
        }

        $logoRaw = \App\Models\Setting::getValue('COMPANY_LOGO', null) ?: \App\Models\Setting::getValue('TENANT_LOGO', null);
        $logo = \App\Models\Setting::resolveLogoUrl($logoRaw, null);
        $phone = \App\Models\Setting::getValue('COMPANY_PHONE', null) ?: \App\Models\Setting::getValue('TENANT_PHONE', null);

        return view('admin.print_vouchers', compact('vouchers', 'template', 'batchId', 'hotspotName', 'dnsName', 'logo', 'phone'));
    }

    public function reports(Request $request)
    {
        $data = $this->buildReportData(
            $request->input('type', 'revenue'),
            $request->input('from'),
            $request->input('to')
        );

        return Inertia::render('Admin/Reports', $data);
    }

    public function reportsPrint(Request $request)
    {
        $data = $this->buildReportData(
            $request->input('type', 'revenue'),
            $request->input('from'),
            $request->input('to')
        );

        return view('admin.reports_print', $data);
    }

    private function buildReportData(string $type, ?string $from, ?string $to): array
    {
        $from = $from ? \Carbon\Carbon::parse($from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $to ? \Carbon\Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $type = in_array($type, ['revenue', 'customers', 'collection']) ? $type : 'revenue';

        $rows = collect();
        $total = 0;
        $summary = [];

        if ($type === 'revenue') {
            $rows = \App\Models\Invoice::selectRaw("DATE(paid_at) as report_date, COUNT(*) as invoice_count, SUM(amount) as total_amount")
                ->where('paid', true)
                ->whereBetween('paid_at', [$from, $to])
                ->groupBy('report_date')
                ->orderBy('report_date')
                ->get();
            $total = $rows->sum('total_amount');
            $summary = ['invoice_count' => $rows->sum('invoice_count'), 'total' => $total];
        } elseif ($type === 'customers') {
            $rows = \App\Models\Customer::whereBetween('created_at', [$from, $to])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'phone', 'created_at']);
            $total = $rows->count();
            $summary = ['customer_count' => $total];
        } elseif ($type === 'collection') {
            $rows = \App\Models\Invoice::selectRaw("collectors.name as collector_name, COUNT(*) as invoice_count, SUM(amount) as total_amount")
                ->join('collectors', 'collectors.id', '=', 'invoices.collector_id', 'left')
                ->where('invoices.paid', true)
                ->whereBetween('invoices.paid_at', [$from, $to])
                ->groupBy('collectors.id', 'collectors.name')
                ->orderByDesc('total_amount')
                ->get();
            $total = $rows->sum('total_amount');
            $summary = ['invoice_count' => $rows->sum('invoice_count'), 'total' => $total];
        }

        return compact('type', 'from', 'to', 'rows', 'total', 'summary');
    }

    public function voucherPackages()
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $packages = DB::table('voucher_packages')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')->get();
        return view('admin.voucher_packages', compact('packages'));
    }

    public function voucherPackageAdd(Request $request)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $request->merge(['price' => $this->sanitizePrice($request->price)]);
        $request->validate([
            'name' => 'required|max:255',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
        ]);
        DB::table('voucher_packages')->insert([
            'name' => $request->name, 'price' => $request->price,
            'duration_days' => $request->duration_days,
            'bandwidth_up' => $request->bandwidth_up,
            'bandwidth_down' => $request->bandwidth_down,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'tenant_id' => $tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->back()->with('msg', 'Paket voucher berhasil ditambahkan');
    }

    public function voucherPackageEdit(Request $request, $id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'bandwidth_up' => 'nullable|integer|min:0',
            'bandwidth_down' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $data = $request->only('name', 'price', 'duration_days', 'bandwidth_up', 'bandwidth_down', 'description');
        $data['price'] = $this->sanitizePrice($data['price']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_at'] = now();
        DB::table('voucher_packages')->where('id', $id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->update($data);
        return redirect()->back()->with('msg', 'Paket voucher berhasil diperbarui');
    }

    public function voucherPackageDelete(Request $request, $id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $deleteFromRouter = $request->boolean('delete_from_router', true);
        $package = DB::table('voucher_packages')->where('id', $id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if ($package) {
            if ($deleteFromRouter) {
                $routers = \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->get();
                $routerId = $request->input('router_id') ?? $package->router_id ?? null;
                $targetRouters = $routerId ? $routers->where('id', $routerId) : $routers;

                foreach ($targetRouters as $router) {
                    try {
                        $svc = new \App\Services\MikrotikService($router);
                        if ($svc->isConnected()) {
                            $mProfiles = $svc->query('/ip/hotspot/user/profile/print', ['?name' => $package->name]);
                            if (!empty($mProfiles)) {
                                foreach ($mProfiles as $mp) {
                                    if (!empty($mp['.id'])) {
                                        $svc->query('/ip/hotspot/user/profile/remove', ['.id' => $mp['.id']]);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Delete hotspot profile error: " . $e->getMessage());
                    }
                }
            }

            DB::table('voucher_packages')->where('id', $id)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->delete();
        }

        return redirect()->back()->with('msg', $deleteFromRouter ? 'Paket voucher dan profil MikroTik berhasil dihapus' : 'Paket voucher berhasil dihapus dari database');
    }

    public function agentReports(Request $request)
    {
        if ($request->has('print')) {
            return view('admin.agent_reports_print');
        }

        return Inertia::render('Admin/AgentReports');
    }

    public function saveFcmToken(Request $request)
    {
        if (auth()->check() && $request->filled('token')) {
            auth()->user()->update(['fcm_token' => $request->token]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }

    // Sanitasi harga: hapus titik (ribuan), ubah koma jadi titik (desimal)
    private function sanitizePrice(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }
}
