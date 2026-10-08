<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DockerService
{
    protected string $socketPath;
    protected string $apiVersion;

    public function __construct(string $socketPath = '/var/run/docker.sock', string $apiVersion = 'v1.44')
    {
        $configuredPath = env('DOCKER_SOCKET_PATH', $socketPath);
        if (!file_exists($configuredPath)) {
            $candidates = ['/var/run/docker.sock', '/run/docker.sock', '/var/run/docker/docker.sock'];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $configuredPath = $cand;
                    break;
                }
            }
        }
        $this->socketPath = $configuredPath;
        $this->apiVersion = env('DOCKER_API_VERSION', $apiVersion);
    }

    /**
     * Check whether Docker socket or CLI is available.
     */
    public function isAvailable(): bool
    {
        if (file_exists($this->socketPath) && is_readable($this->socketPath)) {
            $res = $this->callSocket('/_ping');
            if ($res['status'] === 200 && trim($res['body']) === 'OK') {
                return true;
            }
        }

        // Fallback to CLI
        $check = shell_exec('docker ps 2>&1');
        return $check !== null && !str_contains($check, 'command not found') && !str_contains($check, 'Cannot connect');
    }

    /**
     * Get Docker daemon information and host metrics.
     */
    public function getDaemonInfo(): array
    {
        $info = [];
        $res = $this->callSocket('/info');
        if ($res['status'] === 200) {
            $info = json_decode($res['body'], true) ?? [];
        }

        $versionRes = $this->callSocket('/version');
        $versionData = $versionRes['status'] === 200 ? (json_decode($versionRes['body'], true) ?? []) : [];

        // Host system metrics
        $hostMetrics = $this->getHostMetrics();

        // Calculate containers directly if info is incomplete
        $containersList = $this->getContainers(false);
        $totalContainers = isset($info['Containers']) && (int)$info['Containers'] > 0 ? (int)$info['Containers'] : count($containersList);
        $runningContainers = isset($info['ContainersRunning']) && (int)$info['ContainersRunning'] > 0
            ? (int)$info['ContainersRunning']
            : count(array_filter($containersList, fn ($c) => ($c['state'] ?? '') === 'running'));
        $pausedContainers = $info['ContainersPaused'] ?? count(array_filter($containersList, fn ($c) => ($c['state'] ?? '') === 'paused'));
        $stoppedContainers = $info['ContainersStopped'] ?? ($totalContainers - $runningContainers - $pausedContainers);

        return [
            'available' => !empty($info) || $this->isAvailable(),
            'server_version' => $versionData['Version'] ?? ($info['ServerVersion'] ?? 'N/A'),
            'api_version' => $versionData['ApiVersion'] ?? $this->apiVersion,
            'os' => $info['OperatingSystem'] ?? php_uname('s') . ' ' . php_uname('r'),
            'architecture' => $info['Architecture'] ?? php_uname('m'),
            'kernel' => $info['KernelVersion'] ?? php_uname('v'),
            'cpus' => $info['NCPU'] ?? ($hostMetrics['cpu_cores'] ?? 1),
            'total_memory' => $info['MemTotal'] ?? ($hostMetrics['ram_total_bytes'] ?? 0),
            'containers_total' => max(0, $totalContainers),
            'containers_running' => max(0, $runningContainers),
            'containers_paused' => max(0, $pausedContainers),
            'containers_stopped' => max(0, $stoppedContainers),
            'images_count' => $info['Images'] ?? 0,
            'storage_driver' => $info['Driver'] ?? 'overlay2',
            'docker_root_dir' => $info['DockerRootDir'] ?? '/var/lib/docker',
            'host' => $hostMetrics,
        ];
    }


    /**
     * Get all containers with optional live stats.
     */
    public function getContainers(bool $withStats = true): array
    {
        $res = $this->callSocket('/containers/json?all=1');
        $containers = [];

        if ($res['status'] === 200) {
            $rawList = json_decode($res['body'], true) ?? [];
            foreach ($rawList as $c) {
                $id = $c['Id'] ?? '';
                $name = ltrim($c['Names'][0] ?? '', '/');
                $state = strtolower($c['State'] ?? 'unknown');
                $status = $c['Status'] ?? '';
                $image = $c['Image'] ?? '';
                $created = isset($c['Created']) ? date('Y-m-d H:i:s', $c['Created']) : '-';

                $ports = [];
                if (!empty($c['Ports'])) {
                    foreach ($c['Ports'] as $p) {
                        $public = $p['PublicPort'] ?? null;
                        $private = $p['PrivatePort'] ?? null;
                        $type = $p['Type'] ?? 'tcp';
                        if ($public) {
                            $ports[] = "{$public}:{$private}/{$type}";
                        } elseif ($private) {
                            $ports[] = "{$private}/{$type}";
                        }
                    }
                }

                $stats = [
                    'cpu_percent' => 0,
                    'mem_usage_bytes' => 0,
                    'mem_limit_bytes' => 0,
                    'mem_percent' => 0,
                    'net_input_bytes' => 0,
                    'net_output_bytes' => 0,
                ];

                if ($withStats && $state === 'running') {
                    $stats = $this->getContainerStats($id);
                }

                $containers[] = [
                    'id' => substr($id, 0, 12),
                    'full_id' => $id,
                    'name' => $name,
                    'service' => $this->detectServiceRole($name),
                    'image' => $image,
                    'state' => $state,
                    'status' => $status,
                    'created' => $created,
                    'ports' => implode(', ', $ports),
                    'stats' => $stats,
                ];
            }
        } else {
            // Fallback CLI
            $containers = $this->getContainersFromCli();
        }

        return $containers;
    }

    /**
     * Get real-time stats for a single container.
     */
    public function getContainerStats(string $id): array
    {
        $res = $this->callSocket("/containers/{$id}/stats?stream=0");
        if ($res['status'] !== 200) {
            return [
                'cpu_percent' => 0,
                'mem_usage_bytes' => 0,
                'mem_limit_bytes' => 0,
                'mem_percent' => 0,
                'net_input_bytes' => 0,
                'net_output_bytes' => 0,
            ];
        }

        $data = json_decode($res['body'], true);
        if (!$data) {
            return [
                'cpu_percent' => 0,
                'mem_usage_bytes' => 0,
                'mem_limit_bytes' => 0,
                'mem_percent' => 0,
                'net_input_bytes' => 0,
                'net_output_bytes' => 0,
            ];
        }

        // Calculate CPU %
        $cpuPercent = 0.0;
        $cpuDelta = ($data['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($data['precpu_stats']['cpu_usage']['total_usage'] ?? 0);
        $systemDelta = ($data['cpu_stats']['system_cpu_usage'] ?? 0) - ($data['precpu_stats']['system_cpu_usage'] ?? 0);
        $onlineCpus = $data['cpu_stats']['online_cpus'] ?? count($data['cpu_stats']['cpu_usage']['percpu_usage'] ?? [1]);

        if ($systemDelta > 0 && $cpuDelta > 0) {
            $cpuPercent = ($cpuDelta / $systemDelta) * $onlineCpus * 100.0;
        }

        // Calculate Memory Usage
        $memUsage = ($data['memory_stats']['usage'] ?? 0) - ($data['memory_stats']['stats']['cache'] ?? ($data['memory_stats']['stats']['inactive_file'] ?? 0));
        $memLimit = $data['memory_stats']['limit'] ?? 1;
        $memPercent = $memLimit > 0 ? ($memUsage / $memLimit) * 100.0 : 0.0;

        // Calculate Network IO
        $netIn = 0;
        $netOut = 0;
        if (!empty($data['networks'])) {
            foreach ($data['networks'] as $net) {
                $netIn += $net['rx_bytes'] ?? 0;
                $netOut += $net['tx_bytes'] ?? 0;
            }
        }

        return [
            'cpu_percent' => round($cpuPercent, 2),
            'mem_usage_bytes' => max(0, $memUsage),
            'mem_limit_bytes' => $memLimit,
            'mem_percent' => round($memPercent, 2),
            'net_input_bytes' => $netIn,
            'net_output_bytes' => $netOut,
        ];
    }

    /**
     * Restart a container.
     */
    public function restartContainer(string $idOrName): bool
    {
        $res = $this->callSocket("/containers/{$idOrName}/restart", 'POST');
        if ($res['status'] === 204 || $res['status'] === 200) {
            return true;
        }

        // Fallback to CLI
        $cleanName = escapeshellarg($idOrName);
        shell_exec("docker restart {$cleanName} 2>&1");
        return true;
    }

    /**
     * Start a container.
     */
    public function startContainer(string $idOrName): bool
    {
        $res = $this->callSocket("/containers/{$idOrName}/start", 'POST');
        if ($res['status'] === 204 || $res['status'] === 200) {
            return true;
        }

        $cleanName = escapeshellarg($idOrName);
        shell_exec("docker start {$cleanName} 2>&1");
        return true;
    }

    /**
     * Stop a container.
     */
    public function stopContainer(string $idOrName): bool
    {
        $res = $this->callSocket("/containers/{$idOrName}/stop", 'POST');
        if ($res['status'] === 204 || $res['status'] === 200) {
            return true;
        }

        $cleanName = escapeshellarg($idOrName);
        shell_exec("docker stop {$cleanName} 2>&1");
        return true;
    }

    /**
     * Get logs for a container.
     */
    public function getContainerLogs(string $idOrName, int $tail = 200): string
    {
        $res = $this->callSocket("/containers/{$idOrName}/logs?stdout=1&stderr=1&tail={$tail}&timestamps=1");
        if ($res['status'] === 200) {
            // Docker logs over socket has 8-byte header per line (STREAM_TYPE + 3 bytes padding + 4 bytes size)
            $raw = $res['body'];
            $cleaned = preg_replace('/[\x00-\x08]/', '', $raw);
            return $cleaned ?: "Tidak ada log pada container ini.";
        }

        $cleanName = escapeshellarg($idOrName);
        $logs = shell_exec("docker logs --tail {$tail} {$cleanName} 2>&1");
        return $logs ?: "Tidak ada log pada container ini.";
    }

    /**
     * Get list of Docker images.
     */
    public function getImages(): array
    {
        $res = $this->callSocket('/images/json');
        $images = [];
        if ($res['status'] === 200) {
            $list = json_decode($res['body'], true) ?? [];
            foreach ($list as $img) {
                $tags = $img['RepoTags'] ?? ['<none>:<none>'];
                $images[] = [
                    'id' => substr($img['Id'] ?? '', 7, 12),
                    'tag' => $tags[0] ?? '<none>',
                    'size_bytes' => $img['Size'] ?? 0,
                    'created' => isset($img['Created']) ? date('Y-m-d H:i:s', $img['Created']) : '-',
                ];
            }
        }
        return $images;
    }

    /**
     * Prune unused images, stopped containers, and cache.
     */
    public function pruneSystem(): array
    {
        $containersPruned = $this->callSocket('/containers/prune', 'POST');
        $imagesPruned = $this->callSocket('/images/prune', 'POST');
        $volumesPruned = $this->callSocket('/volumes/prune', 'POST');

        // CLI fallback to clean build cache
        shell_exec('docker builder prune -f 2>&1 || true');

        return [
            'success' => true,
            'message' => 'Cache Docker, container berhenti, dan image tak terpakai berhasil dibersihkan.',
        ];
    }

    /**
     * Direct HTTP call via Unix domain socket.
     */
    protected function callSocket(string $endpoint, string $method = 'GET', ?string $payload = null): array
    {
        if (!file_exists($this->socketPath)) {
            return ['status' => 0, 'body' => 'Socket not found'];
        }

        $url = "http://localhost/{$this->apiVersion}" . $endpoint;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => $this->socketPath,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 0, 'body' => $error];
        }

        return ['status' => $httpCode, 'body' => $response];
    }

    /**
     * Fallback to parse CLI docker ps.
     */
    protected function getContainersFromCli(): array
    {
        $raw = shell_exec("docker ps -a --format '{{json .}}' 2>/dev/null");
        if (!$raw) {
            return [];
        }

        $containers = [];
        $lines = explode("\n", trim($raw));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data) {
                $name = $data['Names'] ?? '';
                $state = strtolower($data['State'] ?? ($data['Status'] ?? 'unknown'));
                $containers[] = [
                    'id' => $data['ID'] ?? '',
                    'full_id' => $data['ID'] ?? '',
                    'name' => $name,
                    'service' => $this->detectServiceRole($name),
                    'image' => $data['Image'] ?? '',
                    'state' => str_starts_with($state, 'up') ? 'running' : 'exited',
                    'status' => $data['Status'] ?? '',
                    'created' => $data['CreatedAt'] ?? '',
                    'ports' => $data['Ports'] ?? '',
                    'stats' => [
                        'cpu_percent' => 0,
                        'mem_usage_bytes' => 0,
                        'mem_limit_bytes' => 0,
                        'mem_percent' => 0,
                        'net_input_bytes' => 0,
                        'net_output_bytes' => 0,
                    ],
                ];
            }
        }
        return $containers;
    }

    /**
     * Detect role of the container based on its name.
     */
    protected function detectServiceRole(string $name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'app') || str_contains($name, 'web') || str_contains($name, 'nodera_billing_app')) {
            return 'Web & Application Server (PHP-FPM + Nginx)';
        }
        if (str_contains($name, 'db') || str_contains($name, 'mariadb') || str_contains($name, 'mysql')) {
            return 'Database Server (MariaDB / MySQL)';
        }
        if (str_contains($name, 'redis')) {
            return 'Memory Cache & Queue Broker (Redis)';
        }
        if (str_contains($name, 'caddy')) {
            return 'SSL Reverse Proxy (Caddy)';
        }
        if (str_contains($name, 'radius')) {
            return 'FreeRADIUS Authentication Server';
        }
        if (str_contains($name, 'vpn') || str_contains($name, 'wireguard')) {
            return 'VPN & Tunnel Server';
        }
        return 'Core Service Container';
    }

    /**
     * Host RAM, CPU, Disk metrics.
     */
    protected function getHostMetrics(): array
    {
        $cores = 1;
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $cores = preg_match_all('/^processor/m', $cpuinfo, $matches);
        }

        $memTotal = 0;
        $memFree = 0;
        $memAvailable = 0;
        if (is_file('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $m)) {
                $memTotal = (int) $m[1] * 1024;
            }
            if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $m)) {
                $memAvailable = (int) $m[1] * 1024;
            } elseif (preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $m)) {
                $memAvailable = (int) $m[1] * 1024;
            }
        }

        $memUsed = max(0, $memTotal - $memAvailable);
        $memUsagePercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0;

        // Disk space
        $diskTotal = @disk_total_space('/') ?: 0;
        $diskFree = @disk_free_space('/') ?: 0;
        $diskUsed = max(0, $diskTotal - $diskFree);
        $diskUsagePercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // Load average
        $load = sys_getloadavg();

        return [
            'cpu_cores' => $cores ?: 1,
            'load_avg_1m' => $load[0] ?? 0,
            'load_avg_5m' => $load[1] ?? 0,
            'load_avg_15m' => $load[2] ?? 0,
            'ram_total_bytes' => $memTotal,
            'ram_used_bytes' => $memUsed,
            'ram_free_bytes' => $memAvailable,
            'ram_percent' => $memUsagePercent,
            'disk_total_bytes' => $diskTotal,
            'disk_used_bytes' => $diskUsed,
            'disk_free_bytes' => $diskFree,
            'disk_percent' => $diskUsagePercent,
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
        ];
    }
}
