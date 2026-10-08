<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TroubleTicket;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected MikrotikService $mikrotik;

    public function __construct(MikrotikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    public function apiStats(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $request->user()?->tenant_id;
        $forceRefresh = $request->boolean('refresh') || $request->has('refresh');
        return response()->json($this->getStatsPayload($tenantId, $request->user()?->name ?? 'Administrator', $forceRefresh));
    }

    protected function getStatsPayload(?int $tenantId, string $userName = 'Administrator', bool $forceRefresh = false): array
    {
        $cacheKey = "dashboard_stats_tenant_" . ($tenantId ?? 'all');
        if (!$forceRefresh && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['user'] = $userName;
                return $cached;
            }
        }

        try {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $dbCustomerCount = Customer::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->count();
            $dbActiveCustomerCount = Customer::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->where('status', 'active')->count();
            $totalMikrotik = \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->count();
            $totalOnus = \App\Models\Onu::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->count();

            $totalPelanggan = $dbCustomerCount;
            $pelangganAktif = $dbActiveCustomerCount;

            // Compute real PPPoE online sessions from connected MikroTik routers
            $onlinePppoe = 0;
            $routers = \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->get();
            foreach ($routers as $r) {
                $cacheKeyRouter = "pppoe_router_cache_{$r->id}";
                $cachedRouterData = \Illuminate\Support\Facades\Cache::get($cacheKeyRouter);
                if ($cachedRouterData && isset($cachedRouterData['active']) && is_array($cachedRouterData['active'])) {
                    $onlinePppoe += count($cachedRouterData['active']);
                } else {
                    try {
                        $mik = new \App\Services\MikrotikService($r);
                        if ($mik->isConnected()) {
                            $actives = $mik->query('/ppp/active/print', ['.proplist' => 'name']);
                            if (is_array($actives)) {
                                $onlinePppoe += count($actives);
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore router connection error in dashboard stats
                    }
                }
            }

            $pendingQuery = Invoice::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where(function ($q) {
                    $q->where('paid', false)
                      ->orWhere('paid', 0)
                      ->orWhereNull('paid')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'unpaid');
                })
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'cancelled');

            $invoiceTertunda = (clone $pendingQuery)->count();
            $invoiceTertundaNominal = (float) ((clone $pendingQuery)->sum('amount') ?? 0);
            
            $currentPeriod = now()->format('Y-m');

            $invoiceHariIni = (float) (Invoice::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where(function ($q) {
                    $q->where('paid', true)->orWhere('paid', 1)->orWhere('status', 'paid');
                })
                ->whereBetween('paid_at', [$todayStart, $todayEnd])
                ->sum('amount') ?? 0);

            $voucherHariIni = (float) (\App\Models\Voucher::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('used', true)
                ->where(function ($q) use ($todayStart, $todayEnd) {
                    $q->whereBetween('used_at', [$todayStart, $todayEnd])
                      ->orWhere(fn($sub) => $sub->whereNull('used_at')->whereBetween('created_at', [$todayStart, $todayEnd]));
                })
                ->sum('price') ?? 0);

            $pendapatanHariIni = $invoiceHariIni + $voucherHariIni;
                
            // Invoices paid in this month OR paid for this period (including advance payment / uang muka)
            $paidInvoicesBulanIni = Invoice::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where(function ($q) {
                    $q->where('paid', true)->orWhere('paid', 1)->orWhere('status', 'paid');
                })
                ->where(function ($q) use ($monthStart, $monthEnd, $currentPeriod) {
                    $q->whereBetween('paid_at', [$monthStart, $monthEnd])
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
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('used_at', [$monthStart, $monthEnd])
                      ->orWhere(fn($sub) => $sub->whereNull('used_at')->whereBetween('created_at', [$monthStart, $monthEnd]));
                })
                ->sum('price') ?? 0);

            $pendapatanBulanIni = $invoiceBulanIni + $voucherBulanIni;

            $tiketGangguan = TroubleTicket::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))->whereIn('status', ['pending', 'in_progress'])->count();
            
            $totalInvoiceLunas = $paidInvoicesBulanIni->count();

            $pengeluaranBulanIni = (float) (\App\Models\Expense::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->sum('amount') ?? 0);

            $tagihanTertunda = (clone $pendingQuery)
                ->with(['customer.package'])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn($i) => [
                    'id' => $i->id,
                    'customer_name' => $i->customer?->name ?? $i->customer_name ?? '-',
                    'customer_code' => $i->customer?->code ?? (string) ($i->customer_id ?? '-'),
                    'package_name' => $i->customer?->package?->name ?? 'Paket Standar',
                    'invoice_number' => $i->invoice_number,
                    'amount' => (float) $i->amount,
                    'due_date' => $i->due_date ? (\Carbon\Carbon::parse($i->due_date)->format('d M Y')) : '-',
                    'status' => $i->status ?? 'pending',
                ])
                ->values()
                ->all();

            $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
            $maxCustomers = $tenant ? (int) $tenant->max_customers : 500;
            $maxRouters = $tenant ? (int) $tenant->max_routers : 5;
            $packageName = $tenant ? $tenant->package_name : "Paket Standar ({$maxCustomers} Pelanggan)";
            $customerUsagePct = $maxCustomers > 0 ? (int) min(100, round(($totalPelanggan / $maxCustomers) * 100)) : 0;
            $isNearCustomerLimit = $maxCustomers > 0 && $customerUsagePct >= 80;
            $isCustomerLimitReached = $maxCustomers > 0 && $totalPelanggan >= $maxCustomers;

            $resultPayload = [
                'user' => $userName,
                'totalPelanggan' => $totalPelanggan,
                'pelangganAktif' => $pelangganAktif,
                'onlinePppoe' => $onlinePppoe,
                'totalMikrotik' => $totalMikrotik,
                'totalOnus' => $totalOnus,
                'invoiceTertunda' => $invoiceTertunda,
                'invoiceTertundaNominal' => $invoiceTertundaNominal,
                'pendapatanHariIni' => $pendapatanHariIni,
                'pendapatanBulanIni' => $pendapatanBulanIni,
                'pengeluaranBulanIni' => $pengeluaranBulanIni,
                'tiketGangguan' => $tiketGangguan,
                'totalInvoiceLunas' => $totalInvoiceLunas,
                'paidInvoices' => $totalInvoiceLunas,
                'tagihanTertunda' => $tagihanTertunda,
                'tenantQuota' => [
                    'packageName' => $packageName,
                    'totalCustomers' => $totalPelanggan,
                    'activeCustomers' => $pelangganAktif,
                    'maxCustomers' => $maxCustomers,
                    'customerUsagePct' => $customerUsagePct,
                    'isNearLimit' => $isNearCustomerLimit,
                    'isLimitReached' => $isCustomerLimitReached,
                    'totalRouters' => $totalMikrotik,
                    'maxRouters' => $maxRouters,
                    'isTrial' => (bool) ($tenant?->trial_ends_at && now()->lessThan($tenant->trial_ends_at)),
                    'trialEndsAt' => $tenant?->trial_ends_at?->format('d M Y'),
                    'expiredAt' => $tenant?->expired_at?->format('d M Y'),
                ],
                'stats' => [
                    'onlinePppoe' => $onlinePppoe,
                    'activeCustomers' => $pelangganAktif,
                    'pendingInvoices' => $invoiceTertunda,
                    'pendingInvoicesNominal' => $invoiceTertundaNominal,
                    'todayRevenue' => $pendapatanHariIni,
                    'monthlyRevenue' => $pendapatanBulanIni,
                    'pendingTickets' => $tiketGangguan,
                ],
            ];

            \Illuminate\Support\Facades\Cache::put($cacheKey, $resultPayload, 20);
            return $resultPayload;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Dashboard getStatsPayload error: " . $e->getMessage());
            return [
                'user' => $userName,
                'totalPelanggan' => 0,
                'onlinePppoe' => 0,
                'totalMikrotik' => 0,
                'totalOnus' => 0,
                'invoiceTertunda' => 0,
                'pendapatanHariIni' => 0,
                'pendapatanBulanIni' => 0,
                'pengeluaranBulanIni' => 0,
                'tiketGangguan' => 0,
                'totalInvoiceLunas' => 0,
                'paidInvoices' => 0,
                'tagihanTertunda' => [],
                'tenantQuota' => [
                    'packageName' => 'Starter',
                    'totalCustomers' => 0,
                    'maxCustomers' => 500,
                    'customerUsagePct' => 0,
                    'isNearLimit' => false,
                    'isLimitReached' => false,
                    'totalRouters' => 0,
                    'maxRouters' => 5,
                    'isTrial' => false,
                    'trialEndsAt' => null,
                    'expiredAt' => null,
                ],
                'stats' => [
                    'onlinePppoe' => 0,
                    'pendingInvoices' => 0,
                    'todayRevenue' => 0,
                    'monthlyRevenue' => 0,
                    'pendingTickets' => 0,
                ],
            ];
        }
    }

    public function dashboard()
    {
        $host = request()->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: $host);
        if (str_starts_with($host, 'gateway.') || $host === 'gateway.' . $baseDomain) {
            return app(\App\Http\Controllers\NoderaPay\NoderaPayDashboardController::class)->dashboard();
        }

        if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.') || $host === 'wa.' . $baseDomain) {
            return app(\App\Http\Controllers\WaGateway\WaGatewayDashboardController::class)->dashboard();
        }

        if (str_starts_with($host, 'panel.')) {
            return app(\App\Http\Controllers\Vpn\DashboardController::class)->index();
        }

        $role = auth()->user()?->role ?? session('admin_role');
        if ($role === 'technician' || session('technician_logged_in')) {
            return redirect('/teknisi/dashboard');
        }
        if ($role === 'collector' || session('collector_logged_in')) {
            return redirect('/kolektor/dashboard');
        }
        if ($role === 'cashier' || session('cashier_logged_in')) {
            return redirect('/kasir/dashboard');
        }
        if ($role === 'superadmin' && !filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN)) {
            return redirect('/superadmin');
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
        $settingCompany = null;
        try {
            $settingCompany = \App\Models\Setting::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('key', 'COMPANY_NAME')->value('value');
        } catch (\Throwable $e) {}
        $companyName = $settingCompany ?: ($tenant?->company_name ?: ($tenant?->name ?: config('app.name', 'NODERA Billing')));
        $tenantName = $tenant?->name ?: ($tenant?->company_name ?: $companyName);

        $invoices = collect();
        try {
            $invoices = Invoice::with(['customer.package'])
                ->when($tenantId, fn ($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where(function ($q) {
                    $q->where('paid', false)
                      ->orWhere('paid', 0)
                      ->orWhereNull('paid')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'unpaid');
                })
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')
                ->latest('id')
                ->limit(10)
                ->get();
        } catch (\Throwable $e) {
            $invoices = collect();
        }

        $dashboardMenus = [];
        try {
            $dashboardMenus = \Illuminate\Support\Facades\DB::table('dashboard_menu_settings')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('sort_order')
                ->pluck('menu_key')
                ->all();
        } catch (\Throwable $e) {
            $dashboardMenus = [];
        }

        $announcements = collect();
        try {
            $announcements = \App\Models\Announcement::active()
                ->orderByDesc('published_at')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            $announcements = collect();
        }

        $latestAudit = null;
        $unreadCount = 0;
        try {
            $latestAudit = \App\Models\AuditLog::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->latest('created_at')
                ->first();
            $unreadCount = \App\Models\AuditLog::when($tenantId, fn($q) => $q->where(fn($sub) => $sub->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('created_at', '>=', now()->subDays(3))
                ->count();
        } catch (\Throwable $e) {
            $latestAudit = null;
            $unreadCount = 0;
        }
        $latestNotificationTimestamp = $latestAudit?->created_at?->toIso8601String() ?? now()->toIso8601String();
        $unreadCount += $invoices->count();

        $userName = session('admin_name') ?? auth()->user()?->name ?? 'Administrator';
        $initialStats = $this->getStatsPayload($tenantId, $userName);

        return Inertia::render('Admin/Dashboard', [
            'userName' => $userName,
            'companyName' => $companyName,
            'tenantName' => $tenantName,
            'dashboardMenus' => is_array($dashboardMenus) ? $dashboardMenus : [],
            'unreadNotificationsCount' => (int) $unreadCount,
            'latestNotificationTimestamp' => $latestNotificationTimestamp,
            'stats' => $initialStats,
            'initialStats' => $initialStats,
            'announcements' => $announcements->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'published_at' => ($a->published_at ?? $a->created_at)?->toIso8601String(),
            ])->values()->all(),
            'pendingInvoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'customer_name' => $i->customer?->name ?? $i->customer_name ?? '-',
                'customer_code' => $i->customer?->code ?? (string) ($i->customer_id ?? '-'),
                'package_name' => $i->customer?->package?->name ?? 'Paket Standar',
                'amount' => (float) $i->amount,
                'due_date' => $i->due_date ? (\Carbon\Carbon::parse($i->due_date)->format('d M Y')) : '-',
                'status' => $i->status ?? 'pending',
            ])->values()->all(),
        ]);
    }

    public function saveMenus(\Illuminate\Http\Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? $request->user()?->tenant_id;
        $menus = $request->input('dashboard_menus') ?? $request->input('menu', []);

        if (is_array($menus) && count($menus) > 0) {
            \Illuminate\Support\Facades\DB::table('dashboard_menu_settings')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
                ->delete();

            $records = [];
            foreach ($menus as $idx => $key) {
                if (empty($key) || !is_string($key)) continue;
                $records[] = [
                    'tenant_id' => $tenantId,
                    'menu_key' => $key,
                    'sort_order' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($records)) {
                \Illuminate\Support\Facades\DB::table('dashboard_menu_settings')->insert($records);
            }
        }

        return redirect()->back()->with('success', 'Pengaturan menu berhasil disimpan.');
    }
}
