<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SetupWizardController;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIspLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Only run check if in Standalone mode
        if (!LicenseService::isStandalone()) {
            return $next($request);
        }

        // 2. If Setup Wizard is not completed yet, let EnsureSetupCompleted middleware handle it
        if (class_exists(SetupWizardController::class) && !SetupWizardController::isSetupCompleted()) {
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

        // 4. Verify through unified LicenseService (with bounded 7-day offline grace)
        $licenseStatus = LicenseService::verifyLicense();

        if (empty($licenseStatus['valid']) && ($licenseStatus['status'] ?? '') !== 'COMMUNITY') {
            $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');
            $licenseKey = LicenseService::getLicenseKey();

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
