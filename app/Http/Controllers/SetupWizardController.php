<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SetupWizardController extends Controller
{
    public static function isSetupCompleted(): bool
    {
        // SaaS Cloud mode is considered always completed
        $isStandalone = (bool) (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false));
        if (!$isStandalone) {
            return true;
        }

        if (filter_var(env('SETUP_COMPLETED', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        try {
            $val = Setting::withoutGlobalScopes()->where('key', 'SETUP_COMPLETED')->value('value');
            if ($val === 'true' || $val === '1') {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    public function show()
    {
        if (self::isSetupCompleted()) {
            return redirect('/login');
        }

        $hardwareId = self::getHardwareId();
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        return Inertia::render('Setup/Wizard', [
            'hardwareId' => $hardwareId,
            'serverIp' => request()->ip(),
            'domain' => request()->getHost(),
            'licenseServer' => $licenseServer,
            'defaultCompanyName' => config('app.name', 'NODERA Billing'),
        ]);
    }

    public static function getHardwareId(): string
    {
        $id = @file_get_contents('/etc/machine-id');
        if (empty($id)) {
            $id = @file_get_contents('/var/lib/dbus/machine-id');
        }
        if (empty($id)) {
            $id = php_uname('n') . '_' . php_uname('m');
        }
        return trim((string) $id);
    }

    public function verifyLicense(Request $request): JsonResponse
    {
        $key = trim($request->input('license_key') ?? '');
        $domain = trim($request->input('domain') ?? $request->getHost());
        $hardwareId = self::getHardwareId();
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        if (empty($key)) {
            return response()->json([
                'valid' => false,
                'message' => 'Silakan masukkan License Key NODERA Anda.',
            ], 422);
        }

        try {
            $resp = Http::timeout(8)->asJson()->post("{$licenseServer}/api/v1/license/verify", [
                'license_key' => $key,
                'domain' => $domain,
                'hardware_id' => $hardwareId,
            ]);

            if ($resp->successful() && $resp->json('valid')) {
                return response()->json([
                    'valid' => true,
                    'status' => $resp->json('status', 'ACTIVE'),
                    'client_name' => $resp->json('client_name'),
                    'package_type' => $resp->json('package_type'),
                    'max_customers' => $resp->json('max_customers'),
                    'max_routers' => $resp->json('max_routers'),
                    'expires_at' => $resp->json('expires_at'),
                    'message' => 'Lisensi valid & berhasil dikunci ke perangkat/server ini!',
                ]);
            }

            return response()->json([
                'valid' => false,
                'status' => $resp->json('status', 'INVALID'),
                'message' => $resp->json('message', 'Lisensi tidak valid atau tidak terdaftar di server pusat.'),
            ], 422);
        } catch (\Throwable $e) {
            Log::warning("SetupWizard: License verify connection error: " . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Gagal menghubungi server pusat lisensi (' . $licenseServer . '). Pastikan server terhubung ke internet.',
            ], 500);
        }
    }

    public function complete(Request $request): JsonResponse
    {
        if (self::isSetupCompleted()) {
            return response()->json(['success' => true, 'redirect' => '/login']);
        }

        $validated = $request->validate([
            'license_key' => 'required|string',
            'company_name' => 'required|string|max:150',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:150',
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|string|max:150',
            'admin_password' => 'required|string|min:6',
        ], [
            'license_key.required' => 'License Key wajib diisi.',
            'company_name.required' => 'Nama Usaha / ISP wajib diisi.',
            'admin_name.required' => 'Nama Administrator wajib diisi.',
            'admin_email.required' => 'Username atau Email Admin wajib diisi.',
            'admin_password.required' => 'Password Admin wajib diisi (minimal 6 karakter).',
        ]);

        $key = trim($validated['license_key']);
        $hardwareId = self::getHardwareId();
        $domain = $request->getHost();
        $licenseServer = rtrim(env('NODERA_LICENSE_SERVER', 'https://panel.dgtlnetsolution.com'), '/');

        // 1. Verify license once more
        try {
            $resp = Http::timeout(8)->asJson()->post("{$licenseServer}/api/v1/license/verify", [
                'license_key' => $key,
                'domain' => $domain,
                'hardware_id' => $hardwareId,
            ]);

            if (!$resp->successful() || !$resp->json('valid')) {
                return response()->json([
                    'success' => false,
                    'message' => $resp->json('message', 'Verifikasi lisensi gagal. Silakan periksa kembali lisensi Anda.'),
                ], 422);
            }
        } catch (\Throwable $e) {
            Log::warning("SetupWizard: complete verify offline fallback: " . $e->getMessage());
        }

        // 2. Save settings to DB
        try {
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'NODERA_LICENSE_KEY', 'tenant_id' => null],
                ['value' => $key]
            );
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'LICENSE_KEY', 'tenant_id' => null],
                ['value' => $key]
            );
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'COMPANY_NAME', 'tenant_id' => null],
                ['value' => $validated['company_name']]
            );
            if (!empty($validated['company_phone'])) {
                Setting::withoutGlobalScopes()->updateOrCreate(
                    ['key' => 'COMPANY_PHONE', 'tenant_id' => null],
                    ['value' => $validated['company_phone']]
                );
            }
            if (!empty($validated['company_email'])) {
                Setting::withoutGlobalScopes()->updateOrCreate(
                    ['key' => 'COMPANY_EMAIL', 'tenant_id' => null],
                    ['value' => $validated['company_email']]
                );
            }
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['key' => 'SETUP_COMPLETED', 'tenant_id' => null],
                ['value' => 'true']
            );
        } catch (\Throwable $e) {
            Log::error("SetupWizard DB save error: " . $e->getMessage());
        }

        // 3. Update .env file
        $envPath = base_path('.env');
        if (File::exists($envPath) && File::isWritable($envPath)) {
            $content = File::get($envPath);
            $replacements = [
                'APP_NAME' => '"' . addslashes($validated['company_name']) . '"',
                'NODERA_LICENSE_KEY' => $key,
                'LICENSE_KEY' => $key,
                'STANDALONE_MODE' => 'true',
                'SETUP_COMPLETED' => 'true',
            ];
            foreach ($replacements as $k => $v) {
                if (str_contains($content, "{$k}=")) {
                    $content = preg_replace("/^{$k}=.*/m", "{$k}={$v}", $content);
                } else {
                    $content .= "\n{$k}={$v}";
                }
            }
            File::put($envPath, $content);
        }

        // 4. Create or Update Admin Billing User
        $adminLogin = trim($validated['admin_email']);
        $user = User::withoutGlobalScopes()
            ->where('email', $adminLogin)
            ->orWhere('username', $adminLogin)
            ->first();

        if (!$user) {
            $user = new User();
        }

        $user->name = $validated['admin_name'];
        if (filter_var($adminLogin, FILTER_VALIDATE_EMAIL)) {
            $user->email = $adminLogin;
            $user->username = explode('@', $adminLogin)[0];
        } else {
            $user->username = $adminLogin;
            $user->email = $validated['company_email'] ?: ($adminLogin . '@isp.local');
        }
        $user->password = Hash::make($validated['admin_password']);
        $user->role = 'admin'; // Admin Billing ISP
        $user->is_active = true;
        $user->tenant_id = null;
        $user->save();

        // 5. Authenticate & Setup Session
        Auth::login($user, true);
        session([
            'admin_logged_in' => true,
            'admin_id' => $user->id,
            'admin_name' => $user->name,
            'admin_role' => 'admin',
            'admin_username' => $user->username ?? $user->email,
        ]);
        session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);

        // 6. Clear cache
        \Illuminate\Support\Facades\Cache::forget('nodera_standalone_license_status');

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi awal berhasil diselesaikan!',
            'redirect' => '/dashboard',
        ]);
    }
}
