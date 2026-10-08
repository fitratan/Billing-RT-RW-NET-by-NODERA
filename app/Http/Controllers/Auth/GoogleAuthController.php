<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ReferralPartner;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use App\Services\ReferralService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Resolve the callback URL dynamically based on the current request.
     */
    protected function getRedirectUrl(Request $request): string
    {
        $host = $request->getHost();
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']);

        // In production / live domains, always use https
        $scheme = $isLocal ? $request->getScheme() : 'https';
        $port = ($isLocal && !in_array($request->getPort(), [80, 443])) ? ':' . $request->getPort() : '';

        return "{$scheme}://{$host}{$port}/auth/google/callback";
    }

    /**
     * Redirect the user to Google OAuth page.
     */
    public function redirect(Request $request)
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        $isGateway = str_starts_with($host, 'gateway.') || $host === 'gateway.' . $baseDomain;
        $isPanel = str_starts_with($host, 'panel.') || $host === 'panel.' . $baseDomain;
        $isWa = str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $host === 'wa.' . $baseDomain;

        $context = $request->query('context');
        if (!$context) {
            $context = $isGateway ? 'noderapay' : ($isWa ? 'wagateway' : ($isPanel ? 'vpn' : 'tenant'));
        }

        $intent = $request->query('intent', 'login');
        $ref = $request->query('ref') ?? $request->query('referral_code');
        $tenantSlug = $request->query('tenant_slug') ?? session('tenant_slug');

        if (empty($clientId) || empty($clientSecret)) {
            $fallbackUrl = ($context === 'noderapay' || $context === 'gateway')
                ? ($isGateway ? '/login' : '/noderapay/login')
                : (($context === 'wagateway' || $context === 'wa')
                    ? ($isWa ? '/login' : '/wagateway/login')
                    : (($context === 'vpn') ? '/vpn/login' : '/login'));
            return redirect($fallbackUrl)->with('error', 'Google OAuth belum dikonfigurasi. Harap isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET pada file .env.');
        }

        $redirectUrl = $this->getRedirectUrl($request);

        $stateData = [
            'context' => $context,
            'intent' => $intent,
            'ref' => $ref,
            'tenant_slug' => $tenantSlug,
            'host' => $host,
            'redirect_url' => $redirectUrl,
            'token' => Str::random(16),
        ];

        session(['google_auth_state' => $stateData]);

        $encodedState = base64_encode(json_encode($stateData));

        $driver = Socialite::driver('google');
        if (method_exists($driver, 'redirectUrl') && !empty($redirectUrl)) {
            $driver->redirectUrl($redirectUrl);
        }
        if (method_exists($driver, 'stateless')) {
            $driver->stateless();
        }

        return $driver
            ->with(['state' => $encodedState])
            ->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request)
    {
        $stateData = session('google_auth_state', []);
        if ($request->has('state')) {
            try {
                $decoded = json_decode(base64_decode($request->query('state')), true);
                if (is_array($decoded)) {
                    $stateData = array_merge($stateData, $decoded);
                }
            } catch (\Throwable $e) {
                // Ignore decoding error, fallback to session state
            }
        }

        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        $isGateway = str_starts_with($host, 'gateway.') || $host === 'gateway.' . $baseDomain;
        $isWa = str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $host === 'wa.' . $baseDomain;
        $context = $stateData['context'] ?? ($isGateway ? 'noderapay' : ($isWa ? 'wagateway' : (str_starts_with($host, 'panel.') ? 'vpn' : 'tenant')));

        $fallbackTarget = ($context === 'noderapay' || $context === 'gateway')
            ? ($isGateway ? '/login' : '/noderapay/login')
            : (($context === 'wagateway' || $context === 'wa')
                ? ($isWa ? '/login' : '/wagateway/login')
                : (($context === 'vpn') ? '/vpn/login' : '/login'));

        if ($request->has('error') || $request->query('denied')) {
            return redirect($fallbackTarget)->with('error', 'Login dengan Google dibatalkan.');
        }

        $redirectUrl = $stateData['redirect_url'] ?? $this->getRedirectUrl($request);

        try {
            $driver = Socialite::driver('google');
            if (method_exists($driver, 'redirectUrl') && !empty($redirectUrl)) {
                $driver->redirectUrl($redirectUrl);
            }
            if (method_exists($driver, 'stateless')) {
                $driver->stateless();
            }
            $googleUser = $driver->user();
        } catch (\Throwable $e) {
            Log::error('[GoogleAuth] Callback exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect($fallbackTarget)->with('error', 'Gagal menghubungkan akun Google. Pastikan domain terdaftar di Google Cloud Console.');
        }

        $intent = $stateData['intent'] ?? 'login';
        $ref = $stateData['ref'] ?? null;
        $tenantSlug = $stateData['tenant_slug'] ?? null;

        $googleId = (string) $googleUser->getId();
        $email = (string) $googleUser->getEmail();
        $name = (string) ($googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User');
        $avatar = (string) ($googleUser->getAvatar() ?: '');

        // -------------------------------------------------------------
        // CONTEXT 1: VPN / Mikhmon / Panel User
        // -------------------------------------------------------------
        if ($context === 'vpn') {
            $vpnUser = VpnUser::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (!$vpnUser) {
                // Register new VpnUser
                $partnerId = null;
                $refCodeUsed = null;
                if (!empty($ref)) {
                    $partner = ReferralService::validateReferralCode($ref);
                    if ($partner) {
                        $partnerId = $partner->id;
                        $refCodeUsed = $partner->referral_code;
                    }
                }

                $bonusSaldo = (int) config('vpn.bonus_saldo', env('VPN_BONUS_SALDO', 2000));

                $vpnUser = VpnUser::create([
                    'referred_by_partner_id' => $partnerId,
                    'referral_code_used' => $refCodeUsed,
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'password' => Hash::make(Str::random(32)),
                    'bonus_saldo' => $bonusSaldo,
                    'is_active' => true,
                    'last_login_at' => now(),
                ]);

                if ($bonusSaldo > 0) {
                    VpnTransaction::create([
                        'vpn_user_id' => $vpnUser->id,
                        'type' => 'bonus',
                        'amount' => $bonusSaldo,
                        'saldo_before' => 0,
                        'saldo_after' => $bonusSaldo,
                        'description' => 'Saldo awal pendaftaran Google' . ($refCodeUsed ? " (Ref: {$refCodeUsed})" : ''),
                    ]);
                }

                try {
                    $wa = WhatsappService::forSuperadmin();
                    if ($wa->isEnabled() && !empty($vpnUser->phone)) {
                        $wa->sendPanelWelcome($vpnUser, $refCodeUsed);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[GoogleAuth] WhatsApp welcome error: ' . $e->getMessage());
                }
            } else {
                if (!$vpnUser->is_active) {
                    return redirect('/vpn/login')->with('error', 'Akun Anda dinonaktifkan.');
                }

                $updates = ['last_login_at' => now()];
                if (empty($vpnUser->google_id)) {
                    $updates['google_id'] = $googleId;
                }
                if (empty($vpnUser->avatar) && !empty($avatar)) {
                    $updates['avatar'] = $avatar;
                }
                $vpnUser->update($updates);
            }

            Auth::guard('vpn')->login($vpnUser, true);
            session([
                'vpn_user_id' => $vpnUser->id,
                'vpn_user_name' => $vpnUser->name,
            ]);

            return redirect('/dashboard')->with('msg', 'Berhasil masuk dengan Google! Selamat datang.');
        }

        // -------------------------------------------------------------
        // CONTEXT 3: NODERA PAY Merchant
        // -------------------------------------------------------------
        if ($context === 'noderapay' || $context === 'gateway') {
            $merchant = \App\Models\NoderaPayMerchant::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (!$merchant) {
                $partnerId = null;
                $refCodeUsed = null;
                if (!empty($ref)) {
                    $partner = ReferralService::validateReferralCode($ref);
                    if ($partner) {
                        $partnerId = $partner->id;
                        $refCodeUsed = $partner->referral_code;
                    }
                }

                $creds = \App\Models\NoderaPayMerchant::generateCredentials();
                $merchant = \App\Models\NoderaPayMerchant::create([
                    'merchant_code'         => $creds['merchant_code'],
                    'name'                  => $name . ' Store',
                    'owner_name'            => $name,
                    'email'                 => strtolower(trim($email)),
                    'phone'                 => '',
                    'google_id'             => $googleId,
                    'avatar'                => $avatar,
                    'password'              => Hash::make(Str::random(32)),
                    'api_key'               => $creds['api_key'],
                    'secret_key'            => $creds['secret_key'],
                    'balance'               => 0.00,
                    'status'                => 'active',
                    'referred_by_partner_id'=> $partnerId,
                    'referral_code_used'    => $refCodeUsed,
                ]);
            } else {
                if ($merchant->status === 'suspended') {
                    $target = $isGateway ? '/login' : '/noderapay/login';
                    return redirect($target)->with('error', 'Akun Merchant Anda dinonaktifkan oleh administrator.');
                }

                $updates = [];
                if (empty($merchant->google_id)) {
                    $updates['google_id'] = $googleId;
                }
                if (empty($merchant->avatar) && !empty($avatar)) {
                    $updates['avatar'] = $avatar;
                }
                if (!empty($updates)) {
                    $merchant->update($updates);
                }
            }

            session([
                'noderapay_merchant_id' => $merchant->id,
            ]);
            cookie()->queue('nodera_remember_merchant', $merchant->id, 525600); // 1 year cookie

            $target = $isGateway ? '/dashboard' : '/noderapay/dashboard';
            return redirect($target)->with('success', 'Selamat datang di NODERA PAY, ' . $merchant->name . '!');
        }

        // -------------------------------------------------------------
        // CONTEXT 4: NODERA WhatsApp Gateway Merchant
        // -------------------------------------------------------------
        if ($context === 'wagateway' || $context === 'wa') {
            $waMerchant = \App\Models\WaMerchant::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if (!$waMerchant) {
                $partnerId = null;
                $refCodeUsed = null;
                if (!empty($ref)) {
                    $partner = ReferralService::validateReferralCode($ref);
                    if ($partner) {
                        $partnerId = $partner->id;
                        $refCodeUsed = $partner->referral_code;
                    }
                }

                $creds = \App\Models\WaMerchant::generateCredentials();
                $waMerchant = \App\Models\WaMerchant::create([
                    'merchant_code'         => $creds['merchant_code'],
                    'name'                  => $name . ' Hub',
                    'owner_name'            => $name,
                    'email'                 => strtolower(trim($email)),
                    'phone'                 => '',
                    'google_id'             => $googleId,
                    'avatar'                => $avatar,
                    'password'              => Hash::make(Str::random(32)),
                    'api_key'               => $creds['api_key'],
                    'secret_key'            => $creds['secret_key'],
                    'plan_type'             => 'free',
                    'quota_monthly'         => 500,
                    'quota_used_this_month' => 0,
                    'credit_balance'        => 0.00,
                    'device_limit'          => 1,
                    'status'                => 'active',
                    'referred_by_partner_id'=> $partnerId,
                    'referral_code_used'    => $refCodeUsed,
                ]);

                \App\Models\WhatsappDevice::create([
                    'merchant_id' => $waMerchant->id,
                    'name'        => 'Device Utama (WhatsApp)',
                    'is_default'  => true,
                    'status'      => 'DISCONNECTED',
                ]);
            } else {
                if ($waMerchant->status === 'suspended') {
                    $target = $isWa ? '/login' : '/wagateway/login';
                    return redirect($target)->with('error', 'Akun Gateway Anda dinonaktifkan oleh administrator.');
                }

                $updates = [];
                if (empty($waMerchant->google_id)) {
                    $updates['google_id'] = $googleId;
                }
                if (empty($waMerchant->avatar) && !empty($avatar)) {
                    $updates['avatar'] = $avatar;
                }
                if (!empty($updates)) {
                    $waMerchant->update($updates);
                }
            }

            session([
                'wagateway_merchant_id' => $waMerchant->id,
            ]);
            cookie()->queue('nodera_remember_wa_merchant', $waMerchant->id, 525600); // 1 year cookie

            $target = $isWa ? '/dashboard' : '/wagateway/dashboard';
            return redirect($target)->with('success', 'Selamat datang di NODERA WhatsApp Gateway, ' . $waMerchant->name . '!');
        }

        // -------------------------------------------------------------
        // CONTEXT 2: SaaS / Tenant / Admin / Staff User
        // -------------------------------------------------------------
        $user = User::withoutGlobalScopes()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (isset($user->is_active) && !$user->is_active) {
                return redirect('/login')->with('error', 'Akun Anda dinonaktifkan.');
            }

            $userUpdates = ['last_login' => now()];
            if (empty($user->google_id)) {
                $userUpdates['google_id'] = $googleId;
            }
            if (empty($user->avatar) && !empty($avatar)) {
                $userUpdates['avatar'] = $avatar;
            }
            $user->update($userUpdates);

            // Superadmin login
            if ($user->role === 'superadmin') {
                Auth::login($user, true);
                session([
                    'admin_logged_in' => true,
                    'admin_id' => $user->id,
                    'admin_name' => $user->name ?? $user->username,
                    'admin_role' => 'superadmin',
                    'admin_username' => $user->username ?? $user->email,
                ]);
                session()->forget(['tenant_id', 'tenant_slug', 'tenant_name', 'impersonating']);
                return redirect('/superadmin');
            }

            // Tenant User
            $tenant = null;
            if ($user->tenant_id) {
                $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
                if (!$tenant) {
                    return redirect('/login')->with('error', 'Instansi tidak ditemukan.');
                }
                if ($tenant->isExpired()) {
                    return redirect('/login')->with('error', 'Masa aktif instansi Anda telah berakhir. Hubungi administrator.');
                }
                if (!$tenant->is_active) {
                    return redirect('/login')->with('error', 'Instansi Anda sedang dinonaktifkan. Hubungi administrator.');
                }

                session([
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => $tenant->name,
                ]);
            }

            Auth::login($user, true);

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
                $targetPath = '/kolektor/dashboard';
            } elseif ($user->role === 'cashier') {
                session([
                    'cashier_logged_in' => true,
                    'cashier_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_name' => $tenant?->name,
                ]);
                $targetPath = '/kasir/dashboard';
            } else { // admin
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
                $targetPath = '/dashboard';
            }

            // Live tenant subdomain redirect
            $currentHost = $request->getHost() ?: '';
            $isLocal = in_array($currentHost, ['localhost', '127.0.0.1', '::1']) || filter_var($currentHost, FILTER_VALIDATE_IP);

            if (!$isLocal && $tenant && $tenant->is_active) {
                $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
                if ($baseDomain && $baseDomain !== 'localhost') {
                    $targetHost = $tenant->slug . '.' . $baseDomain;
                    if ($currentHost !== $targetHost) {
                        $scheme = $request->getScheme();
                        $port = in_array($request->getPort(), [80, 443]) ? '' : ':' . $request->getPort();
                        return redirect()->away($scheme . '://' . $targetHost . $port . $targetPath);
                    }
                }
            }

            return redirect($targetPath);
        }

        // User not found in Users table
        // 1. If user was attempting to login, check if they exist in VpnUser table
        if ($intent === 'login') {
            $vpnUser = VpnUser::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($vpnUser) {
                if (!$vpnUser->is_active) {
                    return redirect('/login')->with('error', 'Akun Anda dinonaktifkan.');
                }
                $updates = ['last_login_at' => now()];
                if (empty($vpnUser->google_id)) {
                    $updates['google_id'] = $googleId;
                }
                if (empty($vpnUser->avatar) && !empty($avatar)) {
                    $updates['avatar'] = $avatar;
                }
                $vpnUser->update($updates);

                Auth::guard('vpn')->login($vpnUser, true);
                session([
                    'vpn_user_id' => $vpnUser->id,
                    'vpn_user_name' => $vpnUser->name,
                ]);

                return redirect('/dashboard')->with('msg', 'Berhasil masuk ke Panel NODERA dengan Google.');
            }
        }

        // 2. Pre-fill registration form with Google data
        session([
            'google_register_data' => [
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'avatar' => $avatar,
            ]
        ]);

        return redirect('/register')->with('info', 'Akun Google (' . $email . ') belum terdaftar di sistem. Nama & email Anda telah diisi secara otomatis, silakan lengkapi data instansi Anda untuk menyelesaikan pendaftaran.');
    }
}
