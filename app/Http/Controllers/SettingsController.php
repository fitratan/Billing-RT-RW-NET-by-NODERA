<?php

namespace App\Http\Controllers;

use App\Http\Controllers\QRISController;
use App\Models\BankAccount;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnTransaction;
use App\Models\VpnUser;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Show settings form
     */
    public function index()
    {
        $role = session('admin_role');

        $keys = [
            'WHATSAPP_PROVIDER', 'WHATSAPP_API_URL', 'WHATSAPP_TOKEN', 'WHATSAPP_SENDER_PHONE', 'WHATSAPP_AUTO_TYPING', 'WHATSAPP_VERIFY_TOKEN',
            'GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD', 'GENIEACS_TOKEN',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_ADMIN_CHAT_IDS',
            'COMPANY_NAME', 'COMPANY_ADDRESS', 'COMPANY_PHONE', 'COMPANY_EMAIL',
            'ISOLIR_HOUR', 'ISOLIR_MINUTE', 'COLLECTOR_COMMISSION_PER_INVOICE',
        ];

        // Get current base URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = "{$protocol}://{$host}";

        // Get all settings
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if ($tenantId) {
            $allSettings = DB::table('settings')
                ->whereIn('key', $keys)
                ->where('tenant_id', $tenantId)
                ->pluck('value', 'key')->toArray();
        } else {
            $allSettings = DB::table('settings')
                ->whereIn('key', $keys)
                ->whereNull('tenant_id')
                ->pluck('value', 'key')->toArray();
        }

        $data = [];
        foreach ($keys as $k) {
            $data[$k] = $allSettings[$k] ?? '';
        }

        $data['webhookUrls'] = [
            'whatsapp' => "{$baseUrl}/webhook/whatsapp",
            'payment'  => "{$baseUrl}/webhook/payment",
            'midtrans' => "{$baseUrl}/webhook/midtrans",
            'telegram' => "{$baseUrl}/webhook/telegram",
        ];
        $data['baseUrl'] = $baseUrl;

        $adminId = session('admin_id') ?? auth()->id();
        $user = $adminId ? \App\Models\User::withoutGlobalScopes()->find($adminId) : auth()->user();
        if (!$user && session('technician_id')) {
            $user = \App\Models\User::withoutGlobalScopes()->find(session('technician_id'));
        }
        if (!$user && session('collector_id')) {
            $user = \App\Models\User::withoutGlobalScopes()->find(session('collector_id'));
        }
        $data['adminUser'] = $user;
        $data['user'] = $user;

        // Fetch Webhook Logs (Only for Admin)
        $data['webhookLogs'] = [];
        if ($role === 'admin') {
            try {
                $data['webhookLogs'] = DB::table('webhook_logs')
                    ->orderBy('created_at', 'DESC')
                    ->limit(50)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // Silently fail
            }
        }

        // Load tenant info for package/expiry/quota
        $tenant = null;
        $packageName = 'Enterprise Edition';
        $customersCount = 0;
        $maxCustomers = 0;
        $routersCount = 0;
        $maxRouters = 0;

        $tenantId = session('tenant_id') ?? ($user?->tenant_id) ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                $packageName = $tenant->package_name;
                $maxCustomers = (int) $tenant->max_customers;
                $maxRouters = (int) $tenant->max_routers;
                $customersCount = \App\Models\Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
                $routersCount = \App\Models\Mikrotik::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            }
        }
        $data['tenant'] = $tenant;
        $data['packageName'] = $packageName;
        $data['customersCount'] = $customersCount;
        $data['maxCustomers'] = $maxCustomers;
        $data['routersCount'] = $routersCount;
        $data['maxRouters'] = $maxRouters;

        // Load tenant bank accounts
        $data['bankAccounts'] = [];
        if ($tenantId) {
            $data['bankAccounts'] = \App\Models\BankAccount::where('tenant_id', $tenantId)
                ->orderBy('sort_order')->orderBy('bank_name')->get();
        }

        $globalCompany = \App\Models\Setting::company();
        $superadminPhone = $globalCompany['phone_wa'] ?? $globalCompany['phone'] ?? '6285155173547';

        // Load linked VpnUser
        $vpnUser = null;
        if ($tenant) {
            $vpnUserId = $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null);
            if ($vpnUserId) {
                $vpnUser = VpnUser::find($vpnUserId);
            }
            if (!$vpnUser && ($tenant->email || $tenant->phone || $user?->email || $user?->phone)) {
                $vpnUser = VpnUser::where(function ($q) use ($tenant, $user) {
                    if ($tenant->email) $q->where('email', $tenant->email);
                    if ($tenant->phone) $q->orWhere('phone', $tenant->phone);
                    if ($user?->email) $q->orWhere('email', $user->email);
                    if ($user?->phone) $q->orWhere('phone', $user->phone);
                })->first();
                if ($vpnUser && !$tenant->vpn_user_id) {
                    $tenant->update(['vpn_user_id' => $vpnUser->id]);
                }
            }
        }

        // Available SaaS subscription packages
        $availablePackages = Package::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where(function ($q) {
                $q->where('type', 'subscription')->orWhereNull('type');
            })
            ->where('is_active', true)
            ->orderByRaw('COALESCE(NULLIF(monthly_price, 0), price) ASC')
            ->orderBy('max_customers', 'asc')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'max_customers' => (int) ($p->max_customers ?? 0),
                'max_routers' => (int) ($p->max_routers ?? 0),
                'monthly_price' => (float) ($p->monthly_price ?? $p->price ?? 0),
                'semi_annual_price' => (float) ($p->semi_annual_price ?? 0),
                'annual_price' => (float) ($p->annual_price ?? 0),
                'duration_options' => $p->duration_options ?? '1,3,6,12',
                'description' => $p->description ?? '',
            ]);

        // Superadmin global payment accounts (tenant_id IS NULL)
        $superadminBankAccounts = BankAccount::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'account_name' => $b->account_name,
            ]);

        $globalQrisGateway = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', 'manual')
            ->first();
        $qrisConfig = $globalQrisGateway?->config_json ?? [];
        $qrisImage = QRISController::resolveQrisImageUrl($qrisConfig);
        $superadminQris = $qrisImage ? [
            'image' => $qrisImage,
            'text'  => $qrisConfig['qris_text'] ?? null,
        ] : null;

        $host = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        $scheme = (request()->isSecure() || str_starts_with(config('app.url', ''), 'https://')) ? 'https://' : 'http://';
        $panelUrl = $scheme . 'panel.' . $baseDomain;

        // Topup requests history for current tenant master wallet
        $topupHistory = [];
        $pendingTopup = null;
        if ($vpnUser) {
            $topupRequests = collect([])->where('vpn_user_id', $vpnUser->id)
                ->latest()
                ->limit(15)
                ->get();

            $topupHistory = $topupRequests->map(fn ($t) => [
                'id' => $t->id,
                'invoice_number' => $t->invoice_number,
                'amount' => (float) $t->amount,
                'total_amount' => (float) ($t->total_amount ?: $t->amount),
                'unique_code' => (int) ($t->unique_code ?? 0),
                'bank_destination' => $t->bank_destination,
                'status' => $t->status,
                'admin_note' => $t->admin_note,
                'created_at' => $t->created_at?->toIso8601String(),
                'verified_at' => $t->verified_at?->toIso8601String(),
            ])->toArray();

            $pendingTopupModel = collect([])->where('vpn_user_id', $vpnUser->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($pendingTopupModel) {
                $pendingTopup = [
                    'id' => $pendingTopupModel->id,
                    'invoice_number' => $pendingTopupModel->invoice_number,
                    'amount' => (float) $pendingTopupModel->amount,
                    'total_amount' => (float) ($pendingTopupModel->total_amount ?: $pendingTopupModel->amount),
                    'bank_destination' => $pendingTopupModel->bank_destination,
                    'status' => $pendingTopupModel->status,
                    'created_at' => $pendingTopupModel->created_at?->toIso8601String(),
                ];
            }
        }

        // Load tenant payment settings
        $usageRecord = null;
        if ($tenantId) {
            $usageRecord = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gateway', '_usage_settings')
                ->first();
        }
        $paymentSettings = $usageRecord?->config_json ?? [
            'enable_gateway' => true,
            'enable_bank_manual' => true,
            'default_gateway' => 'noderapay',
            'expiry_minutes' => 15,
        ];

        // Load configured payment gateways added by this tenant
        $configuredGateways = [];
        if ($tenantId) {
            $tenantGateways = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('gateway', ['manual', '_usage_settings'])
                ->get();

            foreach ($tenantGateways as $gw) {
                $cfg = $gw->config_json ?? [];
                if (!empty($cfg)) {
                    $configuredGateways[] = [
                        'gateway' => $gw->gateway,
                        'name' => match ($gw->gateway) {
                            'noderapay' => 'NODERA PAY Gateway',
                            'wijayapay' => 'WijayaPay Gateway',
                            'tripay' => 'Tripay Gateway',
                            'midtrans' => 'Midtrans Gateway',
                            'duitku' => 'Duitku Gateway',
                            'xendit' => 'Xendit Gateway',
                            'paydisini' => 'Paydisini Gateway',
                            'pakasir' => 'Pakasir Gateway',
                            'cinetpay' => 'CinetPay Gateway',
                            'wave' => 'Wave Money Gateway',
                            'paytech' => 'PayTech Gateway',
                            'fedapay' => 'FedaPay Gateway',
                            default => ucfirst($gw->gateway) . ' Gateway',
                        },
                        'is_active' => (bool) $gw->is_active,
                    ];
                }
            }
        }

        return Inertia::render('Admin/Settings', [
            'role' => $role,
            'settings' => array_intersect_key($data, array_flip($keys)),
            'paymentSettings' => $paymentSettings,
            'configuredGateways' => $configuredGateways,
            'webhookUrls' => $data['webhookUrls'],
            'baseUrl' => $data['baseUrl'],
            'superadminPhone' => $superadminPhone,
            'adminUser' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'address' => $tenant->address ?: ($allSettings['COMPANY_ADDRESS'] ?? ''),
                'phone' => $tenant->phone ?: ($allSettings['COMPANY_PHONE'] ?? ''),
                'email' => $tenant->email ?: ($allSettings['COMPANY_EMAIL'] ?? ''),
                'logo' => \App\Models\Setting::resolveLogoUrl($tenant->logo ?: ($allSettings['COMPANY_LOGO'] ?? null), null),
                'raw_logo' => $tenant->logo ?? ($allSettings['COMPANY_LOGO'] ?? null),
                'is_active' => (bool) $tenant->is_active,
                'auto_renew' => (bool) ($tenant->auto_renew ?? ($tenant->settings['auto_renew'] ?? false)),
                'package_name' => $packageName,
                'max_customers' => $maxCustomers,
                'max_routers' => $maxRouters,
                'customers_count' => $customersCount,
                'routers_count' => $routersCount,
                'expired_at' => $tenant->expired_at?->toIso8601String(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            ] : null,
            'company' => [
                'name' => $allSettings['COMPANY_NAME'] ?? ($tenant?->name ?? 'NODERA'),
                'address' => $allSettings['COMPANY_ADDRESS'] ?? ($tenant?->address ?? ''),
                'phone' => $allSettings['COMPANY_PHONE'] ?? ($tenant?->phone ?? ''),
                'email' => $allSettings['COMPANY_EMAIL'] ?? ($tenant?->email ?? ''),
                'logo' => \App\Models\Setting::resolveLogoUrl($tenant?->logo ?: ($allSettings['COMPANY_LOGO'] ?? null), null),
                'raw_logo' => $tenant?->logo ?? ($allSettings['COMPANY_LOGO'] ?? null),
            ],
            'vpnUser' => $vpnUser ? [
                'id' => $vpnUser->id,
                'name' => $vpnUser->name,
                'email' => $vpnUser->email,
                'phone' => $vpnUser->phone,
                'saldo' => (float) $vpnUser->total_saldo,
            ] : null,
            'masterSaldo' => (float) ($vpnUser?->total_saldo ?? 0),
            'topupHistory' => $topupHistory,
            'pendingTopup' => $pendingTopup,
            'autoRenew' => (bool) ($tenant?->auto_renew ?? ($tenant?->settings['auto_renew'] ?? false)),
            'availablePackages' => $availablePackages,
            'superadminBankAccounts' => $superadminBankAccounts,
            'superadminQris' => $superadminQris,
            'panelUrl' => $panelUrl,
            'packageName' => $packageName,
            'customersCount' => $customersCount,
            'maxCustomers' => $maxCustomers,
            'routersCount' => $routersCount,
            'maxRouters' => $maxRouters,
            'bankAccounts' => collect($data['bankAccounts'])->map(fn ($b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'account_name' => $b->account_name,
                'sort_order' => $b->sort_order,
                'is_active' => (bool) $b->is_active,
            ]),
            'webhookLogs' => collect($data['webhookLogs'])->map(fn ($l) => [
                'id' => $l->id ?? null,
                'source' => $l->source ?? '',
                'status' => $l->status ?? '',
                'created_at' => $l->created_at ?? null,
            ]),
            'systemVersion' => app(\App\Services\SystemUpdateService::class)->getLocalVersion(),
        ]);
    }

    /**
     * Nilai setting per layanan — dipakai sheet gear kontekstual
     * (GenieACS/WhatsApp) di halaman fungsinya masing-masing.
     */
    public function serviceSettings(Request $request)
    {
        $service = $request->input('service', '');
        $keys = match ($service) {
            'genieacs' => ['GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD', 'GENIEACS_TOKEN'],
            'whatsapp' => [
                'WHATSAPP_PROVIDER', 'WHATSAPP_API_URL', 'WHATSAPP_TOKEN', 'WHATSAPP_SENDER_PHONE', 'WHATSAPP_AUTO_TYPING', 'WHATSAPP_VERIFY_TOKEN',
                'WA_TEMPLATE_INVOICE_NEW', 'WA_TEMPLATE_REMINDER',
                'WA_TEMPLATE_PAYMENT_SUCCESS', 'WA_TEMPLATE_ISOLATION',
            ],
            default    => [],
        };

        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if ($tenantId) {
            $values = DB::table('settings')
                ->whereIn('key', $keys)
                ->where('tenant_id', $tenantId)
                ->pluck('value', 'key')->toArray();
        } else {
            $values = DB::table('settings')
                ->whereIn('key', $keys)
                ->whereNull('tenant_id')
                ->pluck('value', 'key')->toArray();
        }

        $resValues = collect($keys)->mapWithKeys(fn ($k) => [$k => $values[$k] ?? ''])->toArray();
        if ($service === 'whatsapp') {
            if (empty($resValues['WHATSAPP_PROVIDER'])) {
                $resValues['WHATSAPP_PROVIDER'] = 'fonnte';
            }
            if (empty($resValues['WHATSAPP_API_URL'])) {
                $resValues['WHATSAPP_API_URL'] = 'https://api.fonnte.com/send';
            }
        }

        return response()->json([
            'service' => $service,
            'values' => $resValues,
        ]);
    }

    /**
     * API Apps page - all API configs in one place
     */
    public function apiApps()
    {
        $keys = [
            'WHATSAPP_PROVIDER', 'WHATSAPP_API_URL', 'WHATSAPP_TOKEN', 'WHATSAPP_SENDER_PHONE', 'WHATSAPP_AUTO_TYPING', 'WHATSAPP_VERIFY_TOKEN',
            'GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD', 'GENIEACS_TOKEN',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_ADMIN_CHAT_IDS',
            'TRIPAY_API_KEY', 'TRIPAY_PRIVATE_KEY', 'TRIPAY_MERCHANT_CODE', 'TRIPAY_MODE',
            'MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY', 'MIDTRANS_MODE',
        ];

        // Get current base URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = "{$protocol}://{$host}";

        // Get all settings strictly isolated
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if ($tenantId) {
            $allSettings = DB::table('settings')
                ->whereIn('key', $keys)
                ->where('tenant_id', $tenantId)
                ->pluck('value', 'key')->toArray();
        } else {
            $allSettings = DB::table('settings')
                ->whereIn('key', $keys)
                ->whereNull('tenant_id')
                ->pluck('value', 'key')->toArray();
        }

        $data = [];
        foreach ($keys as $k) {
            $data[$k] = $allSettings[$k] ?? '';
        }
        if (empty($data['WHATSAPP_PROVIDER'])) {
            $data['WHATSAPP_PROVIDER'] = 'fonnte';
        }
        if (empty($data['WHATSAPP_API_URL'])) {
            $data['WHATSAPP_API_URL'] = 'https://api.fonnte.com/send';
        }

        $data['webhookUrls'] = [
            'whatsapp' => "{$baseUrl}/webhook/whatsapp",
            'payment'  => "{$baseUrl}/webhook/payment",
            'midtrans' => "{$baseUrl}/webhook/midtrans",
            'telegram' => "{$baseUrl}/webhook/telegram",
        ];
        $data['baseUrl'] = $baseUrl;

        return Inertia::render('Admin/ApiApps', [
            'settings' => array_intersect_key($data, array_flip($keys)),
            'webhookUrls' => $data['webhookUrls'],
            'baseUrl' => $data['baseUrl'],
        ]);
    }

    /**
     * Save settings
     */
    public function save(Request $request)
    {
        $role = session('admin_role');
        $authUser = auth()->user();
        if (!$role && $authUser) {
            $role = strtolower((string) $authUser->role);
        }

        $isSuperadmin = $role === 'superadmin';

        $allowed = [
            'WHATSAPP_PROVIDER', 'WHATSAPP_API_URL', 'WHATSAPP_TOKEN', 'WHATSAPP_SENDER_PHONE', 'WHATSAPP_AUTO_TYPING', 'WHATSAPP_VERIFY_TOKEN',
            'WA_TEMPLATE_INVOICE_NEW', 'WA_TEMPLATE_REMINDER',
            'WA_TEMPLATE_PAYMENT_SUCCESS', 'WA_TEMPLATE_ISOLATION',
            'GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD', 'GENIEACS_TOKEN',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_ADMIN_CHAT_IDS',
            'TRIPAY_API_KEY', 'TRIPAY_PRIVATE_KEY', 'TRIPAY_MERCHANT_CODE', 'TRIPAY_MODE',
            'MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY', 'MIDTRANS_MODE',
            'COLLECTOR_COMMISSION_PER_INVOICE',
        ];

        if ($isSuperadmin) {
            $allowed = array_merge($allowed, [
                'COMPANY_NAME', 'COMPANY_ADDRESS', 'COMPANY_PHONE', 'COMPANY_EMAIL',
                'ISOLIR_HOUR', 'ISOLIR_MINUTE',
            ]);
        }

        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();

        foreach ($request->except(['_token', 'X-Inertia']) as $key => $value) {
            if (in_array($key, $allowed, true)) {
                \App\Models\Setting::setValue($key, (string) $value, $tenantId);
            }
        }

        // Handle legacy logo upload if submitted
        $logoMsg = '';
        if ($request->hasFile('COMPANY_LOGO') && $tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                $this->deleteExistingLogo($tenant->logo);
                $path = $this->saveUploadedLogo($request->file('COMPANY_LOGO'), "tenant_{$tenantId}");
                $tenant->update(['logo' => $path]);
                \App\Models\Setting::setValue('COMPANY_LOGO', $path, $tenantId);
                \Illuminate\Support\Facades\Cache::forget("inertia_tenant_branding_{$tenantId}");
                $logoMsg = ' + logo';
            }
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('msg', 'Pengaturan berhasil disimpan' . $logoMsg);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'msg' => 'Pengaturan berhasil disimpan' . $logoMsg]);
        }

        return redirect()->back()->with('msg', 'Pengaturan berhasil disimpan' . $logoMsg);
    }

    /**
     * Helper to store uploaded logo in both storage and public directories to ensure immediate web server accessibility
     */
    private function saveUploadedLogo($file, string $prefix): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = "{$prefix}_" . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;

        // 1. Storage disk public
        $path = $file->storeAs('logos', $filename, 'public');

        // 2. Direct copies into public directories (solves missing symlink / cPanel / subdomains / direct webserver serving)
        try {
            $destDirs = [
                public_path('storage/logos'),
                public_path('uploads/logos'),
                public_path('logos'),
            ];

            foreach ($destDirs as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @copy($file->getRealPath(), $dir . '/' . $filename);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Logo copy to public directory warning: ' . $e->getMessage());
        }

        return $path;
    }

    /**
     * Helper to delete an old logo from all storage and public directories
     */
    private function deleteExistingLogo(?string $logoPath): void
    {
        if (empty($logoPath)) {
            return;
        }

        try {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($logoPath);
            }

            $filename = basename($logoPath);
            $publicFiles = [
                public_path('storage/logos/' . $filename),
                public_path('uploads/logos/' . $filename),
                public_path('logos/' . $filename),
                public_path($logoPath),
            ];

            foreach ($publicFiles as $f) {
                if (file_exists($f) && !is_dir($f)) {
                    @unlink($f);
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Save Tenant Payment Methods & Expiry Settings
     */
    public function savePaymentSettings(Request $request)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) {
            return back()->with('error', 'Tenant ID tidak ditemukan.');
        }

        $data = $request->validate([
            'enable_gateway' => 'nullable|boolean',
            'enable_qris_manual' => 'nullable|boolean',
            'enable_bank_manual' => 'nullable|boolean',
            'default_gateway' => 'nullable|string|max:50',
            'expiry_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $record = \App\Models\PaymentGateway::withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $tenantId,
            'gateway' => '_usage_settings',
        ]);

        $record->config_json = [
            'enable_gateway' => $request->boolean('enable_gateway', true),
            'enable_bank_manual' => $request->boolean('enable_bank_manual', true),
            'default_gateway' => $data['default_gateway'] ?? 'noderapay',
            'expiry_minutes' => max(1, (int) ($data['expiry_minutes'] ?? 15)),
        ];
        $record->is_active = true;
        $record->save();

        if (!empty($data['default_gateway'])) {
            $targetGw = \App\Models\PaymentGateway::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gateway', $data['default_gateway'])
                ->first();
            if ($targetGw && !$targetGw->is_active) {
                $targetGw->update(['is_active' => true]);
                \Illuminate\Support\Facades\Cache::forget("pg_active_{$tenantId}_{$data['default_gateway']}");
                \Illuminate\Support\Facades\Cache::forget("pg_active_0_{$data['default_gateway']}");
            }
        }

        return back()->with('success', 'Pengaturan metode pembayaran pelanggan berhasil disimpan.');
    }

    /**
     * Update Tenant Brand Identity & Logo
     */
    public function updateCompanyProfile(Request $request)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();

        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'company_address' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:100',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:3072',
            'remove_logo' => 'nullable|boolean',
        ]);

        $companyName = trim($validated['company_name']);
        $companyAddress = trim($validated['company_address'] ?? '');
        $companyPhone = trim($validated['company_phone'] ?? '');
        $companyEmail = trim($validated['company_email'] ?? '');

        // 1. Update Settings table
        \App\Models\Setting::setValue('COMPANY_NAME', $companyName, $tenantId);
        \App\Models\Setting::setValue('COMPANY_ADDRESS', $companyAddress, $tenantId);
        \App\Models\Setting::setValue('COMPANY_PHONE', $companyPhone, $tenantId);
        \App\Models\Setting::setValue('COMPANY_EMAIL', $companyEmail, $tenantId);

        // 2. Update Tenant record if exists
        if ($tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            if ($tenant) {
                $tenantData = [
                    'name' => $companyName,
                    'address' => $companyAddress,
                    'phone' => $companyPhone,
                    'email' => $companyEmail,
                ];

                if ($request->boolean('remove_logo')) {
                    $this->deleteExistingLogo($tenant->logo);
                    $tenantData['logo'] = null;
                    \App\Models\Setting::setValue('COMPANY_LOGO', '', $tenantId);
                } elseif ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                    $this->deleteExistingLogo($tenant->logo);
                    $path = $this->saveUploadedLogo($request->file('logo'), "tenant_{$tenantId}");
                    $tenantData['logo'] = $path;
                    \App\Models\Setting::setValue('COMPANY_LOGO', $path, $tenantId);
                }

                $tenant->update($tenantData);
                \Illuminate\Support\Facades\Cache::forget("inertia_tenant_branding_{$tenantId}");
                \Illuminate\Support\Facades\Cache::forget("tenant:slug:{$tenant->slug}");
                \Illuminate\Support\Facades\Cache::forget("tenant_meta:slug:{$tenant->slug}");
            }
        } else {
            $oldGlobalLogo = \App\Models\Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'COMPANY_LOGO')->value('value');
            if ($request->boolean('remove_logo')) {
                $this->deleteExistingLogo($oldGlobalLogo);
                \App\Models\Setting::setValue('COMPANY_LOGO', '', null);
            } elseif ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                $this->deleteExistingLogo($oldGlobalLogo);
                $path = $this->saveUploadedLogo($request->file('logo'), 'global');
                \App\Models\Setting::setValue('COMPANY_LOGO', $path, null);
            }
            \Illuminate\Support\Facades\Cache::forget("inertia_tenant_branding_global");
        }

        return back()->with('msg', 'Profil usaha & logo berhasil disimpan.');
    }

    /**
     * Update Admin Profile (Username, Name, Email)
     */
    public function updateProfile(Request $request)
    {
        $adminId = session('admin_id') ?? auth()->id() ?? auth('web')->id();
        $user = $adminId ? \App\Models\User::withoutGlobalScopes()->find($adminId) : null;
        if (!$user) {
            $user = auth()->user();
            $adminId = $user?->id;
        }

        if (!$user) {
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Sesi admin tidak ditemukan.');
            }
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Sesi admin tidak ditemukan.'], 401);
            }
            return redirect()->to('/admin/my-settings')->with('error', 'Sesi admin tidak ditemukan.');
        }

        $username = trim((string) $request->username) ?: ($user->username ?? '');
        $name = trim((string) $request->name) ?: ($user->name ?? '');
        $email = trim((string) $request->email);
        $phone = trim((string) $request->phone);

        // Validate inputs
        if (empty($name)) {
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Nama wajib diisi.');
            }
            return redirect()->to('/admin/my-settings')->with('error', 'Nama wajib diisi.');
        }

        // Check if username already exists (excluding current user)
        $existingUser = DB::table('users')
            ->where('username', $username)
            ->where('id', '!=', $adminId)
            ->first();

        if ($existingUser) {
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Username sudah digunakan oleh user lain.');
            }
            return redirect()->to('/admin/my-settings')->with('error', 'Username sudah digunakan oleh user lain.');
        }

        try {
            // Update user profile
            DB::table('users')->where('id', $adminId)->update([
                'username' => $username,
                'name' => $name,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'updated_at' => now(),
            ]);

            // If user belongs to a tenant, sync the tenant phone and settings as well
            if ($user->tenant_id) {
                try {
                    \App\Models\Tenant::withoutGlobalScopes()->where('id', $user->tenant_id)->update([
                        'phone' => $phone,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync tenant phone: ' . $e->getMessage());
                }

                try {
                    DB::table('settings')->updateOrInsert(
                        ['tenant_id' => $user->tenant_id, 'key' => 'ADMIN_WA'],
                        ['value' => $phone, 'updated_at' => now(), 'created_at' => now()]
                    );
                    DB::table('settings')->updateOrInsert(
                        ['tenant_id' => $user->tenant_id, 'key' => 'COMPANY_PHONE'],
                        ['value' => $phone, 'updated_at' => now(), 'created_at' => now()]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync tenant settings: ' . $e->getMessage());
                }

                // 🔄 Sync profile with linked VpnUser (Panel)
                try {
                    $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($user->tenant_id);
                    $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
                    $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
                    if (!$vpnUser && ($user->email || $user->phone)) {
                        $vpnUser = VpnUser::where('email', $user->email)->orWhere('phone', $user->phone)->first();
                    }
                    if ($vpnUser) {
                        $vpnUser->update([
                            'name' => $name,
                            'email' => $email ?: $vpnUser->email,
                            'phone' => $phone ?: $vpnUser->phone,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed syncing profile from tenant to panel: ' . $e->getMessage());
                }
            }

            // Update session
            session(['admin_username' => $username, 'admin_name' => $name, 'admin_id' => $adminId]);

            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('msg', 'Profil admin berhasil diperbarui!');
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'msg' => 'Profil berhasil diperbarui!']);
            }

            return redirect()->to('/admin/my-settings')->with('msg', 'Profil berhasil diperbarui!');
        } catch (\Throwable $e) {
            report($e);
            Log::error('SettingsController::updateProfile error: ' . $e->getMessage());
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Gagal memperbarui profil. Laporan telah otomatis dikirimkan ke tim teknis.');
            }
            return redirect()->to('/admin/my-settings')->with('error', 'Gagal memperbarui profil. Laporan telah otomatis dikirimkan ke tim teknis.');
        }
    }

    /**
     * Change Admin Password
     */
    public function changePassword(Request $request)
    {
        $adminId = session('admin_id');

        $currentPassword = $request->post('current_password');
        $newPassword = $request->post('new_password');
        $confirmPassword = $request->post('new_password_confirmation', $request->post('confirm_password'));

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return redirect()->to('/admin/my-settings')->with('error', ' Semua field password wajib diisi.');
        }

        // Check password length
        if (strlen($newPassword) < 6) {
            return redirect()->to('/admin/my-settings')->with('error', ' Password baru minimal 6 karakter.');
        }

        // Check passwords match
        if ($newPassword !== $confirmPassword) {
            return redirect()->to('/admin/my-settings')->with('error', ' Password baru dan konfirmasi tidak cocok.');
        }

        // Get current user
        $user = DB::table('users')->where('id', $adminId)->first();

        // Protect demo tenant password from being modified
        if (session('tenant_slug') === 'demo' || in_array($user->username ?? '', ['demo', 'kolektor_demo', 'teknisi_demo'])) {
            return redirect()->to('/admin/my-settings')->with('error', 'Pengubahan password dinonaktifkan pada mode demo.');
        }

        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            return redirect()->to('/admin/my-settings')->with('error', ' Password saat ini salah.');
        }

        $newHash = Hash::make($newPassword);

        // Update password
        DB::table('users')->where('id', $adminId)->update([
            'password' => $newHash,
            'updated_at' => now(),
        ]);

        // 🔄 Sync password with linked VpnUser (Panel)
        if ($user && isset($user->tenant_id) && $user->tenant_id) {
            try {
                $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($user->tenant_id);
                $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
                $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
                if (!$vpnUser && ($user->email || $user->phone)) {
                    $vpnUser = VpnUser::where('email', $user->email)->orWhere('phone', $user->phone)->first();
                }
                if ($vpnUser) {
                    $vpnUser->update(['password' => $newHash]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed syncing password from tenant to panel: ' . $e->getMessage());
            }
        }

        return redirect()->to('/admin/my-settings')->with('msg', ' Password berhasil diubah!');
    }

    public function bankStore(Request $request) {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) return back()->with('error', 'Tenant tidak ditemukan');
        
        $data = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:200',
        ]);

        try {
            $data['tenant_id'] = $tenantId;
            $data['is_active'] = true;
            $data['sort_order'] = 0;
            \App\Models\BankAccount::create($data);
            return back()->with('msg', 'Rekening berhasil ditambahkan');
        } catch (\Throwable $e) {
            report($e);
            Log::error('[SettingsController] bankStore error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan rekening. Laporan telah otomatis dikirimkan ke tim teknis.');
        }
    }

    public function bankUpdate(Request $request, $id) {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) return back()->with('error', 'Tenant tidak ditemukan');

        try {
            $account = \App\Models\BankAccount::where('tenant_id', $tenantId)->findOrFail($id);
            $data = $request->validate([
                'bank_name' => 'required|string|max:100',
                'account_number' => 'required|string|max:50',
                'account_name' => 'required|string|max:200',
            ]);
            $account->update($data);
            return back()->with('msg', 'Rekening berhasil diperbarui');
        } catch (\Throwable $e) {
            report($e);
            Log::error('[SettingsController] bankUpdate error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui rekening. Laporan telah otomatis dikirimkan ke tim teknis.');
        }
    }

    public function bankDelete($id) {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) return back()->with('error', 'Tenant tidak ditemukan');

        try {
            \App\Models\BankAccount::where('tenant_id', $tenantId)->findOrFail($id)->delete();
            return back()->with('msg', 'Rekening berhasil dihapus');
        } catch (\Throwable $e) {
            report($e);
            Log::error('[SettingsController] bankDelete error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus rekening. Laporan telah otomatis dikirimkan ke tim teknis.');
        }
    }

    public function bankEdit($id) {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $account = \App\Models\BankAccount::where('tenant_id', $tenantId)->findOrFail($id);
        return response()->json($account);
    }

    /**
     * Set Telegram Webhook
     */
    public function setTelegramWebhook(Request $request)
    {
        // Get bot token from settings
        $botToken = \App\Models\Setting::getValue('TELEGRAM_BOT_TOKEN', '');

        if (empty($botToken)) {
            return redirect()->to('/admin/my-settings')->with('error', ' Bot Token belum dikonfigurasi. Silakan isi Bot Token terlebih dahulu.');
        }

        $telegram = new TelegramService();
        $token = $telegram->getBotToken();
        $webhookUrl = TelegramService::buildWebhookUrl($token);

        try {
            $secret = \App\Models\Setting::getValue('TELEGRAM_WEBHOOK_SECRET');
            if (empty($secret)) {
                $secret = bin2hex(random_bytes(32));
                \App\Models\Setting::setValue('TELEGRAM_WEBHOOK_SECRET', $secret);
            }
            $result = $telegram->setWebhook($webhookUrl, $secret);

            if ($result && isset($result['ok']) && $result['ok'] === true) {
                return redirect()->to('/admin/my-settings')->with('msg', ' Telegram Webhook berhasil diset ke: ' . $webhookUrl);
            } else {
                $errorMsg = $result['description'] ?? 'Unknown error';
                return redirect()->to('/admin/my-settings')->with('error', ' Gagal set webhook: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            return redirect()->to('/admin/my-settings')->with('error', ' Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete Telegram Webhook
     */
    public function deleteTelegramWebhook(Request $request)
    {
        // Get bot token from settings
        $botToken = \App\Models\Setting::getValue('TELEGRAM_BOT_TOKEN', '');

        if (empty($botToken)) {
            return redirect()->to('/admin/my-settings')->with('error', ' Bot Token belum dikonfigurasi.');
        }

        try {
            $telegram = new TelegramService();
            $result = $telegram->deleteWebhook();

            if ($result && isset($result['ok']) && $result['ok'] === true) {
                return redirect()->to('/admin/my-settings')->with('msg', ' Telegram Webhook berhasil dihapus. Bot sekarang menggunakan polling mode.');
            } else {
                $errorMsg = $result['description'] ?? 'Unknown error';
                return redirect()->to('/admin/my-settings')->with('error', ' Gagal hapus webhook: ' . $errorMsg);
            }
        } catch (\Exception $e) {
            return redirect()->to('/admin/my-settings')->with('error', ' Error: ' . $e->getMessage());
        }
    }

    public function systemUpdatePage(Request $request, \App\Services\SystemUpdateService $updater)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $tenant = $tenantId ? \App\Models\Tenant::withoutGlobalScopes()->find($tenantId) : null;

        $totalCustomers = \App\Models\Customer::withoutGlobalScopes()->count();
        $totalRouters = \App\Models\Mikrotik::withoutGlobalScopes()->count();
        $licenseKey = $updater->getLicenseKey();
        $isFreeTier = empty($licenseKey);
        $systemVersion = $updater->getLocalVersion();

        $licenseData = [
            'key' => $licenseKey ?: 'STANDALONE-FREE-TIER',
            'is_free_tier' => $isFreeTier,
            'status' => $isFreeTier ? 'FREE TIER (35 PELANGGAN)' : 'TERLISENSI RESMI (PRO)',
            'customer_count' => $totalCustomers,
            'max_customers' => $isFreeTier ? 35 : 999999,
            'router_count' => $totalRouters,
            'max_routers' => $isFreeTier ? 1 : 999,
        ];

        return \Inertia\Inertia::render('Admin/SystemUpdate', [
            'tenant' => $tenant,
            'current_version' => $systemVersion['version'] ?? '2.5.0',
            'latest_version' => $systemVersion['version'] ?? '2.5.0',
            'update_available' => false,
            'release_date' => $systemVersion['release_date'] ?? date('Y-m-d'),
            'changelog' => $systemVersion['changelog'] ?? [],
            'license' => $licenseData,
            'systemVersion' => $systemVersion,
            'updateInfo' => $updater->checkForUpdates(),
        ]);
    }

    public function checkUpdate(Request $request, \App\Services\SystemUpdateService $updater)
    {
        return response()->json($updater->checkForUpdates());
    }

    public function executeSystemUpdate(Request $request, \App\Services\SystemUpdateService $updater)
    {
        $result = $updater->performUpdate();
        if ($result['success']) {
            return response()->json($result);
        }
        return response()->json($result, 500);
    }

    public function saveLicense(Request $request, \App\Services\SystemUpdateService $updater)
    {
        $request->validate([
            'license_key' => 'nullable|string|max:100',
        ]);

        $key = (string) $request->input('license_key', '');
        $res = $updater->saveLicenseKey($key);

        return response()->json($res);
    }

    /**
     * Upgrade Package or Extend Subscription
     */
    public function upgradePackage(Request $request)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) {
            return back()->with('error', 'Tenant tidak ditemukan.');
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'duration' => 'required|integer|min:1|max:36',
            'payment_method' => 'nullable|string|in:balance,qris,bank',
            'payment_proof' => 'nullable|image|max:4096',
            'bank_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $package = Package::withoutGlobalScopes()->whereNull('tenant_id')->findOrFail($validated['package_id']);
        $duration = (int) $validated['duration'];
        $paymentMethod = $validated['payment_method'] ?? 'balance';
        $monthlyPrice = (float) ($package->monthly_price ?? $package->price ?? 0);

        // Calculate price based on duration
        if ($duration === 6 && !empty($package->semi_annual_price) && $package->semi_annual_price > 0) {
            $totalPrice = (float) $package->semi_annual_price;
        } elseif ($duration === 12 && !empty($package->annual_price) && $package->annual_price > 0) {
            $totalPrice = (float) $package->annual_price;
        } else {
            $totalPrice = $monthlyPrice * $duration;
        }

        $isFr = app()->getLocale() === 'fr' 
            || env('APP_LOCALE') === 'fr' 
            || config('app.locale') === 'fr';

        // 1. Payment Method: POTONG SALDO PANEL (Real-time auto debit)
        if ($paymentMethod === 'balance') {
            $vpnUserId = $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null);
            $vpnUser = $vpnUserId ? VpnUser::find($vpnUserId) : null;
            if (!$vpnUser && ($tenant->email || $tenant->phone)) {
                $vpnUser = VpnUser::where('email', $tenant->email)->orWhere('phone', $tenant->phone)->first();
            }

            if (!$vpnUser) {
                return back()->with('error', $isFr 
                    ? 'Compte Panel VPN introuvable. Veuillez vous connecter au panel.' 
                    : 'Akun Panel Klien belum tertaut. Silakan login ke Panel terlebih dahulu.');
            }

            if ((float) $vpnUser->total_saldo < $totalPrice) {
                $cur = $isFr ? 'FCFA' : 'Rp';
                $short = $totalPrice - (float) $vpnUser->total_saldo;
                return back()->with('error', $isFr
                    ? "Solde Panel insuffisant. Il vous manque " . number_format($short, 0, ',', '.') . " {$cur}. Veuillez recharger votre compte."
                    : "Saldo Panel tidak mencukupi. Kurang {$cur} " . number_format($short, 0, ',', '.') . ". Silakan top up saldo terlebih dahulu.");
            }

            try {
                DB::transaction(function () use ($vpnUser, $tenant, $package, $totalPrice, $duration, $isFr) {
                    $u = VpnUser::where('id', $vpnUser->id)->lockForUpdate()->first();
                    $saldoBefore = (float) $u->total_saldo;

                    if ((float) $u->saldo >= $totalPrice) {
                        $u->decrement('saldo', $totalPrice);
                    } else {
                        $remaining = $totalPrice - (float) $u->saldo;
                        $u->update([
                            'saldo' => 0,
                            'bonus_saldo' => max(0, (float) $u->bonus_saldo - $remaining),
                        ]);
                    }
                    $u->refresh();
                    $saldoAfter = (float) $u->total_saldo;

                    // Extend / upgrade tenant
                    $now = now();
                    $baseExpiry = ($tenant->expired_at && $tenant->expired_at->isFuture()) ? $tenant->expired_at : $now;
                    $newExpiry = $baseExpiry->copy()->addMonths($duration);

                    $settings = is_array($tenant->settings) ? $tenant->settings : [];
                    $settings['package_id'] = $package->id;
                    $settings['package_name'] = $package->name;
                    $settings['max_customers'] = (int) $package->max_customers;
                    $settings['max_routers'] = (int) $package->max_routers;
                    $settings['subscribed_price'] = $totalPrice;
                    $settings['subscribed_duration'] = $duration;
                    $settings['is_trial'] = false;
                    $settings['vpn_user_id'] = $u->id;

                    $tenant->update([
                        'vpn_user_id' => $u->id,
                        'max_customers' => (int) $package->max_customers,
                        'max_routers' => (int) $package->max_routers,
                        'expired_at' => $newExpiry,
                        'trial_ends_at' => null,
                        'is_active' => true,
                        'settings' => $settings,
                    ]);

                    VpnTransaction::create([
                        'vpn_user_id' => $u->id,
                        'type' => 'DEBIT',
                        'amount' => $totalPrice,
                        'saldo_before' => $saldoBefore,
                        'saldo_after' => $saldoAfter,
                        'description' => ($isFr ? "Mise à niveau/Renouvellement Forfait Cloud SaaS: " : "Upgrade/Perpanjang Paket Cloud SaaS: ") . "{$tenant->slug} ({$package->name} - {$duration} " . ($isFr ? "Mois" : "Bulan") . ")",
                        'reference' => 'UPG-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    ]);
                });

                return back()->with('msg', $isFr 
                    ? "Forfait mis à niveau avec succès vers {$package->name} pour {$duration} mois !" 
                    : "Paket berhasil di-upgrade ke {$package->name} selama {$duration} bulan!");
            } catch (\Throwable $e) {
                report($e);
                Log::error('SettingsController::upgradePackage balance error: ' . $e->getMessage());
                return back()->with('error', $isFr 
                    ? 'Échec du traitement du débit. Veuillez réessayer.' 
                    : 'Gagal memproses upgrade paket. Silakan coba kembali.');
            }
        }

        // 2. Payment Method: QRIS / BANK TRANSFER (Manual Verification / Notification)
        $proofPath = null;
        if ($request->hasFile('payment_proof') && $request->file('payment_proof')->isValid()) {
            $proofPath = $request->file('payment_proof')->store('payment_proofs', 'public');
        }

        try {
            // Notify Superadmin via Telegram
            $telegram = app(TelegramService::class);
            if ($telegram->isConfigured()) {
                $adminUser = auth()->user() ?: User::withoutGlobalScopes()->find(session('admin_id'));
                $msg = "<b>📦 PERMINTAAN UPGRADE PAKET — NODERA</b>\n\n"
                    . "┌ Detail Tenant\n"
                    . "├ Nama: {$tenant->name}\n"
                    . "├ Slug: <code>{$tenant->slug}</code>\n"
                    . "├ Admin: {$adminUser?->name} ({$adminUser?->phone})\n"
                    . "├ Paket Baru: <b>{$package->name}</b>\n"
                    . "├ Durasi: <b>{$duration} Bulan</b>\n"
                    . "├ Total Biaya: <b>Rp " . number_format($totalPrice, 0, ',', '.') . "</b>\n"
                    . "├ Metode Bayar: " . strtoupper($validated['payment_method']) . ($validated['bank_name'] ? " ({$validated['bank_name']})" : '') . "\n"
                    . "├ Bukti Bayar: " . ($proofPath ? "Ada (Terlampir)" : "Tidak ada") . "\n"
                    . "├ Catatan: " . ($validated['notes'] ?: '-') . "\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s');

                $telegram->sendAdminNotification($msg, 'order', 'HTML');
            }

            return back()->with('msg', $isFr
                ? 'Votre demande de mise à niveau a été transmise avec succès ! Nous la validerons dès confirmation du règlement.'
                : 'Permintaan upgrade paket berhasil dikirimkan! Superadmin akan segera memverifikasi pembayaran Anda.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('msg', $isFr
                ? 'Votre demande a été enregistrée.'
                : 'Permintaan upgrade berhasil dicatat.');
        }
    }

    /**
     * Toggle Auto-Debit Renewal via Panel Balance
     */
    public function toggleAutoRenew(Request $request)
    {
        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();
        if (!$tenantId) {
            return back()->with('error', 'Tenant tidak ditemukan.');
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);
        $newAutoRenew = !(bool) ($tenant->auto_renew ?? ($tenant->settings['auto_renew'] ?? false));

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['auto_renew'] = $newAutoRenew;

        $tenant->update([
            'auto_renew' => $newAutoRenew,
            'settings' => $settings,
        ]);

        $isFr = app()->getLocale() === 'fr' 
            || env('APP_LOCALE') === 'fr' 
            || config('app.locale') === 'fr'
            || str_contains(request()->getHost(), 'airnetsolution')
            || str_contains(config('app.url'), 'airnetsolution');

        $statusStr = $newAutoRenew 
            ? ($isFr ? 'activé' : 'diaktifkan') 
            : ($isFr ? 'désactivé' : 'dinonaktifkan');

        return back()->with('msg', $isFr 
            ? "Renouvellement automatique par solde {$statusStr}." 
            : "Auto-debit perpanjangan via saldo panel berhasil {$statusStr}.");
    }
}


