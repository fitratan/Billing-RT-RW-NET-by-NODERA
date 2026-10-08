<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\VpnPackage;
use App\Models\VpnServer;
use App\Services\TelegramService;
use App\Services\QrisDynamicService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function landing()
    {
        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            return redirect('/login');
        }
        return $this->renderLanding();
    }

    public function index()
    {
        // Mode Standalone / Self-Hosted: Langsung ke login (atau dashboard jika sudah login)
        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            if ($user = auth()->user()) {
                return redirect($user->role === 'superadmin' ? '/superadmin' : '/dashboard');
            }
            return redirect('/login');
        }

        return $this->renderLanding();
    }

    protected function renderLanding()
    {


        $host = request()->getHost();
        $appUrlHost = parse_url(config('app.url'), PHP_URL_HOST);
        $configuredBase = config('app.base_domain');
        
        if ($configuredBase && $configuredBase !== 'localhost') {
            $baseDomain = $configuredBase;
        } elseif ($appUrlHost && $appUrlHost !== 'localhost') {
            $baseDomain = $appUrlHost;
        } else {
            $baseDomain = $host;
        }

        $cleanBaseDomain = preg_replace('/^(panel|www)\./i', '', $baseDomain ?: $host);
        $subdomain = explode('.', $host)[0] ?? '';

        // Subdomain panel.* → langsung tampilkan Espace Services Cloud / VPN / Mikhmon
        if ($subdomain === 'panel') {
            if (auth('vpn')->check() || session('vpn_user_id')) {
                return redirect('/dashboard');
            }
            return redirect('/login');
        }

        // Subdomain shop.* → langsung tampilkan Toko E-Commerce
        if ($subdomain === 'shop') {
            return app(\App\Http\Controllers\ShopController::class)->index(request());
        }

        // Subdomain gateway.* → langsung tampilkan Portal NODERA PAY
        if ($subdomain === 'gateway') {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayAuthController::class)->landing();
        }

        // Subdomain wa.* atau wagateway.* → langsung tampilkan Portal WA GATEWAY
        if ($subdomain === 'wa' || $subdomain === 'wagateway') {
            return app(\App\Http\Controllers\WaGateway\WaGatewayAuthController::class)->landing();
        }

        // Tenant subdomain → tampilkan Landing Page & Toko Online Tenant
        if ($subdomain && $host !== $baseDomain && !in_array($subdomain, ['www', 'panel', 'api', 'admin', 'shop', 'gateway', 'wa', 'wagateway'])) {
            $tenant = Tenant::where('slug', $subdomain)->where('is_active', true)->first();
            if ($tenant) {
                return app(\App\Http\Controllers\TenantShopController::class)->index(request());
            }
        }

        // PWA relaunch: start_url lama = "/?source=pwa". Saat aplikasi dibuka
        // lagi, jangan tampilkan landing marketing — arahkan langsung ke panel
        // sesuai role/session aktif (pelanggan → portal tagihan).
        if (request()->query('source') === 'pwa') {
            $redirect = $this->resolvePwaEntry();
            if ($redirect) {
                return redirect($redirect);
            }
        }

        $company = Setting::company();

        $packages = Package::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where(function ($q) {
                $q->where('type', 'subscription')->orWhereNull('type');
            })
            ->where('is_active', true)
            ->orderByRaw('COALESCE(NULLIF(monthly_price, 0), price) ASC')
            ->get();

        if ($packages->isEmpty()) {
            self::seedDefaultSaasPackages();
            $packages = Package::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where(function ($q) {
                    $q->where('type', 'subscription')->orWhereNull('type');
                })
                ->where('is_active', true)
                ->orderByRaw('COALESCE(NULLIF(monthly_price, 0), price) ASC')
                ->get();
        }

        $standalonePackages = \App\Models\IspLicensePackage::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $isLocalOrIp = in_array($host, ['localhost', '127.0.0.1', '::1']) || filter_var($host, FILTER_VALIDATE_IP) || str_contains($host, 'localhost');

        if ($isLocalOrIp) {
            $defaultPanelUrl = '/vpn';
        } else {
            $scheme = (request()->isSecure() || str_starts_with(config('app.url', ''), 'https://')) ? 'https://' : 'http://';
            $defaultPanelUrl = $scheme . 'panel.' . $cleanBaseDomain;
        }

        $mikhmon = [
            'price' => (float) config('mikhmon.monthly_price'),
            'domain' => config('mikhmon.domain'),
            'panel_url' => config('app.panel_url') ?: $defaultPanelUrl,
        ];

        return view('landing', compact('company', 'packages', 'standalonePackages', 'mikhmon'));
    }

    /**
     * Tentukan pintu masuk PWA saat aplikasi dibuka ulang (start_url lama "/").
     * Mengembalikan null bila tidak ada role yang cocok.
     */
    protected function resolvePwaEntry(): ?string
    {
        if (session('customer_id')) {
            return '/portal';
        }
        if (session('collector_id') || session('admin_role') === 'collector') {
            return '/kolektor/dashboard';
        }
        if (session('technician_id') || session('admin_role') === 'technician') {
            return '/teknisi/dashboard';
        }
        if (session('vpn_user_id') || auth('vpn')->check()) {
            return '/dashboard';
        }
        if ($user = auth()->user()) {
            return $user->role === 'superadmin' ? '/superadmin' : '/dashboard';
        }

        // Tamu → buka portal pelanggan (bukan landing marketing).
        return '/portal/login';
    }

    /**
     * Format payment channel code to clean whitelabel public label.
     */
    protected function formatPublicPaymentChannelName(string $code): string
    {
        $ch = strtoupper(preg_replace('/^([a-z0-9_]+:)/i', '', $code));
        return match ($ch) {
            'QRIS'                                => 'QRIS Realtime (Semua E-Wallet & Bank)',
            'BCAVA', 'BCA_VA', 'BCA'              => 'BCA Virtual Account',
            'BNIVA', 'BNI_VA', 'BNI'              => 'BNI Virtual Account',
            'BRIVA', 'BRI_VA', 'BRI'              => 'BRI Virtual Account',
            'MANDIRIVA', 'MANDIRI_VA', 'ECHANNEL' => 'Mandiri Virtual Account',
            'PERMATAVA', 'PERMATA_VA', 'PERMATA'  => 'Permata Virtual Account',
            'CIMBVA', 'CIMB_VA', 'CIMB'           => 'CIMB Niaga Virtual Account',
            'BSIVA', 'BSI_VA', 'BSI'              => 'BSI Virtual Account',
            'ALFAMART', 'ALFAMIDI'                => 'Gerai Alfamart',
            'INDOMARET'                           => 'Gerai Indomaret',
            'SHOPEEPAY'                           => 'ShopeePay Instant',
            'GOPAY'                               => 'GoPay Instant',
            'OVO'                                 => 'OVO Instant',
            'DANA'                                => 'DANA Instant',
            'CINETPAY'                            => 'Mobile Money (Orange / MTN / Moov / Wave)',
            'WAVE'                                => 'Wave Mobile Money',
            'PAYTECH'                             => 'PayTech Mobile Money',
            'FEDAPAY'                             => 'FedaPay Mobile Money',
            default                               => !empty($ch) ? "Virtual Account {$ch}" : 'Pembayaran Otomatis Realtime',
        };
    }

    /**
     * Get platform payment timeout / expiry in minutes from superadmin settings.
     */
    protected function getPlatformPaymentExpiryMinutes(): int
    {
        $usageRecord = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', '_platform_payment_settings')
            ->first();
        return max(1, (int) ($usageRecord?->config_json['expiry_minutes'] ?? 15));
    }

    public function daftarIsp(Request $request)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        
        // Jika sedang di subdomain gateway.* → render registrasi akun NODERA PAY
        if ($host === 'gateway.' . $baseDomain || str_starts_with($host, 'gateway.')) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayAuthController::class)->showRegister($request);
        }

        // Jika sedang di subdomain wa.* atau wagateway.* → render registrasi akun WA GATEWAY
        if ($host === 'wa.' . $baseDomain || str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayAuthController::class)->showRegister($request);
        }

        // Jika sedang di subdomain panel.* → render registrasi akun panel (Services Cloud / VPN / Mikhmon)
        if ($host === 'panel.' . $baseDomain || str_starts_with($host, 'panel.')) {
            return app(\App\Http\Controllers\Vpn\AuthController::class)->showRegister($request);
        }

        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            return redirect('/login');
        }

        $packages = Package::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where(function ($q) {
                $q->where('type', 'subscription')->orWhereNull('type');
            })
            ->where('is_active', true)
            ->orderByRaw('COALESCE(NULLIF(monthly_price, 0), price) ASC')
            ->orderBy('max_customers', 'asc')
            ->get();

        if ($packages->isEmpty()) {
            self::seedDefaultSaasPackages();
            $packages = Package::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where(function ($q) {
                    $q->where('type', 'subscription')->orWhereNull('type');
                })
                ->where('is_active', true)
                ->orderByRaw('COALESCE(NULLIF(monthly_price, 0), price) ASC')
                ->orderBy('max_customers', 'asc')
                ->get();
        }

        $company = Setting::company();

        // Read Superadmin Platform Payment Settings
        $enableGateway = true;
        $enableQrisManual = false;
        $enableBankManual = false; // Platform uses 100% automated Payment Gateway

        // Rekening superadmin (Deprecated for platform)
        $bankAccounts = [];
        $qrisData = null;

        // Resolve Available Gateways & Channels
        $availableGateways = [];
        if ($enableGateway) {
            // 0. NODERA PAY (Universal Payment Gateway)
            $npGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'noderapay')->first();
            if ($npGw && ($npGw->is_active ?? true)) {
                $npCfg = $npGw->config_json ?? [];
                $npChannels = $npCfg['enabled_channels'] ?? ['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'ALFAMART', 'INDOMARET'];
                $channelList = [];
                foreach ($npChannels as $ch) {
                    $channelList[] = [
                        'code' => 'noderapay:' . $ch,
                        'name' => (strtoupper($ch) === 'QRIS' ? 'QRIS Dinamis (NODERA PAY)' : 'Virtual Account ' . strtoupper($ch)),
                        'gateway' => 'noderapay',
                        'channel' => $ch,
                    ];
                }
                $availableGateways[] = [
                    'id' => 'noderapay',
                    'name' => 'NODERA Pay (Otomatis)',
                    'channels' => $channelList,
                ];
            }

            // 1. WijayaPay
            $wpGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'wijayapay')->first();
            if ($wpGw && ($wpGw->is_active ?? true)) {
                $wpCfg = $wpGw->config_json ?? [];
                if (!empty($wpCfg['WIJAYAPAY_CODE_MERCHANT']) && !empty($wpCfg['WIJAYAPAY_API_KEY'])) {
                    $wpChannels = $wpCfg['enabled_channels'] ?? ['QRIS', 'BCAVA', 'BNIVA', 'BRIVA', 'MANDIRIVA', 'PERMATAVA', 'ALFAMART', 'INDOMARET'];
                    $channelList = [];
                    foreach ($wpChannels as $ch) {
                        $channelList[] = [
                            'code' => 'wijayapay:' . $ch,
                            'name' => $this->formatPublicPaymentChannelName($ch),
                            'gateway' => 'wijayapay',
                            'channel' => $ch,
                        ];
                    }
                    $availableGateways[] = [
                        'id' => 'wijayapay',
                        'name' => 'Pembayaran Otomatis',
                        'channels' => $channelList,
                    ];
                }
            }

            // 2. Tripay
            $tpGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'tripay')->first();
            if ($tpGw && ($tpGw->is_active ?? true)) {
                $tpCfg = $tpGw->config_json ?? [];
                if (!empty($tpCfg['TRIPAY_API_KEY']) && !empty($tpCfg['TRIPAY_MERCHANT_CODE'])) {
                    $tpChannels = $tpCfg['enabled_channels'] ?? ['QRIS', 'BRIVA', 'BCAVA', 'BNIVA', 'MANDIRIVA', 'ALFAMART', 'INDOMARET'];
                    $channelList = [];
                    foreach ($tpChannels as $ch) {
                        $channelList[] = [
                            'code' => 'tripay:' . $ch,
                            'name' => $this->formatPublicPaymentChannelName($ch),
                            'gateway' => 'tripay',
                            'channel' => $ch,
                        ];
                    }
                    $availableGateways[] = [
                        'id' => 'tripay',
                        'name' => 'Pembayaran Otomatis',
                        'channels' => $channelList,
                    ];
                }
            }

            // 3. Midtrans
            $mdGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'midtrans')->first();
            if ($mdGw && ($mdGw->is_active ?? true)) {
                $mdCfg = $mdGw->config_json ?? [];
                if (!empty($mdCfg['MIDTRANS_SERVER_KEY'])) {
                    $mdChannels = $mdCfg['enabled_channels'] ?? ['midtrans:qris', 'midtrans:shopeepay', 'midtrans:bca_va', 'midtrans:bni_va', 'midtrans:bri_va'];
                    $channelList = [];
                    foreach ($mdChannels as $ch) {
                        $channelList[] = [
                            'code' => $ch,
                            'name' => $this->formatPublicPaymentChannelName($ch),
                            'gateway' => 'midtrans',
                            'channel' => $ch,
                        ];
                    }
                    $availableGateways[] = [
                        'id' => 'midtrans',
                        'name' => 'Pembayaran Otomatis',
                        'channels' => $channelList,
                    ];
                }
            }
        }

        return Inertia::render('Register', [
            'company'             => $company,
            'app_domain'          => request()->getHost(),
            'bank_accounts'       => $bankAccounts,
            'selected_package_id' => $request->query('package_id'),
            'initial_ref'         => $request->query('ref') ?? $request->query('referral_code'),
            'qris'                => $qrisData,
            'payment_config'      => [
                'enable_gateway'     => $enableGateway,
                'enable_qris_manual' => $enableQrisManual,
                'enable_bank_manual' => $enableBankManual,
                'default_gateway'    => $usageSettings['default_gateway'] ?? 'wijayapay',
                'gateways'           => $availableGateways,
            ],
            'packages'            => $packages->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'max_customers' => (int) ($p->max_customers ?? 0),
                'monthly_price' => (float) ($p->monthly_price ?? $p->price ?? 0),
                'semi_annual_price' => (float) ($p->semi_annual_price ?? 0),
                'annual_price' => (float) ($p->annual_price ?? 0),
                'duration_options' => $p->duration_options ?? '1,3,6,12',
            ]),
        ]);
    }

    public static function seedDefaultSaasPackages(): void
    {
        $hasPackages = Package::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where(function ($q) {
                $q->where('type', 'subscription')->orWhereNull('type');
            })
            ->exists();

        if ($hasPackages) {
            return;
        }

        $defaults = [
            [
                'name' => 'Cloud Micro / RT-RW Net (150 Pelanggan)',
                'type' => 'subscription',
                'price' => 30000,
                'monthly_price' => 30000,
                'semi_annual_price' => 170000,
                'annual_price' => 320000,
                'duration_options' => '1,3,6,12',
                'max_customers' => 150,
                'max_routers' => 2,
                'is_popular' => false,
                'is_active' => true,
                'description' => 'Paket ekonomis Cloud Managed ISP RT-RW Net hingga 150 pelanggan dan 2 router MikroTik.',
            ],
            [
                'name' => 'Starter (500 Pelanggan)',
                'type' => 'subscription',
                'price' => 125000,
                'monthly_price' => 125000,
                'semi_annual_price' => 700000,
                'annual_price' => 1300000,
                'duration_options' => '1,3,6,12',
                'max_customers' => 500,
                'max_routers' => 5,
                'is_popular' => false,
                'is_active' => true,
                'description' => 'Paket Cloud Managed ISP hingga 500 pelanggan dan 5 router MikroTik.',
            ],
            [
                'name' => 'Boost (1.000 Pelanggan)',
                'type' => 'subscription',
                'price' => 250000,
                'monthly_price' => 250000,
                'semi_annual_price' => 1400000,
                'annual_price' => 2600000,
                'duration_options' => '1,6,12',
                'max_customers' => 1000,
                'max_routers' => 10,
                'is_popular' => true,
                'is_active' => true,
                'description' => 'Paket paling rekomendasi hingga 1.000 pelanggan dan 10 router & OLT.',
            ],
            [
                'name' => 'Ultra (Unlimited Pelanggan)',
                'type' => 'subscription',
                'price' => 500000,
                'monthly_price' => 500000,
                'semi_annual_price' => 2800000,
                'annual_price' => 5000000,
                'duration_options' => '1,6,12',
                'max_customers' => 0,
                'max_routers' => 0,
                'is_popular' => false,
                'is_active' => true,
                'description' => 'Kapasitas unlimited pelanggan aktif untuk ISP skala besar dan multi-tenant.',
            ],
        ];

        foreach ($defaults as $data) {
            Package::withoutGlobalScopes()->firstOrCreate(
                ['name' => $data['name'], 'tenant_id' => null],
                $data
            );
        }
    }

    public function daftarIspStore(Request $request)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);

        // Jika request datang dari subdomain gateway.* → proses registrasi akun NODERA PAY
        if ($host === 'gateway.' . $baseDomain || str_starts_with($host, 'gateway.')) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayAuthController::class)->register($request);
        }

        // Jika request datang dari subdomain wa.* atau wagateway.* → proses registrasi akun WA GATEWAY
        if ($host === 'wa.' . $baseDomain || str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayAuthController::class)->register($request);
        }

        // Jika request datang dari subdomain panel.* → proses registrasi akun panel
        if ($host === 'panel.' . $baseDomain || str_starts_with($host, 'panel.')) {
            return app(\App\Http\Controllers\Vpn\AuthController::class)->register($request);
        }

        if ($request->filled('turnstile_token') && !$request->filled('cf-turnstile-response')) {
            $request->merge(['cf-turnstile-response' => $request->input('turnstile_token')]);
        }

        $turnstileEnabled = (bool) config('services.turnstile.enabled', true) && !app()->environment('testing');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'required|string|max:100',
            'username' => 'required|string|max:50|alpha_dash|unique:users,username',
            'slug' => 'required|string|max:50|lowercase|regex:/^[a-z0-9]+$/',
            'password' => 'required|string|min:6|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:20',
            'referral_code' => 'nullable|string|max:50',
            'package_id' => 'required|exists:packages,id',
            'duration' => 'required|integer|in:1,3,6,12',
            'payment_bank' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:500',
            'payment_proof' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
                    ], [
            'name.required' => 'Nama penanggung jawab wajib diisi.',
            'company.required' => 'Nama brand / ISP wajib diisi.',
            'slug.regex' => 'Subdomain hanya boleh berisi huruf kecil dan angka tanpa spasi.',
            'slug.required' => 'Subdomain wajib diisi.',
            'username.required' => 'Username admin wajib diisi.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, dan strip.',
            'username.unique' => 'Username ini sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'package_id.required' => 'Silakan pilih paket.',
            'duration.required' => 'Silakan pilih durasi berlangganan.',
                    ]);

        // Handle payment proof upload
        $paymentProofPath = null;
        if ($request->hasFile('payment_proof') && $request->file('payment_proof')->isValid()) {
            $paymentProofPath = $request->file('payment_proof')->store('payment_proofs', 'public');
        }

        // Cek slug/subdomain tidak bentrok dengan tenant yg udah aktif, pendaftaran pending, maupun instansi Mikhmon/Pembukuan/FTTH
        $validation = \App\Services\SubdomainValidationService::checkAvailability($data['slug']);
        if (!$validation['available']) {
            return redirect()->back()->withErrors(['slug' => $validation['message']])->withInput();
        }

        $existingTenant = Tenant::where('slug', $data['slug'])->first();
        if ($existingTenant) {
            return back()->withErrors(['slug' => 'Subdomain ini sudah digunakan oleh instansi lain.'])->withInput();
        }

        // Cek pendaftaran aktif (pending dan belum expired)
        $activePendingRequest = RegistrationRequest::where('slug', $data['slug'])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($activePendingRequest) {
            return back()->withErrors(['slug' => 'Subdomain ini sedang dalam proses checkout/pendaftaran aktif.'])->withInput();
        }

        // Hapus pendaftaran lama yang sudah tidak aktif (rejected, cancelled, expired, dsb) untuk slug ini
        RegistrationRequest::where('slug', $data['slug'])
            ->whereNotIn('status', ['approved'])
            ->delete();

        // Check referral partner
        $referralCode = null;
        if (!empty($data['referral_code'])) {
            \App\Services\ReferralService::ensureTablesExist();
            $code = strtoupper(trim($data['referral_code']));
            $refPartner = \App\Models\ReferralPartner::where('referral_code', $code)
                ->where('status', 'approved')
                ->first();
            if ($refPartner) {
                $referralCode = $refPartner->referral_code;
            }
        }

        $pkg = Package::withoutGlobalScopes()->find($data['package_id']);
        $durNum = max(1, (int) ($data['duration'] ?? 1));
        $rawPrice = 0;
        if ($pkg) {
            $monthlyPrice = (float) ($pkg->monthly_price ?: ($pkg->price ?: 0));
            if ($durNum === 12 && !empty($pkg->annual_price) && (float) $pkg->annual_price > 0) {
                $rawPrice = (float) $pkg->annual_price;
            } elseif ($durNum === 6 && !empty($pkg->semi_annual_price) && (float) $pkg->semi_annual_price > 0) {
                $rawPrice = (float) $pkg->semi_annual_price;
            } else {
                $rawPrice = (float) ($monthlyPrice * $durNum);
            }
        }
        if ($rawPrice <= 0) {
            $rawPrice = 150000 * $durNum;
        }

        $discountAmount = $referralCode ? round($rawPrice * 0.10) : 0;
        $finalBasePrice = max(0, $rawPrice - $discountAmount);

        // Read Superadmin Platform Payment Settings
        $usageRecord = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', '_platform_payment_settings')->first();
        $usageSettings = $usageRecord?->config_json ?? [
            'enable_gateway' => true,
            'enable_qris_manual' => true,
            'enable_bank_manual' => true,
            'default_gateway' => 'noderapay',
        ];

        $enableGateway = (bool) ($usageSettings['enable_gateway'] ?? true);
        $enableQrisManual = (bool) ($usageSettings['enable_qris_manual'] ?? true);
        $enableBankManual = (bool) ($usageSettings['enable_bank_manual'] ?? true);
        $defaultGateway = $usageSettings['default_gateway'] ?? 'noderapay';

        $paymentMethod = $request->input('payment_method', $request->input('payment_bank', ''));
        if (empty($paymentMethod) || $paymentMethod === 'auto' || $paymentMethod === 'QRIS') {
            if ($enableGateway && ($defaultGateway === 'noderapay' || empty($defaultGateway))) {
                $paymentMethod = 'noderapay:QRIS';
            } elseif ($enableGateway && $defaultGateway === 'wijayapay') {
                $paymentMethod = 'wijayapay:QRIS';
            } elseif ($enableGateway && $defaultGateway === 'tripay') {
                $paymentMethod = 'tripay:QRIS';
            } elseif ($enableQrisManual) {
                $paymentMethod = 'qris';
            } elseif ($enableBankManual) {
                $paymentMethod = 'bank_manual';
            } else {
                $paymentMethod = 'noderapay:QRIS';
            }
        }

        $paymentBank = $data['payment_bank'] ?? 'QRIS';
        $paymentNotes = $data['payment_notes'] ?? null;
        $uniqueCode = 0;
        $dynamicQrisString = null;
        $totalAmount = $finalBasePrice;

        // 1. Platform Payment Gateway Integration (Auto-detecting active Superadmin Gateway)
        if (str_starts_with($paymentMethod, 'noderapay') || str_starts_with($paymentMethod, 'wijayapay') || str_starts_with($paymentMethod, 'tripay') || str_starts_with($paymentMethod, 'midtrans') || $paymentMethod === 'qris' || $paymentMethod === 'qris_manual' || $paymentBank === 'QRIS' || str_starts_with(strtoupper($paymentBank), 'QRIS')) {
            $refId = 'REG-' . $data['slug'] . '-' . time();
            $preferredGw = null;
            if (str_starts_with($paymentMethod, 'noderapay')) $preferredGw = 'noderapay';
            elseif (str_starts_with($paymentMethod, 'wijayapay')) $preferredGw = 'wijayapay';
            elseif (str_starts_with($paymentMethod, 'tripay')) $preferredGw = 'tripay';
            elseif (str_starts_with($paymentMethod, 'midtrans')) $preferredGw = 'midtrans';

            $pltRes = \App\Services\PlatformPaymentService::createPlatformTransaction([
                'ref_id'         => $refId,
                'amount'         => (float) $totalAmount,
                'payment_method' => 'qris',
                'customer_name'  => $data['name'] ?? $data['company'],
                'customer_email' => $data['email'] ?? '',
                'customer_phone' => $data['phone'] ?? '',
                'description'    => 'Registrasi Tenant ' . ($data['company'] ?? $data['name']),
                'preferred_gw'   => $preferredGw,
                'callback_url'   => url('/api/webhook/' . ($preferredGw ?: 'wijayapay')),
            ]);

            if ($pltRes['success']) {
                $dynamicQrisString = $pltRes['dynamic_qris'] ?? ($pltRes['checkout_url'] ?? null);
                if (!empty($pltRes['total_amount'])) {
                    $totalAmount = (float) $pltRes['total_amount'];
                }
                $paymentBank = $pltRes['bank_destination'] ?? 'Payment Gateway (QRIS)';
                $paymentNotes = $pltRes['admin_note'] ?? ("Ref: " . ($pltRes['trx_reference'] ?? $refId));
            } else {
                $paymentBank = 'Payment Gateway (QRIS)';
                $paymentNotes = 'Payment Gateway belum aktif / transaksi tertunda.';
            }
        }
        // 2. Transfer Bank Manual Superadmin
        else {
            $paymentBank = $data['payment_bank'] ?? 'Transfer Bank Manual';
        }

        $expiryMin = $this->getPlatformPaymentExpiryMinutes();
        $expiresAt = now()->addMinutes($expiryMin);

        $regReq = RegistrationRequest::create([
            'name'                => $data['name'],
            'company'             => $data['company'],
            'slug'                => $data['slug'],
            'username'            => $data['username'],
            'password_hash'       => bcrypt($data['password']),
            'email'               => $data['email'],
            'phone'               => $data['phone'],
            'package_id'          => $data['package_id'],
            'duration'            => $durNum,
            'referral_code'       => $referralCode ?: ($data['referral_code'] ?? null),
            'payment_proof'       => $paymentProofPath,
            'payment_bank'        => $paymentBank,
            'payment_notes'       => $paymentNotes,
            'unique_code'         => $uniqueCode,
            'total_amount'        => $totalAmount,
            'dynamic_qris_string' => $dynamicQrisString,
            'expires_at'          => $expiresAt,
            'status'              => 'pending',
        ]);

        // Kirim notifikasi Telegram ke Superadmin untuk setiap pendaftaran tenant baru (QRIS maupun Transfer Bank)
        try {
            $telegram = app(\App\Services\TelegramService::class);
            if ($telegram->isConfigured()) {
                $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
                $pkgName = $pkg ? $pkg->name : 'Paket ISP';
                $paymentMethodDesc = !empty($data['payment_bank']) && !str_starts_with(strtoupper($data['payment_bank']), 'QRIS')
                    ? "Transfer Bank (" . htmlspecialchars($data['payment_bank']) . ")"
                    : "QRIS Dinamis (Kode Unik: {$uniqueCode})";

                $msg = "<b>PENDAFTARAN TENANT BARU — NODERA</b>\n\n"
                    . "┌ Detail Calon Tenant\n"
                    . "├ Nama: <code>" . htmlspecialchars($data['name']) . "</code>\n"
                    . "├ Brand / ISP: <code>" . htmlspecialchars($data['company']) . "</code>\n"
                    . "├ Subdomain: <code>" . htmlspecialchars($data['slug']) . ".{$baseDomain}</code>\n"
                    . "├ Paket: " . htmlspecialchars($pkgName) . " ({$durNum} Bulan)\n"
                    . "├ Total Tagihan: Rp " . number_format($totalAmount, 0, ',', '.') . "\n"
                    . "├ Metode Bayar: <code>" . $paymentMethodDesc . "</code>\n"
                    . "├ WhatsApp: <code>" . htmlspecialchars($data['phone']) . "</code>\n"
                    . "├ Email: <code>" . htmlspecialchars($data['email']) . "</code>\n"
                    . "├ Status: MENUNGGU PERSETUJUAN / PEMBAYARAN\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s') . "\n\n"
                    . "<i>Klik tombol di bawah untuk menyetujui (ACC) atau menolak pendaftaran ini secara langsung.</i>";

                $keyboard = $telegram->inlineKeyboard([[
                    $telegram->inlineButton('✅ Setujui / ACC', 'reg_approve:' . $regReq->slug),
                    $telegram->inlineButton('❌ Tolak / Reject', 'reg_reject:' . $regReq->slug),
                ]]);

                $res = $telegram->sendAdminNotification($msg, 'registration', 'HTML', $keyboard);
                $msgId = $res['result']['message_id'] ?? ($res['message_id'] ?? null);
                $chatId = $res['result']['chat']['id'] ?? ($res['chat_id'] ?? null);

                if (!empty($msgId)) {
                    $regReq->update([
                        'telegram_message_id' => (string) $msgId,
                        'telegram_chat_id'    => (string) ($chatId ?: ''),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[LandingController] Telegram notification for registration failed: ' . $e->getMessage());
        }

        session()->flash('reg_slug', $data['slug']);
        session()->flash('reg_company', Setting::company());
        session()->flash('reg_domain', request()->getHost());

        return redirect('/register/sukses?slug=' . urlencode($data['slug']));
    }

    public function registerSukses(Request $request)
    {
        $slug = session('reg_slug') ?: $request->query('slug');
        if (! $slug) {
            return redirect('/register');
        }

        $reg = RegistrationRequest::with('package')->where('slug', $slug)->first();
        if (! $reg) {
            return redirect('/register');
        }

        $paymentBankStr = strtoupper($reg->payment_bank ?? '');
        $isGatewayOrder = str_contains($paymentBankStr, 'NODERAPAY')
            || str_contains($paymentBankStr, 'NODERA PAY')
            || str_contains($paymentBankStr, 'WIJAYAPAY')
            || str_contains($paymentBankStr, 'TRIPAY')
            || str_contains($paymentBankStr, 'MIDTRANS')
            || str_contains($paymentBankStr, 'DUITKU')
            || str_contains($paymentBankStr, 'XENDIT')
            || str_contains($paymentBankStr, 'PAYDISINI')
            || str_contains($paymentBankStr, 'PAKASIR')
            || str_contains($paymentBankStr, 'CINETPAY')
            || str_contains($paymentBankStr, 'WAVE')
            || str_contains($paymentBankStr, 'PAYTECH')
            || str_contains($paymentBankStr, 'FEDAPAY');

        $isManualBank = !empty($reg->payment_bank)
            && !str_contains($paymentBankStr, 'QRIS')
            && !$isGatewayOrder;

        $merchantInfo = $reg->dynamic_qris_string ? QrisDynamicService::extractMerchantInfo($reg->dynamic_qris_string) : [];
        $qrSvg = $reg->dynamic_qris_string ? QrisDynamicService::generateQrSvg($reg->dynamic_qris_string) : null;

        $domain = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $domain);
        $loginUrl = 'https://' . $reg->slug . '.' . $baseDomain . '/login';
        if (app()->environment('local') || str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1')) {
            $loginUrl = '/login';
        }

        $phone = Setting::getSuperadminPhone();
        $secondsLeft = max(0, (int) round(now()->diffInSeconds($reg->expires_at, false)));

        return Inertia::render('RegisterSukses', [
            'slug'          => $reg->slug,
            'company'       => Setting::company(),
            'app_domain'    => session('reg_domain', request()->getHost()),
            'wa_number'     => Setting::waNumber($phone),
            'expiry_minutes'=> $this->getPlatformPaymentExpiryMinutes(),
            'registration'  => [
                'id'             => $reg->id,
                'order_id'       => $reg->order_number,
                'slug'           => $reg->slug,
                'name'           => $reg->name,
                'company'        => $reg->company,
                'username'       => $reg->username,
                'email'          => $reg->email,
                'phone'          => $reg->phone,
                'status'         => $reg->status,
                'payment_bank'   => $reg->payment_bank,
                'is_manual_bank' => $isManualBank,
                'is_paid'        => $reg->isPaid(),
                'total_amount'   => (float) $reg->total_amount,
                'unique_code'    => (int) $reg->unique_code,
                'expires_at'     => $reg->expires_at?->toIso8601String(),
                'seconds_left'   => $secondsLeft,
            ],
            'package'       => [
                'id'            => $reg->package?->id,
                'name'          => $reg->package?->name ?? 'Paket ISP Billing',
                'duration'      => (int) $reg->duration,
                'monthly_price' => (float) ($reg->package?->monthly_price ?? 0),
            ],
            'qris'          => [
                'qr_svg'           => $qrSvg,
                'merchant_name'    => $merchantInfo['merchant_name'] ?? 'CV. DIGITAL NETWORK SOLUTION',
                'merchant_city'    => $merchantInfo['merchant_city'] ?? 'SITUBONDO',
                'nmid'             => $merchantInfo['nmid'] ?? 'ID1024366211885',
                'amount'           => (float) $reg->total_amount,
                'amount_formatted' => 'Rp ' . number_format((float) $reg->total_amount, 0, ',', '.'),
            ],
            'login_url'     => $loginUrl,
            'status_url'    => "/register/status/{$reg->slug}",
            'bank_accounts' => \App\Http\Controllers\ShopController::resolveSuperadminBankAccounts(),
        ]);
    }

    public function cancelRegistration(Request $request, string $slug)
    {
        $reg = RegistrationRequest::with('package')->where('slug', $slug)->first();
        if (! $reg) {
            return response()->json(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan.'], 404);
        }

        if ($reg->status === 'pending') {
            // Cek status aktif ke Payment Gateway sebelum membatalkan agar jika user sudah bayar langsung diapprove!
            $npTx = \App\Models\NoderaPayMerchantTransaction::where('ref_id', 'like', "REG-{$reg->slug}%")
                ->orWhere(function ($q) use ($reg) {
                    if (!empty($reg->payment_notes) && preg_match('/Ref:\s*([A-Za-z0-9\-]+)/', $reg->payment_notes, $m)) {
                        $q->where('trx_reference', $m[1]);
                    }
                })
                ->latest('id')
                ->first();

            if ($npTx) {
                try {
                    $engine = app(\App\Services\NoderaPayEngineService::class);
                    $engine->syncTransactionStatus($npTx);
                    $npTx->refresh();
                } catch (\Throwable $e) {}
            }

            if ($npTx && in_array(strtolower((string) $npTx->status), ['paid', 'success', 'berhasil', 'settlement', 'settled', 'completed'])) {
                $reg->update([
                    'paid_at' => now(),
                    'status'  => 'approved',
                ]);
                (new \App\Actions\Tenant\ApproveRegistration)->execute($reg);
                $reg = $reg->fresh();
                return response()->json([
                    'success' => true,
                    'is_paid' => true,
                    'status'  => $reg->status,
                    'message' => 'Pembayaran telah terverifikasi sukses.',
                ]);
            }

            $reg->update(['status' => 'cancelled']);
            RegistrationRequest::retractAndNotifyCancellation(
                $reg,
                'Pendaftar telah membatalkan proses checkout / menutup halaman pembayaran. Tombol persetujuan ACC / Tolak telah ditarik dan dinonaktifkan.'
            );
        }

        return response()->json([
            'success' => true,
            'status'  => $reg->status,
            'message' => 'Pendaftaran berhasil dibatalkan.',
        ]);
    }

    public function checkRegistrationStatus(string $slug)
    {
        $reg = RegistrationRequest::with('package')->where('slug', $slug)->first();
        if (! $reg) {
            return response()->json(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan.'], 404);
        }

        // Active Sync: Check if linked NODERA PAY / WijayaPay merchant transaction is paid
        if ($reg->status === 'pending') {
            $npTx = \App\Models\NoderaPayMerchantTransaction::where('ref_id', 'like', "REG-{$reg->slug}%")
                ->orWhere(function ($q) use ($reg) {
                    if (!empty($reg->payment_notes) && preg_match('/Ref:\s*([A-Za-z0-9\-]+)/', $reg->payment_notes, $m)) {
                        $q->where('trx_reference', $m[1]);
                    }
                })
                ->latest('id')
                ->first();

            if ($npTx && $npTx->status === 'paid') {
                $reg->update([
                    'paid_at' => now(),
                    'status'  => 'approved',
                ]);
                (new \App\Actions\Tenant\ApproveRegistration)->execute($reg);
                $reg = $reg->fresh();
            }
        }

        // Auto expire if pending and past expiration time
        if ($reg->status === 'pending' && $reg->expires_at && $reg->expires_at->isPast()) {
            $reg->update(['status' => 'expired']);
            RegistrationRequest::retractAndNotifyCancellation(
                $reg,
                'Waktu pembayaran QRIS telah habis (Kedaluwarsa).'
            );
        }

        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
        $domain = request()->getHost();
        $loginUrl = 'https://' . $reg->slug . '.' . $baseDomain . '/login';
        if (app()->environment('local') || str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1')) {
            $loginUrl = '/login';
        }

        return response()->json([
            'success'   => true,
            'status'    => $reg->status,
            'is_paid'   => $reg->isPaid(),
            'login_url' => $loginUrl,
            'amount'    => (float) $reg->total_amount,
        ]);
    }

    public function regenerateRegistrationQris(string $slug)
    {
        $reg = RegistrationRequest::where('slug', $slug)->first();
        if (! $reg || $reg->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat digenerate ulang.'], 400);
        }

        $baseAmount = ($reg->total_amount && $reg->unique_code)
            ? ($reg->total_amount - $reg->unique_code)
            : (float) ($reg->total_amount ?: 150000);

        $pltRes = \App\Services\PlatformPaymentService::createPlatformTransaction([
            'ref_id'         => 'REG-' . $reg->slug . '-' . time(),
            'amount'         => (float) $baseAmount,
            'payment_method' => 'qris',
            'customer_name'  => $reg->name,
            'customer_email' => $reg->email,
            'customer_phone' => $reg->phone ?? '',
            'description'    => 'Registrasi NODERA ' . $reg->company,
        ]);

        if ($pltRes['success'] && !empty($pltRes['dynamic_qris'])) {
            $dynamicString = $pltRes['dynamic_qris'];
            $totalAmount = (float) ($pltRes['total_amount'] ?? $baseAmount);
            $expiryMin = $this->getPlatformPaymentExpiryMinutes();
            $expiresAt = $pltRes['expires_at'] ?? now()->addMinutes($expiryMin);

            $reg->update([
                'total_amount'        => $totalAmount,
                'dynamic_qris_string' => $dynamicString,
                'expires_at'          => $expiresAt,
            ]);

            return response()->json([
                'success'          => true,
                'qr_svg'           => QrisDynamicService::generateQrSvg($dynamicString),
                'amount'           => $totalAmount,
                'amount_formatted' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                'unique_code'      => null,
                'expires_at'       => is_string($expiresAt) ? $expiresAt : $expiresAt->toIso8601String(),
                'seconds_left'     => $expiryMin * 60,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Payment Gateway belum aktif / gagal membuat transaksi.'], 422);
    }

    public function vpn()
    {
        if (auth('vpn')->check() || session('vpn_user_id')) {
            return request()->routeIs('panel.*') ? redirect('/dashboard') : redirect('/vpn/dashboard');
        }
        return request()->routeIs('panel.*') ? redirect('/login') : redirect('/vpn/login');
    }

    public function manifest(Request $request)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
        $referer = $request->header('referer', '');
        $refererPath = parse_url($referer, PHP_URL_PATH) ?? '';

        $name = 'NODERA — Billing & ISP Management';
        $shortName = 'NODERA';
        $description = 'Platform Manajemen Billing ISP & Remote VPN Multi-Tenant';

        // Deteksi tenant subdomain & role user aktif
        $tenantId = $request->attributes->get('tenant_id') ?? session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $tenantName = $tenant ? $tenant->name : null;

        $user = auth()->user();
        $role = session('admin_role') ?? ($user ? $user->role : null);

        $isVpnUser = auth('vpn')->check() || session('vpn_user_id');
        $isPanelDomain = ($host === 'panel.'.$baseDomain || str_starts_with($host, 'panel.'));

        $currentUri = $request->getRequestUri();
        $isGatewayDomain = ($host === 'gateway.'.$baseDomain || str_starts_with($host, 'gateway.') || str_contains($host, 'gateway.') || str_contains($refererPath, '/noderapay') || str_contains($currentUri, '/noderapay'));
        $isWaDomain = ($host === 'wa.'.$baseDomain || str_starts_with($host, 'wa.') || str_contains($host, 'wa.') || str_contains($refererPath, '/wagateway') || str_contains($currentUri, '/wagateway'));

        $roleQuery = strtolower((string) ($request->query('role') ?? $request->query('type') ?? ''));
        $isGateway = $isGatewayDomain || $roleQuery === 'gateway' || $roleQuery === 'noderapay' || session('noderapay_merchant_id');
        $isWaGateway = $isWaDomain || $roleQuery === 'wagateway' || session('wa_merchant_id');
        $isCollector = $roleQuery === 'kolektor' || session('collector_id') || $role === 'collector' || str_contains($refererPath, '/kolektor') || str_contains($currentUri, '/kolektor');
        $isTechnician = $roleQuery === 'teknisi' || session('technician_id') || $role === 'technician' || str_contains($refererPath, '/teknisi') || str_contains($currentUri, '/teknisi');
        $isCustomer = $roleQuery === 'customer' || session('customer_id') || str_contains($refererPath, '/portal') || str_contains($currentUri, '/portal') || str_contains($refererPath, '/pelanggan');
        $isCashier = $roleQuery === 'cashier' || session('cashier_id') || str_contains($refererPath, '/cashier') || str_contains($currentUri, '/cashier');

        $scope = '/';
        $pwaId = '/?pwa=admin';

        if ($role === 'superadmin') {
            $startUrl = '/login';
            $pwaId = '/?pwa=superadmin';
            $scope = '/';
            $name = 'NODERA SUPERADMIN';
            $shortName = 'NODERA SUPERADMIN';
            $description = 'Panel Pusat Superadmin NODERA';
        } elseif ($isGateway) {
            $isGatewaySubdomain = str_starts_with($host, 'gateway.') || str_contains($host, 'gateway.');
            $startUrl = session('noderapay_merchant_id') ? ($isGatewaySubdomain ? '/dashboard' : '/noderapay/dashboard') : ($isGatewaySubdomain ? '/login' : '/noderapay/login');
            $pwaId = '/?pwa=noderapay';
            $scope = '/';
            $name = 'NODERA PAY';
            $shortName = 'NODERA PAY';
            $description = 'Universal Payment Gateway & Merchant Portal NODERA PAY';
        } elseif ($isWaGateway) {
            $isWaSubdomain = str_starts_with($host, 'wa.') || str_contains($host, 'wa.');
            $startUrl = session('wa_merchant_id') ? ($isWaSubdomain ? '/dashboard' : '/wagateway/dashboard') : ($isWaSubdomain ? '/login' : '/wagateway/login');
            $pwaId = '/?pwa=wagateway';
            $scope = '/';
            $name = 'NODERA WA';
            $shortName = 'NODERA WA';
            $description = 'Multi-Device WhatsApp Gateway NODERA';
        } elseif ($isCollector) {
            $startUrl = (session('collector_id') || $role === 'collector') ? '/kolektor/dashboard' : '/kolektor/login';
            $pwaId = '/kolektor';
            $scope = '/';
            $name = 'NODERA KOLEKTOR';
            $shortName = 'NODERA KOLEKTOR';
            $description = 'Aplikasi Penagihan Kolektor NODERA';
        } elseif ($isTechnician) {
            $startUrl = (session('technician_id') || $role === 'technician') ? '/teknisi/dashboard' : '/teknisi/login';
            $pwaId = '/teknisi';
            $scope = '/';
            $name = 'NODERA TEKNISI';
            $shortName = 'NODERA TEKNISI';
            $description = 'Aplikasi Operasional Teknisi NODERA';
        } elseif ($isCustomer) {
            $startUrl = '/portal';
            $pwaId = '/portal';
            $scope = '/';
            $name = 'NODERA PELANGGAN';
            $shortName = 'NODERA PELANGGAN';
            $description = 'Aplikasi Portal Pelanggan & Tagihan NODERA';
        } elseif ($isCashier) {
            $startUrl = (session('cashier_id') || $role === 'cashier') ? '/cashier/dashboard' : '/cashier/login';
            $pwaId = '/cashier';
            $scope = '/';
            $name = 'NODERA KASIR';
            $shortName = 'NODERA KASIR';
            $description = 'Aplikasi Kasir Pembayaran NODERA';
        } elseif ($isPanelDomain) {
            $startUrl = '/login';
            $pwaId = '/?pwa=panel';
            $scope = '/';
            $name = 'NODERA PANEL';
            $shortName = 'NODERA PANEL';
            $description = 'Panel VPN & Remote Management NODERA';
        } elseif ($isVpnUser) {
            $startUrl = '/login';
            $pwaId = '/?pwa=vpn';
            $scope = '/';
            $name = 'NODERA VPN';
            $shortName = 'NODERA VPN';
            $description = 'Panel Remote VPN & Mikhmon Online';
        } else {
            $startUrl = '/login';
            $pwaId = '/?pwa=admin';
            $scope = '/';
            $name = 'NODERA ADMIN';
            $shortName = 'NODERA ADMIN';
            $description = 'Panel Manajemen Billing ISP NODERA';
        }

        $manifest = [
            'id' => $pwaId,
            'name' => $name,
            'short_name' => $shortName,
            'description' => $description,
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'background_color' => '#10141A',
            'theme_color' => '#10141A',
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => '/images/logo.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/logo.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/logo.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Panel Admin',
                    'short_name' => 'Admin',
                    'description' => 'Buka panel utama billing',
                    'url' => '/dashboard',
                    'icons' => [['src' => '/images/logo.png', 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Panel Kolektor',
                    'short_name' => 'Kolektor',
                    'description' => 'Buka login kolektor',
                    'url' => '/kolektor/login',
                    'icons' => [['src' => '/images/logo.png', 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Panel Teknisi',
                    'short_name' => 'Teknisi',
                    'description' => 'Buka login teknisi',
                    'url' => '/teknisi/login',
                    'icons' => [['src' => '/images/logo.png', 'sizes' => '192x192']],
                ],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    public function guide(Request $request)
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        if ($host === 'gateway.' . $baseDomain || str_starts_with($host, 'gateway.')) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayDashboardController::class)->docs();
        }

        if ($host === 'wa.' . $baseDomain || str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayDashboardController::class)->docs();
        }

        if ($request->has('pdf') || $request->has('download')) {
            return $this->guidePdf($request);
        }
        $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
        $company = $companySetting?->value ?? [];

        return redirect('/#ecosystem');
    }

    public function guidePdf(Request $request)
    {
        $viewName = view()->exists('panduan-handbook') ? 'panduan-handbook' : (view()->exists('guide-handbook') ? 'guide-handbook' : null);
        if (!$viewName) {
            abort(404, 'Guide handbook view not found.');
        }
        try {
            $fontPath = storage_path('fonts');
            if (!file_exists($fontPath)) {
                @mkdir($fontPath, 0775, true);
            }

            $pdf = app('dompdf.wrapper')->loadView($viewName);
            $pdf->setPaper('a4', 'portrait');
            $pdf->getDomPDF()->getOptions()->set('isRemoteEnabled', true);
            $pdf->getDomPDF()->getOptions()->set('isHtml5ParserEnabled', true);
            $pdf->getDomPDF()->getOptions()->set('fontDir', $fontPath);
            $pdf->getDomPDF()->getOptions()->set('fontCache', $fontPath);

            $filename = 'Buku-Panduan-SOP-NODERA.pdf';

            if ($request->has('stream')) {
                return $pdf->stream($filename);
            }

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            Log::warning('[LandingController] guidePdf error: ' . $e->getMessage());
            return view($viewName);
        }
    }

    public function robots(Request $request)
    {
        $host = preg_replace('/^www\./i', '', $request->getHost());
        $baseUrl = 'https://' . $host;

        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Allow: /register\n";
        $content .= "Allow: /docs\n";
        $content .= "Allow: /terms\n";
        $content .= "Allow: /privacy\n";
        $content .= "Allow: /software-billing-isp\n";
        $content .= "Allow: /billing-rt-rw-net\n";
        $content .= "Allow: /billing-mikrotik\n";
        $content .= "Allow: /monitoring-mikrotik\n";
        $content .= "Allow: /nms-olt\n";
        $content .= "Allow: /gis-fiber-optic\n";
        $content .= "Allow: /mikhmon-online\n";
        $content .= "Allow: /nodera-pay\n";
        $content .= "Allow: /harga\n";
        $content .= "Allow: /blog\n";
        $content .= "Allow: /blog/*\n";
        $content .= "Allow: /tools\n";
        $content .= "Allow: /tools/*\n";
        $content .= "Allow: /downloads\n";
        $content .= "Allow: /sitemap.xml\n\n";

        $content .= "# Protected panels & internal endpoints\n";
        $content .= "Disallow: /admin\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /superadmin\n";
        $content .= "Disallow: /superadmin/\n";
        $content .= "Disallow: /teknisi\n";
        $content .= "Disallow: /teknisi/\n";
        $content .= "Disallow: /kolektor\n";
        $content .= "Disallow: /kolektor/\n";
        $content .= "Disallow: /cashier\n";
        $content .= "Disallow: /cashier/\n";
        $content .= "Disallow: /portal\n";
        $content .= "Disallow: /portal/\n";
        $content .= "Disallow: /pelanggan\n";
        $content .= "Disallow: /pelanggan/\n";
        $content .= "Disallow: /vpn\n";
        $content .= "Disallow: /vpn/\n";
        $content .= "Disallow: /topup\n";
        $content .= "Disallow: /topup/\n";
        $content .= "Disallow: /dashboard\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /webhook/\n";
        $content .= "Disallow: /register/status/\n";
        $content .= "Disallow: /register/sukses\n";
        $content .= "Disallow: /register/cancel/\n";
        $content .= "Disallow: /storage/\n";
        $content .= "Disallow: /uploads/\n";
        $content .= "Disallow: /secure-storage/\n\n";

        $content .= "# Mikhmon standalone instances\n";
        $content .= "Disallow: /hotspot-*/\n";
        $content .= "Disallow: /mikhmon-*/\n";
        $content .= "Disallow: /kas-*/\n\n";

        $content .= "Sitemap: " . $baseUrl . "/sitemap.xml\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap(Request $request)
    {
        $host = preg_replace('/^www\./i', '', $request->getHost());
        if ($host === 'localhost' || empty($host) || $host === '127.0.0.1') {
            $baseUrl = rtrim(config('app.url', 'https://dgtlnetsolution.com'), '/');
        } else {
            $baseUrl = 'https://' . $host;
        }

        $now = now()->toAtomString();

        $urls = [
            ['loc' => $baseUrl . '/', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $baseUrl . '/software-billing-isp', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/billing-rt-rw-net', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/billing-mikrotik', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/monitoring-mikrotik', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/nms-olt', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/gis-fiber-optic', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/mikhmon-online', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/nodera-pay', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/harga', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/register', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/docs', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/blog', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $baseUrl . '/terms', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $baseUrl . '/privacy', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $baseUrl . '/tools', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/tools/loadbalance', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/tools/hotspot-pppoe', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/tools/game', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/tools/stream', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/tools/speedtest', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/tools/port-forward', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/tools/burst-qos', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . '/tools/security', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];

        // Append 20 Blog Articles
        $blogArticles = \App\Http\Controllers\SeoController::getBlogArticles();
        foreach ($blogArticles as $slug => $art) {
            $urls[] = [
                'loc' => $baseUrl . '/blog/' . $slug,
                'lastmod' => date('c', strtotime($art['date'])),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
            $xml .= "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function terms()
    {
        $host = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        if ($host === 'gateway.' . $baseDomain || str_starts_with($host, 'gateway.') || request()->is('noderapay*')) {
            return redirect('/docs');
        }

        $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
        $company = $companySetting?->value ?? [];

        if (view()->exists('terms')) {
            return view('terms', compact('company'));
        }

        return Inertia::render('Terms', [
            'company' => $company,
            'app_domain' => config('app.base_domain') ?: request()->getHost(),
        ]);
    }

    public function privacy()
    {
        $host = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        if ($host === 'gateway.' . $baseDomain || str_starts_with($host, 'gateway.') || request()->is('noderapay*')) {
            return redirect('/docs');
        }

        $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
        $company = $companySetting?->value ?? [];

        if (view()->exists('privacy')) {
            return view('privacy', compact('company'));
        }

        return Inertia::render('Privacy', [
            'company' => $company,
            'app_domain' => config('app.base_domain') ?: request()->getHost(),
        ]);
    }
}
