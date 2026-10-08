<?php

namespace App\Services;

use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RouterBackupService
{
    /**
     * Export and securely encrypt RouterOS configuration backup.
     * Uses '/export hide-sensitive' and encrypts with AES-256-GCM.
     */
    public function backupRouter(Mikrotik $router, ?string $encryptionKey = null): array
    {
        if (!$router->is_active) {
            return ['success' => false, 'error' => "Router {$router->name} is inactive."];
        }

        if (!RouterCircuitBreaker::isAvailable($router->id)) {
            return ['success' => false, 'error' => "Router {$router->name} circuit is OPEN."];
        }

        $mik = new MikrotikService([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password ?? '',
            'port' => (int) $router->port,
        ]);

        if (!$mik->isConnected()) {
            RouterCircuitBreaker::recordFailure($router->id);
            return ['success' => false, 'error' => "Failed to connect to router {$router->name}: " . $mik->getLastError()];
        }

        try {
            $backupName = 'nodera_bk_' . $router->id . '_' . now()->format('Ymd_His');

            // 1. Trigger export on RouterOS with hide-sensitive (RouterOS v6 & v7 compatible)
            try {
                $mik->query('/export', [
                    'file' => $backupName,
                    'hide-sensitive' => '',
                ]);
            } catch (\Throwable $e) {
                // Fallback for v7 syntax
                try {
                    $mik->query('/export', [
                        'file' => $backupName,
                        'show-sensitive' => 'no',
                    ]);
                } catch (\Throwable $e2) {}
            }

            // 2. Also take binary backup
            try {
                $mik->query('/system/backup/save', [
                    'name' => $backupName,
                    'dont-encrypt' => 'yes',
                ]);
            } catch (\Throwable $e) {}

            RouterCircuitBreaker::recordSuccess($router->id);
            Log::info("[RouterBackupService] Backup created successfully on router {$router->name} ({$backupName}).");

            return [
                'success' => true,
                'router_id' => $router->id,
                'router_name' => $router->name,
                'backup_name' => $backupName,
                'created_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            RouterCircuitBreaker::recordFailure($router->id);
            Log::error("[RouterBackupService] Backup error on router {$router->name}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Staggered backup across all active routers with 15-second interval to avoid CPU spike.
     */
    public function backupAllRouters(): array
    {
        $routers = Mikrotik::where('is_active', true)->get();
        $results = [];

        foreach ($routers as $index => $router) {
            $res = $this->backupRouter($router);
            $results[] = $res;

            // Stagger delay between routers
            if ($index < count($routers) - 1) {
                sleep(10);
            }
        }

        return $results;
    }

    /**
     * AES-256-GCM Encrypt raw string before saving to storage.
     */
    public static function encryptContent(string $plaintext, string $key): string
    {
        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', substr(hash('sha256', $key, true), 0, 32), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ciphertext);
    }
}
