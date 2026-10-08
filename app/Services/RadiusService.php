<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Package;
use App\Models\TenantRadiusSetting;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RadiusService — Manages FreeRADIUS AAA & MikroTik User Manager v7 Integration
 * Supports RFC 3576 CoA / PoD (Packet of Disconnect) on UDP 3799.
 */
class RadiusService
{
    /**
     * Auto-migrate if RADIUS tables do not exist yet.
     */
    public static function ensureTablesExist(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_radius_settings')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning("[RadiusService] Auto-migrate error: " . $e->getMessage());
        }
    }

    /**
     * Get tenant RADIUS configuration.
     */
    public function getSetting(?int $tenantId = null): ?TenantRadiusSetting
    {
        self::ensureTablesExist();

        try {
            $tenantId = $tenantId ?? session('tenant_id') ?? auth()->user()?->tenant_id;
            if (!$tenantId) {
                return TenantRadiusSetting::withoutGlobalScopes()->whereNull('tenant_id')->first()
                    ?? TenantRadiusSetting::first();
            }

            return TenantRadiusSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
                ?? TenantRadiusSetting::where('tenant_id', $tenantId)->first();
        } catch (\Throwable $e) {
            Log::warning("[RadiusService] getSetting error: " . $e->getMessage());
            return new TenantRadiusSetting([
                'tenant_id' => $tenantId,
                'radius_mode' => 'disabled',
                'is_active' => false,
            ]);
        }
    }

    /**
     * Resolve database connection based on tenant setting.
     */
    public function getConnection(?int $tenantId = null): ?ConnectionInterface
    {
        $setting = $this->getSetting($tenantId);
        $mode = $setting?->radius_mode ?? 'disabled';

        if ($mode === 'disabled') {
            return null;
        }

        if ($mode === 'remote_db') {
            $tId = $setting->tenant_id ?? 0;
            $connName = "radius_remote_tenant_{$tId}";

            Config::set("database.connections.{$connName}", [
                'driver' => $setting->remote_db_driver ?: 'mysql',
                'host' => $setting->remote_db_host ?: '127.0.0.1',
                'port' => (int) ($setting->remote_db_port ?: 3306),
                'database' => $setting->remote_db_name ?: 'radius',
                'username' => $setting->remote_db_user ?: 'root',
                'password' => $setting->remote_db_pass ?: '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'options' => [
                    \PDO::ATTR_TIMEOUT => 3,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ],
            ]);

            return DB::connection($connName);
        }

        // Local DB mode
        return DB::connection();
    }

    /**
     * Test connection to configured RADIUS backend.
     */
    public function testConnection(?int $tenantId = null): array
    {
        $setting = $this->getSetting($tenantId);
        if (!$setting || $setting->radius_mode === 'disabled') {
            return [
                'status' => 'error',
                'message' => 'Integrasi RADIUS belum diaktifkan.',
                'latency_ms' => 0,
            ];
        }

        $startTime = microtime(true);

        try {
            if ($setting->radius_mode === 'userman_v7') {
                if (empty($setting->userman_host) || empty($setting->userman_user)) {
                    throw new \Exception('Host dan username MikroTik User Manager v7 belum diisi.');
                }

                $mik = new MikrotikService([
                    'host' => $setting->userman_host,
                    'user' => $setting->userman_user,
                    'pass' => $setting->userman_pass ?? '',
                    'port' => (int) ($setting->userman_port ?: 8728),
                ]);

                if (!$mik->isConnected()) {
                    throw new \Exception($mik->getLastError() ?: 'Gagal terhubung ke RouterOS API');
                }

                // Check user-manager existence
                $users = $mik->comm('/user-manager/user/print', ['?disabled' => 'false']);
                $latency = round((microtime(true) - $startTime) * 1000, 2);

                $msg = 'Terhubung ke MikroTik User Manager v7. Sesi user aktif: ' . count($users);
                $this->updateTestResult($setting, 'ok', $msg);

                return [
                    'status' => 'ok',
                    'message' => $msg,
                    'latency_ms' => $latency,
                    'users_count' => count($users),
                ];
            }

            // Database Mode (local_db / remote_db)
            $db = $this->getConnection($tenantId);
            if (!$db) {
                throw new \Exception('Koneksi database RADIUS tidak tersedia.');
            }

            // Run simple query on radcheck table
            $count = $db->table('radcheck')->count();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $msg = "Terhubung ke Database FreeRADIUS ({$setting->radius_mode}). Total Akun radcheck: {$count}";
            $this->updateTestResult($setting, 'ok', $msg);

            return [
                'status' => 'ok',
                'message' => $msg,
                'latency_ms' => $latency,
                'users_count' => $count,
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $msg = 'Gagal: ' . $e->getMessage();
            $this->updateTestResult($setting, 'error', $msg);

            return [
                'status' => 'error',
                'message' => $msg,
                'latency_ms' => $latency,
            ];
        }
    }

    private function updateTestResult(TenantRadiusSetting $setting, string $status, string $message): void
    {
        try {
            $setting->update([
                'last_test_at' => now(),
                'last_test_status' => $status,
                'last_test_message' => $message,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Sync single customer PPPoE credentials to RADIUS backend.
     */
    public function syncCustomer(Customer $customer, ?Package $package = null): bool
    {
        if (empty($customer->pppoe_username)) {
            return false;
        }

        $tenantId = $customer->tenant_id ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || !$setting->is_active || $setting->radius_mode === 'disabled') {
            return false;
        }

        try {
            $username = $customer->pppoe_username;
            $password = $customer->portal_password ?? $customer->phone ?? '123456';
            $pkg = $package ?? $customer->package ?? ($customer->package_id ? Package::find($customer->package_id) : null);
            $packageName = $pkg ? $pkg->name : 'DEFAULT';
            $groupName = ($customer->status === 'isolated') ? 'ISOLATED' : $packageName;

            if ($setting->radius_mode === 'userman_v7') {
                return $this->syncUserToUserManagerV7($setting, $username, $password, $groupName, $pkg);
            }

            // Database Mode (local_db or remote_db)
            $db = $this->getConnection($tenantId);
            if (!$db) {
                return false;
            }

            // 1. Radcheck — Cleartext-Password
            $db->table('radcheck')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $password, 'tenant_id' => $tenantId]
            );

            // 2. Radusergroup — Group / Package profile
            $db->table('radusergroup')->updateOrInsert(
                ['username' => $username],
                ['groupname' => $groupName, 'priority' => 1, 'tenant_id' => $tenantId]
            );

            // 3. If package has rate limit, ensure radgroupreply exists
            if ($pkg && !empty($pkg->profile_normal)) {
                $db->table('radgroupreply')->updateOrInsert(
                    ['groupname' => $pkg->name, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $pkg->profile_normal, 'tenant_id' => $tenantId]
                );
            }

            // Ensure ISOLATED profile exists
            $db->table('radgroupreply')->updateOrInsert(
                ['groupname' => 'ISOLATED', 'attribute' => 'Mikrotik-Rate-Limit'],
                ['op' => ':=', 'value' => '512k/512k', 'tenant_id' => $tenantId]
            );

            Log::info("[RadiusService] Customer synced to RADIUS: {$username} -> Group: {$groupName}");
            return true;
        } catch (\Throwable $e) {
            Log::error("[RadiusService] Failed to sync customer {$customer->pppoe_username} to RADIUS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Isolate customer in RADIUS backend and optionally trigger RFC 3576 CoA disconnect.
     */
    public function isolateCustomer(Customer $customer): bool
    {
        if (empty($customer->pppoe_username)) {
            return false;
        }

        $tenantId = $customer->tenant_id ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || !$setting->is_active || $setting->radius_mode === 'disabled') {
            return false;
        }

        try {
            $username = $customer->pppoe_username;

            if ($setting->radius_mode === 'userman_v7') {
                $this->setUserProfileInUsermanV7($setting, $username, 'ISOLATED');
            } else {
                $db = $this->getConnection($tenantId);
                if ($db) {
                    $db->table('radusergroup')->updateOrInsert(
                        ['username' => $username],
                        ['groupname' => 'ISOLATED', 'priority' => 1, 'tenant_id' => $tenantId]
                    );
                }
            }

            // Trigger RFC 3576 CoA Disconnect if enabled
            if ($setting->auto_coa_on_isolate && !empty($setting->nas_ip) && !empty($setting->nas_secret)) {
                $this->sendDisconnectRequest(
                    username: $username,
                    nasIp: $setting->nas_ip,
                    framedIp: $customer->ip_address,
                    secret: $setting->nas_secret,
                    port: (int) ($setting->coa_port ?: 3799)
                );
            }

            Log::info("[RadiusService] Customer ISOLATED in RADIUS: {$username}");
            return true;
        } catch (\Throwable $e) {
            Log::error("[RadiusService] Failed to isolate customer in RADIUS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unisolate customer in RADIUS backend.
     */
    public function unisolateCustomer(Customer $customer): bool
    {
        $synced = $this->syncCustomer($customer);
        $setting = $this->getSetting($customer->tenant_id);

        if ($synced && $setting && $setting->auto_coa_on_isolate && !empty($setting->nas_ip) && !empty($setting->nas_secret)) {
            // Kick session so user reconnects with normal profile
            $this->sendDisconnectRequest(
                username: $customer->pppoe_username,
                nasIp: $setting->nas_ip,
                framedIp: $customer->ip_address,
                secret: $setting->nas_secret,
                port: (int) ($setting->coa_port ?: 3799)
            );
        }

        return $synced;
    }

    /**
     * Remove customer from RADIUS backend.
     */
    public function removeCustomer(string $username, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || !$setting->is_active || $setting->radius_mode === 'disabled') {
            return false;
        }

        try {
            if ($setting->radius_mode === 'userman_v7') {
                $mik = new MikrotikService([
                    'host' => $setting->userman_host,
                    'user' => $setting->userman_user,
                    'pass' => $setting->userman_pass ?? '',
                    'port' => (int) ($setting->userman_port ?: 8728),
                ]);

                if ($mik->isConnected()) {
                    $existing = $mik->comm('/user-manager/user/print', ['?name' => $username]);
                    if (!empty($existing[0]['.id'])) {
                        $mik->comm('/user-manager/user/remove', ['.id' => $existing[0]['.id']]);
                    }
                }
                return true;
            }

            $db = $this->getConnection($tenantId);
            if ($db) {
                $db->table('radcheck')->where('username', $username)->delete();
                $db->table('radreply')->where('username', $username)->delete();
                $db->table('radusergroup')->where('username', $username)->delete();
            }

            Log::info("[RadiusService] Customer removed from RADIUS: {$username}");
            return true;
        } catch (\Throwable $e) {
            Log::error("[RadiusService] Failed to remove customer from RADIUS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send RFC 3576 Disconnect-Request (Packet of Disconnect / PoD) over UDP socket.
     * Code: 40 (Disconnect-Request), Response: 41 (Disconnect-ACK) or 42 (Disconnect-NAK).
     */
    public function sendDisconnectRequest(
        string $username,
        ?string $nasIp = null,
        ?string $framedIp = null,
        ?string $secret = null,
        int $port = 3799
    ): array {
        if (empty($nasIp) || empty($secret)) {
            $setting = $this->getSetting();
            $nasIp = $nasIp ?: $setting?->nas_ip;
            $secret = $secret ?: $setting?->nas_secret;
            $port = $port ?: ($setting?->coa_port ?: 3799);
        }

        if (empty($nasIp) || empty($secret)) {
            return [
                'success' => false,
                'code' => 0,
                'message' => 'IP NAS atau Secret RADIUS belum dikonfigurasi.',
            ];
        }

        $startTime = microtime(true);

        try {
            $code = 40; // Disconnect-Request
            $identifier = random_int(1, 254);

            // Build Attributes
            $attributes = '';

            // Attribute 1: User-Name
            $attributes .= chr(1) . chr(strlen($username) + 2) . $username;

            // Attribute 8: Framed-IP-Address (optional)
            if (!empty($framedIp) && filter_var($framedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $packedIp = inet_pton($framedIp);
                if ($packedIp !== false) {
                    $attributes .= chr(8) . chr(6) . $packedIp;
                }
            }

            // Attribute 4: NAS-IP-Address (optional)
            if (filter_var($nasIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $packedNas = inet_pton($nasIp);
                if ($packedNas !== false) {
                    $attributes .= chr(4) . chr(6) . $packedNas;
                }
            }

            // Total Packet Length = 20 (Header) + Attributes Length
            $length = 20 + strlen($attributes);

            // Request Authenticator = MD5(Code + Identifier + Length + 16 Zero Octets + Attributes + Secret)
            $headerWithoutAuth = pack('CCn', $code, $identifier, $length);
            $zeroAuth = str_repeat("\0", 16);
            $authenticator = md5($headerWithoutAuth . $zeroAuth . $attributes . $secret, true);

            // Final Packet
            $packet = $headerWithoutAuth . $authenticator . $attributes;

            // Open UDP socket
            $socket = @fsockopen("udp://{$nasIp}", $port, $errno, $errstr, 2.5);
            if (!$socket) {
                return [
                    'success' => false,
                    'code' => 0,
                    'message' => "Gagal membuka socket UDP ke {$nasIp}:{$port} ({$errstr})",
                ];
            }

            stream_set_timeout($socket, 2, 500000); // 2.5 seconds timeout
            fwrite($socket, $packet);

            $response = fread($socket, 4096);
            fclose($socket);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if (empty($response)) {
                return [
                    'success' => false,
                    'code' => 0,
                    'message' => "Tidak ada respon dari NAS {$nasIp}:{$port} (Timeout). Pastikan port 3799 UDP terbuka di router.",
                    'latency_ms' => $latency,
                ];
            }

            $respCode = ord($response[0]);
            $respId = ord($response[1]);

            if ($respCode === 41) { // Disconnect-ACK
                Log::info("[RadiusService] Disconnect-ACK received for user {$username} from NAS {$nasIp}:{$port}");
                return [
                    'success' => true,
                    'code' => 41,
                    'message' => "Disconnect-ACK berhasil diterima. Sesi {$username} diputus di router.",
                    'latency_ms' => $latency,
                ];
            } elseif ($respCode === 42) { // Disconnect-NAK
                Log::warning("[RadiusService] Disconnect-NAK received for user {$username} from NAS {$nasIp}:{$port}");
                return [
                    'success' => false,
                    'code' => 42,
                    'message' => "Disconnect-NAK: NAS menolak permintaan pemutusan (Sesi mungkin sudah tidak aktif atau secret salah).",
                    'latency_ms' => $latency,
                ];
            }

            return [
                'success' => false,
                'code' => $respCode,
                'message' => "Respon tidak dikenal (Code: {$respCode})",
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            Log::error("[RadiusService] CoA Disconnect error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 0,
                'message' => 'Error: ' . $e->getMessage(),
                'latency_ms' => $latency,
            ];
        }
    }

    /**
     * Sync all PPPoE customers of current tenant into RADIUS backend.
     */
    public function syncAllCustomers(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || !$setting->is_active || $setting->radius_mode === 'disabled') {
            return ['total' => 0, 'synced' => 0, 'failed' => 0, 'error' => 'RADIUS mode tidak aktif'];
        }

        $customers = Customer::where('tenant_id', $tenantId)
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->with('package')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($customers as $c) {
            if ($this->syncCustomer($c, null, $tenantId)) {
                $synced++;
            } else {
                $failed++;
            }
        }

        try {
            $setting->update(['last_sync_at' => now()]);
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'total' => $customers->count(),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Get all RADIUS users.
     */
    public function getUsers(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || $setting->radius_mode === 'disabled') {
            return [];
        }

        try {
            if ($setting->radius_mode === 'userman_v7') {
                $mik = new MikrotikService([
                    'host' => $setting->userman_host,
                    'user' => $setting->userman_user,
                    'pass' => $setting->userman_pass ?? '',
                    'port' => (int) ($setting->userman_port ?: 8728),
                ]);

                if ($mik->isConnected()) {
                    return $mik->comm('/user-manager/user/print');
                }
                return [];
            }

            $db = $this->getConnection($tenantId);
            if (!$db) {
                return [];
            }

            $query = $db->table('radcheck as rc')
                ->leftJoin('radusergroup as rug', 'rc.username', '=', 'rug.username')
                ->where('rc.attribute', 'Cleartext-Password')
                ->select('rc.id', 'rc.username', 'rc.value as password', 'rug.groupname', 'rc.tenant_id');

            if ($tenantId && $setting->radius_mode === 'local_db') {
                $query->where('rc.tenant_id', $tenantId);
            }

            return $query->orderBy('rc.id', 'desc')->limit(200)->get()->toArray();
        } catch (\Throwable $e) {
            Log::error("[RadiusService] getUsers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active / recent accounting sessions from radacct.
     */
    public function getActiveSessions(?int $tenantId = null, int $limit = 50): array
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || $setting->radius_mode === 'disabled') {
            return [];
        }

        try {
            if ($setting->radius_mode === 'userman_v7') {
                $mik = new MikrotikService([
                    'host' => $setting->userman_host,
                    'user' => $setting->userman_user,
                    'pass' => $setting->userman_pass ?? '',
                    'port' => (int) ($setting->userman_port ?: 8728),
                ]);

                if ($mik->isConnected()) {
                    $sessions = $mik->comm('/user-manager/session/print', ['?active' => 'true']);
                    return array_slice($sessions, 0, $limit);
                }
                return [];
            }

            $db = $this->getConnection($tenantId);
            if (!$db) {
                return [];
            }

            $query = $db->table('radacct')
                ->select(
                    'radacctid',
                    'username',
                    'groupname',
                    'nasipaddress',
                    'framedipaddress',
                    'callingstationid as mac_address',
                    'acctstarttime',
                    'acctstoptime',
                    'acctsessiontime',
                    'acctinputoctets',
                    'acctoutputoctets',
                    'acctterminatecause'
                );

            if ($tenantId && $setting->radius_mode === 'local_db') {
                $query->where('tenant_id', $tenantId);
            }

            return $query->orderBy('acctstarttime', 'desc')->limit($limit)->get()->toArray();
        } catch (\Throwable $e) {
            Log::error("[RadiusService] getActiveSessions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get NAS router clients.
     */
    public function getNasClients(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $setting = $this->getSetting($tenantId);

        if (!$setting || $setting->radius_mode === 'disabled') {
            return [];
        }

        try {
            $db = $this->getConnection($tenantId);
            if (!$db) {
                return [];
            }

            $query = $db->table('nas')
                ->select('id', 'nasname', 'shortname', 'type', 'ports', 'secret', 'server', 'community', 'description', 'tenant_id');

            if ($tenantId && $setting->radius_mode === 'local_db') {
                $query->where('tenant_id', $tenantId);
            }

            return $query->orderBy('id', 'desc')->get()->toArray();
        } catch (\Throwable $e) {
            Log::error("[RadiusService] getNasClients error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add or update NAS router.
     */
    public function saveNasClient(array $data, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $db = $this->getConnection($tenantId);
        if (!$db) {
            return false;
        }

        return (bool) $db->table('nas')->updateOrInsert(
            ['nasname' => $data['nasname']],
            [
                'shortname' => $data['shortname'] ?? 'mikrotik',
                'type' => $data['type'] ?? 'other',
                'secret' => $data['secret'] ?? 'testing123',
                'description' => $data['description'] ?? 'NODERA Router Client',
                'tenant_id' => $tenantId,
            ]
        );
    }

    /**
     * Delete NAS client.
     */
    public function deleteNasClient(int $id, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? session('tenant_id');
        $db = $this->getConnection($tenantId);
        if (!$db) {
            return false;
        }

        return (bool) $db->table('nas')->where('id', $id)->delete();
    }

    // ==========================================
    // Helper Methods for MikroTik User Manager v7
    // ==========================================

    private function syncUserToUserManagerV7(TenantRadiusSetting $setting, string $username, string $password, string $groupName, ?Package $pkg): bool
    {
        $mik = new MikrotikService([
            'host' => $setting->userman_host,
            'user' => $setting->userman_user,
            'pass' => $setting->userman_pass ?? '',
            'port' => (int) ($setting->userman_port ?: 8728),
        ]);

        if (!$mik->isConnected()) {
            return false;
        }

        // Find existing user
        $existing = $mik->comm('/user-manager/user/print', ['?name' => $username]);

        if (!empty($existing[0]['.id'])) {
            $mik->comm('/user-manager/user/set', [
                '.id' => $existing[0]['.id'],
                'password' => $password,
                'group' => $groupName,
                'disabled' => ($groupName === 'ISOLATED') ? 'true' : 'false',
            ]);
        } else {
            $mik->comm('/user-manager/user/add', [
                'name' => $username,
                'password' => $password,
                'group' => $groupName,
                'disabled' => ($groupName === 'ISOLATED') ? 'true' : 'false',
            ]);
        }

        return true;
    }

    private function setUserProfileInUsermanV7(TenantRadiusSetting $setting, string $username, string $groupName): bool
    {
        $mik = new MikrotikService([
            'host' => $setting->userman_host,
            'user' => $setting->userman_user,
            'pass' => $setting->userman_pass ?? '',
            'port' => (int) ($setting->userman_port ?: 8728),
        ]);

        if (!$mik->isConnected()) {
            return false;
        }

        $existing = $mik->comm('/user-manager/user/print', ['?name' => $username]);
        if (!empty($existing[0]['.id'])) {
            $mik->comm('/user-manager/user/set', [
                '.id' => $existing[0]['.id'],
                'group' => $groupName,
                'disabled' => ($groupName === 'ISOLATED') ? 'true' : 'false',
            ]);
            return true;
        }

        return false;
    }
}
