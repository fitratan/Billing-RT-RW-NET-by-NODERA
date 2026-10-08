<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SetupWizardController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckIspLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Only run check if in Standalone mode
        $isStandalone = (bool) (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false));
        if (!$isStandalone) {
            // SaaS Cloud mode bypasses license check completely (SaaS is the central licensing authority)
            return $next($request);
        }

        // 2. If Setup Wizard is not completed yet, let EnsureSetupCompleted middleware handle it
        if (!SetupWizardController::isSetupCompleted()) {
            return $next($request);
        }

        // 3. Whitelist routes: static assets, up/health, license apis, login/logout, activation, setup, webhooks, client-error
        if ($request->is(
            'up', 'manifest.json', 'assets/*', 'build/*', 'favicon.ico', 'service-worker.js', 'pwa/*',
            'api/v1/license/*', 'api/license/*', 'activation', 'login', 'logout',
            'setup', 'setup/*', 'api/setup/*',
            'webhook/*', 'webhook', 'api/webhook/*', 'api/webhook',
            'client-error', 'api/client-error',
            'admin/my-settings/save-license', 'superadmin/settings/save-license',
            'admin/my-settings/check-update', 'superadmin/settings/check-update'
        )) {
            return $next($request);
        }

        // 4. Get license key from env or database setting
        $licenseKey = env('NODERA_LICENSE_KEY') ?: env('LICENSE_KEY');
        if (empty($licenseKey)) {
            try {
                $licenseKey = \App\Models\Setting::withoutGlobalScopes()
                    ->whereIn('key', ['NODERA_LICENSE_KEY', 'LICENSE_KEY'])
                    ->whereNull('tenant_id')
                    ->value('value');
            } catch (\Throwable $e) {
            }
        }
        $licenseKey = trim((string) $licenseKey);

        // If license key is EMPTY in Standalone mode -> Run in Free Community Edition!
        if (empty($licenseKey)) {
            return $next($request);
        }

        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');
        $cacheKey = 'nodera_standalone_license_status';

        $licenseStatus = Cache::get($cacheKey);

        if (!$licenseStatus) {
            try {
                $domain = $request->getHost();
                $hardwareId = SetupWizardController::getHardwareId();

                $resp = Http::timeout(6)->asJson()->post("{$licenseServer}/api/v1/license/verify", [
                    'license_key' => $licenseKey,
                    'domain' => $domain,
                    'hardware_id' => $hardwareId,
                ]);

                if ($resp->successful() && $resp->json('valid')) {
                    $licenseStatus = [
                        'valid' => true,
                        'status' => $resp->json('status', 'ACTIVE'),
                        'client_name' => $resp->json('client_name'),
                        'package_type' => $resp->json('package_type'),
                        'expires_at' => $resp->json('expires_at'),
                    ];
                    // Cache valid license for 15 minutes
                    Cache::put($cacheKey, $licenseStatus, now()->addMinutes(15));
                } else {
                    $status = $resp->json('status', 'INVALID');
                    $msg = $resp->json('message', 'Lisensi tidak valid atau telah dinonaktifkan.');

                    if ($status === 'HARDWARE_MISMATCH') {
                        $msg = 'Lisensi ini telah terkunci pada perangkat/VPS lain. Satu lisensi hanya berlaku untuk 1 perangkat/VPS. Silakan beli lisensi baru untuk server ini.';
                    } elseif ($status === 'IP_MISMATCH') {
                        $msg = 'Lisensi ini telah terikat pada IP Server lain, tidak dapat digunakan pada server ini. Silakan beli lisensi baru.';
                    } elseif ($status === 'SUSPENDED') {
                        $msg = 'Lisensi NODERA Billing ini sedang ditangguhkan (SUSPENDED) oleh administrator pusat.';
                    } elseif ($status === 'REVOKED') {
                        $msg = 'Lisensi NODERA Billing ini telah dicabut secara permanen (REVOKED).';
                    } elseif ($status === 'EXPIRED') {
                        $msg = 'Masa berlaku lisensi NODERA Billing Anda telah berakhir (EXPIRED). Silakan perpanjang lisensi Anda.';
                    }

                    $licenseStatus = [
                        'valid' => false,
                        'status' => $status,
                        'message' => $msg,
                    ];
                    // Cache failure for 1 minute
                    Cache::put($cacheKey, $licenseStatus, now()->addMinute());
                }
            } catch (\Throwable $e) {
                // If central server temporarily unreachable, fallback gracefully
                Log::warning("NODERA License verification offline fallback: " . $e->getMessage());
                return $next($request);
            }
        }

        if (empty($licenseStatus['valid'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'License verification failed',
                    'status' => $licenseStatus['status'] ?? 'INVALID',
                    'message' => $licenseStatus['message'] ?? 'Lisensi NODERA Billing tidak valid atau telah dibekukan.',
                ], 403);
            }

            return response()->view('errors.license', [
                'message' => $licenseStatus['message'] ?? 'Lisensi NODERA Billing Standalone ini tidak valid atau telah dinonaktifkan oleh administrator pusat.',
                'license_key' => $licenseKey,
                'status' => $licenseStatus['status'] ?? 'INVALID',
                'server' => $licenseServer,
            ], 403);
        }

        return $next($request);
    }
}
