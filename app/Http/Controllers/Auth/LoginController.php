<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        $host = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));

        // Gateway subdomain → NODERA PAY login
        if (str_starts_with($host, 'gateway.') || $host === 'gateway.'.$baseDomain) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayAuthController::class)->showLogin();
        }

        // WA Gateway subdomain → WA GATEWAY login
        if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $host === 'wa.'.$baseDomain) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayAuthController::class)->showLogin();
        }

        // Panel subdomain → VPN login
        if (str_starts_with($host, 'panel.') || $host === 'panel.'.$baseDomain) {
            return app(\App\Http\Controllers\Vpn\AuthController::class)->showLogin();
        }

        // Jika di main/apex domain atau tanpa subdomain tenant yang valid, bersihkan session tenant
        if ($host === $baseDomain || $host === 'www.' . $baseDomain || !str_ends_with($host, '.' . $baseDomain)) {
            session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            return $this->renderLogin('NODERA', null);
        }

        // Jika di subdomain tenant, cari tenant berdasarkan subdomain host
        $subdomain = explode('.', $host)[0] ?? null;
        if ($subdomain && !in_array($subdomain, ['www', 'gateway', 'wa', 'wagateway', 'panel', 'shop'])) {
            $t = Tenant::where('slug', $subdomain)->first();
            if ($t) {
                return $this->renderLogin($t->name ?? 'NODERA', $t->slug);
            }
        }

        session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
        return $this->renderLogin('NODERA', null);
    }

    public function showGlobalLogin()
    {
        return $this->showLoginForm();
    }

    public function showTenantLogin($slug = null)
    {
        $host = request()->getHost();
        if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
            return redirect('/dashboard');
        }
        if (str_starts_with($host, 'gateway.')) {
            return redirect('/dashboard');
        }

        if (! $slug) {
            $slug = request()->route('slug') ?? request()->segment(1);
        }

        if (! $slug) {
            return $this->showGlobalLogin();
        }

        $t = Tenant::where('slug', $slug)->first();

        return $this->renderLogin($t->name ?? 'NODERA', $slug);
    }

    public function showTechnicianLogin()
    {
        return $this->renderLogin('NODERA — Teknisi', null, true);
    }

    public function showSuperadminLogin()
    {
        // Halaman login KHUSUS superadmin — terpisah dari login app
        // (dgtlnetsolution.com/login) yang dipakai admin tenant.
        return $this->renderLogin('NODERA', null, false, true);
    }

    protected function renderLogin(string $tenantName, ?string $tenantSlug, bool $technicianLogin = false, bool $superadminLogin = false)
    {
        $updater = app(\App\Services\SystemUpdateService::class);
        $licenseKey = $updater->getLicenseKey();
        $hwidRaw = php_uname('n') . php_uname('m') . (getenv('PROCESSOR_IDENTIFIER') ?: php_uname('s'));
        $hwid = 'NDR-HWID-' . strtoupper(substr(md5($hwidRaw), 0, 4) . '-' . substr(md5($hwidRaw . '2'), 0, 4) . '-' . substr(md5($hwidRaw . '3'), 0, 4));

        return Inertia::render('Auth/Login', [
            'tenantName' => $tenantName,
            'tenantSlug' => $tenantSlug,
            'technicianLogin' => $technicianLogin,
            'superadminLogin' => $superadminLogin,
            'licenseKey' => $licenseKey,
            'hwid' => $hwid,
        ]);
    }

    public function saveDesktopLicense(Request $request, \App\Services\SystemUpdateService $updater)
    {
        $request->validate([
            'license_key' => 'nullable|string|max:100',
        ]);
        $key = (string) $request->input('license_key', '');
        $res = $updater->saveLicenseKey($key);
        return response()->json($res);
    }

    public function authenticate(Request $request)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);

        // WA Gateway subdomain → proses login akun WA GATEWAY
        if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $host === 'wa.'.$baseDomain) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayAuthController::class)->login($request);
        }

        // Gateway subdomain → proses login akun NODERA PAY
        if (str_starts_with($host, 'gateway.') || $host === 'gateway.'.$baseDomain) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayAuthController::class)->login($request);
        }

        // Panel subdomain → VPN authenticate
        if (str_starts_with($host, 'panel.') || $host === 'panel.'.$baseDomain) {
            return app(\App\Http\Controllers\Vpn\AuthController::class)->login($request);
        }

        $tenantSlug = $request->input('tenant_slug') ?? $request->route('slug') ?? session('tenant_slug');

        if ($request->filled('turnstile_token') && !$request->filled('cf-turnstile-response')) {
            $request->merge(['cf-turnstile-response' => $request->input('turnstile_token')]);
        }

        $turnstileEnabled = (bool) config('services.turnstile.enabled', true) && !app()->environment('testing');

        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'login' => 'nullable|string',
            'username' => 'nullable|string',
            'tenant_slug' => 'nullable|string',
                    ], [
            'email.required' => 'Email, Nomor HP, atau Username wajib diisi.',
            'email.string' => 'Format email, nomor HP, atau username tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Format password tidak valid.',
                    ]);

        $rawInput = $request->input('email') ?? $request->input('login') ?? $request->input('username') ?? '';
        $loginInput = is_string($rawInput) ? trim($rawInput) : '';
        $password = is_string($request->input('password')) ? (string) $request->input('password') : '';

        // Bypass tenant scope saat lookup kredensial: stale session tenant_id
        // bisa menyembunyikan user superadmin (tenant_id null). Kepemilikan
        // tenant tetap divalidasi pada langkah berikutnya.
        $user = User::withoutGlobalScopes()->where(function ($q) use ($loginInput) {
            $q->where('email', $loginInput)
                ->orWhere('phone', $loginInput)
                ->orWhere('username', $loginInput);
        })->first();

        // Jika tidak ditemukan di users, periksa tabel collectors
        if (! $user) {
            $collector = \App\Models\Collector::withoutGlobalScopes()->where(function ($q) use ($loginInput) {
                $q->where('username', $loginInput)->orWhere('phone', $loginInput);
            })->first();

            if ($collector && Hash::check($password, $collector->password)) {
                $tenant = $collector->tenant_id ? Tenant::withoutGlobalScopes()->find($collector->tenant_id) : null;
                if (! $tenant || ! $tenant->is_active || $tenant->isExpired()) {
                    return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
                }
                session([
                    'collector_id' => $collector->id,
                    'collector_name' => $collector->name,
                    'collector_logged_in' => true,
                    'tenant_id' => $collector->tenant_id,
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => $tenant->name,
                ]);
                if ($request->boolean('remember', true)) {
                    cookie()->queue('nodera_remember_collector', $collector->id, 525600);
                }
                return redirect('/kolektor/dashboard');
            }
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            return back()->with('error', 'Email / No HP / Username atau password salah.')->withInput();
        }

            // Check role: admin, superadmin, technician, collector, cashier
            if (! in_array($user->role, ['admin', 'superadmin', 'technician', 'collector', 'cashier'])) {
                return back()->with('error', 'Anda tidak memiliki akses ke aplikasi.');
            }

            if (isset($user->is_active) && ! $user->is_active) {
                return back()->with('error', 'Akun Anda dinonaktifkan.');
            }

            // Superadmin TIDAK boleh login lewat form biasa — tolak sebelum
            // Auth::login() dipanggil supaya tidak ada window di mana
            // superadmin sempat ter-autentikasi via guard biasa.
            if ($user->role === 'superadmin') {
                return back()->withErrors(['email' => 'Akun superadmin login lewat halaman khusus yang telah diberikan.']);
            }

            // Resolve tenant dari akun pengguna
            $tenant = null;
            if ($user->tenant_id && ! in_array($user->role, ['superadmin'])) {
                $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
                if (! $tenant) {
                    return back()->with('error', 'Akun tidak ditemukan. Hubungi administrator.');
                }
                if ($tenant->isExpired()) {
                    return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
                }
                if (! $tenant->is_active) {
                    return back()->with('error', 'Akun Anda sedang dinonaktifkan. Hubungi administrator.');
                }
                session([
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => $tenant->name,
                ]);
            } elseif ($user->role === 'superadmin') {
                // Superadmin login: bersihkan session tenant dari impersonate sebelumnya
                session()->forget(['tenant_id', 'tenant_slug', 'tenant_name', 'impersonating']);
            } else {
                session()->forget(['tenant_id', 'tenant_slug', 'tenant_name']);
            }

            Auth::login($user, $request->boolean('remember', true));

            $user->last_login = now();
            $user->save();

            // Setup session & target path strictly according to user role
            $targetPath = '/dashboard';

            if ($user->role === 'technician') {
                session([
                    'technician_logged_in' => true,
                    'technician_id' => $user->id,
                    'technician_name' => $user->name ?? $user->username,
                    'technician_username' => $user->username ?? $user->email,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_name' => $tenant?->name,
                ]);
                session()->forget(['admin_logged_in', 'admin_id', 'admin_name', 'admin_role', 'admin_username', 'collector_logged_in', 'collector_id', 'cashier_logged_in']);
                $targetPath = '/teknisi/dashboard';
            } elseif ($user->role === 'collector') {
                session([
                    'collector_logged_in' => true,
                    'collector_id' => $user->id,
                    'collector_name' => $user->name ?? $user->username,
                    'collector_username' => $user->username ?? $user->email,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_name' => $tenant?->name,
                ]);
                session()->forget(['admin_logged_in', 'admin_id', 'admin_name', 'admin_role', 'admin_username', 'technician_logged_in', 'technician_id', 'cashier_logged_in']);
                $targetPath = '/kolektor/dashboard';
            } elseif ($user->role === 'cashier') {
                session([
                    'cashier_logged_in' => true,
                    'cashier_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_name' => $tenant?->name,
                ]);
                session()->forget(['admin_logged_in', 'admin_id', 'admin_name', 'admin_role', 'admin_username', 'technician_logged_in', 'collector_logged_in']);
                $targetPath = '/kasir/dashboard';
            } elseif ($user->role === 'admin') {
                session([
                    'admin_logged_in' => true,
                    'admin_id' => $user->id,
                    'admin_name' => $user->name ?? $user->username,
                    'admin_role' => 'admin',
                    'admin_username' => $user->username ?? $user->email,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_name' => $tenant?->name,
                ]);
                session()->forget(['technician_logged_in', 'technician_id', 'collector_logged_in', 'collector_id', 'cashier_logged_in']);
                $targetPath = '/dashboard';
            }

            // Redirect ke subdomain tenant jika ada (HANYA di live domain publik, BUKAN di localhost / IP)
            $currentHost = $request->getHost() ?: '';
            $isLocal = in_array($currentHost, ['localhost', '127.0.0.1', '::1']) || filter_var($currentHost, FILTER_VALIDATE_IP);

            if (!$isLocal && $tenant && $tenant->is_active) {
                $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
                if ($baseDomain && $baseDomain !== 'localhost') {
                    $targetHost = $tenant->slug . '.' . $baseDomain;

                    if ($currentHost === $targetHost) {
                        return redirect($targetPath);
                    }

                    $scheme = $request->getScheme();
                    $port = in_array($request->getPort(), [80, 443]) ? '' : ':' . $request->getPort();
                    $fullTargetUrl = $scheme . '://' . $targetHost . $port . $targetPath;
                    return Inertia::location($fullTargetUrl);
                }
            }

            return redirect()->to($targetPath);
    }

    /**
     * Login KHUSUS superadmin (halaman /nodera/superadmin/login — URL
     * tersembunyi, terpisah dari login app admin tenant di dgtlnetsolution.com/login).
     */
    public function authenticateSuperadmin(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'email' => 'nullable|string',
            'login' => 'nullable|string',
            'username' => 'nullable|string',
        ], [
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Format password tidak valid.',
        ]);

        $rawInput = $request->input('email') ?? $request->input('login') ?? $request->input('username') ?? '';
        $loginInput = is_string($rawInput) ? trim($rawInput) : '';
        $password = is_string($request->input('password')) ? (string) $request->input('password') : '';

        $user = User::withoutGlobalScopes()->where(function ($q) use ($loginInput) {
            $q->where('email', $loginInput)
                ->orWhere('phone', $loginInput)
                ->orWhere('username', $loginInput);
        })->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return back()->with('error', 'Email / No HP / Username atau password salah.')->withInput();
        }

        if ($user->role !== 'superadmin') {
            return back()->with('error', 'Halaman ini khusus akun superadmin. Admin tenant login di dgtlnetsolution.com/login.')->withInput();
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return back()->with('error', 'Akun Anda dinonaktifkan.');
        }

        Auth::login($user, $request->filled('remember'));

        session([
            'admin_logged_in' => true,
            'admin_id' => $user->id,
            'admin_name' => $user->name ?? $user->username,
            'admin_role' => 'superadmin',
            'admin_username' => $user->username ?? $user->email,
        ]);
        session()->forget(['tenant_id', 'tenant_slug', 'tenant_name', 'impersonating', '2fa_verified']);

        $user->last_login = now();
        $user->save();

        if ($user->two_factor_enabled) {
            session()->forget('2fa_verified');

            return redirect()->route('superadmin.2fa');
        }

        return redirect('/superadmin');
    }

    public function logout(Request $request)
    {
        $host = $request->getHost();
        if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $request->is('wagateway*')) {
            $request->session()->forget('wagateway_merchant_id');
            cookie()->queue(cookie()->forget('nodera_remember_wa_merchant'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location('/login');
            }
            return redirect('/login');
        }

        if (str_starts_with($host, 'gateway.') || $request->is('noderapay*')) {
            $request->session()->forget('noderapay_merchant_id');
            cookie()->queue(cookie()->forget('nodera_remember_merchant'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location('/login');
            }
            return redirect('/login');
        }

        $wasImpersonating = session('impersonating');
        $wasSuperadmin = session('admin_role') === 'superadmin';

        Auth::logout();
        Auth::guard('vpn')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session()->forget(['vpn_user_id', 'vpn_user_name', 'admin_logged_in', 'tenant_id', 'tenant_slug', 'collector_logged_in', 'technician_logged_in', 'cashier_logged_in', 'noderapay_merchant_id', 'wagateway_merchant_id']);
        cookie()->queue(cookie()->forget('nodera_remember_merchant'));
        cookie()->queue(cookie()->forget('nodera_remember_wa_merchant'));

        $targetUrl = '/login';
        if ($wasImpersonating) {
            $targetUrl = '/superadmin';
        } elseif ($wasSuperadmin) {
            $targetUrl = route('superadmin.login');
        }

        if ($request->header('X-Inertia')) {
            return \Inertia\Inertia::location($targetUrl);
        }

        return redirect($targetUrl);
    }
}
