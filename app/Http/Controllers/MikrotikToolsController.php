<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MikrotikToolsController extends Controller
{
    /**
     * Get shared props for tools pages
     */
    protected function getSharedProps(Request $request, string $currentTool)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $request->user()?->tenant_id;
        
        $registeredRouters = [];
        $registeredOlts = [];
        if ($request->user() || session('admin_logged_in') || session('superadmin_logged_in')) {
            $routersQuery = Mikrotik::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('is_active', true)
                ->orderBy('name');

            $registeredRouters = $routersQuery->get()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'host' => $r->host,
                'port' => (int) ($r->port ?? 8728),
                'username' => $r->username,
                'location' => $r->location,
            ]);

            $oltsQuery = \App\Models\Olt::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('is_active', true)
                ->orderBy('name');

            $registeredOlts = $oltsQuery->get()->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'host' => $o->host,
                'snmp_port' => (int) ($o->snmp_port ?? 161),
                'telnet_port' => (int) ($o->telnet_port ?? 23),
                'snmp_community' => $o->snmp_community ?? 'public',
                'model' => $o->model,
                'location' => $o->location,
                'onu_count' => (int) ($o->onu_count ?? 0),
            ]);
        }

        $company = Setting::company();

        return [
            'currentTool' => $currentTool,
            'registeredRouters' => $registeredRouters,
            'registeredOlts' => $registeredOlts,
            'company' => $company,
            'appName' => config('app.name', 'NODERA'),
            'isAuthenticated' => (bool) ($request->user() || session('admin_logged_in') || session('superadmin_logged_in')),
        ];
    }

    public function index(Request $request)
    {
        return Inertia::render('Tools/LoadBalance', $this->getSharedProps($request, 'loadbalance'));
    }

    public function loadbalance(Request $request)
    {
        return Inertia::render('Tools/LoadBalance', $this->getSharedProps($request, 'loadbalance'));
    }

    public function game(Request $request)
    {
        return Inertia::render('Tools/GameTraffic', $this->getSharedProps($request, 'game'));
    }

    public function stream(Request $request)
    {
        return Inertia::render('Tools/StreamTraffic', $this->getSharedProps($request, 'stream'));
    }

    public function speedtest(Request $request)
    {
        return Inertia::render('Tools/SpeedtestBypass', $this->getSharedProps($request, 'speedtest'));
    }

    public function security(Request $request)
    {
        return Inertia::render('Tools/SecurityHardening', $this->getSharedProps($request, 'security'));
    }

    public function hotspotPppoe(Request $request)
    {
        return Inertia::render('Tools/HotspotPppoe', $this->getSharedProps($request, 'hotspot_pppoe'));
    }

    public function portForward(Request $request)
    {
        return Inertia::render('Tools/PortForward', $this->getSharedProps($request, 'port_forward'));
    }

    public function burstQos(Request $request)
    {
        return Inertia::render('Tools/BurstQos', $this->getSharedProps($request, 'burst_qos'));
    }

    /**
     * Test connection to a MikroTik Router
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'router_id' => 'nullable|integer',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $service = $this->resolveMikrotikService($request);
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter koneksi MikroTik tidak lengkap.',
            ], 422);
        }

        if (!$service->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => $service->getLastError() ?: 'Gagal terhubung ke router MikroTik. Periksa Host, Port API (8728), Username dan Password.',
            ], 400);
        }

        try {
            $resource = $service->getResource(false);
            $identity = $service->query('/system/identity/print');
            $identityName = $identity[0]['name'] ?? 'MikroTik';

            return response()->json([
                'success' => true,
                'message' => "Berhasil terhubung ke {$identityName}!",
                'router_info' => [
                    'identity' => $identityName,
                    'board_name' => $resource['board_name'] ?? 'RouterBOARD',
                    'version' => $resource['version'] ?? 'RouterOS',
                    'cpu_load' => ($resource['cpu_load'] ?? 0) . '%',
                    'uptime' => $resource['uptime'] ?? '-',
                    'free_memory' => $resource['free_memory'] ?? '-',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terhubung tetapi gagal membaca informasi router: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute a single CLI command via RouterOS API
     */
    public function executeCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'router_id' => 'nullable|integer',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $service = $this->resolveMikrotikService($request);
        if (!$service || !$service->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => $service?->getLastError() ?: 'Tidak dapat terhubung ke MikroTik API.',
            ], 400);
        }

        $cli = trim($request->input('command'));
        if (empty($cli) || str_starts_with($cli, '#') || str_starts_with($cli, '//')) {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'Komentar dilewati.',
            ]);
        }

        try {
            if ($this->isFindRemoveCommand($cli)) {
                $removeRes = $this->executeFindRemoveCommand($service, $cli);
                return response()->json($removeRes);
            }

            $apiAction = $this->parseCliToApiCommand($cli);
            if (!$apiAction) {
                return response()->json([
                    'success' => false,
                    'message' => "Format CLI tidak didukung untuk API: {$cli}",
                ]);
            }

            $response = $service->query($apiAction['command'], $apiAction['params']);
            $trap = $service->getLastTrap();
            $err = $service->getLastError();
            $errMsg = !empty($err) ? $err : $trap;

            if (!empty($errMsg)) {
                if (str_contains(strtolower($errMsg), 'already have') || str_contains(strtolower($errMsg), 'already exists') || str_contains(strtolower($errMsg), 'already registered')) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Sudah ada di router (OK)',
                        'api_command' => $apiAction['command'],
                        'params' => $apiAction['params'],
                        'response' => $response,
                    ]);
                }

                Log::warning("MikroTik Command failed: {$cli}", [
                    'api' => $apiAction,
                    'trap' => $trap,
                    'err' => $err,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errMsg,
                    'api_command' => $apiAction['command'],
                    'params' => $apiAction['params'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Berhasil diterapkan.',
                'api_command' => $apiAction['command'],
                'params' => $apiAction['params'],
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::warning("MikroTik Tools Command Execution failed: " . $e->getMessage(), [
                'cli' => $cli,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eksekusi gagal: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute batch of commands sequentially
     */
    public function executeBatch(Request $request)
    {
        $request->validate([
            'commands' => 'required|array',
            'commands.*' => 'required|string',
            'router_id' => 'nullable|integer',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $service = $this->resolveMikrotikService($request);
        if (!$service || !$service->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => $service?->getLastError() ?: 'Tidak dapat terhubung ke MikroTik API.',
            ], 400);
        }

        $commands = $request->input('commands', []);
        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($commands as $index => $rawCli) {
            $cli = trim($rawCli);
            if (empty($cli) || str_starts_with($cli, '#') || str_starts_with($cli, '//')) {
                $results[] = [
                    'line' => $index + 1,
                    'command' => $cli,
                    'status' => 'skipped',
                    'message' => 'Komentar',
                ];
                $skippedCount++;
                continue;
            }

            if ($this->isFindRemoveCommand($cli)) {
                $removeRes = $this->executeFindRemoveCommand($service, $cli);
                $results[] = [
                    'line' => $index + 1,
                    'command' => $cli,
                    'status' => $removeRes['success'] ? 'success' : 'failed',
                    'message' => $removeRes['message'] ?? 'OK',
                ];
                if ($removeRes['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
                continue;
            }

            $apiAction = $this->parseCliToApiCommand($cli);
            if (!$apiAction) {
                $results[] = [
                    'line' => $index + 1,
                    'command' => $cli,
                    'status' => 'failed',
                    'message' => 'Format CLI tidak dapat di-parse ke API',
                ];
                $failedCount++;
                continue;
            }

            try {
                $service->query($apiAction['command'], $apiAction['params']);
                $trap = $service->getLastTrap();
                $err = $service->getLastError();
                $errMsg = !empty($err) ? $err : $trap;

                if (!empty($errMsg)) {
                    if (str_contains(strtolower($errMsg), 'already have') || str_contains(strtolower($errMsg), 'already exists')) {
                        $results[] = [
                            'line' => $index + 1,
                            'command' => $cli,
                            'status' => 'success',
                            'message' => 'Sudah ada (OK)',
                        ];
                        $successCount++;
                    } else {
                        $results[] = [
                            'line' => $index + 1,
                            'command' => $cli,
                            'status' => 'failed',
                            'message' => $errMsg,
                        ];
                        $failedCount++;
                    }
                } else {
                    $results[] = [
                        'line' => $index + 1,
                        'command' => $cli,
                        'status' => 'success',
                        'message' => 'OK',
                    ];
                    $successCount++;
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'line' => $index + 1,
                    'command' => $cli,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                $failedCount++;
            }
        }

        return response()->json([
            'success' => $failedCount === 0,
            'summary' => [
                'total' => count($commands),
                'success' => $successCount,
                'failed' => $failedCount,
                'skipped' => $skippedCount,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Upload and inject error.html to MikroTik router (FTP and RouterOS API fallback)
     */
    public function uploadIsolirPage(Request $request)
    {
        $request->validate([
            'html_content' => 'required|string',
            'router_id' => 'nullable|integer',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $service = $this->resolveMikrotikService($request);
        if (!$service || !$service->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => $service?->getLastError() ?: 'Tidak dapat terhubung ke MikroTik API.',
            ], 400);
        }

        $html = $request->input('html_content');
        $uploaded = false;
        $details = [];

        // 1. Dapatkan kredensial router untuk FTP
        $host = null;
        $user = null;
        $pass = null;
        if ($request->filled('router_id')) {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $request->user()?->tenant_id;
            $router = Mikrotik::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->find($request->input('router_id'));
            if ($router) {
                $host = $router->host;
                $user = $router->username;
                $pass = $router->password ?? '';
            }
        } else {
            $host = $request->input('host');
            $user = $request->input('username') ?: 'admin';
            $pass = $request->input('password') ?? '';
        }

        if ($host && str_contains($host, ':')) {
            [$host] = explode(':', $host, 2);
        }

        // 2. Pastikan webproxy direset-html terlebih dahulu agar folder webproxy dibuat otomatis
        try {
            $service->query('/ip/proxy/reset-html');
            $details[] = 'Proxy reset-html berhasil diinisialisasi.';
        } catch (\Throwable $e) {
            // ignore
        }

        // 3. Coba upload via FTP
        if (function_exists('ftp_connect') && !empty($host)) {
            try {
                $ftp = @ftp_connect($host, 21, 5);
                if ($ftp && @ftp_login($ftp, $user, $pass)) {
                    @ftp_pasv($ftp, true);
                    $tmp = tmpfile();
                    fwrite($tmp, $html);
                    fseek($tmp, 0);

                    // Buat direktori webproxy jika belum ada
                    @ftp_mkdir($ftp, 'webproxy');

                    $f1 = @ftp_fput($ftp, 'webproxy/error.html', $tmp, FTP_BINARY);
                    fseek($tmp, 0);
                    $f2 = @ftp_fput($ftp, 'error.html', $tmp, FTP_BINARY);
                    fclose($tmp);
                    ftp_close($ftp);

                    if ($f1 || $f2) {
                        $uploaded = true;
                        $details[] = 'Berhasil mengunggah file error.html via FTP ke MikroTik.';
                    }
                }
            } catch (\Throwable $e) {
                Log::info("MikroTik FTP upload note: " . $e->getMessage());
            }
        }

        // 4. Jika FTP belum sukses atau port 21 ditutup, coba via RouterOS API
        if (!$uploaded) {
            try {
                // Cek file webproxy/error.html
                $files = $service->query('/file/print', ['?name' => 'webproxy/error.html']);
                if (empty($files)) {
                    $files = $service->query('/file/print', ['?name' => 'error.html']);
                }

                if (!empty($files) && isset($files[0]['.id'])) {
                    $fileId = $files[0]['.id'];
                    $service->query('/file/set', [
                        '.id' => $fileId,
                        'contents' => $html,
                    ]);
                    $err = $service->getLastError() ?: $service->getLastTrap();
                    if (empty($err)) {
                        $uploaded = true;
                        $details[] = 'Berhasil memperbarui isi file webproxy/error.html via MikroTik API.';
                    }
                } else {
                    $service->query('/file/add', [
                        'name' => 'webproxy/error.html',
                        'contents' => $html,
                    ]);
                    $err = $service->getLastError() ?: $service->getLastTrap();
                    if (empty($err)) {
                        $uploaded = true;
                        $details[] = 'Berhasil membuat file webproxy/error.html via MikroTik API.';
                    }
                }
            } catch (\Throwable $e) {
                Log::info("MikroTik API file upload note: " . $e->getMessage());
            }
        }

        if ($uploaded) {
            return response()->json([
                'success' => true,
                'message' => 'Halaman error.html isolir berhasil di-inject ke router MikroTik!',
                'details' => $details,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengunggah otomatis. Pastikan service FTP (port 21) aktif di /ip service MikroTik atau unggah file error.html secara manual via Winbox Files.',
            'details' => $details,
        ], 500);
    }

    /**
     * Resolve MikrotikService strictly from authenticated tenant's registered router
     */
    protected function resolveMikrotikService(Request $request): ?MikrotikService
    {
        $user = $request->user();
        if (!$user && !session('admin_logged_in') && !session('superadmin_logged_in')) {
            return null;
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $user?->tenant_id;
        $isSuperadmin = (bool) (session('superadmin_logged_in') || $user?->role === 'superadmin');

        if ($request->filled('router_id')) {
            $query = Mikrotik::query();
            if (!$isSuperadmin) {
                if (!$tenantId) {
                    return null;
                }
                $query->where('tenant_id', $tenantId);
            }

            $router = $query->find($request->input('router_id'));
            if ($router) {
                return new MikrotikService($router);
            }
        }

        // Only allow fallback to default tenant router if registered, NEVER accept arbitrary raw host input from untrusted clients
        if ($tenantId) {
            $defaultRouter = Mikrotik::where('tenant_id', $tenantId)->where('is_active', true)->first();
            if ($defaultRouter) {
                return new MikrotikService($defaultRouter);
            }
        }

        return null;
    }

    /**
     * Convert CLI command string into API path and arguments
     */
    protected function parseCliToApiCommand(string $cli): ?array
    {
        $cli = trim($cli);
        if (empty($cli)) {
            return null;
        }

        $tokens = [];
        preg_match_all('/(?:[^\s"\'=]+|"[^"]*"|\'[^\']*\')+=?(?:[^\s"\'=]+|"[^"]*"|\'[^\']*\')*|[^\s"\'=]+|"[^"]*"|\'[^\']*/', $cli, $matches);
        $rawTokens = array_filter(array_map('trim', $matches[0] ?? []));

        $pathTokens = [];
        $paramTokens = [];
        $isParamSection = false;

        foreach ($rawTokens as $token) {
            if (!$isParamSection && str_starts_with($token, '/')) {
                $pathTokens[] = ltrim($token, '/');
            } elseif (!$isParamSection && in_array(strtolower($token), ['add', 'set', 'remove', 'enable', 'disable', 'print', 'find'])) {
                $pathTokens[] = strtolower($token);
                $isParamSection = true;
            } elseif (str_contains($token, '=') || $isParamSection) {
                $isParamSection = true;
                $paramTokens[] = $token;
            } else {
                $pathTokens[] = $token;
            }
        }

        if (empty($pathTokens)) {
            return null;
        }

        $apiPath = '/' . implode('/', $pathTokens);
        $params = [];

        foreach ($paramTokens as $pt) {
            if (str_contains($pt, '=')) {
                [$key, $val] = explode('=', $pt, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                $params[$key] = $val;
            } else {
                $params[$pt] = 'yes';
            }
        }

        return [
            'command' => $apiPath,
            'params' => $params,
        ];
    }

    /**
     * Check if command is a script-style find-and-remove command e.g. /ip firewall mangle remove [find comment~"NODERA"]
     */
    protected function isFindRemoveCommand(string $cli): bool
    {
        return preg_match('/^(.*?)\s+remove\s+\[\s*find\s*(.*?)\s*\]$/i', trim($cli)) === 1;
    }

    /**
     * Execute find-and-remove by querying print, filtering matching items, and removing each by .id
     */
    protected function executeFindRemoveCommand(MikrotikService $service, string $cli): array
    {
        if (!preg_match('/^(.*?)\s+remove\s+\[\s*find\s*(.*?)\s*\]$/i', trim($cli), $m)) {
            return ['success' => false, 'message' => 'Format bukan find remove.'];
        }

        $pathPart = trim($m[1]);
        $criteria = trim($m[2]);

        $tokens = array_filter(explode(' ', str_replace('/', ' ', $pathPart)));
        $basePath = '/' . implode('/', $tokens);

        $field = 'comment';
        $pattern = 'NODERA';
        $isRegex = false;

        if (preg_match('/([a-zA-Z0-9_-]+)\s*(~|=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]*))/i', $criteria, $cm)) {
            $field = $cm[1];
            $op = $cm[2];
            $pattern = !empty($cm[3]) ? $cm[3] : (!empty($cm[4]) ? $cm[4] : ($cm[5] ?? ''));
            $isRegex = ($op === '~');
        }

        try {
            $items = $service->query($basePath . '/print');
            $trap = $service->getLastTrap();
            if (!empty($trap)) {
                return [
                    'success' => true,
                    'message' => 'Dilewati (Menu tidak tersedia di router ini).',
                ];
            }

            if (empty($items) || !is_array($items)) {
                return [
                    'success' => true,
                    'message' => '0 item ditemukan (Bersih).',
                ];
            }

            $removedCount = 0;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $val = $item[$field] ?? '';
                $match = false;
                if ($isRegex) {
                    $match = str_contains(strtolower($val), strtolower($pattern));
                } else {
                    $match = (strtolower($val) === strtolower($pattern));
                }

                if ($match && !empty($item['.id'])) {
                    $service->query($basePath . '/remove', ['.id' => $item['.id']]);
                    $removedCount++;
                }
            }

            return [
                'success' => true,
                'message' => "Berhasil menghapus {$removedCount} item.",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'message' => 'Dilewati: ' . $e->getMessage(),
            ];
        }
    }
}
