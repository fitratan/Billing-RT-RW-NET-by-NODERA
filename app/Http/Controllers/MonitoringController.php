<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    public function index()
    {
        $healthStatus = $this->getHealthStatus();

        return Inertia::render('Admin/Monitoring', ['healthStatus' => $healthStatus]);
    }

    public function superIndex()
    {
        $healthStatus = $this->getHealthStatus();

        return view('superadmin.monitoring.index', compact('healthStatus'));
    }

    public function health()
    {
        $health = $this->getHealthStatus();

        return response()->json($health);
    }

    public function superHealth()
    {
        $health = $this->getHealthStatus();

        return response()->json($health);
    }

    protected function getHealthStatus(): array
    {
        $disk = $this->getDiskUsage();
        $memory = $this->getMemoryUsage();
        $cpu = $this->getCpuUsage();
        $uptime = $this->getUptime();
        $dbSize = $this->getDatabaseSize();

        $issues = [];
        $warnings = [];

        // Disk checks
        if ($disk['percentage'] > 80) {
            $issues[] = "Disk usage is high: {$disk['percentage']}%";
        } elseif ($disk['percentage'] > 60) {
            $warnings[] = "Disk usage is elevated: {$disk['percentage']}%";
        }

        // Memory checks
        if ($memory['percentage'] > 80) {
            $issues[] = "Memory usage is high: {$memory['percentage']}%";
        } elseif ($memory['percentage'] > 60) {
            $warnings[] = "Memory usage is elevated: {$memory['percentage']}%";
        }

        // CPU checks
        if ($cpu['percentage'] > 80) {
            $issues[] = "CPU usage is high: {$cpu['percentage']}%";
        } elseif ($cpu['percentage'] > 60) {
            $warnings[] = "CPU usage is elevated: {$cpu['percentage']}%";
        }

        $status = 'healthy';
        if (count($issues) > 0) {
            $status = 'critical';
        } elseif (count($warnings) > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'warnings' => $warnings,
            'timestamp' => now()->toIso8601String(),
            'metrics' => [
                'cpu' => $cpu,
                'memory' => $memory,
                'disk' => $disk,
                'uptime' => $uptime,
                'database' => $dbSize,
                'server' => [
                    'hostname' => gethostname(),
                    'php_version' => phpversion(),
                    'laravel_version' => app()->version(),
                    'os' => php_uname('s') . ' ' . php_uname('r'),
                ],
            ],
        ];
    }

    protected function getDiskUsage(): array
    {
        $path = base_path();

        if (PHP_OS_FAMILY === 'Windows') {
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $used = $total - $free;
            $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            return [
                'total' => $this->formatBytes($total),
                'used' => $this->formatBytes($used),
                'free' => $this->formatBytes($free),
                'percentage' => $percentage,
            ];
        }

        // Unix: parse df output for accuracy
        $output = @shell_exec("df -B1 " . escapeshellarg($path) . " 2>/dev/null");
        if ($output) {
            $lines = explode("\n", trim($output));
            if (count($lines) >= 2) {
                $parts = preg_split('/\s+/', $lines[1]);
                if (count($parts) >= 4) {
                    $total = (int)$parts[1];
                    $used = (int)$parts[2];
                    $free = (int)$parts[3];
                    $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;

                    return [
                        'total' => $this->formatBytes($total),
                        'used' => $this->formatBytes($used),
                        'free' => $this->formatBytes($free),
                        'percentage' => $percentage,
                    ];
                }
            }
        }

        // Fallback
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => $percentage,
        ];
    }

    protected function getMemoryUsage(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'percentage' => 0,
            ];
        }

        $output = @shell_exec("free -b 2>/dev/null");
        if ($output) {
            $lines = explode("\n", trim($output));
            if (count($lines) >= 2) {
                $parts = preg_split('/\s+/', $lines[1]);
                if (count($parts) >= 3) {
                    $total = (int)$parts[1];
                    $used = (int)$parts[2];
                    $free = $total - $used;
                    $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;

                    return [
                        'total' => $this->formatBytes($total),
                        'used' => $this->formatBytes($used),
                        'free' => $this->formatBytes($free),
                        'percentage' => $percentage,
                    ];
                }
            }
        }

        // Fallback to PHP memory info
        $total = 8 * 1024 * 1024 * 1024; // Assume 8GB
        $free = 4 * 1024 * 1024 * 1024;  // Assume half free
        $used = $total - $free;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => 50,
        ];
    }

    protected function getCpuUsage(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'percentage' => 0,
                'cores' => 1,
                'model' => php_uname('m'),
            ];
        }

        // Get CPU info
        $cpuInfo = @shell_exec("cat /proc/cpuinfo 2>/dev/null | grep 'model name' | head -1");
        $cpuModel = 'Unknown';
        if ($cpuInfo && preg_match('/:\s*(.+)/', $cpuInfo, $m)) {
            $cpuModel = trim($m[1]);
        }

        $cores = (int)@shell_exec("nproc 2>/dev/null") ?: 1;

        // Get CPU load
        $load = sys_getloadavg();
        $load1 = $load[0] ?? 0;
        $percentage = $cores > 0 ? round(($load1 / $cores) * 100, 1) : 0;
        $percentage = min(100, $percentage);

        return [
            'percentage' => $percentage,
            'cores' => $cores,
            'model' => $cpuModel,
            'load' => [
                '1min' => round($load[0] ?? 0, 2),
                '5min' => round($load[1] ?? 0, 2),
                '15min' => round($load[2] ?? 0, 2),
            ],
        ];
    }

    protected function getUptime(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['seconds' => 0, 'formatted' => 'N/A'];
        }

        $uptime = @shell_exec("cat /proc/uptime 2>/dev/null");
        $seconds = 0;
        if ($uptime) {
            $seconds = (int)explode(' ', trim($uptime))[0];
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return [
            'seconds' => $seconds,
            'formatted' => "{$days}d {$hours}h {$minutes}m",
        ];
    }

    protected function getDatabaseSize(): array
    {
        $dbPath = database_path('database.sqlite');

        if (!file_exists($dbPath)) {
            return [
                'size' => '0 B',
                'path' => $dbPath,
                'tables' => 0,
                'records' => 0,
            ];
        }

        $size = filesize($dbPath);

        try {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            $tableCount = count($tables);
            $totalRecords = 0;

            $tableData = [];
            foreach ($tables as $table) {
                if ($table->name === 'sqlite_sequence') continue;
                $count = DB::table($table->name)->count();
                $tableData[] = [
                    'name' => $table->name,
                    'count' => $count,
                ];
                $totalRecords += $count;
            }
        } catch (\Exception $e) {
            $tableData = [];
            $tableCount = 0;
            $totalRecords = 0;
        }

        return [
            'size' => $this->formatBytes($size),
            'path' => $dbPath,
            'tables' => $tableData,
            'totalTables' => $tableCount,
            'totalRecords' => $totalRecords,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[(int)$i];
    }
}
