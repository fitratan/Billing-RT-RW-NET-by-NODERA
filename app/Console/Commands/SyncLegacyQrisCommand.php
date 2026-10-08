<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Services\QrisDynamicService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncLegacyQrisCommand extends Command
{
    protected $signature = 'qris:sync-legacy {--force : Re-scan even if raw string exists}';
    protected $description = 'Scan all existing QRIS configurations and auto-detect barcode EMVCo payload strings and merchant metadata';

    public function handle(): int
    {
        $this->info('Scanning all manual QRIS configurations across tenants & superadmin...');

        $gateways = PaymentGateway::withoutGlobalScopes()
            ->where('gateway', 'manual')
            ->get();

        if ($gateways->isEmpty()) {
            $this->warn('Tidak ada konfigurasi Payment Gateway manual yang ditemukan.');
            return 0;
        }

        $rows = [];
        $updatedCount = 0;

        foreach ($gateways as $gw) {
            $config = $gw->config_json ?? [];
            $tenantLabel = $gw->tenant_id ? "Tenant #{$gw->tenant_id}" : "Superadmin (Global)";

            // 1. Always purge huge base64 strings to prevent MySQL TEXT (64KB) truncation
            $hadBase64 = !empty($config['qris_image_base64']);
            unset($config['qris_image_base64']);

            // 2. Ensure immutable webhook secret exists
            if (empty($config['webhook_secret'])) {
                $config['webhook_secret'] = 'sec_' . Str::random(32);
            }

            $rawString = $config['qris_raw_string'] ?? null;
            if ($rawString) {
                $rawString = QrisDynamicService::sanitizePayload($rawString);
                $config['qris_raw_string'] = $rawString;
            }

            $hasImage = !empty($config['qris_image_path']);

            // 3. If image path is missing or forced, scan physical files in uploads/qris
            if (empty($config['qris_image_path'])) {
                $scanDirs = [
                    public_path('uploads/qris'),
                    base_path('uploads/qris'),
                    storage_path('app/public/qris'),
                    public_path('storage/qris'),
                ];
                foreach ($scanDirs as $dir) {
                    if (is_dir($dir)) {
                        $files = glob($dir . '/*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE);
                        if (!empty($files)) {
                            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
                            $config['qris_image_path'] = 'uploads/qris/' . basename($files[0]);
                            $hasImage = true;
                            break;
                        }
                    }
                }
            }

            // 4. Auto-decode if missing or forced
            if (($this->option('force') || empty($rawString) || !str_starts_with($rawString, '000201')) && $hasImage) {
                $detected = QrisDynamicService::resolveAndDecodeFromConfig($config);
                if ($detected) {
                    $sanitized = QrisDynamicService::sanitizePayload($detected);
                    if (str_starts_with($sanitized, '000201')) {
                        $config['qris_raw_string'] = $sanitized;
                        $rawString = $sanitized;
                        $info = QrisDynamicService::extractMerchantInfo($sanitized);
                        if (!empty($info['merchant_name'])) {
                            $config['merchant_name'] = $info['merchant_name'];
                        }
                        if (!empty($info['merchant_city'])) {
                            $config['merchant_city'] = $info['merchant_city'];
                        }
                        $config['enable_dynamic_qris'] = true;
                        $updatedCount++;
                    }
                }
            }

            // Save cleaned and verified config
            $gw->config_json = $config;
            $gw->saveQuietly();

            $merchant = $config['merchant_name'] ?? '-';
            $status = (!empty($config['qris_raw_string']) && str_starts_with($config['qris_raw_string'], '000201'))
                ? '<info>TERDETEKSI (OK)</info>'
                : '<comment>BELUM TERDETEKSI</comment>';

            $rows[] = [
                $tenantLabel,
                $merchant,
                $config['qris_image_path'] ?? '-',
                $status,
                substr($config['webhook_secret'] ?? '', 0, 16) . '...',
            ];
        }

        $this->table(['Scope / Tenant', 'Merchant Name', 'Image Path', 'Barcode EMVCo', 'Secret Token'], $rows);
        $this->info("Pemeriksaan selesai. {$updatedCount} gateway diperbarui / barcode berhasil dideteksi.");

        return 0;
    }
}
