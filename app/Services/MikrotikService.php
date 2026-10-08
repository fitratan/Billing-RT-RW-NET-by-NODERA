<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * MikrotikService — lightweight RouterOS API client via socket.
 *
 * Uses basic socket (fsockopen) to communicate with the MikroTik API port (8728).
 * Handles RouterOS protocol length encoding for commands up to 4 kB.
 *
 * Requires these config keys (set via ConfigService or env):
 *   MIKROTIK_HOST, MIKROTIK_USER, MIKROTIK_PASS, MIKROTIK_PORT (default: 8728)
 */
class MikrotikService
{
    private ConfigService $config;

    private bool $connected = false;

    private ?string $lastError = null;
    private ?string $lastTrap = null;

    /** @var resource|null */
    private $socket = null;

    private array $connectionConfig = [];

    /** Instance flag to prevent repeated connection attempts in the same service instance. */
    private bool $connectionFailed = false;

    public function __construct(array|\App\Models\Mikrotik|null $routerConfig = null)
    {
        $this->config = new ConfigService();

        if ($routerConfig instanceof \App\Models\Mikrotik) {
            // Guard: Jika instance Mikrotik tidak memiliki field username/password (misal hasil partial select), reload dari database
            if ((empty($routerConfig->username) && empty($routerConfig->user)) && $routerConfig->exists) {
                $routerConfig = \App\Models\Mikrotik::withoutGlobalScopes()->find($routerConfig->id) ?? $routerConfig;
            }

            $host = $this->sanitizeHost($routerConfig->host);
            $port = (int) ($routerConfig->port ?: 8728);
            if (str_contains($host, ':')) {
                [$h, $p] = explode(':', $host, 2);
                $host = $h;
                if (is_numeric($p) && empty($routerConfig->port)) {
                    $port = (int) $p;
                }
            }

            $this->connectionConfig = [
                'host' => $host,
                'user' => (string) ($routerConfig->username ?? $routerConfig->user ?? ''),
                'pass' => (string) ($routerConfig->password ?? $routerConfig->pass ?? ''),
                'port' => $port,
                'api_mode' => $routerConfig->api_mode ?? 'api',
            ];
        } elseif (is_array($routerConfig) && !empty($routerConfig)) {
            $host = $this->sanitizeHost($routerConfig['host'] ?? '');
            $port = (int) ($routerConfig['port'] ?? 8728);
            if (str_contains($host, ':')) {
                [$h, $p] = explode(':', $host, 2);
                $host = $h;
                if (is_numeric($p) && empty($routerConfig['port'])) {
                    $port = (int) $p;
                }
            }
            $this->connectionConfig = [
                'host' => $host,
                'user' => (string) ($routerConfig['user'] ?? $routerConfig['username'] ?? ''),
                'pass' => (string) ($routerConfig['pass'] ?? $routerConfig['password'] ?? ''),
                'port' => $port,
                'api_mode' => $routerConfig['api_mode'] ?? 'api',
            ];
        } else {
            // Check for active tenant-scoped Mikrotik router in database
            $activeRouter = null;
            try {
                $activeRouter = \App\Models\Mikrotik::where('is_active', true)->first()
                    ?? \App\Models\Mikrotik::first();
            } catch (\Throwable $e) {
                $activeRouter = null;
            }

            if ($activeRouter) {
                $host = $this->sanitizeHost($activeRouter->host);
                $port = (int) ($activeRouter->port ?: 8728);
                if (str_contains($host, ':')) {
                    [$h, $p] = explode(':', $host, 2);
                    $host = $h;
                    if (is_numeric($p) && empty($activeRouter->port)) {
                        $port = (int) $p;
                    }
                }
                $this->connectionConfig = [
                    'host' => $host,
                    'user' => (string) ($activeRouter->username ?? $activeRouter->user ?? ''),
                    'pass' => (string) ($activeRouter->password ?? $activeRouter->pass ?? ''),
                    'port' => $port,
                    'api_mode' => $activeRouter->api_mode ?? 'api',
                ];
            } else {
                $isSuperadmin = session('admin_role') === 'superadmin';
                $host = $this->sanitizeHost($this->config->get('MIKROTIK_HOST'));
                $user = (string) $this->config->get('MIKROTIK_USER');
                $pass = (string) $this->config->get('MIKROTIK_PASS');
                $port = (int) ($this->config->get('MIKROTIK_PORT') ?: 8728);

                if ($isSuperadmin && !empty($host) && !empty($user)) {
                    $this->connectionConfig = [
                        'host' => $host,
                        'user' => $user,
                        'pass' => $pass,
                        'port' => $port,
                        'api_mode' => 'api',
                    ];
                } else {
                    $this->lastError = 'MikroTik belum dikonfigurasi. Silakan tambahkan router di menu Router MikroTik.';
                    return;
                }
            }
        }
    }

    private function sanitizeHost(?string $host): string
    {
        if (!$host) return '';
        $h = trim($host);
        $h = preg_replace('#^https?://#i', '', $h);
        $h = preg_replace('#^ssl://#i', '', $h);
        $h = preg_replace('#^tcp://#i', '', $h);
        return rtrim($h, '/');
    }

    private function clearFailureCache(string $host, int $port): void
    {
        \Illuminate\Support\Facades\Cache::forget("mik_fail_cnt_" . md5("{$host}:{$port}"));
        \Illuminate\Support\Facades\Cache::forget("mik_cb_down_" . md5("{$host}:{$port}"));
    }

    private function recordFailure(string $host, int $port): void
    {
        $failKey = "mik_fail_cnt_" . md5("{$host}:{$port}");
        $fails = (int) \Illuminate\Support\Facades\Cache::get($failKey, 0) + 1;
        \Illuminate\Support\Facades\Cache::put($failKey, $fails, 300);

        if ($fails >= 2) {
            $cbKey = "mik_cb_down_" . md5("{$host}:{$port}");
            \Illuminate\Support\Facades\Cache::put($cbKey, true, 60);
        }
    }

    // ------------------------------------------------------------------
    //  Socket / protocol internals (Proven Denis Basta RouterosAPI engine)
    // ------------------------------------------------------------------

    public function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            return chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            return chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            return chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else {
            return chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
    }

    public function readWord(): ?string
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return null;
        }

        $firstByte = fread($this->socket, 1);
        if ($firstByte === false || $firstByte === '') {
            return null;
        }

        $byte = ord($firstByte);
        $length = 0;

        if ($byte & 128) {
            if (($byte & 192) === 128) {
                $length = (($byte & 63) << 8) + ord(fread($this->socket, 1));
            } elseif (($byte & 224) === 192) {
                $length = (($byte & 31) << 8) + ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
            } elseif (($byte & 240) === 224) {
                $length = (($byte & 15) << 8) + ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
            } else {
                $length = ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
                $length = ($length << 8) + ord(fread($this->socket, 1));
            }
        } else {
            $length = $byte;
        }

        if ($length === 0) {
            return ''; // Empty word indicates end of sentence in RouterOS protocol
        }

        $word = '';
        while (strlen($word) < $length) {
            $toRead = $length - strlen($word);
            $chunk = fread($this->socket, $toRead);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $word .= $chunk;
        }

        return $word;
    }

    public function readSentence(): ?array
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return null;
        }

        $sentence = [];
        while (true) {
            $word = $this->readWord();
            if ($word === null) {
                return !empty($sentence) ? $sentence : null;
            }
            if ($word === '') {
                return $sentence;
            }
            $sentence[] = $word;
        }
    }

    private function connect(): bool
    {
        if ($this->connected && is_resource($this->socket)) {
            return true;
        }

        if ($this->connectionFailed) {
            return false;
        }

        if (empty($this->connectionConfig)) {
            $this->lastError = 'Konfigurasi router kosong.';
            return false;
        }

        $host = $this->connectionConfig['host'];
        $port = (int) ($this->connectionConfig['port'] ?? 8728);

        $cbKey = "mik_cb_down_" . md5("{$host}:{$port}");
        if (\Illuminate\Support\Facades\Cache::has($cbKey)) {
            $this->lastError = "Circuit Breaker: Router {$host}:{$port} sedang dalam cooldown.";
            $this->connectionFailed = true;
            return false;
        }

        $login = trim((string) ($this->connectionConfig['user'] ?? ''));
        $password = (string) ($this->connectionConfig['pass'] ?? '');
        $isSsl = ($port === 8729 || ($this->connectionConfig['api_mode'] ?? '') === 'ssl');
        $protocol = $isSsl ? 'ssl://' : '';

        // Resolve domain name to IPv4 to prevent IPv6 socket stalls
        $targetHost = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($host);
            if ($resolved && $resolved !== $host) {
                $targetHost = $resolved;
            }
        }

        $context = stream_context_create([
            'ssl' => [
                'ciphers' => 'ADH:ALL',
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $timeout = 2.5; // Fast connect timeout (2.5s max) to prevent gateway timeout
        $this->connected = false;
        $this->socket = @stream_socket_client(
            $protocol . $targetHost . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->recordFailure($host, $port);
            $this->connectionFailed = true;
            $this->lastError = "Tidak dapat membuka socket ke {$host}:{$port} (" . ($errstr ?: 'Waktu koneksi habis') . ")";
            return false;
        }

        stream_set_timeout($this->socket, 3); // 3s read timeout

        // Modern RouterOS (v6.43+ and v7.x) Authentication
        // Direct credential login in step 1 eliminates duplicate failed login logs
        $this->writeWord('/login', false);
        $this->writeWord('=name=' . $login, false);
        $this->writeWord('=password=' . $password, true);

        $response = $this->readSentence();

        if ($response && isset($response[0])) {
            if ($response[0] === '!done') {
                // Check if RouterOS is legacy pre-6.43 returning a challenge in =ret=
                $retChallenge = null;
                foreach ($response as $item) {
                    if (str_starts_with($item, '=ret=')) {
                        $retChallenge = substr($item, 5);
                        break;
                    }
                }

                if ($retChallenge && strlen($retChallenge) === 32) {
                    // Legacy pre-6.43 challenge/response
                    $challengeResponse = '00' . md5(chr(0) . $password . pack('H*', $retChallenge));
                    $this->writeWord('/login', false);
                    $this->writeWord('=name=' . $login, false);
                    $this->writeWord('=response=' . $challengeResponse, true);

                    $chalResponse = $this->readSentence();
                    if ($chalResponse && isset($chalResponse[0]) && $chalResponse[0] === '!done') {
                        $this->connected = true;
                    } else {
                        $this->lastError = 'Username atau password MikroTik salah';
                    }
                } else {
                    // Modern RouterOS (v6.43+ and v7.x) login successful!
                    $this->connected = true;
                }
            } elseif ($response[0] === '!trap') {
                $trapMsg = 'Username atau password MikroTik salah';
                foreach ($response as $item) {
                    if (str_starts_with($item, '=message=')) {
                        $trapMsg = 'MikroTik: ' . substr($item, 9);
                        break;
                    }
                }
                $this->lastError = $trapMsg;
                // Read trailing !done if present
                $this->readSentence();
            }
        }

        if (!$this->connected && is_resource($this->socket)) {
            @fclose($this->socket);
            $this->socket = null;
        }

        if ($this->connected) {
            $this->clearFailureCache($host, $port);
            return true;
        }

        $this->connectionFailed = true;
        $this->recordFailure($host, $port);

        if (!$this->lastError) {
            $this->lastError = "Gagal login ke {$host}:{$port}. Periksa apakah service 'api' aktif di IP > Services dan username/password benar.";
        }

        return false;
    }

    public function writeWord(string $word, bool $isLast = false): void
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return;
        }

        $len = strlen($word);
        fwrite($this->socket, $this->encodeLength($len));
        if ($len > 0) {
            fwrite($this->socket, $word);
        }

        if ($isLast) {
            fwrite($this->socket, chr(0));
        }
    }

    private function readRaw(): array
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return [];
        }

        $response = [];

        while (true) {
            $sentence = $this->readSentence();
            if ($sentence === null) {
                // Socket lost or read timeout
                $this->connected = false;
                if (is_resource($this->socket)) {
                    @fclose($this->socket);
                    $this->socket = null;
                }
                break;
            }
            if (empty($sentence)) {
                continue;
            }

            foreach ($sentence as $word) {
                $response[] = $word;
            }

            $type = $sentence[0] ?? '';
            if ($type === '!done') {
                break;
            }
            if ($type === '!fatal') {
                $this->connected = false;
                if (is_resource($this->socket)) {
                    @fclose($this->socket);
                    $this->socket = null;
                }
                break;
            }
            if ($type === '!trap') {
                $this->lastTrap = implode(', ', $sentence);
                foreach ($sentence as $item) {
                    if (str_starts_with($item, '=message=')) {
                        $this->lastError = substr($item, 9);
                        break;
                    }
                }
                if (empty($this->lastError)) {
                    $this->lastError = $this->lastTrap;
                }
                // Do NOT break here: RouterOS sends a terminating !done sentence after !trap
            }
        }

        return $response;
    }

    /**
     * Read the full parsed response from the socket.
     */
    public function readResponse(): array
    {
        $rawResponse = $this->readRaw();

        $parsed = [];
        $current = null;

        foreach ($rawResponse as $item) {
            if (in_array($item, ['!fatal', '!re', '!trap'], true)) {
                if ($item === '!re') {
                    $parsed[] = [];
                    $current = &$parsed[count($parsed) - 1];
                } elseif ($item === '!trap') {
                    $current = null;
                }
            } elseif ($item !== '!done') {
                if (str_starts_with($item, '=')) {
                    $parts = explode('=', substr($item, 1), 2);
                    if (count($parts) === 2 && $current !== null && is_array($current)) {
                        $current[$parts[0]] = $parts[1];
                    }
                }
            }
        }

        return $parsed;
    }

    // ------------------------------------------------------------------
    //  Public command execution
    // ------------------------------------------------------------------

    /**
     * Execute an arbitrary RouterOS command (alias for query).
     */
    public function execute(string $command, array $params = []): array
    {
        return $this->query($command, $params);
    }

    /**
     * Execute an arbitrary RouterOS command and return parsed records.
     */
    public function query(string $command, array $params = []): array
    {
        $this->lastTrap = null;
        $this->lastError = null;

        if (! $this->connect()) {
            return [];
        }

        $count = count($params);
        $this->writeWord($command, $count === 0);
        $i = 0;

        foreach ($params as $k => $v) {
            $kStr = (string) $k;
            if (str_starts_with($kStr, '?')) {
                $el = "{$kStr}={$v}";
            } elseif (str_starts_with($kStr, '~')) {
                $el = "{$kStr}~{$v}";
            } else {
                $el = "={$kStr}={$v}";
            }

            $isLast = ($i++ === $count - 1);
            $this->writeWord($el, $isLast);
        }

        $rawResponse = $this->readRaw();

        // Parse records
        $parsed = [];
        $current = null;

        foreach ($rawResponse as $item) {
            if (in_array($item, ['!fatal', '!re', '!trap'], true)) {
                if ($item === '!re') {
                    $parsed[] = [];
                    $current = &$parsed[count($parsed) - 1];
                } elseif ($item === '!trap') {
                    $current = null;
                }
            } elseif ($item !== '!done') {
                if (str_starts_with($item, '=')) {
                    $parts = explode('=', substr($item, 1), 2);
                    if (count($parts) === 2 && $current !== null && is_array($current)) {
                        $current[$parts[0]] = $parts[1];
                    }
                }
            }
        }

        return $parsed;
    }

    public function __destruct()
    {
        if ($this->socket) {
            @fclose($this->socket);
        }
    }

    // ------------------------------------------------------------------
    //  System resource
    // ------------------------------------------------------------------

    public function getResource(bool $useCache = true): array
    {
        $host = $this->connectionConfig['host'] ?? '';
        $port = $this->connectionConfig['port'] ?? 8728;
        $cacheKey = "mik_res_" . md5("{$host}:{$port}");
        if ($useCache && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return \Illuminate\Support\Facades\Cache::get($cacheKey);
        }

        $rows = $this->query('/system/resource/print');
        $row = $rows[0] ?? [];

        if (empty($row)) {
            return [];
        }

        $healthMap = [];
        try {
            $healthRows = $this->query('/system/health/print');
            foreach ($healthRows as $hRow) {
                if (isset($hRow['name']) && isset($hRow['value'])) {
                    $healthMap[strtolower(trim((string) $hRow['name']))] = $hRow['value'];
                }
                foreach ($hRow as $k => $v) {
                    if (!in_array($k, ['.id', 'name', 'value', 'type'])) {
                        $healthMap[strtolower(trim((string) $k))] = $v;
                    }
                }
            }
        } catch (\Exception $e) {}

        $temp = isset($healthMap['temperature'])
            ? (int) $healthMap['temperature']
            : (isset($healthMap['cpu-temperature'])
                ? (int) $healthMap['cpu-temperature']
                : (isset($healthMap['board-temperature1'])
                    ? (int) $healthMap['board-temperature1']
                    : (isset($healthMap['board-temperature']) ? (int) $healthMap['board-temperature'] : null)));

        $voltage = isset($healthMap['voltage']) ? (float) $healthMap['voltage'] : null;

        $data = [
            'cpu_load' => isset($row['cpu-load']) ? (int) $row['cpu-load'] : null,
            'free_memory' => $row['free-memory'] ?? null,
            'total_memory' => $row['total-memory'] ?? null,
            'memory_percent' => (isset($row['total-memory']) && (int) $row['total-memory'] > 0 && isset($row['free-memory']))
                ? (int) round(((int) $row['total-memory'] - (int) $row['free-memory']) / (int) $row['total-memory'] * 100)
                : null,
            'free_hdd' => $row['free-hdd-space'] ?? null,
            'total_hdd' => $row['total-hdd-space'] ?? null,
            'uptime' => $row['uptime'] ?? null,
            'version' => $row['version'] ?? null,
            'board_name' => $row['board-name'] ?? null,
            'model' => $row['model'] ?? null,
            'temperature' => $temp,
            'voltage' => $voltage,
        ];

        if ($useCache) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $data, 5);
        }

        return $data;
    }

    public function getDetailedResource(): array
    {
        $rows = $this->query('/system/resource/print');
        $row = $rows[0] ?? [];

        if (empty($row)) {
            return [];
        }

        $identity = null;
        try {
            $idRows = $this->query('/system/identity/print');
            $identity = $idRows[0]['name'] ?? null;
        } catch (\Exception $e) {}

        $rb = [];
        try {
            $rbRows = $this->query('/system/routerboard/print');
            $rb = $rbRows[0] ?? [];
        } catch (\Exception $e) {}

        $healthMap = [];
        try {
            $healthRows = $this->query('/system/health/print');
            foreach ($healthRows as $hRow) {
                if (isset($hRow['name']) && isset($hRow['value'])) {
                    $healthMap[strtolower(trim((string) $hRow['name']))] = $hRow['value'];
                }
                foreach ($hRow as $k => $v) {
                    if (!in_array($k, ['.id', 'name', 'value', 'type'])) {
                        $healthMap[strtolower(trim((string) $k))] = $v;
                    }
                }
            }
        } catch (\Exception $e) {}

        $totalMem = isset($row['total-memory']) ? (int) $row['total-memory'] : 0;
        $freeMem = isset($row['free-memory']) ? (int) $row['free-memory'] : 0;
        $usedMem = max(0, $totalMem - $freeMem);
        $memPercent = $totalMem > 0 ? (int) round(($usedMem / $totalMem) * 100) : 0;

        $totalHdd = isset($row['total-hdd-space']) ? (int) $row['total-hdd-space'] : 0;
        $freeHdd = isset($row['free-hdd-space']) ? (int) $row['free-hdd-space'] : 0;
        $usedHdd = max(0, $totalHdd - $freeHdd);
        $hddPercent = $totalHdd > 0 ? (int) round(($usedHdd / $totalHdd) * 100) : 0;

        return [
            'identity' => $identity,
            'cpu_load' => isset($row['cpu-load']) ? (int) $row['cpu-load'] : 0,
            'cpu_count' => isset($row['cpu-count']) ? (int) $row['cpu-count'] : 1,
            'cpu_frequency' => isset($row['cpu-frequency']) ? (int) $row['cpu-frequency'] : null,
            'cpu_model' => $row['cpu'] ?? null,
            'architecture' => $row['architecture-name'] ?? null,
            'board_name' => $row['board-name'] ?? ($rb['model'] ?? null),
            'version' => $row['version'] ?? null,
            'uptime' => $row['uptime'] ?? null,
            'total_memory' => $totalMem,
            'free_memory' => $freeMem,
            'used_memory' => $usedMem,
            'memory_percent' => $memPercent,
            'total_hdd' => $totalHdd,
            'free_hdd' => $freeHdd,
            'used_hdd' => $usedHdd,
            'hdd_percent' => $hddPercent,
            'platform' => $row['platform'] ?? 'MikroTik',
            'model' => $rb['model'] ?? ($row['model'] ?? null),
            'serial_number' => $rb['serial-number'] ?? null,
            'current_firmware' => $rb['current-firmware'] ?? null,
            'upgrade_firmware' => $rb['upgrade-firmware'] ?? null,
            'temperature' => isset($healthMap['temperature'])
                ? (int) $healthMap['temperature']
                : (isset($healthMap['cpu-temperature'])
                    ? (int) $healthMap['cpu-temperature']
                    : (isset($healthMap['board-temperature1'])
                        ? (int) $healthMap['board-temperature1']
                        : (isset($healthMap['board-temperature']) ? (int) $healthMap['board-temperature'] : null))),
            'voltage' => isset($healthMap['voltage']) ? (float) $healthMap['voltage'] : null,
        ];
    }

    public function getInterfaces(): array
    {
        try {
            return $this->query('/interface/print', [
                '.proplist' => 'name,type,running,disabled,rx-byte,tx-byte,comment,mac-address,mtu',
            ]);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    public function isConnected(): bool
    {
        return $this->connect();
    }

    public function connectManual(string $host, string $user, string $pass, int $port = 8728): bool
    {
        $this->connectionConfig = [
            'host' => $this->sanitizeHost($host),
            'user' => $user,
            'pass' => $pass,
            'port' => $port,
            'api_mode' => ($port === 8729 ? 'ssl' : 'api'),
        ];
        $this->connected = false;
        $this->connectionFailed = false;
        $this->lastError = null;
        return $this->connect();
    }

    public function getLastError(): string
    {
        return $this->lastError ?? '';
    }

    public function getLastTrap(): ?string
    {
        return $this->lastTrap;
    }

    /**
     * Ambil '.id' record pertama dari hasil print. Guard: record tanpa '.id'
     * (mis. hasil parsing aneh) → null, bukan crash "undefined array key".
     */
    private function firstId(array $rows): ?string
    {
        foreach ($rows as $r) {
            if (is_array($r) && isset($r['.id'])) {
                return $r['.id'];
            }
        }
        return null;
    }

    public function getPppoeSecret(string $username): ?array
    {
        return $this->findSecret($username);
    }

    /**
     * Cari secret PPPoE by nama. LANGSUNG print SEMUA + cocokin client-side
     * (case-insensitive). JANGAN pakai filter '?name=' — di RouterOS asli
     * query berfilter bisa bikin koneksi mati/!fatal → query berikutnya ikut
     * kosong ("User tidak ditemukan" padahal secret ada).
     */
    public function findSecret(string $username): ?array
    {
        $all = $this->query('/ppp/secret/print');
        foreach ($all as $r) {
            if (is_array($r) && strtolower((string) ($r['name'] ?? '')) === strtolower(trim($username))) {
                return $r;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  PPPoE — active sessions
    // ------------------------------------------------------------------

    public function getActivePppoe(): array
    {
        return $this->query('/ppp/active/print', [
            '.proplist' => 'name,address,uptime,service,caller-id',
        ]);
    }

    public function getPppoeActive(): array
    {
        return $this->getActivePppoe();
    }

    public function getPppoeActiveCount(): int
    {
        $active = $this->getActivePppoe();

        return count($active);
    }

    // ------------------------------------------------------------------
    //  PPPoE — secrets (CRUD)
    // ------------------------------------------------------------------

    public function getPppoeSecrets(?array $proplist = null): array
    {
        $params = [];
        if ($proplist !== null) {
            $params['.proplist'] = implode(',', $proplist);
        } else {
            $params['.proplist'] = '.id,name,service,profile,remote-address,local-address,last-logged-out,disabled,comment';
        }
        return $this->query('/ppp/secret/print', $params);
    }

    /**
     * PPPoE users yang OFFLINE: ada di secret tapi tidak ada di active session.
     * Sertakan last-logged-out dari tabel secret.
     */
    public function getInactivePppoe(): array
    {
        $secrets = $this->query('/ppp/secret/print', [
            '.proplist' => 'name,profile,disabled,last-logged-out',
        ]);
        $active = $this->getActivePppoe();
        $activeNames = array_map(fn($a) => $a['name'] ?? '', $active);

        return array_values(array_filter($secrets, function ($s) use ($activeNames) {
            return !in_array($s['name'] ?? '', $activeNames) && ($s['disabled'] ?? 'false') !== 'true';
        }));
    }

    public function getPppoeProfiles(): array
    {
        return $this->query('/ppp/profile/print');
    }

    public function addPppoeSecret(string $username, string $password, string $profile = 'default', string $service = 'pppoe'): bool
    {
        if (empty($username) || empty($password)) {
            $this->lastError = 'Username dan password wajib diisi';

            return false;
        }

        try {
            $this->query('/ppp/secret/add', [
                'name' => $username,
                'password' => $password,
                'profile' => $profile,
                'service' => $service,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Helper alias to create / provision PPPoE secret with array or positional arguments
     */
    public function createPppoeSecret(array|string $dataOrUsername, ?string $password = null, string $profile = 'default', string $service = 'pppoe'): bool
    {
        if (is_array($dataOrUsername)) {
            $username = $dataOrUsername['name'] ?? ($dataOrUsername['username'] ?? '');
            $password = $dataOrUsername['password'] ?? '123456';
            $profile = $dataOrUsername['profile'] ?? 'default';
            $service = $dataOrUsername['service'] ?? 'pppoe';
            return $this->addPppoeSecret($username, $password, $profile, $service);
        }

        return $this->addPppoeSecret((string)$dataOrUsername, $password ?? '123456', $profile, $service);
    }

    public function updatePppoeSecret(string $username, array $data): bool
    {
        $users = [$this->findSecret($username)];
        if (empty($users) || empty($users[0])) {
            $this->lastError = 'User tidak ditemukan di router';

            return false;
        }

        $id = $this->firstId($users);
        if (!$id) { $this->lastError = 'User tidak ditemukan'; return false; }
        $params = ['.id' => $id];

        if (isset($data['name']) && ! empty($data['name'])) {
            $params['name'] = $data['name'];
        }
        if (isset($data['password']) && ! empty($data['password'])) {
            $params['password'] = $data['password'];
        }
        if (isset($data['profile'])) {
            $params['profile'] = $data['profile'];
        }
        if (isset($data['service'])) {
            $params['service'] = $data['service'];
        }

        try {
            $this->query('/ppp/secret/set', $params);

            if (isset($data['profile'])) {
                $this->kickPppoeUser($username);
            }

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function deletePppoeSecret(string $username): bool
    {
        $users = [$this->findSecret($username)];
        if (empty($users[0])) {
            // Diagnosa: kasih tau apa yang terjadi — secret beneran ga ada,
            // atau print balik kosong, atau ada error RouterOS (trap).
            $trap = $this->getLastTrap();
            $this->lastError = 'User tidak ditemukan di router'
                . ($trap ? ' (RouterOS: ' . $trap . ')' : '')
                . ' — cek nama secret di Winbox: "' . $username . '"';
            return false;
        }
        $id = $this->firstId($users);
        if (! $id) {
            $this->lastError = 'User ditemukan tapi tanpa .id — cek respon router.';

            return false;
        }

        try {
            $this->kickPppoeUser($username);
            $this->query('/ppp/secret/remove', ['.id' => $id]);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function setPppoeUserProfile(string $username, string $profile): bool
    {
        $secret = $this->findSecret($username);
        if (empty($secret) || empty($secret['.id'])) {
            $this->lastError = "Secret '{$username}' tidak ditemukan di MikroTik";

            return false;
        }

        $id = $secret['.id'];

        // Cocokkan nama profile secara case-insensitive dari router
        $targetProfile = $profile;
        try {
            $profiles = $this->query('/ppp/profile/print');
            foreach ($profiles as $p) {
                if (is_array($p) && strtolower((string) ($p['name'] ?? '')) === strtolower(trim($profile))) {
                    $targetProfile = $p['name'];
                    break;
                }
            }
        } catch (\Exception $e) {}

        try {
            $this->query('/ppp/secret/set', [
                '.id' => $id,
                'profile' => $targetProfile,
            ]);

            $this->kickPppoeUser($username);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function kickPppoeUser(string $username): void
    {
        if (empty($username)) {
            return;
        }

        try {
            $active = $this->query('/ppp/active/print');
            foreach ($active as $r) {
                if (!is_array($r) || !isset($r['.id'])) {
                    continue;
                }
                $activeUser = strtolower((string) ($r['name'] ?? ($r['user'] ?? '')));
                if ($activeUser === strtolower(trim($username))) {
                    $this->query('/ppp/active/remove', ['.id' => $r['.id']]);
                }
            }
        } catch (\Exception $e) {
            Log::error("kickPppoeUser error for {$username}: " . $e->getMessage());
        }
    }

    public function enablePppoeSecret(string $username): bool
    {
        $secret = $this->findSecret($username);
        if (empty($secret) || empty($secret['.id'])) {
            $this->lastError = "Secret '{$username}' tidak ditemukan di MikroTik";
            return false;
        }

        $id = $secret['.id'];

        $this->query('/ppp/secret/enable', ['.id' => $id]);

        return true;
    }

    public function disablePppoeSecret(string $username): bool
    {
        $secret = $this->findSecret($username);
        if (empty($secret) || empty($secret['.id'])) {
            $this->lastError = "Secret '{$username}' tidak ditemukan di MikroTik";
            return false;
        }

        $id = $secret['.id'];

        $this->query('/ppp/secret/disable', ['.id' => $id]);
        $this->kickPppoeUser($username);

        return true;
    }

    /** Aliases for the controller layer. */
    public function enablePppoe(string $username): bool
    {
        return $this->enablePppoeSecret($username);
    }

    public function disablePppoe(string $username): bool
    {
        return $this->disablePppoeSecret($username);
    }

    // ------------------------------------------------------------------
    //  PPPoE — profile management
    // ------------------------------------------------------------------

    public function addPppoeProfile(string $name, string $rateLimit = '', string $localAddress = '', string $remoteAddress = ''): bool
    {
        if (empty($name)) {
            $this->lastError = 'Nama profile wajib diisi';

            return false;
        }

        try {
            $params = ['name' => $name];

            if (! empty($rateLimit)) {
                $params['rate-limit'] = $rateLimit;
            }
            if (! empty($localAddress)) {
                $params['local-address'] = $localAddress;
            }
            if (! empty($remoteAddress)) {
                $params['remote-address'] = $remoteAddress;
            }

            $this->query('/ppp/profile/add', $params);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function updatePppoeProfile(string $name, array $data): bool
    {
        $profiles = $this->query('/ppp/profile/print', ['?name' => $name]);
        if (empty($profiles)) {
            $this->lastError = 'Profile tidak ditemukan';

            return false;
        }

        $id = $profiles[0]['.id'];
        $params = ['.id' => $id];

        if (isset($data['name'])) {
            $params['name'] = $data['name'];
        }
        if (isset($data['rate_limit'])) {
            $params['rate-limit'] = $data['rate_limit'];
        }
        if (isset($data['local_address'])) {
            $params['local-address'] = $data['local_address'];
        }
        if (isset($data['remote_address'])) {
            $params['remote-address'] = $data['remote_address'];
        }

        try {
            $this->query('/ppp/profile/set', $params);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function deletePppoeProfile(string $name): bool
    {
        if (in_array($name, ['default', 'default-encryption'], true)) {
            $this->lastError = 'Profile default tidak dapat dihapus';

            return false;
        }

        $profiles = $this->query('/ppp/profile/print', ['?name' => $name]);
        if (empty($profiles)) {
            $this->lastError = 'Profile tidak ditemukan';

            return false;
        }

        try {
            $this->query('/ppp/profile/remove', ['.id' => $profiles[0]['.id']]);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Auto-inject / provision the PPPoE on-down disconnect accounting script to all PPP profiles in MikroTik.
     * Ensures 100% accurate cumulative bandwidth without manual copy-paste by billing admin.
     */
    public function autoProvisionPppoeAccountingScript(int $routerId): array
    {
        $results = ['success' => true, 'updated_profiles' => [], 'skipped_profiles' => []];

        try {
            $profiles = $this->query('/ppp/profile/print');
            if (empty($profiles) || !is_array($profiles)) {
                return $results;
            }

            $syncUrl = url('/api/v1/mikrotik/session-disconnect');
            if (str_starts_with($syncUrl, 'http://localhost') || str_starts_with($syncUrl, 'http://127.0.0.1')) {
                $appUrl = config('app.url');
                if ($appUrl && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
                    $syncUrl = rtrim($appUrl, '/') . '/api/v1/mikrotik/session-disconnect';
                }
            }

            $targetScript = ':local u "$user"; :local ifname $"interface"; :local rx 0; :local tx 0; :do { :if ([:len $ifname] > 0) do={ :set rx [/interface get $ifname rx-byte]; :set tx [/interface get $ifname tx-byte]; } else={ :set rx [/interface get [find name="<pppoe-$u>"] rx-byte]; :set tx [/interface get [find name="<pppoe-$u>"] tx-byte]; }; } on-error={}; /tool fetch url="' . $syncUrl . '" http-method=post http-data="username=$u&rx=$rx&tx=$tx&router_id=' . $routerId . '" keep-result=no;';

            foreach ($profiles as $prof) {
                $id = $prof['.id'] ?? null;
                $name = $prof['name'] ?? '';
                $currentOnDown = $prof['on-down'] ?? '';

                if (!$id) {
                    continue;
                }

                // Skip if already contains session-disconnect for this router
                if (str_contains($currentOnDown, 'session-disconnect') && str_contains($currentOnDown, "router_id={$routerId}")) {
                    $results['skipped_profiles'][] = $name;
                    continue;
                }

                // Combine with existing script if present, or set targetScript
                $newScript = $targetScript;
                if (!empty($currentOnDown) && !str_contains($currentOnDown, 'session-disconnect')) {
                    $newScript = trim($currentOnDown) . "\n" . $targetScript;
                }

                $this->query('/ppp/profile/set', [
                    '.id' => $id,
                    'on-down' => $newScript,
                ]);

                $results['updated_profiles'][] = $name;
            }
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            \Illuminate\Support\Facades\Log::warning("[MikrotikService] Failed to auto-provision PPPoE accounting script on router #{$routerId}: " . $e->getMessage());
        }

        return $results;
    }

    // ------------------------------------------------------------------
    //  Hotspot — users
    // ------------------------------------------------------------------

    public function getHotspotUsers(?array $proplist = null): array
    {
        $params = [];
        if ($proplist !== null) {
            $params['.proplist'] = implode(',', $proplist);
        } else {
            $params['.proplist'] = '.id,name,profile,server,comment,disabled,uptime,bytes-in,bytes-out,limit-uptime,limit-bytes-total';
        }
        return $this->query('/ip/hotspot/user/print', $params);
    }

    public function getHotspotActive(): array
    {
        $raw = $this->query('/ip/hotspot/active/print', [
            '.proplist' => '.id,user,address,uptime,bytes-in,bytes-out,server,profile,mac-address,session-time-left',
        ]);
        $userProfiles = [];
        $serverProfiles = [];
        try {
            $users = $this->query('/ip/hotspot/user/print', [
                '.proplist' => 'name,profile',
            ]);
            foreach ($users as $u) {
                if (! empty($u['name'])) {
                    $userProfiles[$u['name']] = $u['profile'] ?? 'default';
                }
            }
            $servers = $this->query('/ip/hotspot/print', [
                '.proplist' => 'name,profile',
            ]);
            foreach ($servers as $s) {
                if (! empty($s['name'])) {
                    $serverProfiles[$s['name']] = $s['profile'] ?? '';
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        $result = [];
        foreach ($raw as $item) {
            $username = $item['user'] ?? '';
            $serverName = $item['server'] ?? '';
            $result[] = [
                'id'                => $item['.id'] ?? null,
                'user'              => $username,
                'profile'           => $item['profile'] ?? ($userProfiles[$username] ?? 'default'),
                'server'            => $serverName,
                'server_profile'    => $serverProfiles[$serverName] ?? '',
                'address'           => $item['address'] ?? '',
                'mac_address'       => $item['mac-address'] ?? '',
                'uptime'            => $item['uptime'] ?? '',
                'session_time_left' => $item['session-time-left'] ?? $item['time-left'] ?? '',
                'idle_time'         => $item['idle-time'] ?? '',
                'bytes_in'          => $item['bytes-in'] ?? 0,
                'bytes_out'         => $item['bytes-out'] ?? 0,
                'limit_bytes_total' => $item['limit-bytes-total'] ?? '',
                'login_by'          => $item['login-by'] ?? '',
            ];
        }

        return $result;
    }

    public function removeHotspotActive(string $id): bool
    {
        $this->query('/ip/hotspot/active/remove', ['.id' => $id]);

        return empty($this->getLastTrap());
    }

    /**
     * Clean and Kick Expired Hotspot Active Sessions & Users (Mikhmon Expired Cleaner Logic)
     */
     public function cleanExpiredHotspotUsers(): array
     {
        $kicked = [];
        $activeList = $this->query('/ip/hotspot/active/print');
        $userList = $this->query('/ip/hotspot/user/print');

        $usersMap = [];
        $usersToDelete = [];

        foreach ($userList as $u) {
            $name = $u['name'] ?? '';
            if (empty($name) || in_array(strtolower($name), ['admin', 'default'], true)) {
                continue;
            }
            $usersMap[$name] = $u;

            // Check if user is already expired (limit-uptime = 1s from Mikhmon or uptime exceeded)
            $limitUptime = trim((string) ($u['limit-uptime'] ?? ''));
            $uptime = trim((string) ($u['uptime'] ?? ''));
            $limitBytes = (int) ($u['limit-bytes-total'] ?? 0);
            $bytesIn = (int) ($u['bytes-in'] ?? 0);
            $bytesOut = (int) ($u['bytes-out'] ?? 0);

            $isExpired = false;
            $expireReason = '';

            if ($limitUptime === '1s') {
                $isExpired = true;
                $expireReason = 'Mikhmon expired tag (limit-uptime=1s)';
            } elseif (!empty($limitUptime) && !empty($uptime)) {
                $limitSec = $this->parseMikrotikDuration($limitUptime);
                $uptimeSec = $this->parseMikrotikDuration($uptime);
                if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                    $isExpired = true;
                    $expireReason = 'Uptime telah mencapai limit (' . $limitUptime . ')';
                }
            } elseif ($limitBytes > 0 && ($bytesIn + $bytesOut) >= $limitBytes) {
                $isExpired = true;
                $expireReason = 'Batas kuota data habis';
            }

            if ($isExpired && !empty($u['.id'])) {
                $usersToDelete[] = [
                    'id' => $u['.id'],
                    'name' => $name,
                    'reason' => $expireReason,
                ];
            }
        }

        foreach ($activeList as $act) {
            $username = $act['user'] ?? '';
            $actId = $act['.id'] ?? null;
            if (! $actId || ! $username) {
                continue;
            }

            $shouldKick = false;
            $reason = '';

            // 1. Cek session-time-left di active session
            $timeLeft = $act['session-time-left'] ?? $act['time-left'] ?? null;
            if ($timeLeft !== null && in_array(trim($timeLeft), ['0s', '00:00:00', '0', '0m', '0h'], true)) {
                $shouldKick = true;
                $reason = 'Batas waktu (session-time-left) habis';
            }

            // 2. Cek limit-uptime vs uptime
            $userObj = $usersMap[$username] ?? null;
            if (! $shouldKick && $userObj) {
                $limitUptime = $userObj['limit-uptime'] ?? null;
                $uptime = $act['uptime'] ?? $userObj['uptime'] ?? null;
                if ($limitUptime && $uptime) {
                    $limitSec = $this->parseMikrotikDuration($limitUptime);
                    $uptimeSec = $this->parseMikrotikDuration($uptime);
                    if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                        $shouldKick = true;
                        $reason = 'Uptime telah mencapai limit (' . $limitUptime . ')';
                    }
                }

                // 3. Cek limit-bytes-total vs bytes
                $limitBytes = (int) ($userObj['limit-bytes-total'] ?? $act['limit-bytes-total'] ?? 0);
                $bytesIn = (int) ($act['bytes-in'] ?? $userObj['bytes-in'] ?? 0);
                $bytesOut = (int) ($act['bytes-out'] ?? $userObj['bytes-out'] ?? 0);
                if ($limitBytes > 0 && ($bytesIn + $bytesOut) >= $limitBytes) {
                    $shouldKick = true;
                    $reason = 'Batas kuota data habis';
                }
            }

            if ($shouldKick) {
                $this->query('/ip/hotspot/active/remove', ['.id' => $actId]);
                $kicked[] = [
                    'user' => $username,
                    'reason' => $reason,
                ];
            }
        }

        // Remove expired users in chunks
        if (!empty($usersToDelete)) {
            $idsChunk = array_chunk(array_column($usersToDelete, 'id'), 50);
            foreach ($idsChunk as $chunk) {
                try {
                    $this->query('/ip/hotspot/user/remove', ['.id' => implode(',', $chunk)]);
                } catch (\Exception $e) {
                    foreach ($chunk as $singleId) {
                        try {
                            $this->query('/ip/hotspot/user/remove', ['.id' => $singleId]);
                        } catch (\Exception $ex) {
                            // ignore
                        }
                    }
                }
            }

            foreach ($usersToDelete as $del) {
                $kicked[] = [
                    'user' => $del['name'],
                    'reason' => 'User kedaluwarsa dihapus (' . $del['reason'] . ')',
                ];
            }
        }

        return $kicked;
    }

    public function parseMikrotikDuration(string $duration): int
    {
        $duration = trim(strtolower($duration));
        if (empty($duration)) {
            return 0;
        }

        $seconds = 0;
        if (preg_match('/(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/', $duration, $m)) {
            $weeks = (int) ($m[1] ?? 0);
            $days = (int) ($m[2] ?? 0);
            $hours = (int) ($m[3] ?? 0);
            $minutes = (int) ($m[4] ?? 0);
            $secs = (int) ($m[5] ?? 0);
            $seconds = ($weeks * 604800) + ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $secs;
        }

        if ($seconds === 0 && str_contains($duration, ':')) {
            $parts = explode(':', $duration);
            if (count($parts) === 3) {
                $seconds = ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
            } elseif (count($parts) === 2) {
                $seconds = ((int) $parts[0] * 60) + (int) $parts[1];
            }
        }

        return $seconds;
    }

    public function getActiveHotspotUsers(): array
    {
        return $this->query('/ip/hotspot/active/print', [
            '.proplist' => 'user,address,uptime',
        ]);
    }

    public function addHotspotUser(
        string $username,
        string $password,
        string $profile = 'default',
        string $limitUptime = '',
        int|string $limitBytesTotal = 0,
        string $server = 'all',
        string $customComment = ''
    ): bool {
        if (empty($username) || empty($password)) {
            $this->lastError = 'Username dan password wajib diisi';

            return false;
        }

        try {
            $params = [
                'name' => trim($username),
                'password' => trim($password),
                'profile' => !empty($profile) ? trim($profile) : 'default',
            ];

            // Server: Di ROS6 dan ROS7, jika 'all' atau kosong, hilangkan parameter agar berlaku untuk semua server
            if (! empty($server) && strtolower(trim($server)) !== 'all') {
                $params['server'] = trim($server);
            }

            $limitUptime = trim((string) $limitUptime);
            if (! empty($limitUptime) && $limitUptime !== '0' && $limitUptime !== '0s') {
                $params['limit-uptime'] = $limitUptime;
            }

            if (! empty($limitBytesTotal) && (int) $limitBytesTotal > 0) {
                $params['limit-bytes-total'] = (string) $limitBytesTotal;
            }

            if (! empty($customComment)) {
                $params['comment'] = trim($customComment);
            } else {
                $params['comment'] = (! empty($limitUptime) ? 'vc-' : 'up-') . trim($username) . '-nodera';
            }

            $this->query('/ip/hotspot/user/add', $params);

            // Jika gagal karena profile belum ada atau tidak cocok di MikroTik, buat profile otomatis lalu ulangi
            if (! empty($this->lastTrap) && (
                str_contains(strtolower($this->lastTrap), 'profile') ||
                str_contains(strtolower($this->lastTrap), 'input does not match')
            )) {
                $this->lastTrap = null;
                $this->lastError = null;

                $profAddParams = [
                    'name' => $params['profile'],
                    'shared-users' => '1',
                ];
                if (!empty($limitUptime)) {
                    $profAddParams['session-timeout'] = $limitUptime;
                }
                $this->query('/ip/hotspot/user/profile/add', $profAddParams);

                $this->lastTrap = null;
                $this->lastError = null;
                $this->query('/ip/hotspot/user/add', $params);
            }

            if (! empty($this->lastTrap)) {
                $this->lastError = $this->lastTrap;
                return false;
            }

            if (! empty($this->lastError)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Batch add hotspot users using optimized RouterOS script execution or pipelined queries
     */
    public function addHotspotUsersBatch(array $users): array
    {
        if (empty($users)) {
            return ['created' => 0, 'failed' => 0, 'error' => null];
        }

        $created = 0;
        $failed = 0;
        $lastError = null;

        // Execute in script chunks of 250 users
        $chunks = array_chunk($users, 250);
        foreach ($chunks as $chunkIndex => $chunk) {
            $scriptLines = [];
            $scriptLines[] = "/ip hotspot user";

            foreach ($chunk as $u) {
                $uname = addslashes($u['username']);
                $pass = addslashes($u['password']);
                $prof = addslashes($u['profile'] ?? 'default');
                $comment = addslashes($u['comment'] ?? '');
                $limitUptime = !empty($u['limit_uptime']) ? $u['limit_uptime'] : '';
                $limitBytes = !empty($u['limit_bytes']) ? (int) $u['limit_bytes'] : 0;
                $server = !empty($u['server']) && strtolower($u['server']) !== 'all' ? $u['server'] : '';

                $line = "add name=\"{$uname}\" password=\"{$pass}\" profile=\"{$prof}\"";
                if (!empty($server)) {
                    $line .= " server=\"{$server}\"";
                }
                if (!empty($limitUptime) && $limitUptime !== '0' && $limitUptime !== '0s') {
                    $line .= " limit-uptime=\"{$limitUptime}\"";
                }
                if ($limitBytes > 0) {
                    $line .= " limit-bytes-total={$limitBytes}";
                }
                if (!empty($comment)) {
                    $line .= " comment=\"{$comment}\"";
                }
                $scriptLines[] = $line;
            }

            $scriptSource = implode("\n", $scriptLines);
            $scriptName = 'nodera_gen_' . uniqid() . '_' . $chunkIndex;

            $scriptSuccess = false;
            try {
                // Add & Run RouterOS internal script for near-instant native execution
                $this->query('/system/script/add', [
                    'name' => $scriptName,
                    'source' => $scriptSource,
                ]);

                if (empty($this->lastTrap) && empty($this->lastError)) {
                    $this->query('/system/script/run', [
                        'number' => $scriptName,
                    ]);

                    if (empty($this->lastTrap) && empty($this->lastError)) {
                        $scriptSuccess = true;
                        $created += count($chunk);
                    }
                }

                // Cleanup script
                $this->query('/system/script/remove', [
                    'numbers' => $scriptName,
                ]);
            } catch (\Throwable $e) {
                $scriptSuccess = false;
            }

            if (!$scriptSuccess) {
                // Fallback to sequential API query if script creation is disabled or failed on router
                foreach ($chunk as $u) {
                    $ok = $this->addHotspotUser(
                        $u['username'],
                        $u['password'],
                        $u['profile'] ?? 'default',
                        $u['limit_uptime'] ?? '',
                        $u['limit_bytes'] ?? 0,
                        $u['server'] ?? 'all',
                        $u['comment'] ?? ''
                    );
                    if ($ok) {
                        $created++;
                    } else {
                        $failed++;
                        $lastError = $this->getLastError() ?: 'Gagal menambahkan user ke MikroTik.';
                    }
                }
            }
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'error' => $lastError,
        ];
    }

    /**
     * Batch delete hotspot users
     */
    public function deleteHotspotUsersBatch(array $usernames): int
    {
        if (empty($usernames)) {
            return 0;
        }

        $deleted = 0;
        $chunks = array_chunk($usernames, 100);
        foreach ($chunks as $chunk) {
            $scriptLines = [];
            foreach ($chunk as $u) {
                $safeUser = addslashes($u);
                $scriptLines[] = "/ip hotspot user remove [find name=\"{$safeUser}\"]";
            }
            $scriptSource = implode("\n", $scriptLines);
            $scriptName = 'nodera_rm_' . uniqid();

            try {
                $this->query('/system/script/add', [
                    'name' => $scriptName,
                    'source' => $scriptSource,
                ]);
                $this->query('/system/script/run', [
                    'number' => $scriptName,
                ]);
                $this->query('/system/script/remove', [
                    'numbers' => $scriptName,
                ]);
                $deleted += count($chunk);
            } catch (\Throwable $e) {
                foreach ($chunk as $u) {
                    if ($this->deleteHotspotUser($u)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    public function getHotspotServers(): array
    {
        return $this->query('/ip/hotspot/print');
    }

    public function updateHotspotUser(string $username, array $data): bool
    {
        $users = $this->query('/ip/hotspot/user/print', ['?name' => $username]);
        if (empty($users)) {
            $this->lastError = 'User tidak ditemukan';

            return false;
        }

        $id = $users[0]['.id'];
        $params = ['.id' => $id];

        if (isset($data['password']) && ! empty($data['password'])) {
            $params['password'] = $data['password'];
        }
        if (isset($data['profile'])) {
            $params['profile'] = $data['profile'];
        }
        if (isset($data['limit_uptime'])) {
            $params['limit-uptime'] = $data['limit_uptime'];
            $params['comment'] = ! empty($data['limit_uptime'])
                ? 'vc-' . $username . '-gembok'
                : 'up-' . $username . '-gembok';
        }

        try {
            $this->query('/ip/hotspot/user/set', $params);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function deleteHotspotUser(string $username): bool
    {
        $users = $this->query('/ip/hotspot/user/print', ['?name' => $username]);
        if (empty($users)) {
            $this->lastError = 'User tidak ditemukan';

            return false;
        }

        try {
            $active = $this->query('/ip/hotspot/active/print', ['?user' => $username]);
            if (! empty($active)) {
                $this->query('/ip/hotspot/active/remove', ['.id' => $active[0]['.id']]);
            }

            $this->query('/ip/hotspot/user/remove', ['.id' => $users[0]['.id']]);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    // ------------------------------------------------------------------
    //  Hotspot — profiles
    // ------------------------------------------------------------------

    public function getHotspotProfiles(): array
    {
        return $this->query('/ip/hotspot/user/profile/print');
    }

    public function addHotspotProfile(string $name, int $sharedUsers = 1, string $rateLimit = ''): bool
    {
        if (empty($name)) {
            $this->lastError = 'Nama profile wajib diisi';

            return false;
        }

        try {
            $params = [
                'name' => $name,
                'shared-users' => (string) $sharedUsers,
            ];

            if (! empty($rateLimit)) {
                $params['rate-limit'] = $rateLimit;
            }

            $this->query('/ip/hotspot/user/profile/add', $params);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function updateHotspotProfile(string $name, array $data): bool
    {
        $profiles = $this->query('/ip/hotspot/user/profile/print', ['?name' => $name]);
        if (empty($profiles)) {
            $this->lastError = 'Profile tidak ditemukan';

            return false;
        }

        $id = $profiles[0]['.id'];
        $params = ['.id' => $id];

        if (isset($data['name'])) {
            $params['name'] = $data['name'];
        }
        if (isset($data['shared_users'])) {
            $params['shared-users'] = (string) $data['shared_users'];
        }
        if (isset($data['rate_limit'])) {
            $params['rate-limit'] = $data['rate_limit'];
        }

        try {
            $this->query('/ip/hotspot/user/profile/set', $params);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function deleteHotspotProfile(string $name): bool
    {
        if ($name === 'default') {
            $this->lastError = 'Profile default tidak dapat dihapus';

            return false;
        }

        $profiles = $this->query('/ip/hotspot/user/profile/print', ['?name' => $name]);
        if (empty($profiles)) {
            $this->lastError = 'Profile tidak ditemukan';

            return false;
        }

        try {
            $this->query('/ip/hotspot/user/profile/remove', ['.id' => $profiles[0]['.id']]);

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    // ------------------------------------------------------------------
    //  Hotspot — profile info (price / duration from comment or on-login script)
    // ------------------------------------------------------------------

    /**
     * Parse hotspot profile pricing from comment or on-login (Mikhmon) script.
     *
     * Comment format: "HARGA:5000|DURASI:1jam"
     * Mikhmon on-login: :put (\",rem,2002,1d,3000,,Disable,\");
     */
    public function getHotspotProfileInfo(string $profileName): ?array
    {
        if (! $this->connect()) {
            return null;
        }

        try {
            $this->writeWord('/ip/hotspot/user/profile/print');
            $this->writeWord('?name=' . $profileName);
            $this->writeWord('');

            $response = $this->readResponse();

            if (empty($response)) {
                return null;
            }

            $profile = $response[0] ?? null;
            if (! $profile) {
                return null;
            }

            $info = [];

            // 1. Try comment format
            if (isset($profile['comment'])) {
                if (preg_match('/HARGA:(\d+)/', $profile['comment'], $m)) {
                    $info['price'] = (int) $m[1];
                }
                if (preg_match('/DURASI:([^|]+)/', $profile['comment'], $m)) {
                    $info['duration'] = trim($m[1]);
                }
            }

            // 2. Try Mikhmon on-login format
            if ((empty($info['price']) || empty($info['duration'])) && isset($profile['on-login'])) {
                $cleanScript = preg_replace('/\s+/', '', $profile['on-login']);

                $marker = ',rem,';
                $startPos = strpos($cleanScript, $marker);
                if ($startPos !== false) {
                    $sub = substr($cleanScript, $startPos + strlen($marker));
                    $parts = explode(',', $sub);

                    if (count($parts) >= 3) {
                        if (empty($info['duration']) && ! empty($parts[1])) {
                            $info['duration'] = $parts[1];
                        }
                        if (empty($info['price']) && ! empty($parts[2]) && is_numeric($parts[2])) {
                            $info['price'] = (int) $parts[2];
                        }
                    }
                }
            }

            return ! empty($info) ? $info : null;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return null;
        }
    }

    // ------------------------------------------------------------------
    //  ARP Table & Static IP Management
    // ------------------------------------------------------------------

    /**
     * Get all ARP entries (/ip/arp/print)
     */
    public function getArpTable(?string $interface = null): array
    {
        if (! $this->connect()) {
            return [];
        }

        try {
            $this->writeWord('/ip/arp/print');
            if ($interface) {
                $this->writeWord('?interface=' . $interface);
            }
            $this->writeWord('');

            $response = $this->readResponse();
            $items = [];
            foreach ($response as $item) {
                if (isset($item['address'])) {
                    $items[] = [
                        'id' => $item['.id'] ?? null,
                        'address' => $item['address'],
                        'mac_address' => $item['mac-address'] ?? '',
                        'interface' => $item['interface'] ?? '',
                        'comment' => $item['comment'] ?? '',
                        'dynamic' => ($item['dynamic'] ?? 'false') === 'true',
                        'disabled' => ($item['disabled'] ?? 'false') === 'true',
                        'invalid' => ($item['invalid'] ?? 'false') === 'true',
                        'published' => ($item['published'] ?? 'false') === 'true',
                    ];
                }
            }
            return $items;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * Add or update static ARP entry (/ip/arp/add)
     */
    public function addArpEntry(string $address, string $macAddress, string $interface, ?string $comment = null): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $addr = trim($address);
            $mac = strtoupper(trim($macAddress));
            $iface = trim($interface);

            // First check if already exists, then remove dynamic or duplicate entry
            $existing = $this->findArpEntry($addr);
            if ($existing && !empty($existing['.id'])) {
                $this->query('/ip/arp/remove', ['.id' => $existing['.id']]);
            }

            $params = [
                'address' => $addr,
                'mac-address' => $mac,
                'interface' => $iface,
            ];
            if (! empty($comment)) {
                $params['comment'] = trim($comment);
            }

            $this->query('/ip/arp/add', $params);
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Find ARP entry by IP address
     */
    public function findArpEntry(string $address): ?array
    {
        if (! $this->connect()) {
            return null;
        }

        try {
            $addr = trim($address);
            $res = $this->query('/ip/arp/print', ['?address' => $addr]);
            if (! empty($res[0])) {
                return $res[0];
            }

            // Fallback scan
            $all = $this->query('/ip/arp/print');
            foreach ($all as $item) {
                if (isset($item['address']) && trim($item['address']) === $addr) {
                    return $item;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Remove ARP entry (/ip/arp/remove)
     */
    public function removeArpEntry(string $addressOrId): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $id = $addressOrId;
            if (filter_var($addressOrId, FILTER_VALIDATE_IP)) {
                $found = $this->findArpEntry($addressOrId);
                if (! $found || empty($found['.id'])) {
                    return true; // Already not present
                }
                $id = $found['.id'];
            }

            $this->query('/ip/arp/remove', ['.id' => $id]);
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Set ARP entry disabled / enabled (/ip/arp/set disabled=yes/no)
     */
    public function setArpDisabled(string $addressOrId, bool $disabled): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $id = $addressOrId;
            if (filter_var($addressOrId, FILTER_VALIDATE_IP)) {
                $found = $this->findArpEntry($addressOrId);
                if (! $found || empty($found['.id'])) {
                    return false;
                }
                $id = $found['.id'];
            }

            $this->query('/ip/arp/set', [
                '.id' => $id,
                'disabled' => $disabled ? 'yes' : 'no',
            ]);
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Resolve a profile name or rate limit string into a valid RouterOS Simple Queue max-limit (e.g. 10M/10M)
     */
    public function resolveRateLimit(string $rateOrProfile): string
    {
        $cleaned = trim($rateOrProfile);
        if (empty($cleaned)) {
            return '10M/10M';
        }

        // Direct upload/download format like "5M/10M", "1024k/2048k", "0/0"
        if (str_contains($cleaned, '/')) {
            return $cleaned;
        }

        // Check for isolir / expired keywords
        if (in_array(strtolower($cleaned), ['isolir', 'profile_isolir', 'expired', 'isolir_profile', 'block', 'drop', 'suspended'], true)) {
            return '128k/128k';
        }

        // Handle single rate patterns like "10Mbps", "10Mb", "10M", "1000k", "512k"
        if (preg_match('/^(\d+)\s*(mbps|mb|m|k|kbps|g|gbps)?$/i', $cleaned, $matches)) {
            $num = $matches[1];
            $unit = strtoupper($matches[2] ?? 'M');
            if (str_starts_with($unit, 'M') || $unit === '') {
                return "{$num}M/{$num}M";
            }
            if (str_starts_with($unit, 'K')) {
                return "{$num}k/{$num}k";
            }
            if (str_starts_with($unit, 'G')) {
                return "{$num}G/{$num}G";
            }
        }

        // If it's a PPP Profile name on the router, look up its rate-limit
        try {
            $profiles = $this->getPppoeProfiles();
            foreach ($profiles as $p) {
                if (strcasecmp($p['name'] ?? '', $cleaned) === 0 && !empty($p['rate-limit'])) {
                    $rl = trim($p['rate-limit']);
                    if (str_contains($rl, '/')) {
                        return $rl;
                    }
                    return "{$rl}/{$rl}";
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Check if there is a number embedded in the string (e.g. "Paket-20M" -> "20M/20M", "10_MBPS" -> "10M/10M")
        if (preg_match('/(\d+)\s*(mbps|mb|m|k|kbps|g|gbps)?/i', $cleaned, $matches)) {
            $num = $matches[1];
            $rawUnit = strtoupper($matches[2] ?? 'M');
            if (str_starts_with($rawUnit, 'K')) {
                $unit = 'k';
            } elseif (str_starts_with($rawUnit, 'G')) {
                $unit = 'G';
            } else {
                $unit = 'M';
            }
            return "{$num}{$unit}/{$num}{$unit}";
        }

        return '10M/10M';
    }

    /**
     * Find Simple Queue by name or target IP
     */
    public function findSimpleQueue(string $nameOrTarget): ?array
    {
        if (! $this->connect()) {
            return null;
        }

        $nameOrTarget = trim($nameOrTarget);
        $targetIp = explode('/', $nameOrTarget)[0];
        $isIp = filter_var($targetIp, FILTER_VALIDATE_IP);

        // 1. Search by exact name
        $res = $this->query('/queue/simple/print', ['?name' => $nameOrTarget]);
        if (! empty($res[0]['.id'])) {
            return $res[0];
        }

        // 2. Search by target IP if valid IP
        if ($isIp) {
            $res = $this->query('/queue/simple/print', ['?target' => "{$targetIp}/32"]);
            if (! empty($res[0]['.id'])) {
                return $res[0];
            }

            $res = $this->query('/queue/simple/print', ['?target' => $targetIp]);
            if (! empty($res[0]['.id'])) {
                return $res[0];
            }
        }

        // 3. Fallback: print all queues and inspect name/target
        $all = $this->query('/queue/simple/print');
        foreach ($all as $q) {
            $qName = $q['name'] ?? '';
            $qTarget = $q['target'] ?? '';
            if (strcasecmp($qName, $nameOrTarget) === 0) {
                return $q;
            }
            if ($isIp && str_contains($qTarget, $targetIp)) {
                return $q;
            }
        }

        return null;
    }

    /**
     * Add or update Simple Queue for Static IP rate limiting (/queue/simple/add or /queue/simple/set)
     */
    public function addSimpleQueue(string $name, string $targetIp, string $maxLimit = '10M/10M', ?string $comment = null): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $cleanName = trim($name);
            $cleanIp = trim($targetIp);
            $target = $cleanIp . (str_contains($cleanIp, '/') ? '' : '/32');
            $resolvedLimit = $this->resolveRateLimit($maxLimit);

            // Find existing queue by name or target IP
            $existing = $this->findSimpleQueue($cleanName) ?? $this->findSimpleQueue($cleanIp);

            if ($existing && ! empty($existing['.id'])) {
                $params = [
                    '.id' => $existing['.id'],
                    'name' => $cleanName,
                    'target' => $target,
                    'max-limit' => $resolvedLimit,
                    'disabled' => 'no',
                ];
                if (! empty($comment)) {
                    $params['comment'] = trim($comment);
                }
                $this->query('/queue/simple/set', $params);
                return empty($this->lastTrap);
            }

            $params = [
                'name' => $cleanName,
                'target' => $target,
                'max-limit' => $resolvedLimit,
            ];
            if (! empty($comment)) {
                $params['comment'] = trim($comment);
            }

            $this->query('/queue/simple/add', $params);
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Remove Simple Queue (/queue/simple/remove)
     */
    public function removeSimpleQueue(string $nameOrTarget): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $existing = $this->findSimpleQueue($nameOrTarget);
            if (! $existing || empty($existing['.id'])) {
                return true; // Already gone
            }

            $this->query('/queue/simple/remove', ['.id' => $existing['.id']]);
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Set Simple Queue Rate (Dynamic FUP / Speed Shaping without disconnect)
     */
    public function setSimpleQueueRate(string $nameOrTarget, string $maxLimit): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $existing = $this->findSimpleQueue($nameOrTarget);
            if (! $existing || empty($existing['.id'])) {
                return false;
            }

            $resolvedLimit = $this->resolveRateLimit($maxLimit);
            $this->query('/queue/simple/set', [
                '.id' => $existing['.id'],
                'max-limit' => $resolvedLimit,
                'disabled' => 'no',
            ]);

            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Add or update IP address in firewall address-list (e.g. ISOLIR_LIST)
     */
    public function addAddressList(string $list, string $address, string $comment = ''): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $cleanList = trim($list);
            $cleanAddr = trim($address);

            $res = $this->query('/ip/firewall/address-list/print', [
                '?list' => $cleanList,
                '?address' => $cleanAddr,
            ]);

            if (empty($res)) {
                $params = [
                    'list' => $cleanList,
                    'address' => $cleanAddr,
                ];
                if (! empty($comment)) {
                    $params['comment'] = trim($comment);
                }
                $this->query('/ip/firewall/address-list/add', $params);
            } else {
                if (isset($res[0]['disabled']) && $res[0]['disabled'] === 'true') {
                    $this->query('/ip/firewall/address-list/set', [
                        '.id' => $res[0]['.id'],
                        'disabled' => 'no',
                    ]);
                }
            }
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Remove IP address from firewall address-list (e.g. ISOLIR_LIST)
     */
    public function removeAddressList(string $list, string $address): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            $cleanList = trim($list);
            $cleanAddr = trim($address);

            $res = $this->query('/ip/firewall/address-list/print', [
                '?list' => $cleanList,
                '?address' => $cleanAddr,
            ]);

            foreach ($res as $item) {
                if (! empty($item['.id'])) {
                    $this->query('/ip/firewall/address-list/remove', [
                        '.id' => $item['.id'],
                    ]);
                }
            }
            return empty($this->lastTrap);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get active IP address for PPPoE session
     */
    public function getActiveSessionIp(string $username): ?string
    {
        if (! $this->connect()) {
            return null;
        }

        try {
            $res = $this->query('/ppp/active/print', [
                '?name' => trim($username),
            ]);

            return $res[0]['address'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
