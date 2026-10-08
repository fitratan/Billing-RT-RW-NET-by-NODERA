<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerUsage;
use App\Models\Mikrotik;
use App\Services\MikrotikService;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TopBandwidthController extends Controller
{
    /**
     * Tampilkan halaman Top Bandwidth.
     */
    public function index()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $routers = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host']);

        return Inertia::render('Admin/TopBandwidth', [
            'routers' => $routers,
        ]);
    }

    /**
     * Ambil data Top Bandwidth kumulatif bulan berjalan (tidak reset saat DC).
     */
    public function data(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $type = $request->input('type', 'pppoe');
        $routerId = $request->input('router_id');

        if (!$routerId) {
            return response()->json([
                'success' => false,
                'message' => 'Router tidak dipilih',
            ]);
        }

        $router = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($routerId);
        if (!$router) {
            return response()->json([
                'success' => false,
                'message' => 'Router tidak ditemukan',
            ]);
        }

        $mik = null;
        try {
            $mikService = new MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) $router->port,
            ]);
            if ($mikService->isConnected()) {
                $mik = $mikService;
            }
        } catch (\Throwable $e) {
            $mik = null;
        }

        try {
            if ($type === 'hotspot') {
                if (!$mik) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat terhubung ke ' . $router->name . ' (MikroTik offline)',
                    ]);
                }
                return $this->getHotspotData($mik);
            }
            if ($type === 'arp') {
                return $this->getArpData($mik, $router, $tenantId);
            }

            return $this->getPppoeData($mik, $router, $tenantId);
        } catch (\Throwable $e) {
            Log::error('TopBandwidth error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Ambil data bandwidth PPPoE (Akumulasi Kumulatif Bulan Berjalan).
     */
    private function getPppoeData(?MikrotikService $mik, Mikrotik $router, ?int $tenantId): \Illuminate\Http\JsonResponse
    {
        // 1. Ambil Sesi Aktif PPPoE dari MikroTik (jika MikroTik terhubung)
        $activeSessions = [];
        if ($mik && $mik->isConnected()) {
            try {
                $activeSessions = $mik->query('/ppp/active/print', [
                    '.proplist' => 'name,address,uptime,caller-id',
                ]) ?: [];
            } catch (\Throwable $e) {
                $activeSessions = [];
            }
        }

        $activeByUsername = [];
        foreach ($activeSessions as $act) {
            $uName = trim($act['name'] ?? '');
            if ($uName) {
                $activeByUsername[$uName] = $act;
            }
        }

        // 2. Ambil data interface untuk membaca rx-byte & tx-byte secara langsung
        $interfaces = [];
        if ($mik && $mik->isConnected()) {
            try {
                $interfaces = $mik->query('/interface/print', [
                    '.proplist' => 'name,rx-byte,tx-byte,type',
                ]) ?: [];
            } catch (\Throwable $e) {
                $interfaces = [];
            }
        }

        $ifaceMap = [];
        foreach ($interfaces as $iface) {
            $name = $iface['name'] ?? '';
            if ($name) {
                $ifaceMap[$name] = $iface;
                $cleanName = trim($name, '<>');
                $ifaceMap[$cleanName] = $iface;
            }
        }

        // 3. Sync traffic aktif router saat ini ke CustomerUsage database (jika MikroTik online)
        if ($mik && $mik->isConnected()) {
            try {
                app(UsageService::class)->pollRouter($router);
            } catch (\Throwable $e) {
                Log::warning("[TopBandwidth] Rapid sync poll failed: " . $e->getMessage());
            }
        }

        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        // 4. Ambil data pelanggan dari database
        $customers = Customer::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) use ($router) {
                $q->where('router_id', $router->id)
                  ->orWhereNull('router_id');
            })
            ->whereNotNull('pppoe_username')
            ->with(['package'])
            ->get();

        // 5. Ambil akumulasi usage bulan ini
        $usages = CustomerUsage::where('period_month', $month)
            ->where('period_year', $year)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->keyBy('customer_id');

        $result = [];
        $grandTotal = 0;
        $processedUsernames = [];

        foreach ($customers as $cust) {
            $username = trim($cust->pppoe_username ?? '');
            if (!$username) {
                continue;
            }

            $usage = $usages->get($cust->id);
            $active = $activeByUsername[$username] 
                ?? ($activeByUsername[strtolower($username)] 
                ?? ($activeByUsername[strtoupper($username)] ?? null));
            $isOnline = !empty($active);

            // Cari interface MikroTik untuk pelanggan ini
            $iface = $ifaceMap["<pppoe-{$username}>"] 
                ?? ($ifaceMap["pppoe-{$username}"] 
                ?? ($ifaceMap[$username] 
                ?? ($ifaceMap["<pppoe-" . strtolower($username) . ">"] ?? null)));

            $liveIn = $iface ? (int) ($iface['rx-byte'] ?? 0) : ($active ? (int) ($active['bytes-in'] ?? 0) : 0);
            $liveOut = $iface ? (int) ($iface['tx-byte'] ?? 0) : ($active ? (int) ($active['bytes-out'] ?? 0) : 0);

            // Hitung akumulasi bulanan:
            // Database menyimpan akumulasi sesi-sesi sebelumnya sejak tgl 1 bulan berjalan.
            // Jika user sedang online, tambahkan sisa live byte yang belum tercatat di database.
            $savedIn = $usage ? (int) $usage->bytes_in : 0;
            $savedOut = $usage ? (int) $usage->bytes_out : 0;

            if ($isOnline) {
                $lastSyncedIn = $usage ? (int) $usage->last_total_bytes_in : 0;
                $lastSyncedOut = $usage ? (int) $usage->last_total_bytes_out : 0;

                $uncommittedIn = max(0, $liveIn - $lastSyncedIn);
                $uncommittedOut = max(0, $liveOut - $lastSyncedOut);

                $bytesIn = $savedIn + $uncommittedIn;
                $bytesOut = $savedOut + $uncommittedOut;
            } else {
                // Saat Offline (DC), pertahankan 100% total pemakaian yang sudah tercatat
                $bytesIn = $savedIn;
                $bytesOut = $savedOut;
            }

            $totalBytes = $bytesIn + $bytesOut;

            // Lewati jika belum ada traffic sama sekali dan tidak online
            if ($totalBytes === 0 && !$isOnline) {
                continue;
            }

            $ip = $active['address'] ?? ($cust->ip_address ?: '-');
            $mac = $active['caller-id'] ?? ($cust->mac_address ?: '');
            $uptimeStr = $isOnline
                ? ('Online (' . ($active['uptime'] ?? 'Aktif') . ')')
                : ($usage?->last_update ? 'Offline (Terakhir: ' . \Carbon\Carbon::parse($usage->last_update)->diffForHumans() . ')' : 'Offline');

            $grandTotal += $totalBytes;
            $processedUsernames[$username] = true;
            $processedUsernames[strtolower($username)] = true;

            $result[] = [
                'name' => $cust->name . ($cust->name !== $username ? " ({$username})" : ''),
                'address' => $username,
                'ip' => $ip,
                'mac' => $mac,
                'uptime' => $uptimeStr,
                'is_online' => $isOnline,
                'package' => $cust->package?->name ?? 'PPPoE',
                'tx' => $this->formatBytes($bytesOut), // Download
                'rx' => $this->formatBytes($bytesIn),  // Upload
                'total' => $this->formatBytes($totalBytes),
                'total_bytes' => $totalBytes,
            ];
        }

        // Tambahkan sesi aktif yang belum terdaftar di database pelanggan
        foreach ($activeSessions as $u) {
            $username = trim($u['name'] ?? '');
            if (!$username || isset($processedUsernames[$username]) || isset($processedUsernames[strtolower($username)])) {
                continue;
            }

            $ip = $u['address'] ?? '-';
            $mac = $u['caller-id'] ?? '';
            $uptime = $u['uptime'] ?? '-';

            $iface = $ifaceMap["<pppoe-{$username}>"] 
                ?? ($ifaceMap["pppoe-{$username}"] 
                ?? ($ifaceMap[$username] 
                ?? ($ifaceMap["<pppoe-" . strtolower($username) . ">"] ?? null)));

            $tx = $iface ? (int) ($iface['tx-byte'] ?? 0) : 0;
            $rx = $iface ? (int) ($iface['rx-byte'] ?? 0) : 0;
            $total = $tx + $rx;

            $grandTotal += $total;
            $processedUsernames[$username] = true;

            $result[] = [
                'name' => $username,
                'address' => $username,
                'ip' => $ip,
                'mac' => $mac,
                'uptime' => 'Online (' . $uptime . ')',
                'is_online' => true,
                'package' => 'PPPoE',
                'tx' => $this->formatBytes($tx),
                'rx' => $this->formatBytes($rx),
                'total' => $this->formatBytes($total),
                'total_bytes' => $total,
            ];
        }

        // Urutkan dari total bandwidth kumulatif terbesar
        usort($result, fn($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        $syncScript = '/ppp profile set [find default=yes] on-down=":local u \"$user\"; :local rx 0; :local tx 0; :do { :set rx [/interface get [find name=\"<pppoe-$u>\"] rx-byte]; :set tx [/interface get [find name=\"<pppoe-$u>\"] tx-byte]; } on-error={}; /tool fetch url=\"' . url('/api/v1/mikrotik/session-disconnect') . '\" http-method=post http-data=\"username=$u&rx=$rx&tx=$tx&router_id=' . $router->id . '\" keep-result=no;"';

        return response()->json([
            'success' => true,
            'type' => 'pppoe',
            'period_label' => $now->translatedFormat('F Y'),
            'data' => $result,
            'grand_total' => $this->formatBytes($grandTotal),
            'sync_script' => $syncScript,
        ]);
    }

    /**
     * Ambil data Hotspot (Voucher) kumulatif.
     * Di MikroTik, /ip/hotspot/user menyimpan total bytes-in & bytes-out kumulatif meskipun user sedang DC/logout.
     */
    private function getHotspotData(MikrotikService $mik): \Illuminate\Http\JsonResponse
    {
        try {
            $active = $mik->query('/ip/hotspot/active/print', [
                '.proplist' => 'user,address,uptime,mac-address,bytes-in,bytes-out',
            ]) ?: [];
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'type' => 'hotspot',
                'message' => 'Hotspot belum di setup di MikroTik ini',
                'hotspot_not_setup' => true,
            ]);
        }

        $activeByUsername = [];
        foreach ($active as $act) {
            $uName = trim($act['user'] ?? '');
            if ($uName) {
                $activeByUsername[$uName] = $act;
            }
        }

        $users = [];
        try {
            $users = $mik->query('/ip/hotspot/user/print', [
                '.proplist' => 'name,profile,uptime,bytes-in,bytes-out,comment',
            ]) ?: [];
        } catch (\Throwable $e) {
            $users = [];
        }

        $result = [];
        $grandTotal = 0;
        $processedUsers = [];

        foreach ($users as $u) {
            $uName = trim($u['name'] ?? '');
            if (!$uName || $uName === 'default-trial') {
                continue;
            }

            $activeEntry = $activeByUsername[$uName] ?? null;
            $isOnline = !empty($activeEntry);

            $rxBytes = max((int) ($u['bytes-in'] ?? 0), (int) ($activeEntry['bytes-in'] ?? 0));
            $txBytes = max((int) ($u['bytes-out'] ?? 0), (int) ($activeEntry['bytes-out'] ?? 0));
            $totalBytes = $rxBytes + $txBytes;

            // Lewati voucher yang belum pernah login / 0 bytes jika offline
            if ($totalBytes === 0 && !$isOnline) {
                continue;
            }

            $grandTotal += $totalBytes;
            $processedUsers[$uName] = true;
            $uptimeStr = $isOnline
                ? ('Online (' . ($activeEntry['uptime'] ?? 'Aktif') . ')')
                : ('Offline (Uptime: ' . ($u['uptime'] ?? '-') . ')');

            $result[] = [
                'name' => $uName,
                'address' => $uName,
                'ip' => $activeEntry['address'] ?? '-',
                'mac' => $activeEntry['mac-address'] ?? '',
                'uptime' => $uptimeStr,
                'is_online' => $isOnline,
                'package' => $u['profile'] ?? 'Hotspot Voucher',
                'tx' => $this->formatBytes($txBytes),
                'rx' => $this->formatBytes($rxBytes),
                'total' => $this->formatBytes($totalBytes),
                'total_bytes' => $totalBytes,
            ];
        }

        // Masukkan sesi aktif hotspot yang belum terdaftar di users (misal trial/dynamic)
        foreach ($active as $act) {
            $uName = trim($act['user'] ?? '');
            if (!$uName || isset($processedUsers[$uName])) {
                continue;
            }

            $rxBytes = (int) ($act['bytes-in'] ?? 0);
            $txBytes = (int) ($act['bytes-out'] ?? 0);
            $totalBytes = $rxBytes + $txBytes;

            $grandTotal += $totalBytes;
            $processedUsers[$uName] = true;

            $result[] = [
                'name' => $uName,
                'address' => $uName,
                'ip' => $act['address'] ?? '-',
                'mac' => $act['mac-address'] ?? '',
                'uptime' => 'Online (' . ($act['uptime'] ?? 'Aktif') . ')',
                'is_online' => true,
                'package' => 'Hotspot Active',
                'tx' => $this->formatBytes($txBytes),
                'rx' => $this->formatBytes($rxBytes),
                'total' => $this->formatBytes($totalBytes),
                'total_bytes' => $totalBytes,
            ];
        }

        usort($result, fn($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        return response()->json([
            'success' => true,
            'type' => 'hotspot',
            'period_label' => now()->translatedFormat('F Y'),
            'data' => $result,
            'grand_total' => $this->formatBytes($grandTotal),
        ]);
    }

    /**
     * Ambil top bandwidth untuk client ARP / Simple Queue / Static IP.
     */
    private function getArpData(?MikrotikService $mik, $router, ?int $tenantId): \Illuminate\Http\JsonResponse
    {
        // 1. Ambil Simple Queues (penampung counter bytes bandwidth IP statis/ARP)
        $queues = [];
        if ($mik && $mik->isConnected()) {
            try {
                $queues = $mik->query('/queue/simple/print', [
                    '.proplist' => 'name,target,bytes,total-bytes,rate,comment,disabled,dynamic',
                ]) ?: [];
            } catch (\Throwable $e) {
                $queues = [];
            }
        }

        // 2. Ambil entri ARP Table (/ip/arp/print)
        $arps = [];
        if ($mik && $mik->isConnected()) {
            try {
                $arps = $mik->query('/ip/arp/print', [
                    '.proplist' => 'address,mac-address,interface,comment,disabled,dynamic,complete',
                ]) ?: [];
            } catch (\Throwable $e) {
                $arps = [];
            }
        }

        // 3. Mapping data pelanggan tenant dari database
        $customers = Customer::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) {
                $q->whereIn('connection_type', ['static', 'arp'])
                    ->orWhereNotNull('ip_address')
                    ->orWhere('pppoe_username', 'LIKE', 'arp_%');
            })
            ->get(['id', 'name', 'code', 'ip_address', 'mac_address', 'connection_type']);

        $customerByIp = [];
        $customerByMac = [];
        foreach ($customers as $c) {
            if ($c->ip_address) {
                $customerByIp[trim($c->ip_address)] = $c;
            }
            if ($c->mac_address) {
                $cleanMac = strtolower(str_replace([':', '-', '.'], '', trim($c->mac_address)));
                $customerByMac[$cleanMac] = $c;
            }
        }

        // Index ARP berdasarkan IP & MAC
        $arpByIp = [];
        $arpByMac = [];
        foreach ($arps as $arp) {
            $ip = trim($arp['address'] ?? '');
            $mac = trim($arp['mac-address'] ?? '');
            if ($ip) {
                $arpByIp[$ip] = $arp;
            }
            if ($mac) {
                $cleanMac = strtolower(str_replace([':', '-', '.'], '', $mac));
                $arpByMac[$cleanMac] = $arp;
            }
        }

        $result = [];
        $grandTotal = 0;
        $processedIps = [];

        // 4. Proses Simple Queues yang memiliki statistik byte
        foreach ($queues as $q) {
            $qName = $q['name'] ?? '';
            $target = $q['target'] ?? '';
            $dynamic = $q['dynamic'] ?? 'false';

            // Lewati antrean dinamis bawaan PPPoE atau Hotspot
            if ($dynamic === 'true' && (
                str_starts_with(strtolower($qName), '<pppoe-') ||
                str_starts_with(strtolower($qName), '<hotspot-') ||
                str_starts_with(strtolower($qName), 'hs-')
            )) {
                continue;
            }

            $ip = '';
            if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $target, $matches)) {
                $ip = $matches[1];
            }

            $bytesStr = $q['bytes'] ?? '0/0';
            $parts = explode('/', $bytesStr);
            $uploadBytes = isset($parts[0]) ? (int) $parts[0] : 0;   // Client Upload (RX to router)
            $downloadBytes = isset($parts[1]) ? (int) $parts[1] : 0; // Client Download (TX from router)
            $totalBytes = $uploadBytes + $downloadBytes;

            $arpEntry = ($ip && isset($arpByIp[$ip])) ? $arpByIp[$ip] : null;
            $mac = $arpEntry['mac-address'] ?? '';
            $iface = $arpEntry['interface'] ?? 'Queue';

            // Resolusi nama pengguna
            $name = '';
            $cust = null;
            if ($ip && isset($customerByIp[$ip])) {
                $cust = $customerByIp[$ip];
            } elseif ($mac) {
                $cleanMac = strtolower(str_replace([':', '-', '.'], '', $mac));
                if (isset($customerByMac[$cleanMac])) {
                    $cust = $customerByMac[$cleanMac];
                }
            }

            if ($cust) {
                $name = $cust->name;
            } else {
                $comment = $q['comment'] ?? ($arpEntry['comment'] ?? '');
                $cleanComment = trim(preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $comment));
                if (!empty($cleanComment) && !filter_var($cleanComment, FILTER_VALIDATE_IP)) {
                    $name = $cleanComment;
                } elseif (!empty($qName) && !preg_match('/^queue\d+$/i', $qName) && !filter_var($qName, FILTER_VALIDATE_IP)) {
                    $name = $qName;
                } elseif ($ip) {
                    $name = 'IP ' . $ip;
                } else {
                    $name = $qName ?: 'Pelanggan Statis';
                }
            }

            if ($ip) {
                $processedIps[$ip] = true;
            }

            $grandTotal += $totalBytes;

            $addressStr = $ip;
            if ($mac) {
                $addressStr = $ip ? "{$ip} ({$mac})" : $mac;
            }

            $isOnline = $arpEntry && ($arpEntry['complete'] ?? '') === 'true';
            $uptimeStr = $isOnline ? "{$iface} (Aktif)" : "{$iface} (Idle)";

            $result[] = [
                'name' => $name,
                'address' => $ip ?: ($mac ?: '-'),
                'ip' => $ip ?: '-',
                'mac' => $mac ?: '',
                'uptime' => $uptimeStr,
                'is_online' => $isOnline,
                'tx' => $this->formatBytes($downloadBytes),
                'rx' => $this->formatBytes($uploadBytes),
                'total' => $this->formatBytes($totalBytes),
                'total_bytes' => $totalBytes,
            ];
        }

        // 5. Masukkan entri ARP yang belum terdaftar di antrean Simple Queue
        foreach ($arps as $arp) {
            $ip = trim($arp['address'] ?? '');
            if (!$ip || isset($processedIps[$ip])) {
                continue;
            }

            $mac = trim($arp['mac-address'] ?? '');
            $iface = $arp['interface'] ?? 'bridge';
            $comment = $arp['comment'] ?? '';

            $name = '';
            if (isset($customerByIp[$ip])) {
                $name = $customerByIp[$ip]->name;
            } else {
                $cleanComment = trim(preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $comment));
                $name = $cleanComment ?: ('ARP ' . $ip);
            }

            $addressStr = $ip ?: ($mac ?: '-');
            $isOnline = ($arp['complete'] ?? '') === 'true';
            $uptimeStr = $isOnline ? "{$iface} (Aktif)" : "{$iface} (Idle)";

            $result[] = [
                'name' => $name,
                'address' => $addressStr,
                'ip' => $ip ?: '-',
                'mac' => $mac ?: '',
                'uptime' => $uptimeStr,
                'is_online' => $isOnline,
                'tx' => '0 B',
                'rx' => '0 B',
                'total' => '0 B',
                'total_bytes' => 0,
            ];
            $processedIps[$ip] = true;
        }

        usort($result, fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        return response()->json([
            'success' => true,
            'type' => 'arp',
            'data' => $result,
            'grand_total' => $this->formatBytes($grandTotal),
        ]);
    }

    /**
     * Format bytes ke MB, GB, atau TB.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2) . ' TB';
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        return number_format($bytes / 1024, 2) . ' KB';
    }
}
