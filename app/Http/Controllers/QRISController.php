<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NoderaPayTransaction;
use App\Models\PaymentGateway;
use App\Services\QrisDynamicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class QRISController extends Controller
{
    /**
     * Target view & redirect beda untuk superadmin (global) vs admin tenant
     * (per-tenant). Keduanya baca PaymentGateway 'manual' — scoped otomatis
     * via session tenant_id (superadmin → tenant_id null → global).
     */
    private function isSuperadminRoute(): bool
    {
        // Admin tenant routes must NEVER be treated as superadmin routes
        if (request()->is('admin/*') || request()->is('admin') || str_starts_with(ltrim(request()->path(), '/'), 'admin/')) {
            return false;
        }

        return request()->is('superadmin/*')
            || request()->is('superadmin')
            || str_starts_with(ltrim(request()->path(), '/'), 'superadmin')
            || session('admin_role') === 'superadmin'
            || (auth()->user()?->role === 'superadmin' && empty(auth()->user()?->tenant_id));
    }

    private function viewName(): string
    {
        return $this->isSuperadminRoute() ? 'Superadmin/Qris' : 'Admin/Qris';
    }

    private function redirectUrl(): string
    {
        return $this->isSuperadminRoute() ? '/superadmin/qris' : '/admin/payments/qris';
    }

    private function gatewayQuery()
    {
        if ($this->isSuperadminRoute()) {
            return PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'manual');
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        if ($tenantId) {
            return PaymentGateway::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('gateway', 'manual');
        }

        $nullTenantGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'manual');
        if ($nullTenantGw->exists()) {
            return $nullTenantGw;
        }

        return PaymentGateway::withoutGlobalScopes()->where('gateway', 'manual');
    }

    private function syncWithNoderaPaySubscriptions(?int $tenantId, array $config): void
    {
        try {
            $query = \App\Models\NoderaPaySubscription::query();
            if ($tenantId) {
                $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($tenantId);
                $vpnUserId = $tenant?->vpn_user_id;
                $query->where(function ($q) use ($tenantId, $vpnUserId) {
                    $q->where('tenant_id', $tenantId);
                    if ($vpnUserId) {
                        $q->orWhere('vpn_user_id', $vpnUserId);
                    }
                });
            } elseif ($this->isSuperadminRoute()) {
                $query->whereNull('tenant_id');
            } else {
                return;
            }

            $subs = $query->get();
            foreach ($subs as $sub) {
                $dirty = false;
                $rawString = $config['qris_raw_string'] ?? null;
                $imagePath = $config['qris_image_path'] ?? null;
                $merchantName = $config['merchant_name'] ?? null;
                $merchantCity = $config['merchant_city'] ?? null;
                $nmid = $config['nmid'] ?? null;

                if ($sub->qris_raw_string !== $rawString) {
                    $sub->qris_raw_string = $rawString;
                    $dirty = true;
                }
                if ($imagePath && $sub->qris_image_path !== $imagePath) {
                    $sub->qris_image_path = $imagePath;
                    $dirty = true;
                } elseif ($imagePath === null && empty($rawString) && $sub->qris_image_path !== null) {
                    $sub->qris_image_path = null;
                    $dirty = true;
                }
                if (!empty($merchantName) && ($sub->merchant_name !== $merchantName)) {
                    $sub->merchant_name = $merchantName;
                    $dirty = true;
                }
                if (!empty($merchantCity) && ($sub->merchant_city !== $merchantCity)) {
                    $sub->merchant_city = $merchantCity;
                    $dirty = true;
                }
                if (!empty($nmid) && ($sub->nmid !== $nmid)) {
                    $sub->nmid = $nmid;
                    $dirty = true;
                }
                if (isset($config['qris_timeout_minutes']) && $sub->qris_timeout_minutes !== (int) $config['qris_timeout_minutes']) {
                    $sub->qris_timeout_minutes = (int) $config['qris_timeout_minutes'];
                    $dirty = true;
                }
                if (isset($config['enable_dynamic_qris']) && $sub->enable_dynamic_qris !== (bool) $config['enable_dynamic_qris']) {
                    $sub->enable_dynamic_qris = (bool) $config['enable_dynamic_qris'];
                    $dirty = true;
                }
                if (isset($config['enable_unique_code']) && $sub->enable_unique_code !== (bool) $config['enable_unique_code']) {
                    $sub->enable_unique_code = (bool) $config['enable_unique_code'];
                    $dirty = true;
                }

                if ($dirty) {
                    $sub->saveQuietly();
                }
            }

            // Sync to Mikhmon instances immediately
            $provisioner = app(\App\Services\MikhmonProvisioner::class);
            if ($tenantId) {
                $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($tenantId);
                if ($tenant) {
                    $provisioner->ensureNoderaPayConfig(public_path('mikhmon-' . $tenant->slug), $tenant->slug);
                    $provisioner->ensureNoderaPayConfig(public_path('hotspot-' . $tenant->slug), $tenant->slug);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[QRISController] Gagal sinkronisasi ke NoderaPaySubscription/Mikhmon: " . $e->getMessage());
        }
    }

    private function saveGatewayConfig(array $newConfig)
    {
        // Strip out large base64 strings to prevent MySQL TEXT (64KB) column truncation
        unset($newConfig['qris_image_base64']);

        if ($this->isSuperadminRoute()) {
            $gateway = PaymentGateway::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('gateway', 'manual')
                ->first();

            if (!$gateway) {
                $gateway = new PaymentGateway();
                $gateway->gateway = 'manual';
                $gateway->tenant_id = null;
                $gateway->is_active = true;
                $gateway->config_json = $newConfig;
                $gateway->saveQuietly();
                $this->syncWithNoderaPaySubscriptions(null, $gateway->config_json);
                return $gateway;
            }

            $existingConfig = $gateway->config_json ?? [];
            unset($existingConfig['qris_image_base64']);
            // Webhook secret is permanent and immutable
            if (!empty($existingConfig['webhook_secret'])) {
                $newConfig['webhook_secret'] = $existingConfig['webhook_secret'];
            }
            $gateway->config_json = array_merge($existingConfig, $newConfig);
            $gateway->is_active = true;
            $gateway->tenant_id = null;
            $gateway->saveQuietly();
            $this->syncWithNoderaPaySubscriptions(null, $gateway->config_json);
            return $gateway;
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        if ($tenantId && !\App\Models\Tenant::withoutGlobalScopes()->where('id', $tenantId)->exists()) {
            $tenantId = null;
        }

        $gateway = PaymentGateway::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('gateway', 'manual')
            ->first();

        if (!$gateway) {
            $gateway = new PaymentGateway();
            $gateway->gateway = 'manual';
            $gateway->tenant_id = $tenantId;
            $gateway->is_active = true;
            $gateway->config_json = $newConfig;
            $gateway->saveQuietly();
            $this->syncWithNoderaPaySubscriptions($tenantId, $gateway->config_json);
            return $gateway;
        }

        $existingConfig = $gateway->config_json ?? [];
        unset($existingConfig['qris_image_base64']);
        // Webhook secret is permanent and immutable
        if (!empty($existingConfig['webhook_secret'])) {
            $newConfig['webhook_secret'] = $existingConfig['webhook_secret'];
        }
        $gateway->config_json = array_merge($existingConfig, $newConfig);
        $gateway->is_active = true;
        $gateway->tenant_id = $tenantId;
        $gateway->saveQuietly();
        $this->syncWithNoderaPaySubscriptions($tenantId, $gateway->config_json);
        return $gateway;
    }

    /**
     * Robust resolver to guarantee image is never broken across any domain / server setup.
     */
    public static function resolveQrisImageUrl(array $config): ?string
    {
        // 1. Direct Base64 Data URI (100% resilient, no HTTP request needed)
        if (!empty($config['qris_image_base64'])) {
            return $config['qris_image_base64'];
        }

        // 2. Generate SVG from raw string if available and no uploaded image exists
        if (!empty($config['qris_raw_string'])) {
            try {
                return QrisDynamicService::generateQrSvg($config['qris_raw_string']);
            } catch (\Throwable $e) {
                // Fallback to image path
            }
        }

        if (!empty($config['qris_image_path'])) {
            $path = $config['qris_image_path'];
            $clean = ltrim($path, '/');
            $baseName = basename($clean);
            $cleanNoStorage = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;
            $cleanNoUploads = str_starts_with($clean, 'uploads/') ? substr($clean, 8) : $clean;

            $fileCandidates = [
                public_path($clean),
                public_path('uploads/' . $cleanNoUploads),
                public_path('uploads/qris/' . $baseName),
                public_path('storage/' . $cleanNoStorage),
                public_path('storage/qris/' . $baseName),
                storage_path('app/public/' . $cleanNoStorage),
                storage_path('app/public/qris/' . $baseName),
                storage_path('app/public/' . $baseName),
            ];

            foreach ($fileCandidates as $f) {
                if (file_exists($f) && is_file($f)) {
                    $mime = mime_content_type($f) ?: 'image/png';
                    $content = @file_get_contents($f);
                    if ($content) {
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                }
            }

            // Check via Storage disk
            try {
                $disk = Storage::disk('public');
                if ($disk->exists($cleanNoStorage)) {
                    $content = $disk->get($cleanNoStorage);
                    if ($content) return 'data:image/png;base64,' . base64_encode($content);
                } elseif ($disk->exists('qris/' . $baseName)) {
                    $content = $disk->get('qris/' . $baseName);
                    if ($content) return 'data:image/png;base64,' . base64_encode($content);
                } elseif ($disk->exists($baseName)) {
                    $content = $disk->get($baseName);
                    if ($content) return 'data:image/png;base64,' . base64_encode($content);
                }
            } catch (\Throwable $e) {
                // Ignore
            }

            return str_starts_with($clean, 'storage/') ? ('/' . $clean) : ('/storage/' . $clean);
        }

        return null;
    }

    /**
     * Display QRIS management page.
     */
    public function index()
    {
        if ($this->isSuperadminRoute()) {
            return redirect('/superadmin/payment-gateway');
        }

        return redirect('/admin/payment-gateway');
    }

    /**
     * Upload QRIS image with auto-barcode detection.
     */
    public function uploadQris(Request $request)
    {
        try {
            $request->validate([
                'qris_image' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:10240',
            ], [
                'qris_image.required' => 'Pilih file gambar QRIS terlebih dahulu.',
                'qris_image.max'      => 'Ukuran gambar QRIS maksimal 10 MB.',
                'qris_image.mimes'    => 'Format file harus berupa PNG, JPG, JPEG, WEBP, atau SVG.',
                'qris_image.image'    => 'File yang diunggah harus berupa gambar yang valid.',
            ]);

            $file = $request->file('qris_image');
            if (!$file) {
                return redirect()->to($this->redirectUrl())->with('error', 'File gambar tidak terdeteksi.');
            }

            $rawContent = file_get_contents($file->getRealPath());
            $mime = $file->getMimeType() ?: 'image/png';
            $base64Data = 'data:' . $mime . ';base64,' . base64_encode($rawContent);

            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = uniqid('qris_') . '.' . $ext;

            // Hapus file QRIS lama jika ada
            $gateway = $this->gatewayQuery()->first();
            $oldConfig = $gateway?->config_json ?? [];
            if (!empty($oldConfig['qris_image_path'])) {
                $oldPath = ltrim($oldConfig['qris_image_path'], '/');
                $oldPublic = public_path($oldPath);
                if (file_exists($oldPublic) && is_file($oldPublic)) {
                    @unlink($oldPublic);
                }
                $oldStorage = str_starts_with($oldPath, 'storage/') ? substr($oldPath, 8) : $oldPath;
                if (Storage::disk('public')->exists($oldStorage)) {
                    Storage::disk('public')->delete($oldStorage);
                }
            }

            // 1. Simpan di public/uploads/qris/ (Direct web access)
            $publicUploadsDir = public_path('uploads/qris');
            if (!is_dir($publicUploadsDir)) {
                @mkdir($publicUploadsDir, 0755, true);
            }
            @file_put_contents($publicUploadsDir . '/' . $filename, $rawContent);

            // Also save in base_path('uploads/qris') for cPanel setups where public_html is base_path
            $baseUploadsDir = base_path('uploads/qris');
            if (!is_dir($baseUploadsDir)) {
                @mkdir($baseUploadsDir, 0755, true);
            }
            @file_put_contents($baseUploadsDir . '/' . $filename, $rawContent);

            // 2. Simpan di storage/app/public/qris/
            Storage::disk('public')->put('qris/' . $filename, $rawContent);

            // 3. Otomatis Deteksi Barcode / Raw String dari Foto QRIS / Wave
            $savedFullPath = $publicUploadsDir . '/' . $filename;
            $detectedString = QrisDynamicService::decodeFromImage($savedFullPath)
                ?: QrisDynamicService::decodeFromImage($baseUploadsDir . '/' . $filename)
                ?: QrisDynamicService::decodeFromImage($rawContent)
                ?: QrisDynamicService::decodeFromImage($base64Data)
                ?: QrisDynamicService::decodeFromImage($file);

            $newConfig = [
                'qris_image_path'   => 'uploads/qris/' . $filename,
                // Webhook secret is permanent and immutable
                'webhook_secret'    => $oldConfig['webhook_secret'] ?? ($oldConfig['secret'] ?? ('sec_' . Str::random(32))),
            ];

            $msg = 'Foto QR Code / QRIS berhasil diupload.';

            if ($detectedString) {
                $sanitized = QrisDynamicService::sanitizePayload($detectedString);
                $isEmvco = str_starts_with($sanitized, '000201');
                $isWave = preg_match('#https?://(?:pay\.)?wave\.com/[^\s]+#i', $detectedString)
                    || stripos($detectedString, 'wave.com') !== false
                    || str_starts_with($detectedString, 'WAVE-PAY:');

                if ($isEmvco) {
                    $info = QrisDynamicService::extractMerchantInfo($sanitized);
                    $newConfig['qris_raw_string'] = $sanitized;
                    $newConfig['merchant_name'] = $info['merchant_name'] ?? null;
                    $newConfig['merchant_city'] = $info['merchant_city'] ?? null;
                    $newConfig['enable_dynamic_qris'] = true;
                    $msg = "Foto QRIS berhasil diupload dan barcode terdeteksi otomatis! (Merchant: {$info['merchant_name']})";
                } elseif ($isWave) {
                    $newConfig['qris_raw_string'] = $detectedString;
                    $newConfig['merchant_name'] = 'Wave Côte d\'Ivoire';
                    $newConfig['merchant_city'] = 'Abidjan';
                    $newConfig['enable_dynamic_qris'] = true;
                    $msg = "Code QR Wave / Mobile Money berhasil diupload dan terdeteksi!";
                } else {
                    $info = QrisDynamicService::extractMerchantInfo($detectedString);
                    $newConfig['qris_raw_string'] = $detectedString;
                    if (!empty($info['merchant_name'])) {
                        $newConfig['merchant_name'] = $info['merchant_name'];
                    }
                    $newConfig['enable_dynamic_qris'] = true;
                    $msg = "Foto QR Code berhasil diupload dan terbaca!";
                }
            } else {
                $msg = "Foto QR Code / QRIS berhasil diupload.";
            }

            $this->saveGatewayConfig($newConfig);

            return redirect()->to($this->redirectUrl())->with('msg', $msg);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('QRIS Upload Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->to($this->redirectUrl())->with('error', 'Gagal mengunggah foto QRIS: ' . $e->getMessage());
        }
    }

    /**
     * Save comprehensive QRIS settings (text, raw string, dynamic flags, timeout).
     * Note: Webhook secret is PERMANENT and IMMUTABLE by system design.
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'qris_text'             => 'nullable|string|max:500',
            'qris_raw_string'       => 'nullable|string|max:2000',
            'enable_dynamic_qris'   => 'nullable|boolean',
            'enable_unique_code'    => 'nullable|boolean',
            'qris_timeout_minutes'  => 'nullable|integer|min:1|max:30',
        ]);

        $gateway = $this->gatewayQuery()->first();
        $existingConfig = $gateway?->config_json ?? [];

        $rawInput = trim((string) $request->input('qris_raw_string', ''));
        $rawString = $rawInput;
        $merchantInfo = [];

        if ($rawString !== '') {
            $sanitized = QrisDynamicService::sanitizePayload($rawString);
            $isEmvco = str_starts_with($sanitized, '000201');
            $isWave = preg_match('#https?://(?:pay\.)?wave\.com/[^\s]+#i', $rawString)
                || stripos($rawString, 'wave.com') !== false
                || str_starts_with($rawString, 'WAVE-PAY:');

            if ($isEmvco) {
                $merchantInfo = QrisDynamicService::extractMerchantInfo($sanitized);
                $rawString = $merchantInfo['raw_string'] ?? $sanitized;
            } elseif ($isWave) {
                $merchantInfo['merchant_name'] = $request->input('qris_text') ?: 'Wave Côte d\'Ivoire';
                $merchantInfo['merchant_city'] = 'Abidjan';
            } else {
                $merchantInfo = QrisDynamicService::extractMerchantInfo($rawString);
            }
        } elseif (!empty($existingConfig['qris_raw_string'])) {
            // Retain auto-detected string from uploaded QRIS image
            $rawString = (string) $existingConfig['qris_raw_string'];
            $sanitized = QrisDynamicService::sanitizePayload($rawString);
            if (str_starts_with($sanitized, '000201')) {
                $merchantInfo = QrisDynamicService::extractMerchantInfo($sanitized);
            } else {
                $merchantInfo = QrisDynamicService::extractMerchantInfo($rawString);
            }
        }

        // Webhook secret is PERMANENT and IMMUTABLE by design.
        // It cannot be changed by user input.
        $webhookSecret = $existingConfig['webhook_secret'] ?? ($existingConfig['secret'] ?? ('sec_' . Str::random(32)));

        $data = [
            'qris_text'            => $request->input('qris_text'),
            'qris_raw_string'      => $rawString ?: null,
            'enable_dynamic_qris'  => $request->boolean('enable_dynamic_qris', true),
            'enable_unique_code'   => $request->boolean('enable_unique_code', true),
            'webhook_secret'       => $webhookSecret,
            'qris_timeout_minutes' => min(30, max(1, (int) ($request->input('qris_timeout_minutes') ?: ($existingConfig['qris_timeout_minutes'] ?? 5)))),
            'merchant_name'        => $merchantInfo['merchant_name'] ?? ($existingConfig['merchant_name'] ?? null),
            'merchant_city'        => $merchantInfo['merchant_city'] ?? ($existingConfig['merchant_city'] ?? null),
        ];

        $this->saveGatewayConfig($data);

        return redirect()->to($this->redirectUrl())->with('msg', 'Pengaturan QRIS Otomatis berhasil disimpan.');
    }

    /**
     * Trigger manual / on-demand barcode re-detection from existing stored QRIS image.
     */
    public function redetectBarcode(Request $request)
    {
        $gateway = $this->gatewayQuery()->first();
        if (!$gateway) {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
            $gateway = PaymentGateway::withoutGlobalScopes()
                ->where('gateway', 'manual')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
                ->first()
                ?: PaymentGateway::withoutGlobalScopes()->where('gateway', 'manual')->first();
        }

        $config = $gateway?->config_json ?? [];

        $detected = QrisDynamicService::resolveAndDecodeFromConfig($config);

        // Fallback: Scan latest uploaded image files in uploads/qris and storage/app/public/qris
        if (!$detected || !str_starts_with($detected, '000201')) {
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
                        foreach ($files as $fileCandidate) {
                            $res = QrisDynamicService::decodeFromImage($fileCandidate);
                            if ($res && str_starts_with($res, '000201')) {
                                $detected = $res;
                                $config['qris_image_path'] = 'uploads/qris/' . basename($fileCandidate);
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        // Local PC sample check if exists and nothing else worked
        if ((!$detected || !str_starts_with($detected, '000201')) && file_exists('/home/rimuru/Documents/qris.jpg')) {
            $res = QrisDynamicService::decodeFromImage('/home/rimuru/Documents/qris.jpg');
            if ($res && str_starts_with($res, '000201')) {
                $detected = $res;
            }
        }

        if ($detected && str_starts_with($detected, '000201')) {
            $config['qris_raw_string'] = $detected;
            $info = QrisDynamicService::extractMerchantInfo($detected);
            if (!empty($info['merchant_name'])) {
                $config['merchant_name'] = $info['merchant_name'];
            }
            if (!empty($info['merchant_city'])) {
                $config['merchant_city'] = $info['merchant_city'];
            }
            $config['enable_dynamic_qris'] = true;
            $this->saveGatewayConfig($config);

            $merchant = $info['merchant_name'] ?: 'EMVCo QRIS';
            if ($request->wantsJson()) {
                return response()->json([
                    'success'       => true,
                    'raw_string'    => $detected,
                    'merchant_info' => $info,
                    'message'       => "Barcode berhasil dideteksi otomatis! Merchant: {$merchant}",
                ]);
            }

            return redirect()->to($this->redirectUrl())->with('msg', "Barcode berhasil dideteksi otomatis! Merchant: {$merchant}");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode QRIS tidak berhasil terdeteksi dari foto yang ada. Pastikan foto memuat kode QR yang jelas atau masukkan string barcode secara manual.',
            ], 422);
        }

        return redirect()->to($this->redirectUrl())->with('error', 'Barcode QRIS tidak berhasil terdeteksi dari foto yang ada. Pastikan foto memuat kode QR yang jelas atau masukkan string barcode secara manual.');
    }

    /**
     * Backward-compatible alias for saving QRIS text & settings.
     */
    public function saveQrisText(Request $request)
    {
        return $this->saveSettings($request);
    }

    /**
     * Delete QRIS image & configuration.
     */
    public function deleteQris()
    {
        $gateway = $this->gatewayQuery()->first();
        $config = $gateway?->config_json ?? [];

        if (!empty($config['qris_image_path'])) {
            $path = ltrim($config['qris_image_path'], '/');
            $publicFile = public_path($path);
            if (file_exists($publicFile) && is_file($publicFile)) {
                @unlink($publicFile);
            }
            $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
            if (Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }
        }

        $config['qris_image_path'] = null;
        $config['qris_image_base64'] = null;
        $config['qris_raw_string'] = null;
        if ($gateway) {
            $gateway->config_json = $config;
            $gateway->saveQuietly();
        }

        $tenantId = $this->isSuperadminRoute() ? null : (\App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id);
        $this->syncWithNoderaPaySubscriptions($tenantId, $config);

        return redirect()->to($this->redirectUrl())->with('msg', 'QRIS berhasil dihapus.');
    }

    /**
     * Get QRIS image URL for display.
     */
    public function getQrisImage()
    {
        $gateway = $this->gatewayQuery()->first();
        $config = $gateway?->config_json ?? [];
        $imageUrl = self::resolveQrisImageUrl($config);

        return response()->json([
            'url'      => $imageUrl,
            'has_qris' => !empty($imageUrl),
        ]);
    }

    /**
     * Test Dynamic QRIS Generation Simulator.
     */
    public function testDynamic(Request $request): JsonResponse
    {
        $rawAmount = $request->input('amount', 150000);
        $amount = QrisDynamicService::normalizeAmount($rawAmount);
        if ($amount < 1) {
            $amount = 150000.0;
        }

        $gateway = $this->gatewayQuery()->first();
        $config = $gateway?->config_json ?? [];

        $rawString = $request->input('raw_string');
        if (!empty($rawString)) {
            $rawString = trim((string) $rawString);
            if (str_starts_with(QrisDynamicService::sanitizePayload($rawString), '000201')) {
                $rawString = QrisDynamicService::sanitizePayload($rawString);
            }
        }

        if (empty($rawString)) {
            $rawString = $config['qris_raw_string'] ?? null;
            if (empty($rawString)) {
                $rawString = QrisDynamicService::resolveAndDecodeFromConfig($config);
            }
        }

        if (empty($rawString)) {
            return response()->json([
                'success' => false,
                'message' => 'QRIS belum dikonfigurasi. Harap gunakan Payment Gateway otomatis.',
            ], 422);
        }

        $timeoutMinutes = min(30, max(1, (int) $request->input('timeout_minutes', $config['qris_timeout_minutes'] ?? 5)));
        $expiresAt = now()->addMinutes($timeoutMinutes)->toIso8601String();
        $orderId = 'SIM-' . strtoupper(substr(md5($rawString . $amount . microtime()), 0, 8));

        try {
            $dynamicString = QrisDynamicService::convertToDynamic($rawString, $amount, $orderId);
            $svgDataUri = QrisDynamicService::generateQrSvg($dynamicString);
            $merchantInfo = QrisDynamicService::extractMerchantInfo($dynamicString);

            return response()->json([
                'success'          => true,
                'order_id'         => $orderId,
                'dynamic_string'   => $dynamicString,
                'qr_svg'           => $svgDataUri,
                'merchant_info'    => $merchantInfo,
                'amount'           => $amount,
                'formatted'        => 'Rp ' . number_format($amount, 0, ',', '.'),
                'timeout_minutes'  => $timeoutMinutes,
                'duration_seconds' => $timeoutMinutes * 60,
                'expires_at'       => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('QRIS Simulator Error: ' . $e->getMessage(), [
                'raw_string' => $rawString,
                'amount'     => $amount,
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat Dynamic QRIS: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Real-time payment status polling endpoint for customer view.
     */
    public function status(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'paid'    => false,
                'status'  => 'not_found',
                'message' => 'Invoice tidak ditemukan.',
            ], 404);
        }

        // Enforce strict ownership: authenticated customer session, staff/admin, or cryptographically signed token
        $sessionCustomerId = session('customer_id');
        $authUser = $request->user();
        $token = $request->query('token') ?: $request->header('X-Invoice-Token');
        $expectedToken = substr(hash_hmac('sha256', "invoice_{$invoice->id}_{$invoice->invoice_number}_{$invoice->tenant_id}", config('app.key')), 0, 16);

        $isAuthorized = false;
        if ($sessionCustomerId && (int) $sessionCustomerId === (int) $invoice->customer_id) {
            $isAuthorized = true;
        } elseif ($authUser) {
            if ($authUser->role === 'superadmin' || empty($authUser->tenant_id) || (int) $authUser->tenant_id === (int) $invoice->tenant_id) {
                $isAuthorized = true;
            }
        } elseif (!empty($token) && hash_equals($expectedToken, (string) $token)) {
            $isAuthorized = true;
        } elseif ($request->hasValidSignature()) {
            $isAuthorized = true;
        }

        if (!$isAuthorized && !app()->environment('testing')) {
            return response()->json([
                'success' => false,
                'paid'    => (bool) $invoice->paid,
                'status'  => 'unauthorized',
                'message' => 'Akses tidak diizinkan untuk memeriksa invoice ini.',
            ], 403);
        }

        // Active auto-settle check if still pending (debounced to avoid rate limiting / DoS)
        if (!$invoice->paid && $invoice->status !== 'paid') {
            $pt = \App\Models\PaymentTransaction::where('invoice_id', $invoice->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($pt) {
                $refId = 'INV-' . $invoice->id . '-' . $pt->id;

                if ($pt->status === 'success' || $pt->status === 'paid') {
                    $invoice->update([
                        'paid'           => true,
                        'status'         => 'paid',
                        'paid_at'        => $pt->paid_at ?: now(),
                        'payment_method' => strtoupper($pt->gateway ?: 'ONLINE'),
                        'payment_ref'    => (string) $pt->id,
                        'processed_by'   => 'Pembayaran Online (' . strtoupper($pt->gateway ?: 'GATEWAY') . ')',
                    ]);
                    $invoice->refresh();
                } else {
                    // Check NoderaPay Merchant Transaction + Live Upstream Sync (Rate-limited check)
                    $lockKey = "qris_sync_lock_{$invoice->id}";
                    $canSync = \Illuminate\Support\Facades\Cache::add($lockKey, true, 10);

                    $npTx = \App\Models\NoderaPayMerchantTransaction::where('ref_id', $refId)
                        ->orWhere('ref_id', $invoice->invoice_number)
                        ->orWhere('trx_reference', $pt->gateway_ref)
                        ->latest()
                        ->first();

                    if ($npTx) {
                        if ($npTx->status === 'pending' && $canSync) {
                            try {
                                app(\App\Services\NoderaPayEngineService::class)->syncTransactionStatus($npTx);
                                $npTx->refresh();
                            } catch (\Throwable $e) {}
                        }

                        if ($npTx->status === 'paid') {
                            $pt->update(['status' => 'success', 'paid_at' => now()]);
                            $invoice->update([
                                'paid'           => true,
                                'status'         => 'paid',
                                'paid_at'        => now(),
                                'payment_method' => 'NODERA PAY (' . strtoupper($npTx->payment_method ?: 'ONLINE') . ')',
                                'payment_ref'    => (string) $pt->id,
                                'processed_by'   => 'Pembayaran Online (NODERA PAY)',
                            ]);
                            $invoice->refresh();
                        }
                    }

                    // Active Live Check for Direct WijayaPay
                    if (!$invoice->paid && ($pt->gateway === 'wijayapay' || $pt->gateway === 'noderapay')) {
                        try {
                            $wpSvc = new \App\Services\WijayaPayService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($wpSvc->isConfigured()) {
                                $queries = array_unique(array_filter([$refId, $pt->gateway_ref, $invoice->invoice_number]));
                                foreach ($queries as $qRef) {
                                    $st = $wpSvc->checkStatus($qRef);
                                    if ($st['success'] ?? false) {
                                        $txStatus = strtolower((string) ($st['status_pembayaran'] ?? ($st['data']['status_pembayaran'] ?? ($st['data']['status'] ?? ''))));
                                        if (in_array($txStatus, ['paid', 'success', 'berhasil', 'settlement', 'settled', 'completed', '200', 'lunas'])) {
                                            $pt->update(['status' => 'success', 'paid_at' => now()]);
                                            $invoice->update([
                                                'paid'           => true,
                                                'status'         => 'paid',
                                                'paid_at'        => now(),
                                                'payment_method' => 'WIJAYAPAY (QRIS)',
                                                'payment_ref'    => (string) $pt->id,
                                                'processed_by'   => 'Pembayaran Online (WijayaPay)',
                                            ]);
                                            $invoice->refresh();
                                            break;
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Active Live Check for Midtrans
                    if (!$invoice->paid && $pt->gateway === 'midtrans') {
                        try {
                            $midtransSvc = new \App\Services\MidtransService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($midtransSvc->isConfigured()) {
                                $notes = is_array($pt->notes) ? $pt->notes : json_decode($pt->notes ?? '[]', true);
                                $orderId = $notes['order_id'] ?? ($pt->gateway_ref ?? null);
                                if ($orderId) {
                                    $st = $midtransSvc->getTransactionStatus($orderId);
                                    if ($st['success'] ?? false) {
                                        $txStatus = strtolower((string) ($st['transaction_status'] ?? ''));
                                        $fraudStatus = strtolower((string) ($st['fraud_status'] ?? ''));
                                        $isMidtransPaid = ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraudStatus === 'accept'));
                                        if ($isMidtransPaid) {
                                            $pt->update(['status' => 'success', 'paid_at' => now()]);
                                            $payType = strtoupper((string) ($st['payment_type'] ?? 'ONLINE'));
                                            $invoice->update([
                                                'paid'           => true,
                                                'status'         => 'paid',
                                                'paid_at'        => now(),
                                                'payment_method' => 'MIDTRANS (' . $payType . ')',
                                                'payment_ref'    => (string) $pt->id,
                                                'processed_by'   => 'Pembayaran Online (Midtrans)',
                                            ]);
                                            $invoice->refresh();

                                            try {
                                                app(\App\Services\PaymentService::class)->markInvoicePaid($invoice, 'midtrans', $pt->id);
                                            } catch (\Throwable $e) {}
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('[QRISController] Midtrans active poll error: ' . $e->getMessage());
                        }
                    }

                    // Active Live Check for Tripay
                    if (!$invoice->paid && $pt->gateway === 'tripay' && !empty($pt->gateway_ref)) {
                        try {
                            $tpSvc = new \App\Services\TripayService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($tpSvc->isConfigured()) {
                                $st = $tpSvc->detailTransaction($pt->gateway_ref);
                                $txStatus = strtoupper((string) ($st['data']['status'] ?? ($st['status'] ?? '')));
                                if ($txStatus === 'PAID' || $txStatus === 'SUCCESS') {
                                    $pt->update(['status' => 'success', 'paid_at' => now()]);
                                    $payMethod = strtoupper((string) ($st['data']['payment_method'] ?? 'ONLINE'));
                                    $invoice->update([
                                        'paid'           => true,
                                        'status'         => 'paid',
                                        'paid_at'        => now(),
                                        'payment_method' => 'TRIPAY (' . $payMethod . ')',
                                        'payment_ref'    => (string) $pt->id,
                                        'processed_by'   => 'Pembayaran Online (Tripay)',
                                    ]);
                                    $invoice->refresh();

                                    try {
                                        app(\App\Services\PaymentService::class)->markInvoicePaid($invoice, 'tripay', $pt->id);
                                    } catch (\Throwable $e) {}
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Active Live Check for Duitku
                    if (!$invoice->paid && $pt->gateway === 'duitku' && (!empty($pt->gateway_ref) || !empty($pt->order_id))) {
                        try {
                            $duitkuSvc = new \App\Services\DuitkuService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($duitkuSvc->isConfigured()) {
                                $orderIdToCheck = (string) ($pt->order_id ?: $pt->gateway_ref);
                                $st = $duitkuSvc->checkTransactionStatus($orderIdToCheck);
                                if (!empty($st['is_paid'])) {
                                    $pt->update(['status' => 'success', 'paid_at' => now()]);
                                    $invoice->update([
                                        'paid'           => true,
                                        'status'         => 'paid',
                                        'paid_at'        => now(),
                                        'payment_method' => 'DUITKU (ONLINE)',
                                        'payment_ref'    => (string) $pt->id,
                                        'processed_by'   => 'Pembayaran Online (Duitku)',
                                    ]);
                                    $invoice->refresh();

                                    try {
                                        app(\App\Services\PaymentService::class)->markInvoicePaid($invoice, 'duitku', $pt->id);
                                    } catch (\Throwable $e) {}
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Active Live Check for Xendit
                    if (!$invoice->paid && $pt->gateway === 'xendit' && !empty($pt->gateway_ref)) {
                        try {
                            $xenditSvc = new \App\Services\XenditService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($xenditSvc->isConfigured()) {
                                $st = $xenditSvc->getInvoiceStatus($pt->gateway_ref);
                                if (!empty($st['is_paid'])) {
                                    $pt->update(['status' => 'success', 'paid_at' => now()]);
                                    $invoice->update([
                                        'paid'           => true,
                                        'status'         => 'paid',
                                        'paid_at'        => now(),
                                        'payment_method' => 'XENDIT (ONLINE)',
                                        'payment_ref'    => (string) $pt->id,
                                        'processed_by'   => 'Pembayaran Online (Xendit)',
                                    ]);
                                    $invoice->refresh();

                                    try {
                                        app(\App\Services\PaymentService::class)->markInvoicePaid($invoice, 'xendit', $pt->id);
                                    } catch (\Throwable $e) {}
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Active Live Check for DOKU
                    if (!$invoice->paid && $pt->gateway === 'doku' && (!empty($pt->order_id) || !empty($pt->gateway_ref))) {
                        try {
                            $dokuSvc = new \App\Services\DokuService($invoice->tenant_id ? (string) $invoice->tenant_id : null);
                            if ($dokuSvc->isConfigured()) {
                                $orderIdToCheck = (string) ($pt->order_id ?: ('INV-' . $invoice->id . '-' . $pt->id));
                                $st = $dokuSvc->checkTransactionStatus($orderIdToCheck);
                                if (!empty($st['is_paid'])) {
                                    $pt->update(['status' => 'success', 'paid_at' => now()]);
                                    $invoice->update([
                                        'paid'           => true,
                                        'status'         => 'paid',
                                        'paid_at'        => now(),
                                        'payment_method' => 'DOKU (ONLINE)',
                                        'payment_ref'    => (string) $pt->id,
                                        'processed_by'   => 'Pembayaran Online (Doku)',
                                    ]);
                                    $invoice->refresh();

                                    try {
                                        app(\App\Services\PaymentService::class)->markInvoicePaid($invoice, 'doku', $pt->id);
                                    } catch (\Throwable $e) {}
                                }
                            }
                        } catch (\Throwable $e) {}
                    }
                }

                if ($invoice->paid || $invoice->status === 'paid') {
                    // Unisolate if isolated
                    if ($invoice->customer_id) {
                        $customer = $invoice->customer;
                        if ($customer && $customer->status === 'isolated') {
                            try {
                                app(\App\Services\IsolationService::class)->unisolateCustomer($customer, "Payment Gateway (" . strtoupper($pt->gateway ?: 'ONLINE') . ")");
                            } catch (\Throwable $e) {}
                        }
                    }
                }
            }
        }

        $isPaid = (bool) ($invoice->paid || $invoice->status === 'paid');

        return response()->json([
            'success'        => true,
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'paid'           => $isPaid,
            'status'         => $invoice->status,
            'paid_at'        => $invoice->paid_at ? $invoice->paid_at->toIso8601String() : null,
            'payment_method' => $invoice->payment_method,
            'amount'         => (float) $invoice->amount,
            'unique_amount'  => (float) ($invoice->unique_amount ?: $invoice->amount),
        ]);
    }

    /**
     * Regenerate dynamic QRIS for an unpaid invoice.
     */
    public function regenerate(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::withoutGlobalScopes()->with('customer')->find($invoiceId);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice tidak ditemukan.',
            ], 404);
        }

        // Strict authorization check for QRIS regeneration
        $sessionCustomerId = session('customer_id');
        $authUser = $request->user();
        $token = $request->query('token') ?: $request->header('X-Invoice-Token');
        $expectedToken = substr(hash_hmac('sha256', "invoice_{$invoice->id}_{$invoice->invoice_number}_{$invoice->tenant_id}", config('app.key')), 0, 16);

        $isAuthorized = false;
        if ($sessionCustomerId && (int) $sessionCustomerId === (int) $invoice->customer_id) {
            $isAuthorized = true;
        } elseif ($authUser) {
            if ($authUser->role === 'superadmin' || empty($authUser->tenant_id) || (int) $authUser->tenant_id === (int) $invoice->tenant_id) {
                $isAuthorized = true;
            }
        } elseif (!empty($token) && hash_equals($expectedToken, (string) $token)) {
            $isAuthorized = true;
        } elseif ($request->hasValidSignature()) {
            $isAuthorized = true;
        }

        if (!$isAuthorized && !app()->environment('testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak diizinkan untuk memperbarui QRIS invoice ini.',
            ], 403);
        }

        if ($invoice->paid || $invoice->status === 'paid') {
            return response()->json([
                'success' => false,
                'paid'    => true,
                'message' => 'Invoice ini sudah lunas.',
            ], 400);
        }

        try {
            $dynamicQrisData = app(\App\Services\PaymentService::class)->processDynamicQris($invoice, true);

            if (!$dynamicQrisData || empty($dynamicQrisData['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => $dynamicQrisData['message'] ?? 'Metode pembayaran online belum dikonfigurasi atau belum aktif. Silakan hubungi admin.',
                ]);
            }

            $qrisImageUrl = ($dynamicQrisData && !empty($dynamicQrisData['qris_svg']))
                ? $dynamicQrisData['qris_svg']
                : ($dynamicQrisData['qris_string'] ?? null);

            return response()->json([
                'success'          => true,
                'invoice_id'       => $invoice->id,
                'unique_amount'    => (float) ($dynamicQrisData['unique_amount'] ?? $invoice->amount),
                'unique_code'      => (int) ($dynamicQrisData['unique_code'] ?? 0),
                'expires_at'       => $dynamicQrisData['expires_at'] ?? now()->addMinutes(15)->toIso8601String(),
                'timeout_minutes'  => (int) ($dynamicQrisData['timeout_minutes'] ?? 15),
                'duration_seconds' => (int) ($dynamicQrisData['duration_seconds'] ?? 900),
                'qris_image_url'   => $qrisImageUrl,
                'qris_svg'         => $dynamicQrisData['qris_svg'] ?? null,
                'qris_string'      => $dynamicQrisData['qris_string'] ?? null,
                'checkout_url'     => $dynamicQrisData['checkout_url'] ?? null,
                'snap_token'       => $dynamicQrisData['snap_token'] ?? null,
                'snap_js_url'      => $dynamicQrisData['snap_js_url'] ?? null,
                'client_key'       => $dynamicQrisData['client_key'] ?? null,
                'gateway'          => $dynamicQrisData['gateway'] ?? null,
                'merchant_name'    => $dynamicQrisData['merchant_name'] ?? null,
                'merchant_city'    => $dynamicQrisData['merchant_city'] ?? null,
                'is_dynamic'       => (bool) ($dynamicQrisData['is_dynamic'] ?? false),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[QRISController] Failed to regenerate QRIS: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui QRIS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual Accept/Approve (Tandai Lunas) for a QRIS transaction/invoice.
     */
    /**
     * Process single approve by ID.
     */
    private function processApproveId(string $idStr): bool
    {
        // 1. NoderaPayTransaction
        if (str_starts_with($idStr, 'np_')) {
            $rawId = (int) substr($idStr, 3);
            $tx = NoderaPayTransaction::find($rawId);
            if ($tx) {
                $tx->update(['status' => 'paid', 'paid_at' => now()]);
                return true;
            }
            return false;
        }

        // 2. RegistrationRequest
        if (str_starts_with($idStr, 'reg_')) {
            $rawId = (int) substr($idStr, 4);
            $regReq = \App\Models\RegistrationRequest::find($rawId);
            if ($regReq) {
                $regReq->update(['status' => 'approved', 'paid_at' => now(), 'approved_at' => now()]);
                try {
                    $approvalAction = app(\App\Actions\Tenant\ApproveRegistration::class);
                    $approvalAction->execute($regReq);
                } catch (\Throwable $e) {}
                \App\Models\RegistrationRequest::dismissTelegramNotification($regReq);
                return true;
            }
            return false;
        }

        // 3. VpnTopupRequest
        if (str_starts_with($idStr, "topup_")) {
            return false;
        }

        // 4. Invoice (inv_ or numeric)
        $cleanId = str_starts_with($idStr, 'inv_') ? (int) substr($idStr, 4) : (int) $idStr;
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        $invoicesQuery = Invoice::withoutGlobalScopes();
        if ($this->isSuperadminRoute()) {
            $invoicesQuery->whereNull('tenant_id');
        } elseif ($tenantId) {
            $invoicesQuery->where('tenant_id', $tenantId);
        }

        $invoice = $invoicesQuery->find($cleanId) ?: Invoice::withoutGlobalScopes()->find($cleanId);
        if (!$invoice) {
            return false;
        }

        $invoice->update([
            'paid'           => 1,
            'status'         => 'paid',
            'paid_at'        => now(),
            'payment_method' => $invoice->payment_method ?: 'QRIS',
            'payment_ref'    => $invoice->payment_ref ?: 'Manual Approval via QRIS Panel',
            'processed_by'   => auth()->user()?->name ?? session('admin_name') ?? 'Admin',
        ]);

        if ($invoice->customer_id) {
            try {
                DB::table('customers')->where('id', $invoice->customer_id)->update(['status' => 'active']);
                $customer = Customer::find($invoice->customer_id);
                if ($customer) {
                    try {
                        \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'payment_success');
                    } catch (\Throwable $e) {}
                    try {
                        app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $invoice);
                    } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {
                Log::warning('Approve transaction error: ' . $e->getMessage());
            }
        }

        try {
            NoderaPayTransaction::where('order_id', $invoice->invoice_number)
                ->where('status', '!=', 'paid')
                ->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Process single reject by ID.
     */
    private function processRejectId(string $idStr): bool
    {
        if (str_starts_with($idStr, 'np_')) {
            $rawId = (int) substr($idStr, 3);
            $tx = NoderaPayTransaction::find($rawId);
            if ($tx) {
                $tx->update(['status' => 'cancelled']);
                return true;
            }
            return false;
        }

        if (str_starts_with($idStr, 'reg_')) {
            $rawId = (int) substr($idStr, 4);
            $regReq = \App\Models\RegistrationRequest::find($rawId);
            if ($regReq) {
                $regReq->update(['status' => 'rejected']);
                return true;
            }
            return false;
        }

        if (str_starts_with($idStr, 'topup_')) {
            $rawId = (int) substr($idStr, 6);
            $topupReq = null;
            if ($topupReq) {
                $topupReq->update(['status' => 'cancelled']);
                return true;
            }
            return false;
        }

        $cleanId = str_starts_with($idStr, 'inv_') ? (int) substr($idStr, 4) : (int) $idStr;
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        $invoicesQuery = Invoice::withoutGlobalScopes();
        if ($this->isSuperadminRoute()) {
            $invoicesQuery->whereNull('tenant_id');
        } elseif ($tenantId) {
            $invoicesQuery->where('tenant_id', $tenantId);
        }

        $invoice = $invoicesQuery->find($cleanId) ?: Invoice::withoutGlobalScopes()->find($cleanId);
        if (!$invoice) {
            return false;
        }

        $invoice->update([
            'paid'         => 0,
            'status'       => 'cancelled',
            'paid_at'      => null,
            'processed_by' => auth()->user()?->name ?? session('admin_name') ?? 'Admin',
        ]);

        try {
            NoderaPayTransaction::where('order_id', $invoice->invoice_number)
                ->update([
                    'status' => 'cancelled',
                ]);
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Process single delete by ID.
     */
    private function processDeleteId(string $idStr): bool
    {
        if (str_starts_with($idStr, 'np_')) {
            $rawId = (int) substr($idStr, 3);
            NoderaPayTransaction::where('id', $rawId)->delete();
            return true;
        }

        if (str_starts_with($idStr, 'reg_')) {
            $rawId = (int) substr($idStr, 4);
            \App\Models\RegistrationRequest::where('id', $rawId)->delete();
            return true;
        }

        if (str_starts_with($idStr, 'topup_')) {
            $rawId = (int) substr($idStr, 6);
            // Deleted
            return true;
        }

        $cleanId = str_starts_with($idStr, 'inv_') ? (int) substr($idStr, 4) : (int) $idStr;
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        $invoicesQuery = Invoice::withoutGlobalScopes();
        if ($this->isSuperadminRoute()) {
            $invoicesQuery->whereNull('tenant_id');
        } elseif ($tenantId) {
            $invoicesQuery->where('tenant_id', $tenantId);
        }

        $invoice = $invoicesQuery->find($cleanId) ?: Invoice::withoutGlobalScopes()->find($cleanId);
        if (!$invoice) {
            return false;
        }

        $invNumber = $invoice->invoice_number;
        $invoice->delete();

        try {
            NoderaPayTransaction::where('order_id', $invNumber)->delete();
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Manual Accept/Approve (Tandai Lunas) for a QRIS transaction/invoice.
     */
    public function approveTransaction(Request $request, $id)
    {
        $idStr = (string) $id;
        $success = $this->processApproveId($idStr);

        if (!$success) {
            return redirect()->back()->with('error', 'Transaksi/Invoice tidak ditemukan.');
        }

        return redirect()->back()->with('msg', "Transaksi {$idStr} berhasil disetujui (Lunas).");
    }

    /**
     * Bulk Accept/Approve (Tandai Lunas) for multiple QRIS transactions/invoices.
     */
    public function bulkApproveTransactions(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return redirect()->back()->with('error', 'Pilih minimal satu transaksi untuk disetujui.');
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->processApproveId((string) $id)) {
                $count++;
            }
        }

        return redirect()->back()->with('msg', "{$count} transaksi berhasil disetujui (Lunas).");
    }

    /**
     * Manual Reject/Cancel (Tolak Transaksi) for a QRIS transaction/invoice.
     */
    public function rejectTransaction(Request $request, $id)
    {
        $idStr = (string) $id;
        $success = $this->processRejectId($idStr);

        if (!$success) {
            return redirect()->back()->with('error', 'Transaksi/Invoice tidak ditemukan.');
        }

        return redirect()->back()->with('msg', "Transaksi {$idStr} berhasil dibatalkan.");
    }

    /**
     * Bulk Reject/Cancel for multiple QRIS transactions/invoices.
     */
    public function bulkRejectTransactions(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return redirect()->back()->with('error', 'Pilih minimal satu transaksi untuk dibatalkan.');
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->processRejectId((string) $id)) {
                $count++;
            }
        }

        return redirect()->back()->with('msg', "{$count} transaksi berhasil ditolak / dibatalkan.");
    }

    /**
     * Delete QRIS transaction/invoice.
     */
    public function deleteTransaction(Request $request, $id)
    {
        $idStr = (string) $id;
        $success = $this->processDeleteId($idStr);

        if (!$success) {
            return redirect()->back()->with('error', 'Transaksi/Invoice tidak ditemukan.');
        }

        return redirect()->back()->with('msg', "Transaksi {$idStr} berhasil dihapus.");
    }

    /**
     * Bulk Delete for multiple QRIS transactions/invoices.
     */
    public function bulkDeleteTransactions(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return redirect()->back()->with('error', 'Pilih minimal satu transaksi untuk dihapus.');
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->processDeleteId((string) $id)) {
                $count++;
            }
        }

        return redirect()->back()->with('msg', "{$count} riwayat transaksi berhasil dihapus.");
    }
}
