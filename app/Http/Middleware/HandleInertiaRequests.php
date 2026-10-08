<?php

namespace App\Http\Middleware;

use App\Services\ConfigService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $request->user()?->tenant_id;

        // 1. Shared Tenant & Branding Cache (300s TTL)
        $tenantSharedKey = "inertia_tenant_branding_" . ($tenantId ?? 'global');
        $tenantMeta = \Illuminate\Support\Facades\Cache::remember($tenantSharedKey, 300, function () use ($tenantId) {
            try {
                $tenant = $tenantId ? \App\Models\Tenant::withoutGlobalScopes()->find($tenantId) : null;
                $settingCompany = $tenantId 
                    ? \App\Models\Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'COMPANY_NAME')->value('value')
                    : null;

                $cName = $settingCompany ?: ($tenant?->company_name ?: ($tenant?->name ?: config('app.name', 'NODERA Billing')));
                $tName = $tenant?->name ?: ($tenant?->company_name ?: config('app.name', 'NODERA Billing'));
                $tPlan = $tenant ? ($tenant->package_name ?: ($tenant->settings['package_name'] ?? 'Starter')) : 'Starter';
                $logo = $tenant?->logo ?: ($tenantId ? \App\Models\Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'COMPANY_LOGO')->value('value') : null);
                if (!$logo && $tenant) {
                    $slugCandidates = array_filter([$tenant->slug ?? null, $tenant->subdomain ?? null, $tenant->username ?? null]);
                    foreach ($slugCandidates as $sCandidate) {
                        $mikhmonLogo = public_path("hotspot-{$sCandidate}/img/logo.png");
                        if (file_exists($mikhmonLogo) && filesize($mikhmonLogo) > 0) {
                            $logo = "/hotspot-{$sCandidate}/img/logo.png";
                            break;
                        }
                    }
                }
                $tLogo = \App\Models\Setting::resolveLogoUrl($logo, null);

                return [
                    'companyName' => $cName,
                    'tenantName'  => $tName,
                    'tenantPlan'  => $tPlan,
                    'tenantLogo'  => $tLogo,
                ];
            } catch (\Throwable $e) {
                return [
                    'companyName' => config('app.name', 'NODERA Billing'),
                    'tenantName'  => config('app.name', 'NODERA Billing'),
                    'tenantPlan'  => 'Starter',
                    'tenantLogo'  => null,
                ];
            }
        });

        // 2. Global Telegram Community URL Cache (300s TTL)
        $telegramUrl = \Illuminate\Support\Facades\Cache::remember('setting_tg_community_url', 300, function () {
            try {
                return \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_COMMUNITY_URL')->whereNull('tenant_id')->value('value')
                    ?: \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_COMMUNITY')->whereNull('tenant_id')->value('value')
                    ?: env('TELEGRAM_COMMUNITY_URL', '')
                    ?: env('TELEGRAM_COMMUNITY', '');
            } catch (\Throwable $e) {
                return '';
            }
        });

        // 3. Unified Default Permissions Map
        $allUnifiedDefaults = [
            'dashboard'       => true,
            'customers'       => true,
            'create_customer' => true,
            'collect_payment' => true,
            'earnings'        => true,
            'pool'            => true,
            'history'         => true,
            'pppoe'           => true,
            'isolate'         => true,
            'map'             => true,
            'top_bandwidth'   => true,
        ];

        // 4. Role Permissions Matrix per Tenant (Cached 300s)
        $rolePermissions = \Illuminate\Support\Facades\Cache::remember("role_perms_matrix_" . ($tenantId ?? 'all'), 300, function () use ($tenantId, $allUnifiedDefaults) {
            try {
                $rawTech = \App\Models\Setting::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('key', 'ROLE_PERMISSIONS_TECHNICIAN')->value('value');
                $rawColl = \App\Models\Setting::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('key', 'ROLE_PERMISSIONS_COLLECTOR')->value('value');

                return [
                    'technician' => $rawTech ? array_merge($allUnifiedDefaults, json_decode($rawTech, true) ?: []) : $allUnifiedDefaults,
                    'collector'  => $rawColl ? array_merge($allUnifiedDefaults, json_decode($rawColl, true) ?: []) : $allUnifiedDefaults,
                ];
            } catch (\Throwable $e) {
                return [
                    'technician' => $allUnifiedDefaults,
                    'collector'  => $allUnifiedDefaults,
                ];
            }
        });

        // 5. Active User Permissions (Single resolution)
        $activeUserPermissions = (function () use ($request, $allUnifiedDefaults) {
            try {
                $techId = session('technician_id') ?? ($request->user()?->role === 'technician' ? $request->user()?->id : null);
                if ($techId) {
                    $user = \App\Models\User::withoutGlobalScopes()->find($techId);
                    if ($user) {
                        return (!empty($user->permissions) && is_array($user->permissions))
                            ? array_merge($allUnifiedDefaults, $user->permissions)
                            : $allUnifiedDefaults;
                    }
                }

                if (session('collector_logged_in') && session('collector_id')) {
                    $collector = \App\Models\Collector::withoutGlobalScopes()->find(session('collector_id'));
                    return (!empty($collector?->permissions) && is_array($collector->permissions))
                        ? array_merge($allUnifiedDefaults, $collector->permissions)
                        : $allUnifiedDefaults;
                }
                return $allUnifiedDefaults;
            } catch (\Throwable $e) {
                return $allUnifiedDefaults;
            }
        })();

        return [
            ...parent::share($request),
            'appVersion'           => config('app.version'),
            'telegramCommunityUrl' => $telegramUrl,
            'companyName'          => $tenantMeta['companyName'] ?? config('app.name', 'NODERA Billing'),
            'tenantName'           => $tenantMeta['tenantName'] ?? config('app.name', 'NODERA Billing'),
            'tenantPlan'           => $tenantMeta['tenantPlan'] ?? 'Starter',
            'tenantLogo'           => $tenantMeta['tenantLogo'] ?? null,
            'csrf_token'           => csrf_token(),
            'impersonating'        => (bool) session('impersonating'),
            'genieacsConfigured'   => (bool) (new ConfigService)->get('GENIEACS_URL'),
            'lockedAddonRoutes'    => $tenantId ? \App\Services\AddonService::getLockedRoutesForTenant($tenantId) : [],
            'lockedAddonSlugs'     => $tenantId ? \App\Services\AddonService::getLockedSlugsForTenant($tenantId) : [],
            'rolePermissions'      => $rolePermissions,
            'permissions'          => $activeUserPermissions,
            'userPermissions'      => $activeUserPermissions,
            'auth' => [
                'user' => (function () use ($request) {
                    try {
                        if (($request->is('kolektor/*') || $request->is('collector/*')) && session('collector_id')) {
                            $collector = \App\Models\Collector::withoutGlobalScopes()->find(session('collector_id'));
                            return [
                                'id' => session('collector_id'),
                                'name' => $collector?->name ?? session('collector_name', 'Kolektor'),
                                'email' => $collector?->phone ?? $collector?->username ?? 'Kolektor',
                                'role' => 'kolektor',
                                'username' => $collector?->username,
                            ];
                        }
                        if (($request->is('teknisi/*') || $request->is('technician/*')) && session('technician_id')) {
                            $tech = \App\Models\User::withoutGlobalScopes()->find(session('technician_id'));
                            return [
                                'id' => session('technician_id'),
                                'name' => $tech?->name ?? session('technician_name', 'Teknisi'),
                                'email' => $tech?->email ?? $tech?->username ?? 'Teknisi',
                                'role' => 'teknisi',
                                'username' => $tech?->username,
                            ];
                        }
                        if ($user = $request->user()) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'role' => $user->role ?? (session('admin_role') ?: 'admin'),
                                'saldo' => (float) ($user->total_saldo ?? $user->saldo ?? 0),
                                'is_superadmin' => ($user->role === 'superadmin' || session('admin_role') === 'superadmin'),
                            ];
                        }
                        if ($vpnUser = auth('vpn')->user() ?: (session('vpn_user_id') ? \App\Models\VpnUser::find(session('vpn_user_id')) : null)) {
                            return [
                                'id' => $vpnUser->id,
                                'name' => $vpnUser->name,
                                'email' => $vpnUser->email,
                                'role' => 'vpn_user',
                                'saldo' => (float) ($vpnUser->total_saldo ?? $vpnUser->saldo ?? 0),
                            ];
                        }
                        if (session('collector_logged_in') && session('collector_id')) {
                            $collector = \App\Models\Collector::withoutGlobalScopes()->find(session('collector_id'));
                            return [
                                'id' => session('collector_id'),
                                'name' => $collector?->name ?? session('collector_name', 'Kolektor'),
                                'email' => $collector?->phone ?? $collector?->username ?? 'Kolektor',
                                'role' => 'kolektor',
                            ];
                        }
                        if (session('technician_logged_in') && session('technician_id')) {
                            $tech = \App\Models\User::withoutGlobalScopes()->find(session('technician_id'));
                            return [
                                'id' => session('technician_id'),
                                'name' => $tech?->name ?? session('technician_name', 'Teknisi'),
                                'email' => $tech?->email ?? $tech?->username ?? 'Teknisi',
                                'role' => 'teknisi',
                            ];
                        }
                        if (session('agent_logged_in') && session('agent_id')) {
                            $agent = \App\Models\Agent::withoutGlobalScopes()->find(session('agent_id'));
                            return [
                                'id' => session('agent_id'),
                                'name' => $agent?->name ?? session('agent_name', 'Agen'),
                                'email' => $agent?->phone ?? 'Agen',
                                'role' => 'agent',
                            ];
                        }
                        if (session('cashier_logged_in') && session('cashier_id')) {
                            $cashier = \App\Models\Cashier::withoutGlobalScopes()->find(session('cashier_id'));
                            return [
                                'id' => session('cashier_id'),
                                'name' => $cashier?->name ?? session('cashier_name', 'Kasir'),
                                'email' => $cashier?->username ?? 'Kasir',
                                'role' => 'kasir',
                            ];
                        }
                        if (session('customer_portal_logged_in') && session('customer_id')) {
                            $cust = \App\Models\Customer::withoutGlobalScopes()->find(session('customer_id'));
                            return [
                                'id' => session('customer_id'),
                                'name' => $cust?->name ?? session('customer_name', 'Pelanggan'),
                                'email' => $cust?->phone ?? $cust?->pppoe_username ?? 'Pelanggan',
                                'role' => 'pelanggan',
                            ];
                        }
                    } catch (\Throwable $e) {
                        return null;
                    }
                    return null;
                })(),
            ],
            'flash' => [
                'success' => session('success'),
                'msg' => session('msg') ?: session('success'),
                'error' => session('error'),
                'info' => session('info'),
                'warning' => session('warning'),
                'reset_password_data' => session('reset_password_data'),
            ],
            'turnstile' => [
                'enabled' => (bool) config('services.turnstile.enabled', true),
                'site_key' => config('services.turnstile.site_key'),
            ],
            'googleRegisterData' => session('google_register_data'),
            'wa_merchant' => (function () use ($request) {
                try {
                    $mId = session('wagateway_merchant_id') ?? $request->cookie('nodera_remember_wa_merchant');
                    if ($mId) {
                        $m = \App\Models\WaMerchant::find($mId);
                        if ($m && $m->status === 'active') {
                            return [
                                'id' => $m->id,
                                'merchant_code' => $m->merchant_code,
                                'name' => $m->name,
                                'owner_name' => $m->owner_name,
                                'email' => $m->email,
                                'phone' => $m->phone,
                                'plan_type' => $m->plan_type,
                                'credit_balance' => (float) $m->credit_balance,
                                'device_limit' => $m->device_limit,
                                'quota_monthly' => $m->quota_monthly,
                                'quota_used_this_month' => $m->quota_used_this_month,
                                'api_key' => $m->api_key,
                            ];
                        }
                    }
                    if (\Illuminate\Support\Facades\Auth::check()) {
                        $u = \Illuminate\Support\Facades\Auth::user();
                        $m = \App\Models\WaMerchant::where('email', $u->email)->first();
                        if ($m && $m->status === 'active') {
                            session(['wagateway_merchant_id' => $m->id]);
                            return [
                                'id' => $m->id,
                                'merchant_code' => $m->merchant_code,
                                'name' => $m->name,
                                'owner_name' => $m->owner_name,
                                'email' => $m->email,
                                'phone' => $m->phone,
                                'plan_type' => $m->plan_type,
                                'credit_balance' => (float) $m->credit_balance,
                                'device_limit' => $m->device_limit,
                                'quota_monthly' => $m->quota_monthly,
                                'quota_used_this_month' => $m->quota_used_this_month,
                                'api_key' => $m->api_key,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    return null;
                }
                return null;
            })(),
            'cloud_activity_logs' => (function () {
                try {
                    if (request()->is('superadmin*') || \Illuminate\Support\Facades\Auth::guard('superadmin')->check()) {
                        return \App\Models\AuditLog::withoutGlobalScopes()
                            ->latest('id')
                            ->take(8)
                            ->get()
                            ->map(function ($log) {
                                $act = $log->action ?? 'AKTIVITAS';
                                $isDelete = str_contains(strtolower($act), 'delete') || str_contains(strtolower($act), 'hapus');
                                return [
                                    'id' => $log->id,
                                    'type' => $act,
                                    'title' => $act . ': ' . ($log->entity_type ?? 'Sistem') . ($log->entity_id ? " #{$log->entity_id}" : ''),
                                    'amount' => 0,
                                    'is_credit' => !$isDelete,
                                    'time' => $log->created_at ? $log->created_at->diffForHumans() : 'Baru saja',
                                ];
                            })
                            ->toArray();
                    }

                    // 1. Check VPN user
                    $vpnUser = \Illuminate\Support\Facades\Auth::guard('vpn')->user();
                    if ($vpnUser && isset($vpnUser->id)) {
                        $logs = collect();

                        // 1a. Transactions
                        $txLogs = \App\Models\VpnTransaction::where('vpn_user_id', $vpnUser->id)
                            ->latest('id')
                            ->take(8)
                            ->get();

                        foreach ($txLogs as $tx) {
                            $type = strtoupper((string)($tx->type ?? ''));
                            $isCredit = in_array($type, ['TOPUP', 'MANUAL_ADD', 'REFERRAL_BONUS', 'CREDIT']);
                            $logs->push([
                                'id' => 'tx_' . $tx->id,
                                'type' => $tx->type ?? ($isCredit ? 'credit' : 'debit'),
                                'title' => $tx->description ?: ($isCredit ? 'Topup Saldo Masuk' : 'Pembayaran Layanan'),
                                'amount' => (float) abs($tx->amount ?? 0),
                                'is_credit' => $isCredit,
                                'created_at' => $tx->created_at,
                                'time' => $tx->created_at ? $tx->created_at->diffForHumans() : 'Baru saja',
                            ]);
                        }

                        // 1b. Topup Requests
                        if ($logs->count() < 6 && method_exists($vpnUser, 'topupRequests')) {
                            $topups = $vpnUser->topupRequests()->latest('id')->take(4)->get();
                            foreach ($topups as $topup) {
                                $st = strtoupper((string)($topup->status ?? 'pending'));
                                $logs->push([
                                    'id' => 'topup_' . $topup->id,
                                    'type' => 'TOPUP',
                                    'title' => 'Request Topup: Rp ' . number_format((float)$topup->amount, 0, ',', '.') . ' (' . $st . ')',
                                    'amount' => (float) ($topup->amount ?? 0),
                                    'is_credit' => $st !== 'CANCELLED' && $st !== 'EXPIRED',
                                    'created_at' => $topup->created_at,
                                    'time' => $topup->created_at ? $topup->created_at->diffForHumans() : 'Baru saja',
                                ]);
                            }
                        }

                        // 1c. Active Accounts
                        if ($logs->count() < 6 && method_exists($vpnUser, 'accounts')) {
                            $accounts = $vpnUser->accounts()->withoutGlobalScopes()->latest('id')->take(4)->get();
                            foreach ($accounts as $acc) {
                                $logs->push([
                                    'id' => 'acc_' . $acc->id,
                                    'type' => 'VPN',
                                    'title' => 'Akun Remote: ' . $acc->vpn_username . ' (' . strtoupper($acc->status ?? 'ACTIVE') . ')',
                                    'amount' => 0,
                                    'is_credit' => true,
                                    'created_at' => $acc->created_at,
                                    'time' => $acc->created_at ? $acc->created_at->diffForHumans() : 'Baru saja',
                                ]);
                            }
                        }

                        // 1d. Mikhmon Subscriptions
                        if ($logs->count() < 6 && \App\Models\MikhmonSubscription::where('vpn_user_id', $vpnUser->id)->exists()) {
                            $mikhs = \App\Models\MikhmonSubscription::where('vpn_user_id', $vpnUser->id)->latest('id')->take(4)->get();
                            foreach ($mikhs as $mikh) {
                                $logs->push([
                                    'id' => 'mikh_' . $mikh->id,
                                    'type' => 'MIKHMON',
                                    'title' => 'Mikhmon Online: ' . $mikh->subdomain . ' (' . strtoupper($mikh->status ?? 'ACTIVE') . ')',
                                    'amount' => 0,
                                    'is_credit' => true,
                                    'created_at' => $mikh->created_at,
                                    'time' => $mikh->created_at ? $mikh->created_at->diffForHumans() : 'Baru saja',
                                ]);
                            }
                        }

                        // 1e. Announcements
                        if ($logs->count() < 6 && \App\Models\Announcement::where('is_active', true)->exists()) {
                            $announcements = \App\Models\Announcement::where('is_active', true)->latest('id')->take(3)->get();
                            foreach ($announcements as $ann) {
                                $logs->push([
                                    'id' => 'ann_' . $ann->id,
                                    'type' => 'INFO',
                                    'title' => $ann->title ?? 'Pengumuman Sistem',
                                    'amount' => 0,
                                    'is_credit' => true,
                                    'created_at' => $ann->created_at,
                                    'time' => $ann->created_at ? $ann->created_at->diffForHumans() : 'Baru saja',
                                ]);
                            }
                        }

                        // Fallback onboarding item
                        if ($logs->isEmpty()) {
                            $logs->push([
                                'id' => 'system_ready',
                                'type' => 'SYSTEM',
                                'title' => 'Akun Cloud Panel Siap Digunakan',
                                'amount' => (float) ($vpnUser->total_saldo ?? $vpnUser->saldo ?? 0),
                                'is_credit' => true,
                                'created_at' => $vpnUser->created_at ?? now(),
                                'time' => 'Baru saja',
                            ]);
                        }

                        return $logs->sortByDesc('created_at')->values()->take(8)->toArray();
                    }

                    // 2. Fallback to Admin / Tenant User
                    $adminUser = \Illuminate\Support\Facades\Auth::user();
                    if ($adminUser && isset($adminUser->id)) {
                        $logs = collect();
                        $tenantId = $adminUser->tenant_id ?? null;

                        if ($tenantId && class_exists(\App\Models\Invoice::class)) {
                            $invoices = \App\Models\Invoice::where('tenant_id', $tenantId)
                                ->where('status', 'PAID')
                                ->latest('id')
                                ->take(6)
                                ->get();

                            foreach ($invoices as $inv) {
                                $logs->push([
                                    'id' => 'inv_' . $inv->id,
                                    'type' => 'PAYMENT',
                                    'title' => 'Pembayaran Tagihan #' . ($inv->invoice_number ?? $inv->id) . ' (' . ($inv->customer?->name ?? 'Pelanggan') . ')',
                                    'amount' => (float) ($inv->total_amount ?? $inv->amount ?? 0),
                                    'is_credit' => true,
                                    'created_at' => $inv->created_at,
                                    'time' => $inv->created_at ? $inv->created_at->diffForHumans() : 'Baru saja',
                                ]);
                            }
                        }

                        if ($logs->isEmpty()) {
                            $logs->push([
                                'id' => 'admin_ready',
                                'type' => 'SYSTEM',
                                'title' => 'Sistem ISP Billing Beroperasi Normal',
                                'amount' => 0,
                                'is_credit' => true,
                                'created_at' => $adminUser->created_at ?? now(),
                                'time' => 'Baru saja',
                            ]);
                        }

                        return $logs->sortByDesc('created_at')->values()->take(8)->toArray();
                    }
                } catch (\Throwable $e) {
                    return [];
                }
                return [];
            })(),
            'monthly_revenue' => (function () {
                try {
                    $now = \Carbon\Carbon::now();
                    $startOfMonth = $now->copy()->startOfMonth();
                    $endOfMonth = $now->copy()->endOfMonth();
                    $currentPeriod = $now->format('Y-m');

                    $user = \Illuminate\Support\Facades\Auth::user();
                    $tenantId = $user?->tenant_id;
                    $isSuperadmin = in_array($user?->role, ['superadmin', 'developer']);

                    if ($isSuperadmin) {
                        // Tenant registrations this month
                        $regs = \App\Models\RegistrationRequest::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                            ->whereIn('payment_status', ['paid', 'verified', 'PAID', 'VERIFIED'])
                            ->with('package')
                            ->get();
                        $regRev = $regs->sum(fn ($r) => $r->package ? (float) ($r->package->monthly_price ?? $r->package->price ?? 0) * max(1, (int) ($r->duration ?? 1)) : (float) ($r->amount ?? 0));

                        // Licenses revenue
                        $licRev = (float) \App\Models\IspLicense::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                            ->where('status', '!=', 'REVOKED')
                            ->sum('price');

                        // Shop revenue
                        $shopRev = (float) \App\Models\ShopOrder::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                            ->whereIn('payment_status', ['paid', 'verified', 'PAID', 'VERIFIED'])
                            ->sum('total_amount');

                        // Invoices revenue
                        $invRev = (float) \App\Models\Invoice::withoutGlobalScopes()
                            ->where(function ($q) {
                                $q->where('paid', true)->orWhere('paid', 1)->orWhere('status', 'paid');
                            })
                            ->where(function ($q) use ($startOfMonth, $endOfMonth, $currentPeriod) {
                                $q->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
                                  ->orWhere('period', $currentPeriod)
                                  ->orWhere('periods_breakdown', 'like', "%\"{$currentPeriod}\"%");
                            })
                            ->sum('amount');

                        return (float) ($regRev + $licRev + $shopRev + $invRev);
                    }

                    // Admin Billing / ISP Tenant Scope
                    $paidInvoicesBulanIni = \App\Models\Invoice::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                        ->where(function ($q) {
                            $q->where('paid', true)->orWhere('paid', 1)->orWhere('status', 'paid');
                        })
                        ->where(function ($q) use ($startOfMonth, $endOfMonth, $currentPeriod) {
                            $q->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
                              ->orWhere('period', $currentPeriod)
                              ->orWhere('periods_breakdown', 'like', "%\"{$currentPeriod}\"%");
                        })
                        ->get();

                    $invoiceBulanIni = (float) $paidInvoicesBulanIni->sum(function ($inv) use ($currentPeriod) {
                        if (!empty($inv->periods_breakdown)) {
                            $bd = is_array($inv->periods_breakdown) ? $inv->periods_breakdown : json_decode($inv->periods_breakdown, true);
                            if (is_array($bd) && count($bd) > 0) {
                                $matched = 0;
                                $found = false;
                                foreach ($bd as $item) {
                                    if (isset($item['period']) && $item['period'] === $currentPeriod) {
                                        $matched += (float) ($item['amount'] ?? 0);
                                        $found = true;
                                    }
                                }
                                if ($found) return $matched;
                            }
                        }
                        return (float) ($inv->amount ?? 0);
                    });

                    $voucherBulanIni = (float) (\App\Models\Voucher::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                        ->where('used', true)
                        ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                            $q->whereBetween('used_at', [$startOfMonth, $endOfMonth])
                              ->orWhere(fn($sub) => $sub->whereNull('used_at')->whereBetween('created_at', [$startOfMonth, $endOfMonth]));
                        })
                        ->sum('price') ?? 0);

                    return (float) ($invoiceBulanIni + $voucherBulanIni);
                } catch (\Throwable $e) {
                    return 0;
                }
            })(),
            'cloud_panel_balance' => (function () use ($tenantId, $request) {
                try {
                    $tenant = $tenantId ? \App\Models\Tenant::withoutGlobalScopes()->find($tenantId) : null;
                    $vpnUserId = $tenant?->vpn_user_id ?? ($tenant?->settings['vpn_user_id'] ?? null);
                    $vpnUser = $vpnUserId ? \App\Models\VpnUser::find($vpnUserId) : null;
                    if (!$vpnUser && $tenant?->email) {
                        $vpnUser = \App\Models\VpnUser::where('email', $tenant->email)->first();
                    }
                    if (!$vpnUser && \Illuminate\Support\Facades\Auth::check()) {
                        $u = \Illuminate\Support\Facades\Auth::user();
                        $vpnUser = \App\Models\VpnUser::where('email', $u->email)->first();
                    }
                    if (!$vpnUser && \Illuminate\Support\Facades\Auth::guard('vpn')->check()) {
                        $vpnUser = \Illuminate\Support\Facades\Auth::guard('vpn')->user();
                    }
                    return (float) ($vpnUser?->total_saldo ?? $vpnUser?->saldo ?? $tenant?->saldo ?? 0);
                } catch (\Throwable $e) {
                    return 0;
                }
            })(),
            'active_addons' => (function () use ($tenantId) {
                try {
                    if (!$tenantId) return [];
                    return \App\Models\TenantAddon::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->whereIn('status', ['approved', 'active'])
                        ->where(function ($q) {
                            $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                        })
                        ->with('addon')
                        ->get()
                        ->map(fn($ta) => [
                            'id' => $ta->addon_id,
                            'slug' => $ta->addon?->slug,
                            'name' => $ta->addon?->name,
                            'route_name' => $ta->addon?->route_name,
                            'expired_at' => $ta->expired_at?->format('d M Y'),
                            'is_active' => true,
                        ])
                        ->toArray();
                } catch (\Throwable $e) {
                    return [];
                }
            })(),
            'available_addons' => (function () {
                try {
                    return \Illuminate\Support\Facades\Cache::remember('all_active_addons_list', 300, function() {
                        return \App\Models\Addon::withoutGlobalScopes()
                            ->where('is_active', true)
                            ->orderBy('id')
                            ->get()
                            ->map(fn($a) => [
                                'id' => $a->id,
                                'name' => $a->name,
                                'slug' => $a->slug,
                                'description' => $a->description,
                                'price' => (float) $a->price,
                                'billing_cycle' => $a->billing_cycle ?? 'monthly',
                                'route_name' => $a->route_name,
                                'feature_key' => $a->feature_key ?? $a->slug,
                            ])
                            ->toArray();
                    });
                } catch (\Throwable $e) {
                    return [];
                }
            })(),
            'license_info' => \App\Services\LicenseService::getLicensePayload(),
        ];
    }
}
