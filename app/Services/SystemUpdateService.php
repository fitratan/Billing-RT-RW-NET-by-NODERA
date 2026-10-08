<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemUpdateService
{
    protected string $versionFile;

    public function __construct()
    {
        $this->versionFile = base_path('version.json');
    }

    /**
     * Get active license key from environment or database setting
     */
    public function getLicenseKey(): string
    {
        $key = env('NODERA_LICENSE_KEY') ?: env('LICENSE_KEY');
        if (empty($key)) {
            try {
                $key = \App\Models\Setting::withoutGlobalScopes()
                    ->whereIn('key', ['NODERA_LICENSE_KEY', 'LICENSE_KEY'])
                    ->whereNull('tenant_id')
                    ->value('value');
            } catch (\Throwable $e) {
            }
        }
        return trim((string) $key);
    }

    /**
     * Get local version metadata
     */
    public function getLocalVersion(): array
    {
        $base = [];
        if (File::exists($this->versionFile)) {
            $data = json_decode(File::get($this->versionFile), true);
            if (is_array($data)) {
                $base = $data;
            }
        }

        $isStandalone = (bool) (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false));
        $licenseKey = $this->getLicenseKey();
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        $licenseInfo = [
            'is_standalone' => $isStandalone,
            'license_key' => $licenseKey,
            'has_license' => !empty($licenseKey),
            'license_server' => $licenseServer,
            'license_status' => !empty($licenseKey) ? 'TERDAFTAR' : 'UNREGISTERED',
        ];

        return array_merge([
            'version' => config('app.version', '2.6.0'),
            'release_date' => date('Y-m-d'),
            'codename' => $isStandalone ? 'Enterprise ISP Edition (Standalone)' : 'Cloud SaaS Edition',
            'changelog' => [
                'Pembaruan performa & sinkronisasi realtime Top Bandwidth MikroTik.',
                'Penyempurnaan visual jalur kabel GIS Map (Feeder Biru Neon & Drop Wire Hijau).',
                'Otomasi 1-Click Update & Docker Container Deployment.',
            ],
        ], $base, $licenseInfo);
    }

    /**
     * Save / update license key in system
     */
    public function saveLicenseKey(string $key): array
    {
        $key = trim($key);
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        // 1. Simpan ke tabel Setting
        try {
            \App\Models\Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'NODERA_LICENSE_KEY', 'tenant_id' => null],
                ['value' => $key]
            );
            \App\Models\Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'LICENSE_KEY', 'tenant_id' => null],
                ['value' => $key]
            );
        } catch (\Throwable $e) {
        }

        // 2. Simpan ke berkas .env jika dapat ditulisi
        $envPath = base_path('.env');
        if (File::exists($envPath) && File::isWritable($envPath)) {
            $content = File::get($envPath);
            if (str_contains($content, 'NODERA_LICENSE_KEY=')) {
                $content = preg_replace('/^NODERA_LICENSE_KEY=.*/m', "NODERA_LICENSE_KEY={$key}", $content);
            } else {
                $content .= "\nNODERA_LICENSE_KEY={$key}";
            }
            if (str_contains($content, 'LICENSE_KEY=')) {
                $content = preg_replace('/^LICENSE_KEY=.*/m', "LICENSE_KEY={$key}", $content);
            } else {
                $content .= "\nLICENSE_KEY={$key}";
            }
            File::put($envPath, $content);
        }

        // 3. Bersihkan cache validasi lisensi
        \Illuminate\Support\Facades\Cache::forget('nodera_standalone_license_status');

        // 4. Lakukan verifikasi kilat ke server pusat & perbarui tier lisensi
        if (!empty($key)) {
            try {
                $hardwareId = \App\Http\Controllers\SetupWizardController::getHardwareId();
                $resp = Http::timeout(8)->asJson()->post("{$licenseServer}/api/v1/license/verify", [
                    'license_key' => $key,
                    'domain' => request()->getHost(),
                    'hardware_id' => $hardwareId,
                ]);

                if ($resp->successful() && $resp->json('valid')) {
                    $statusData = [
                        'valid' => true,
                        'status' => $resp->json('status', 'ACTIVE'),
                        'client_name' => $resp->json('client_name'),
                        'package_type' => $resp->json('package_type'),
                        'max_customers' => $resp->json('max_customers'),
                        'max_routers' => $resp->json('max_routers'),
                        'expires_at' => $resp->json('expires_at'),
                    ];
                    \Illuminate\Support\Facades\Cache::put('nodera_standalone_license_status', $statusData, now()->addMinutes(15));

                    if ($resp->json('package_type')) {
                        \App\Models\Setting::withoutGlobalScopes()->updateOrCreate(
                            ['key' => 'LICENSE_PACKAGE_TYPE', 'tenant_id' => null],
                            ['value' => $resp->json('package_type')]
                        );
                    }

                    $pkgName = $resp->json('package_type') ?: 'Active';
                    return [
                        'success' => true,
                        'message' => "Lisensi {$key} ({$pkgName}) berhasil diperbarui dan aktif seketika!",
                        'license_key' => $key,
                        'client_name' => $resp->json('client_name'),
                        'package_type' => $resp->json('package_type'),
                        'max_customers' => $resp->json('max_customers'),
                        'max_routers' => $resp->json('max_routers'),
                        'expires_at' => $resp->json('expires_at'),
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $resp->json('message', 'Lisensi tidak valid atau ditolak oleh server pusat NODERA.'),
                        'license_key' => $key,
                    ];
                }
            } catch (\Throwable $e) {
                return [
                    'success' => true,
                    'message' => "Nomor lisensi {$key} berhasil disimpan.",
                    'license_key' => $key,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Nomor lisensi berhasil diperbarui.',
            'license_key' => $key,
        ];
    }


    /**
     * Check if a new version is available
     */
    public function checkForUpdates(): array
    {
        $local = $this->getLocalVersion();
        $currentVersion = $local['version'] ?? '2.6.0';

        $licenseKey = env('NODERA_LICENSE_KEY');
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        // 1. If License Key is present (Standalone or Registered Installation), check Central Update Server
        if (!empty($licenseKey)) {
            try {
                $resp = Http::timeout(6)->asJson()->get("{$licenseServer}/api/v1/license/update-check", [
                    'license_key' => $licenseKey,
                    'current_version' => $currentVersion,
                ]);

                if ($resp->successful() && $resp->json('valid')) {
                    return [
                        'current_version' => $currentVersion,
                        'latest_version' => $resp->json('latest_version', $currentVersion),
                        'update_available' => (bool) $resp->json('update_available', false),
                        'codename' => $resp->json('codename', 'NODERA Enterprise ISP Edition'),
                        'release_date' => $resp->json('release_date', date('Y-m-d')),
                        'changelog' => $resp->json('changelog', []),
                        'image_tag' => $resp->json('image_tag', 'fitratan/nodera-billing:standalone-latest'),
                        'checked_at' => now()->translatedFormat('d F Y H:i:s'),
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug("UpdateCheck: Central API check error: " . $e->getMessage());
            }
        }

        // 2. Fallback to Git check if repository exists
        $hasGit = is_dir(base_path('.git'));
        $updateAvailable = false;
        $latestVersion = $currentVersion;
        $remoteChangelog = [];

        if ($hasGit) {
            try {
                $rawBranch = trim((string) @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')) ?: 'master';
                $branch = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $rawBranch) ?: 'master';
                @shell_exec("git fetch origin " . escapeshellarg($branch) . " 2>&1");
                $behindCount = (int) trim((string) @shell_exec("git rev-list --count HEAD..origin/" . escapeshellarg($branch) . " 2>/dev/null"));

                if ($behindCount > 0) {
                    $updateAvailable = true;
                    $vParts = explode('.', $currentVersion);
                    if (count($vParts) === 3) {
                        $vParts[2] = (int) $vParts[2] + 1;
                        $latestVersion = implode('.', $vParts);
                    } else {
                        $latestVersion = $currentVersion . '.1';
                    }
                    $gitLog = (string) @shell_exec("git log HEAD..origin/" . escapeshellarg($branch) . " --oneline -n 5 2>/dev/null");
                    $remoteChangelog = array_filter(explode("\n", trim($gitLog)));
                }
            } catch (\Throwable $e) {
                Log::debug("UpdateCheck: Git check failed: " . $e->getMessage());
            }
        }

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
            'codename' => $local['codename'] ?? 'NODERA Enterprise ISP Edition',
            'release_date' => $local['release_date'] ?? date('Y-m-d'),
            'changelog' => !empty($remoteChangelog) ? array_values($remoteChangelog) : ($local['changelog'] ?? []),
            'checked_at' => now()->translatedFormat('d F Y H:i:s'),
        ];
    }

    /**
     * Execute 1-Click System Update (From Admin Web Dashboard)
     */
    public function performUpdate(): array
    {
        $logs = [];
        $startTime = microtime(true);

        try {
            $logs[] = "Memulai proses pembaruan sistem otomatis...";

            // 1. Pull Latest Code via Git if repository exists
            if (is_dir(base_path('.git'))) {
                $rawBranch = trim((string) @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')) ?: 'master';
                $branch = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $rawBranch) ?: 'master';
                $logs[] = "Menarik berkas pembaruan dari branch {$branch}...";
                $gitPull = shell_exec('cd ' . escapeshellarg(base_path()) . ' && git pull origin ' . escapeshellarg($branch) . ' 2>&1');
                $logs[] = $gitPull ? trim($gitPull) : 'Sinkronisasi berkas selesai.';
            } else {
                $logs[] = 'Lingkungan Standalone terdeteksi. Memvalidasi integritas aplikasi...';
            }

            // 2. Run Database Migrations
            $logs[] = 'Menjalankan migrasi struktur database & index performa...';
            try {
                Artisan::call('migrate', ['--force' => true]);
                $logs[] = 'Migrasi database berhasil: ' . trim(Artisan::output());
            } catch (\Throwable $e) {
                $logs[] = 'Peringatan migrasi: ' . $e->getMessage();
            }

            // 3. Restart queue workers
            try {
                Artisan::call('queue:restart');
                $logs[] = 'Background queue worker berhasil direstart.';
            } catch (\Throwable $e) {
                // Ignore
            }

            // 4. Clear & Optimize application cache
            $logs[] = 'Membersihkan dan mengoptimalkan cache sistem (config, route, view)...';
            Artisan::call('optimize:clear');
            try {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
                $logs[] = 'Cache performa berhasil dioptimalkan.';
            } catch (\Throwable $e) {
                $logs[] = 'Optimasi cache: ' . $e->getMessage();
            }

            // 5. Update local version file
            $local = $this->getLocalVersion();
            $checkInfo = $this->checkForUpdates();
            $newVer = $checkInfo['latest_version'] ?? $local['version'];

            $local['version'] = $newVer;
            $local['last_updated_at'] = now()->toDateTimeString();
            File::put($this->versionFile, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $duration = round(microtime(true) - $startTime, 2);
            $logs[] = "🎉 Pembaruan sistem berhasil selesai dalam {$duration} detik!";

            return [
                'success' => true,
                'message' => "Sistem berhasil diperbarui ke versi {$newVer}!",
                'version' => $newVer,
                'logs' => $logs,
                'duration' => "{$duration}s",
            ];
        } catch (\Throwable $e) {
            Log::error("SystemUpdate: Failed to perform update: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal memperbarui sistem: ' . $e->getMessage(),
                'logs' => $logs,
            ];
        }
    }
}
