<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Jobs\SendWhatsappNotification;

class CollectorAuthController extends Controller
{
    private function resolveTenant(Request $request): ?Tenant
    {
        $slug = $request->route('slug') ?: ($request->route('tenant') ?: session('tenant_slug'));

        if (! $slug) {
            $host = $request->getHost();
            $baseDomain = config('app.base_domain', 'dgtlnetsolution.com');
            if ($host !== $baseDomain && ! str_starts_with($host, 'panel.') && ! str_starts_with($host, 'vpn.')) {
                $subdomain = explode('.', $host)[0] ?? '';
                if ($subdomain && $subdomain !== 'www' && $subdomain !== 'localhost' && ! filter_var($subdomain, FILTER_VALIDATE_IP)) {
                    $slug = $subdomain;
                }
            }
        }

        if ($slug) {
            $t = Tenant::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->first();
            if ($t) return $t;
        }

        if (session('tenant_id')) {
            $t = Tenant::withoutGlobalScopes()->where('id', session('tenant_id'))->where('is_active', true)->first();
            if ($t) return $t;
        }

        return null;
    }

    private function checkPermission(string $permissionKey): bool
    {
        try {
            $collId = session('collector_id') ?? session('technician_id');
            if (! $collId) return false;

            $staff = Collector::withoutGlobalScopes()->find($collId)
                ?? User::withoutGlobalScopes()->find($collId);
            if (! $staff) return false;

            $allDefault = [
                'collect_payment' => true,
                'customers' => true,
                'dashboard' => true,
                'pool' => true,
                'create_customer' => true,
                'pppoe' => true,
                'isolate' => true,
                'map' => true,
                'top_bandwidth' => true,
                'earnings' => true,
                'history' => true,
            ];

            $perms = (!empty($staff->permissions) && is_array($staff->permissions))
                ? array_merge($allDefault, $staff->permissions)
                : $allDefault;

            return (bool) ($perms[$permissionKey] ?? true);
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function showLoginForm(Request $request)
    {
        $tenant = $this->resolveTenant($request);

        // Auto-login dari Cookie Persistent "Ingat Saya"
        $rememberId = $request->cookie('nodera_remember_collector');
        if ($rememberId) {
            $collectorQuery = Collector::withoutGlobalScopes()
                ->where('is_active', true);
            if ($tenant) {
                $collectorQuery->where('tenant_id', $tenant->id);
            }
            $collector = $collectorQuery->find($rememberId);
            if ($collector) {
                $collectorTenant = $collector->tenant_id ? Tenant::withoutGlobalScopes()->find($collector->tenant_id) : $tenant;
                session([
                    'collector_id' => $collector->id,
                    'collector_name' => $collector->name,
                    'collector_logged_in' => true,
                    'tenant_id' => $collector->tenant_id,
                    'tenant_slug' => $collectorTenant?->slug,
                    'tenant_name' => $collectorTenant?->name,
                ]);
                return redirect('/kolektor/dashboard');
            }
        }

        if ($tenant) {
            session([
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
            ]);
        }

        return Inertia::render('Auth/KolektorLogin', [
            'tenantName' => $tenant?->name ?? 'NODERA',
            'tenantSlug' => $tenant?->slug,
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $tenant = $this->resolveTenant($request);
        $username = is_string($request->username) ? trim($request->username) : '';

        // Cari kolektor (prioritaskan tenant context jika ada)
        $collectorQuery = Collector::withoutGlobalScopes()
            ->where('username', $username)
            ->where('is_active', true);

        if ($tenant) {
            $collectorQuery->where('tenant_id', $tenant->id);
        }

        $collector = $collectorQuery->first();

        // Jika tidak ditemukan di Collector, cari di User (teknisi/staf yang memiliki hak akses kolektor)
        if (! $collector) {
            $userQuery = User::withoutGlobalScopes()
                ->whereIn('role', ['collector', 'technician', 'cashier'])
                ->where(function ($q) use ($request) {
                    $q->where('username', $request->username)
                        ->orWhere('email', $request->username)
                        ->orWhere('phone', $request->username);
                });
            if ($tenant) {
                $userQuery->where('tenant_id', $tenant->id);
            }
            $u = $userQuery->first();
            if ($u && Hash::check($request->password, $u->password)) {
                $collector = $u;
            }
        }

        if (!$tenant && $collector && $collector->tenant_id) {
            $tenant = Tenant::withoutGlobalScopes()->where('id', $collector->tenant_id)->first();
        }

        if (! $collector || ! Hash::check($request->password, $collector->password)) {
            return back()->with('error', 'Username atau password salah')->withInput();
        }

        // Cek tenant masih ada & aktif
        if (! $tenant || ! $tenant->is_active || $tenant->isExpired()) {
            return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
        }

        session([
            'collector_id' => $collector->id,
            'collector_name' => $collector->name ?? $collector->username,
            'collector_username' => $collector->username,
            'collector_logged_in' => true,
            'technician_id' => $collector->id,
            'technician_name' => $collector->name ?? $collector->username,
            'technician_username' => $collector->username,
            'technician_logged_in' => true,
            'tenant_id' => $collector->tenant_id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
        ]);

        if ($request->boolean('remember', true)) {
            cookie()->queue('nodera_remember_collector', $collector->id, 525600);
        }

        return redirect('/kolektor/dashboard');
    }

    public function saveFcmToken(Request $request)
    {
        $collectorId = session('collector_id');
        if ($collectorId && $request->filled('token')) {
            Collector::where('id', $collectorId)->update(['fcm_token' => $request->token]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }

    public function dashboard(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $collector = Collector::findOrFail($collectorId);
        $tenantId = session('tenant_id') ?? $collector->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();

        \App\Services\CronService::consolidatePendingInvoices($tenantId);

        $routerId = $collector->router_id;
        $hasAssigned = Customer::where('collector_id', $collectorId)->exists();
        $cleanCollectorName = strtolower(trim($collector->name ?? ''));
        $cleanCollectorUser = strtolower(trim($collector->username ?? ''));

        $currentPeriodKey = Carbon::now()->format('Y-m');
        $activePeriod = ($request->filled('period') && $request->input('period') !== 'all')
            ? $request->input('period')
            : $currentPeriodKey;

        // Base invoices query strictly scoped to this collector's assigned customers or router or processed by this collector
        $invoiceQuery = Invoice::with(['customer.package', 'customer.router'])
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId));

        if ($hasAssigned) {
            $invoiceQuery->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId)
                  ->orWhereHas('customer', fn ($cq) => $cq->where('collector_id', $collectorId))
                  ->orWhere(function ($sub) use ($cleanCollectorName, $cleanCollectorUser) {
                      $sub->where('paid', 1);
                      if ($cleanCollectorName !== '') {
                          $sub->whereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                      } elseif ($cleanCollectorUser !== '') {
                          $sub->whereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                      }
                  });
            });
        } elseif ($routerId) {
            $invoiceQuery->whereHas('customer', fn ($cq) => $cq->where('router_id', $routerId));
        }

        $unpaidCount = (clone $invoiceQuery)->where('paid', 0)->count();
        $unpaidAmount = (float) (clone $invoiceQuery)->where('paid', 0)->sum('amount');

        // Query paid revenue specifically collected by this collector in active period
        $myPaidQuery = Invoice::when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('paid', 1)
            ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId);
                if ($cleanCollectorName !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                }
                if ($cleanCollectorUser !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                }
            })
            ->where(function ($q) use ($activePeriod) {
                $q->where('period', $activePeriod)
                  ->orWhere('periods_breakdown', 'like', "%\"{$activePeriod}\"%")
                  ->orWhere('due_date', 'like', "{$activePeriod}%")
                  ->orWhere('paid_at', 'like', "{$activePeriod}%");
            });

        $myPaidCount = (clone $myPaidQuery)->count();
        $myPaidAmount = (float) (clone $myPaidQuery)->sum('amount');

        // Today Payments by this collector
        $todayPayments = Invoice::with('customer')
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('paid', 1)
            ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId);
                if ($cleanCollectorName !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                }
                if ($cleanCollectorUser !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                }
            })
            ->whereDate('paid_at', today())
            ->latest('paid_at')
            ->get();

        // 14 Days Collection Trend for ApexCharts
        $dailyTrend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayPaid = Invoice::when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
                ->where('paid', 1)
                ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                    $q->where('collector_id', $collectorId);
                    if ($cleanCollectorName !== '') {
                        $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                    }
                    if ($cleanCollectorUser !== '') {
                        $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                    }
                })
                ->whereDate('paid_at', $date)
                ->get();

            $dailyTrend[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->translatedFormat('d M'),
                'amount' => (float) $dayPaid->sum('amount'),
                'count' => $dayPaid->count(),
            ];
        }

        // Recent 10 Collection Transactions
        $recentPayments = Invoice::with(['customer.package', 'customer.router'])
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('paid', 1)
            ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId);
                if ($cleanCollectorName !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                }
                if ($cleanCollectorUser !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                }
            })
            ->latest('paid_at')
            ->take(10)
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'customer_name' => $inv->customer?->name ?? $inv->customer_name,
                    'customer_code' => $inv->customer?->code ?? '-',
                    'amount' => (float) $inv->amount,
                    'paid_at' => $inv->paid_at ? \Carbon\Carbon::parse($inv->paid_at)->format('d M Y H:i') : null,
                    'period' => $inv->period,
                ];
            });

        // Attendance
        $todayAttendance = Attendance::where('collector_id', $collectorId)
            ->whereDate('date', today())
            ->first();

        return Inertia::render('Collector/Dashboard', [
            'collector' => [
                'name' => $collector->name,
                'username' => $collector->username,
                'router' => $collector->router?->name,
            ],
            'stats' => [
                'totalCustomers' => $myPaidCount + $unpaidCount,
                'paidCount' => $myPaidCount,
                'unpaidCount' => $unpaidCount,
                'paidAmount' => $myPaidAmount,
                'myPaidAmount' => $myPaidAmount,
                'myPaidCount' => $myPaidCount,
                'unpaidAmount' => $unpaidAmount,
                'todayPaidAmount' => (float) $todayPayments->sum('amount'),
                'todayPaidCount' => $todayPayments->count(),
            ],
            'chartData' => [
                'dailyTrend' => $dailyTrend,
                'paidRatio' => [
                    'paid' => $myPaidCount,
                    'unpaid' => $unpaidCount,
                ],
            ],
            'recentPayments' => $recentPayments,
            'todayPayments' => $todayPayments->map(fn ($i) => [
                'id' => $i->id,
                'customer_name' => $i->customer?->name ?? $i->customer_name,
                'amount' => (float) $i->amount,
                'paid_at' => $i->paid_at?->format('H:i'),
                'period' => $i->period,
            ]),
            'attendance' => $todayAttendance ? [
                'clock_in' => $todayAttendance->clock_in?->format('H:i'),
                'clock_out' => $todayAttendance->clock_out?->format('H:i'),
                'is_present' => true,
            ] : null,
            'permissions' => (!empty($collector->permissions) && is_array($collector->permissions))
                ? array_merge([
                    'collect_payment' => true,
                    'customers' => true,
                    'dashboard' => true,
                    'pool' => true,
                    'pppoe' => true,
                    'isolate' => true,
                    'map' => true,
                    'top_bandwidth' => true,
                    'earnings' => true,
                    'history' => true,
                ], $collector->permissions)
                : [
                    'collect_payment' => true,
                    'customers' => true,
                    'dashboard' => true,
                    'pool' => true,
                    'pppoe' => true,
                    'isolate' => true,
                    'map' => true,
                    'top_bandwidth' => true,
                    'earnings' => true,
                    'history' => true,
                ],
        ]);
    }

    public function invoices(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $collector = Collector::findOrFail($collectorId);
        $tenantId = session('tenant_id') ?? $collector->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();

        \App\Services\CronService::consolidatePendingInvoices($tenantId);

        $routerId = $collector->router_id;
        $hasAssigned = Customer::where('collector_id', $collectorId)->exists();
        $cleanCollectorName = strtolower(trim($collector->name ?? ''));
        $cleanCollectorUser = strtolower(trim($collector->username ?? ''));

        $packages = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $invoiceQuery = Invoice::with(['customer.package', 'customer.router', 'customer.collector'])
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId));

        if ($hasAssigned) {
            $invoiceQuery->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId)
                  ->orWhereHas('customer', fn ($cq) => $cq->where('collector_id', $collectorId))
                  ->orWhere(function ($sub) use ($cleanCollectorName, $cleanCollectorUser) {
                      $sub->where('paid', 1);
                      if ($cleanCollectorName !== '') {
                          $sub->whereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                      } elseif ($cleanCollectorUser !== '') {
                          $sub->whereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                      }
                  });
            });
        } elseif ($routerId) {
            $invoiceQuery->whereHas('customer', fn ($cq) => $cq->where('router_id', $routerId));
        }

        if ($packageFilter = $request->input('package')) {
            $invoiceQuery->whereHas('customer', fn ($cq) => $cq->where('package_id', $packageFilter));
        }

        if ($search = $request->input('search')) {
            $invoiceQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $currentPeriodKey = Carbon::now()->format('Y-m');
        $activePeriod = ($request->filled('period') && $request->input('period') !== 'all')
            ? $request->input('period')
            : $currentPeriodKey;

        // Support filtering by exact date/day or period
        $selectedDate = $request->input('date');
        $selectedDay = $request->input('day');

        if ($selectedDate) {
            $invoiceQuery->where(function ($q) use ($selectedDate) {
                $q->whereDate('due_date', $selectedDate)
                  ->orWhereDate('paid_at', $selectedDate)
                  ->orWhereDate('created_at', $selectedDate);
            });
        } elseif ($selectedDay && $selectedDay !== 'all') {
            $invoiceQuery->where(function ($q) use ($selectedDay, $activePeriod) {
                $q->whereRaw('DAY(due_date) = ?', [(int) $selectedDay])
                  ->orWhereRaw('DAY(paid_at) = ?', [(int) $selectedDay])
                  ->orWhereRaw('DAY(created_at) = ?', [(int) $selectedDay]);
            });
        } elseif ($request->filled('period') && $request->input('period') !== 'all') {
            $invoiceQuery->where(function ($q) use ($activePeriod) {
                $q->where('period', $activePeriod)
                  ->orWhere('periods_breakdown', 'like', "%\"{$activePeriod}\"%")
                  ->orWhere('due_date', 'like', "{$activePeriod}%")
                  ->orWhere('paid_at', 'like', "{$activePeriod}%");
            });
        }

        $myPaidQuery = Invoice::when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('paid', 1)
            ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId);
                if ($cleanCollectorName !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                }
                if ($cleanCollectorUser !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                }
            })
            ->where(function ($q) use ($activePeriod) {
                $q->where('period', $activePeriod)
                  ->orWhere('periods_breakdown', 'like', "%\"{$activePeriod}\"%")
                  ->orWhere('due_date', 'like', "{$activePeriod}%")
                  ->orWhere('paid_at', 'like', "{$activePeriod}%");
            });

        $myPaidCount = (clone $myPaidQuery)->count();
        $myPaidAmount = (float) (clone $myPaidQuery)->sum('amount');

        $totalInvoices = (clone $invoiceQuery)->count();
        $unpaidCount = (clone $invoiceQuery)->where('paid', 0)->count();
        $unpaidAmount = (float) (clone $invoiceQuery)->where('paid', 0)->sum('amount');
        $totalPaidAmount = (float) (clone $invoiceQuery)->where('paid', 1)->sum('amount');

        $filter = $request->input('status');
        if ($filter === 'paid') {
            $invoiceQuery->where('paid', 1)
                ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                    $q->where('collector_id', $collectorId);
                    if ($cleanCollectorName !== '') {
                        $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                    }
                    if ($cleanCollectorUser !== '') {
                        $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                    }
                });
        } elseif ($filter === 'unpaid') {
            $invoiceQuery->where('paid', 0);
        } else {
            $invoiceQuery->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('paid', 0)
                  ->orWhere(function ($sub) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                      $sub->where('paid', 1)
                          ->where(function ($s) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                              $s->where('collector_id', $collectorId);
                              if ($cleanCollectorName !== '') {
                                  $s->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                              }
                              if ($cleanCollectorUser !== '') {
                                  $s->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                              }
                          });
                  });
            });
        }

        $displayedInvoices = $invoiceQuery->orderBy('invoices.created_at', 'desc')->get();

        $items = $displayedInvoices->map(function ($inv) {
            $c = $inv->customer;
            $assignedCollectorName = $c?->collector?->name ?? ($inv->collector_id ? Collector::find($inv->collector_id)?->name : null);
            return [
                'id' => $c ? $c->id : $inv->customer_id,
                'name' => $c ? $c->name : ($inv->customer_name ?? 'Pelanggan'),
                'code' => $c ? $c->code : '-',
                'phone' => $c ? $c->phone : null,
                'address' => $c ? $c->address : null,
                'router' => $c?->router?->name ?? null,
                'package' => $c?->package?->name ?? null,
                'status' => $c?->status ?? 'active',
                'assigned_collector_name' => $assignedCollectorName,
                'latest_invoice' => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'amount' => (float) $inv->amount,
                    'paid' => (bool) $inv->paid,
                    'due_date' => $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('Y-m-d') : null,
                    'paid_at' => $inv->paid_at ? \Carbon\Carbon::parse($inv->paid_at)->toIso8601String() : null,
                    'period' => $inv->period,
                    'periods_breakdown' => $inv->periods_breakdown,
                    'processed_by' => $inv->processed_by,
                    'collector_id' => $inv->collector_id,
                    'assigned_collector_name' => $assignedCollectorName,
                ],
            ];
        })->values();

        $todayPayments = Invoice::with(['customer.collector'])
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where('paid', 1)
            ->where(function ($q) use ($collectorId, $cleanCollectorName, $cleanCollectorUser) {
                $q->where('collector_id', $collectorId);
                if ($cleanCollectorName !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorName]);
                }
                if ($cleanCollectorUser !== '') {
                    $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanCollectorUser]);
                }
            })
            ->whereDate('paid_at', today())
            ->latest('paid_at')
            ->get();

        $todayAttendance = Attendance::where('collector_id', $collectorId)
            ->whereDate('date', today())
            ->first();

        return Inertia::render('Collector/Invoices', [
            'collector' => [
                'name' => $collector->name,
                'username' => $collector->username,
                'router' => $collector->router?->name,
            ],
            'selectedPeriod' => $activePeriod,
            'selectedDate' => $selectedDate,
            'selectedDay' => $selectedDay,
            'initialDay' => $selectedDay,
            'initialPeriod' => $request->input('period', $currentPeriodKey),
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'package' => $request->input('package'),
                'show_all' => (bool) $request->input('show_all'),
                'date' => $selectedDate,
                'day' => $selectedDay,
                'period' => $activePeriod,
            ],
            'stats' => [
                'totalCustomers' => $myPaidCount + $unpaidCount,
                'paidCount' => $myPaidCount,
                'unpaidCount' => $unpaidCount,
                'paidAmount' => $myPaidAmount,
                'myPaidAmount' => $myPaidAmount,
                'myPaidCount' => $myPaidCount,
                'unpaidAmount' => $unpaidAmount,
                'todayPaidAmount' => (float) $todayPayments->sum('amount'),
                'todayPaidCount' => $todayPayments->count(),
            ],
            'todayPayments' => $todayPayments->map(fn ($i) => [
                'id' => $i->id,
                'customer_name' => $i->customer?->name ?? $i->customer_name,
                'amount' => (float) $i->amount,
                'paid_at' => $i->paid_at?->format('H:i'),
                'period' => $i->period,
                'assigned_collector_name' => $i->customer?->collector?->name ?? $collector->name,
            ]),
            'customers' => $items,
            'packages' => $packages,
            'attendance' => $todayAttendance ? [
                'clock_in' => $todayAttendance->clock_in?->format('H:i'),
                'clock_out' => $todayAttendance->clock_out?->format('H:i'),
                'is_present' => true,
            ] : null,
            'permissions' => (!empty($collector->permissions) && is_array($collector->permissions))
                ? array_merge([
                    'collect_payment' => true,
                    'customers' => true,
                    'dashboard' => true,
                    'pool' => true,
                    'pppoe' => true,
                    'isolate' => true,
                    'map' => true,
                    'top_bandwidth' => true,
                    'earnings' => true,
                    'history' => true,
                ], $collector->permissions)
                : [
                    'collect_payment' => true,
                    'customers' => true,
                    'dashboard' => true,
                    'pool' => true,
                    'pppoe' => true,
                    'isolate' => true,
                    'map' => true,
                    'top_bandwidth' => true,
                    'earnings' => true,
                    'history' => true,
                ],
        ]);
    }

    /**
     * Top Bandwidth versi kolektor — router tenant kolektor, layout kolektor.
     */
    public function topBandwidth()
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('top_bandwidth')) {
            return redirect('/kolektor/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat Top Bandwidth.');
        }

        $routers = Mikrotik::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/TopBandwidth', [
            'routers' => $routers,
            'mode' => 'collector',
        ]);
    }

    /**
     * PPPoE versi kolektor — daftar user & sesi aktif di router tenant kolektor.
     */
    public function pppoe(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('pppoe')) {
            return redirect('/kolektor/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses menu PPPoE.');
        }

        $collector = Collector::find($collectorId);
        $tenantId  = $collector?->tenant_id;
        $routers   = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('is_active', true)->orderBy('name')->get();
        $routerId  = $request->get('router_id', $collector?->router_id ?: $routers->first()?->id);
        $users     = [];
        $active    = [];
        $inactive  = [];
        $ifaceByName = [];
        $error     = null;
        $forceRefresh = $request->boolean('refresh') || $request->has('refresh');

        if ($routerId) {
            $router = Mikrotik::find($routerId);
            if ($router) {
                try {
                    $cacheKey = "pppoe_router_cache_{$router->id}";
                    $routerData = $forceRefresh ? null : \Illuminate\Support\Facades\Cache::get($cacheKey);

                    if (!$routerData) {
                        $mik = new MikrotikService($router);
                        if (! $mik->isConnected()) {
                            $error = 'Tidak dapat terhubung ke ' . $router->name . ($mik->getLastError() ? " ({$mik->getLastError()})" : '');
                        } else {
                            $rUsers = $mik->query('/ppp/secret/print', [
                                '.proplist' => 'name,password,profile,disabled,last-logged-out,comment,remote-address,service',
                            ]);
                            if (!is_array($rUsers)) $rUsers = [];

                            $rActive = $mik->query('/ppp/active/print', [
                                '.proplist' => 'name,service,caller-id,address,uptime,session-id,radius',
                            ]);
                            if (!is_array($rActive)) $rActive = [];

                            $activeNamesMap = array_flip(array_filter(array_column($rActive, 'name')));
                            $rInactive = array_values(array_filter($rUsers, function ($s) use ($activeNamesMap) {
                                $uname = $s['name'] ?? '';
                                return !isset($activeNamesMap[$uname]) && ($s['disabled'] ?? 'false') !== 'true';
                            }));

                            $rIfaces = [];
                            try {
                                $ifaces = $mik->query('/interface/print', [
                                    '.proplist' => 'name,rx-byte,tx-byte',
                                ]);
                                if (is_array($ifaces)) {
                                    foreach ($ifaces as $iface) {
                                        $iname = $iface['name'] ?? '';
                                        if ($iname) {
                                            $rIfaces[$iname] = $iface;
                                            $clean = trim($iname, '<> ');
                                            $rIfaces[$clean] = $iface;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {}

                            $routerData = [
                                'users' => $rUsers,
                                'active' => $rActive,
                                'inactive' => $rInactive,
                                'ifaces' => $rIfaces,
                            ];
                            \Illuminate\Support\Facades\Cache::put($cacheKey, $routerData, 20);
                        }
                    }

                    if ($routerData) {
                        $users = $routerData['users'] ?? [];
                        $active = $routerData['active'] ?? [];
                        $inactive = $routerData['inactive'] ?? [];
                        $ifaceByName = $routerData['ifaces'] ?? [];
                    }
                } catch (\Throwable $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }

        // Load customers & their linked ONUs dengan caching 45s untuk efisiensi polling realtime
        $cacheKeyMeta = "pppoe_meta_map_" . ($tenantId ?? 'all');
        $metaMaps = $forceRefresh ? null : \Illuminate\Support\Facades\Cache::get($cacheKeyMeta);

        if (!$metaMaps) {
            $customers = Customer::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->select(['id', 'name', 'phone', 'pppoe_username', 'tenant_id'])
                ->with(['onu' => fn ($q) => $q->select(['id', 'customer_id', 'status', 'rx_power', 'tx_power', 'pon_port', 'name', 'olt_id', 'serial_number'])])
                ->get();
            $customerMap = [];
            foreach ($customers as $c) {
                $onu = $c->onu;
                $data = [
                    'customer_id'   => $c->id,
                    'customer_name' => $c->name,
                    'phone'         => $c->phone,
                    'onu_status'    => $onu?->status,
                    'onu_rx_power'  => $onu?->rx_power ? (float) $onu->rx_power : null,
                    'onu_tx_power'  => $onu?->tx_power ? (float) $onu->tx_power : null,
                    'onu_pon'       => $onu?->pon_port,
                    'onu_name'      => $onu?->name,
                    'olt_name'      => null,
                    'onu_serial'    => $onu?->serial_number,
                ];

                if (!empty($c->pppoe_username)) {
                    $customerMap[strtolower(trim($c->pppoe_username))] = $data;
                }
                if (!empty($c->name)) {
                    $customerMap[strtolower(trim($c->name))] = $data;
                }
            }

            // Also check if any ONU name matches PPPoE username directly
            $unlinkedOnus = \App\Models\Onu::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->with('olt')->whereNull('customer_id')->get();
            $onuDirectMap = [];
            foreach ($unlinkedOnus as $o) {
                if (!empty($o->name)) {
                    $onuDirectMap[strtolower(trim($o->name))] = [
                        'customer_id'   => null,
                        'customer_name' => null,
                        'phone'         => null,
                        'onu_status'    => $o->status,
                        'onu_rx_power'  => $o->rx_power ? (float) $o->rx_power : null,
                        'onu_tx_power'  => $o->tx_power ? (float) $o->tx_power : null,
                        'onu_pon'       => $o->pon_port,
                        'onu_name'      => $o->name,
                        'olt_name'      => $o->olt?->name,
                        'onu_serial'    => $o->serial_number,
                    ];
                }
            }

            $metaMaps = [
                'customers' => $customerMap,
                'onus'      => $onuDirectMap,
            ];
            \Illuminate\Support\Facades\Cache::put($cacheKeyMeta, $metaMaps, 45);
        }

        $customerMap = $metaMaps['customers'] ?? [];
        $onuDirectMap = $metaMaps['onus'] ?? [];

        $resolveMeta = function (string $username) use ($customerMap, $onuDirectMap) {
            $key = strtolower(trim($username));
            return $customerMap[$key] ?? $onuDirectMap[$key] ?? null;
        };

        $resolveTraffic = function (string $username) use (&$ifaceByName) {
            $ifaceRow = $ifaceByName[$username] ?? null;
            if (!$ifaceRow && !empty($ifaceByName)) {
                foreach ($ifaceByName as $iname => $row) {
                    if (stripos($iname, $username) !== false) {
                        $ifaceRow = $row;
                        break;
                    }
                }
            }

            $rx = $ifaceRow ? (int)($ifaceRow['rx-byte'] ?? 0) : 0;
            $tx = $ifaceRow ? (int)($ifaceRow['tx-byte'] ?? 0) : 0;
            $total = $rx + $tx;

            return [
                'bytes_in'    => $rx,
                'bytes_out'   => $tx,
                'total_bytes' => $total,
            ];
        };

        return Inertia::render('Admin/Pppoe', [
            'mode'     => 'collector',
            'routerId' => $routerId,
            'error'    => $error,
            'routers'  => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'users'    => collect($users)->map(function ($u) use ($resolveMeta, $resolveTraffic) {
                $username = $u['name'] ?? '';
                $meta = $resolveMeta($username);
                $traffic = $resolveTraffic($username);
                return [
                    'name'            => $username,
                    'customer_name'   => $meta['customer_name'] ?? null,
                    'customer_id'     => $meta['customer_id'] ?? null,
                    'phone'           => $meta['phone'] ?? null,
                    'onu_status'      => $meta['onu_status'] ?? null,
                    'onu_rx_power'    => $meta['onu_rx_power'] ?? null,
                    'onu_pon'         => $meta['onu_pon'] ?? null,
                    'onu_name'        => $meta['onu_name'] ?? null,
                    'olt_name'        => $meta['olt_name'] ?? null,
                    'password'        => $u['password'] ?? '',
                    'profile'         => $u['profile'] ?? '',
                    'disabled'        => ($u['disabled'] ?? '') === 'true',
                    'last_logged_out' => $u['last-logged-out'] ?? null,
                    'onu_tx_power'    => $meta['onu_tx_power'] ?? null,
                    'onu_pon'         => $meta['onu_pon'] ?? null,
                    'onu_name'        => $meta['onu_name'] ?? null,
                    'olt_name'        => $meta['olt_name'] ?? null,
                    'onu_serial'      => $meta['onu_serial'] ?? null,
                ];
            }),
            'active' => collect($active)->map(function ($a) use ($resolveMeta, $resolveTraffic) {
                $username = $a['name'] ?? '';
                $meta = $resolveMeta($username);
                $traffic = $resolveTraffic($username);
                return [
                    'name'          => $username,
                    'address'       => $a['address'] ?? '',
                    'uptime'        => $a['uptime'] ?? '',
                    'bytes_in'      => $traffic['bytes_in'],
                    'bytes_out'     => $traffic['bytes_out'],
                    'total_bytes'   => $traffic['total_bytes'],
                    'customer_id'   => $meta['customer_id'] ?? null,
                    'customer_name' => $meta['customer_name'] ?? null,
                    'phone'         => $meta['phone'] ?? null,
                    'onu_status'    => $meta['onu_status'] ?? null,
                    'onu_rx_power'  => $meta['onu_rx_power'] ?? null,
                    'onu_tx_power'  => $meta['onu_tx_power'] ?? null,
                    'onu_pon'       => $meta['onu_pon'] ?? null,
                    'onu_name'      => $meta['onu_name'] ?? null,
                    'olt_name'      => $meta['olt_name'] ?? null,
                    'onu_serial'    => $meta['onu_serial'] ?? null,
                ];
            }),
            'inactive' => collect($inactive)->map(function ($i) use ($resolveMeta, $resolveTraffic) {
                $username = $i['name'] ?? '';
                $meta = $resolveMeta($username);
                $traffic = $resolveTraffic($username);
                return [
                    'name'            => $username,
                    'profile'         => $i['profile'] ?? '',
                    'last_logged_out' => $i['last-logged-out'] ?? null,
                    'customer_id'     => $meta['customer_id'] ?? null,
                    'customer_name'   => $meta['customer_name'] ?? null,
                    'phone'           => $meta['phone'] ?? null,
                    'onu_status'      => $meta['onu_status'] ?? null,
                    'onu_rx_power'    => $meta['onu_rx_power'] ?? null,
                    'onu_tx_power'    => $meta['onu_tx_power'] ?? null,
                    'onu_pon'         => $meta['onu_pon'] ?? null,
                    'onu_name'        => $meta['onu_name'] ?? null,
                    'olt_name'        => $meta['olt_name'] ?? null,
                    'onu_serial'      => $meta['onu_serial'] ?? null,
                    'bytes_in'        => $traffic['bytes_in'],
                    'bytes_out'       => $traffic['bytes_out'],
                    'total_bytes'     => $traffic['total_bytes'],
                ];
            }),
        ]);
    }


    public function checkIn(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $existing = Attendance::where('collector_id', $collectorId)
            ->whereDate('date', today())->first();

        if ($existing) {
            return back()->with('error', 'Sudah check-in hari ini');
        }

        Attendance::create([
            'collector_id' => $collectorId,
            'date' => today(),
            'check_in' => now(),
            'tenant_id' => session('tenant_id'),
        ]);

        return back()->with('msg', 'Check-in berhasil');
    }

    public function checkOut(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $attendance = Attendance::where('collector_id', $collectorId)
            ->whereDate('date', today())->first();

        if (! $attendance) {
            return back()->with('error', 'Belum check-in hari ini');
        }

        if ($attendance->check_out) {
            return back()->with('error', 'Sudah check-out hari ini');
        }

        $attendance->update(['check_out' => now()]);

        return back()->with('msg', 'Check-out berhasil');
    }

    public function markPaid(Request $request, $customerId)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('collect_payment')) {
            return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk memproses pembayaran tagihan.');
        }

        $collector = Collector::findOrFail($collectorId);

        $request->validate([
            'paid_periods' => 'nullable|string|max:500',
        ]);

        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);

        // Ownership: kolektor hanya boleh menagih pelanggan di router-nya
        // atau pelanggan yang ditugaskan langsung ke dirinya.
        if (! $this->customerInCollectorScope($customer)) {
            return back()->with('error', 'Pelanggan di luar wilayah penagihan Anda');
        }

        $paidPeriodsStr = $request->input('paid_periods', '');
        $paidPeriodsArr = array_filter(array_map('trim', explode(',', (string) $paidPeriodsStr)));
        $rawBreakdown = $request->input('periods_breakdown');

        // Fetch all unpaid invoices for this customer with lock
        $unpaidInvoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where(function ($q) {
                $q->where('paid', false)
                  ->orWhere('paid', 0)
                  ->orWhere('status', '!=', 'paid');
            })
            ->lockForUpdate()
            ->orderBy('id', 'asc')
            ->get();

        if ($unpaidInvoices->isEmpty()) {
            $packagePrice = (float) ($customer->package?->price ?? 0);
            $invoice = Invoice::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'invoice_number' => 'INV-' . date('Ym') . '-' . $customer->id,
                'amount' => $packagePrice,
                'description' => 'Tagihan ' . date('F Y'),
                'due_date' => now()->endOfMonth(),
                'period' => date('Y-m'),
                'status' => 'pending',
                'paid' => false,
            ]);
            $unpaidInvoices = collect([$invoice]);
        }

        $paidTotalAmount = 0;
        $paidCount = 0;
        $lastPaidInvoice = null;

        // 1. Multiple unpaid invoices in DB
        if ($unpaidInvoices->count() > 1 && !empty($paidPeriodsArr)) {
            foreach ($unpaidInvoices as $inv) {
                $invPeriod = $inv->period ?? ($inv->due_date ? date('Y-m', strtotime($inv->due_date)) : '');
                $invBd = $inv->periods_breakdown ? json_decode($inv->periods_breakdown, true) : [];

                if (!empty($invBd)) {
                    $paidBd = [];
                    $remBd = [];
                    foreach ($invBd as $item) {
                        if (in_array($item['period'], $paidPeriodsArr)) {
                            $paidBd[] = $item;
                        } else {
                            $remBd[] = $item;
                        }
                    }

                    if (!empty($paidBd) && !empty($remBd)) {
                        $pAmount = array_sum(array_column($paidBd, 'amount'));
                        $rAmount = array_sum(array_column($remBd, 'amount'));
                        $inv->update([
                            'amount' => $rAmount,
                            'description' => implode(' + ', array_map(fn($p) => $p['label'] ?? $p['period'], $remBd)),
                            'period' => end($remBd)['period'] ?? $remBd[0]['period'] ?? $inv->period,
                            'periods_breakdown' => json_encode($remBd),
                        ]);

                        $paidInvNumber = 'INV-' . now()->format('Ym') . '-P-' . $customer->id . '-' . rand(100, 999);
                        $lastPaidInvoice = Invoice::create([
                            'tenant_id' => $customer->tenant_id,
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'invoice_number' => $paidInvNumber,
                            'amount' => $pAmount,
                            'description' => "Pembayaran Tagihan (" . count($paidBd) . " Periode: " . implode(', ', array_map(fn($p) => $p['label'] ?? $p['period'], $paidBd)) . ")",
                            'period' => $paidBd[0]['period'] ?? $inv->period,
                            'due_date' => $inv->due_date,
                            'paid' => 1,
                            'status' => 'paid',
                            'paid_at' => now(),
                            'payment_method' => 'manual',
                            'processed_by' => $collector->name,
                            'collector_id' => $collectorId,
                            'periods_breakdown' => json_encode($paidBd),
                        ]);
                        $paidCount += count($paidBd);
                        $paidTotalAmount += $pAmount;
                    } elseif (!empty($paidBd) && empty($remBd)) {
                        $inv->update([
                            'paid' => true,
                            'paid_at' => now(),
                            'status' => 'paid',
                            'payment_method' => 'manual',
                            'processed_by' => $collector->name,
                            'collector_id' => $collectorId,
                        ]);
                        $lastPaidInvoice = $inv;
                        $paidCount += count($paidBd);
                        $paidTotalAmount += (float) $inv->amount;
                    }
                } elseif (in_array($invPeriod, $paidPeriodsArr)) {
                    $inv->update([
                        'paid' => true,
                        'paid_at' => now(),
                        'status' => 'paid',
                        'payment_method' => 'manual',
                        'processed_by' => $collector->name,
                        'collector_id' => $collectorId,
                    ]);
                    $lastPaidInvoice = $inv;
                    $paidCount++;
                    $paidTotalAmount += (float) $inv->amount;
                }
            }
        }

        // 2. Single invoice with multi-period breakdown or fallback
        if ($paidCount === 0) {
            $invoice = $unpaidInvoices->first();
            $breakdown = $invoice->periods_breakdown ? json_decode($invoice->periods_breakdown, true) : [];
            if (!empty($rawBreakdown)) {
                $parsed = is_array($rawBreakdown) ? $rawBreakdown : json_decode($rawBreakdown, true);
                if (!empty($parsed) && is_array($parsed)) {
                    $breakdown = $parsed;
                }
            }

            if (!empty($breakdown) && !empty($paidPeriodsArr)) {
                $paidItems = [];
                $remaining = [];
                foreach ($breakdown as $bd) {
                    if (in_array($bd['period'], $paidPeriodsArr)) {
                        $paidItems[] = $bd;
                    } else {
                        $remaining[] = $bd;
                    }
                }

                if (!empty($remaining) && !empty($paidItems)) {
                    $paidAmount = array_sum(array_column($paidItems, 'amount'));
                    $remainingAmount = array_sum(array_column($remaining, 'amount'));
                    $newDesc = implode(' + ', array_map(fn($p) => ($p['label'] ?? $p['period']), $remaining));

                    $invoice->update([
                        'amount' => $remainingAmount,
                        'description' => $newDesc,
                        'period' => end($remaining)['period'] ?? $remaining[0]['period'] ?? $invoice->period,
                        'periods_breakdown' => json_encode($remaining),
                    ]);

                    $paidInvNumber = 'INV-' . now()->format('Ym') . '-P-' . $customer->id . '-' . rand(100, 999);
                    $lastPaidInvoice = Invoice::create([
                        'tenant_id' => $customer->tenant_id,
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'invoice_number' => $paidInvNumber,
                        'amount' => $paidAmount,
                        'description' => "Pembayaran Tagihan (" . count($paidItems) . " Periode: " . implode(', ', array_map(fn($p) => $p['label'] ?? $p['period'], $paidItems)) . ")",
                        'period' => $paidItems[0]['period'] ?? $invoice->period,
                        'due_date' => $invoice->due_date,
                        'paid' => 1,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => 'manual',
                        'processed_by' => $collector->name,
                        'collector_id' => $collectorId,
                        'periods_breakdown' => json_encode($paidItems),
                    ]);

                    $paidCount = count($paidItems);
                    $paidTotalAmount = $paidAmount;
                } else {
                    $invoice->update([
                        'paid' => true,
                        'paid_at' => now(),
                        'status' => 'paid',
                        'payment_method' => 'manual',
                        'processed_by' => $collector->name,
                        'collector_id' => $collectorId,
                    ]);
                    $lastPaidInvoice = $invoice;
                    $paidCount = 1;
                    $paidTotalAmount = (float) $invoice->amount;
                }
            } else {
                $invoice->update([
                    'paid' => true,
                    'paid_at' => now(),
                    'status' => 'paid',
                    'payment_method' => 'manual',
                    'processed_by' => $collector->name,
                    'collector_id' => $collectorId,
                ]);
                $lastPaidInvoice = $invoice;
                $paidCount = 1;
                $paidTotalAmount = (float) $invoice->amount;
            }
        }

        $invoice = $lastPaidInvoice ?? $unpaidInvoices->first();

        // Un-isolate customer unconditionally if previously isolated via background queue
        \App\Jobs\UnisolateCustomerJob::dispatch($customer->id, 'Kolektor ' . $collector->name);

        try {
            app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Push notification failed on collector markPaid: " . $e->getMessage());
        }

        // Dispatch background job to send WhatsApp payment success notification
        SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'payment_success');

        return back()->with('msg', "Pembayaran {$customer->name} berhasil dicatat. Layanan telah aktif kembali.");
    }

    public function markPaidBatch(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('collect_payment')) {
            return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk memproses pembayaran tagihan.');
        }

        $collector = Collector::findOrFail($collectorId);

        $rawIds = $request->input('ids', $request->input('customer_ids', $request->input('invoice_ids', [])));
        if (! is_array($rawIds)) {
            $rawIds = explode(',', (string) $rawIds);
        }
        $ids = array_filter(array_map('intval', $rawIds));

        if (empty($ids)) {
            return back()->with('error', 'Tidak ada pelanggan yang dipilih.');
        }

        $paidPeriodsMap = $request->input('paid_periods_map', []);
        $count = 0;
        foreach ($ids as $cid) {
            $customer = Customer::withoutGlobalScopes()->find($cid);
            if (! $customer || ! $this->customerInCollectorScope($customer)) {
                continue;
            }
            $invoice = Invoice::withoutGlobalScopes()
                ->where('customer_id', $cid)
                ->where(function ($q) {
                    $q->where('paid', false)
                      ->orWhere('paid', 0)
                      ->orWhereNull('paid')
                      ->orWhere('status', '!=', 'paid');
                })
                ->latest('id')
                ->first();

            if (! $invoice) {
                $packagePrice = (float) ($customer->package?->price ?? 0);
                $invoice = Invoice::create([
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'invoice_number' => 'INV-' . date('Ym') . '-' . $customer->id,
                    'amount' => $packagePrice,
                    'description' => 'Tagihan ' . date('F Y'),
                    'due_date' => now()->endOfMonth(),
                    'period' => date('Y-m'),
                    'status' => 'pending',
                    'paid' => false,
                ]);
            }
            if ($invoice) {
                $paidPeriods = $paidPeriodsMap[$cid] ?? $paidPeriodsMap[$invoice->id] ?? null;
                $breakdown = $invoice->periods_breakdown ? json_decode($invoice->periods_breakdown, true) : [];

                if (! empty($breakdown) && ! empty($paidPeriods)) {
                    $paidArr = is_array($paidPeriods) ? $paidPeriods : explode(',', (string) $paidPeriods);
                    $remaining = [];
                    $paidAmount = 0;
                    foreach ($breakdown as $bd) {
                        if (in_array($bd['period'], $paidArr)) {
                            $paidAmount += $bd['amount'];
                        } else {
                            $remaining[] = $bd;
                        }
                    }

                    if (! empty($remaining)) {
                        $newAmount = array_sum(array_column($remaining, 'amount'));
                        $newDesc = implode(' + ', array_map(fn ($p) => ($p['label'] ?? $p['period']), $remaining));
                        $invoice->update([
                            'amount' => $newAmount,
                            'description' => $newDesc,
                            'periods_breakdown' => json_encode($remaining),
                        ]);

                        $paidInvNumber = 'INV-' . date('Ym') . '-P-' . $customer->id . '-' . rand(100, 999);
                        $paidInvoice = Invoice::create([
                            'tenant_id' => $invoice->tenant_id,
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'invoice_number' => $paidInvNumber,
                            'amount' => $paidAmount,
                            'description' => "Pembayaran Tagihan (" . count($paidArr) . " Periode)",
                            'period' => $paidArr[0] ?? $invoice->period,
                            'due_date' => $invoice->due_date,
                            'paid' => true,
                            'paid_at' => now(),
                            'status' => 'paid',
                            'payment_method' => 'manual',
                            'processed_by' => $collector->name,
                            'collector_id' => $collectorId,
                            'periods_breakdown' => json_encode(array_filter($breakdown, fn($bd) => in_array($bd['period'], $paidArr))),
                        ]);

                        \App\Jobs\UnisolateCustomerJob::dispatch($customer->id, 'Kolektor ' . $collector->name);

                        try {
                            app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $paidInvoice);
                        } catch (\Throwable $e) {}

                        SendWhatsappNotification::dispatchAfterResponse($customer, $paidInvoice, 'payment_success');
                        $count++;
                        continue;
                    }
                }

                $invoice->update([
                    'paid' => true,
                    'paid_at' => now(),
                    'status' => 'paid',
                    'payment_method' => 'manual',
                    'processed_by' => $collector->name,
                    'collector_id' => $collectorId,
                ]);

                \App\Jobs\UnisolateCustomerJob::dispatch($customer->id, 'Kolektor ' . $collector->name);

                try {
                    app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $invoice);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Push notification failed on collector batch: " . $e->getMessage());
                }

                // Dispatch WhatsApp payment success notification
                SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'payment_success');
                $count++;
            }
        }

        return back()->with('msg', "{$count} pembayaran berhasil dicatat. Seluruh layanan telah diaktifkan kembali.");
    }

    /**
     * Cek apakah pelanggan berada dalam wilayah penagihan kolektor.
     * Kolektor berhak menagih jika pelanggan ditugaskan langsung kepadanya,
     * berada di router miliknya, atau berada dalam tenant yang sama.
     */
    private function customerInCollectorScope($customer)
    {
        $collectorId = session('collector_id');
        $tenantId = session('tenant_id');

        if ($tenantId && (int) $customer->tenant_id !== (int) $tenantId) {
            return false;
        }

        if ((int) $customer->collector_id === (int) $collectorId) {
            return true;
        }

        $collector = Collector::find($collectorId);
        if ($collector && $collector->router_id && (int) $customer->router_id === (int) $collector->router_id) {
            return true;
        }

        // Tanpa pembatasan router/collector, fallback ke scope tenant saja.
        return true;
    }

    public function logout()
    {
        session()->forget(['collector_id', 'collector_name', 'collector_logged_in', 'tenant_id', 'tenant_slug', 'tenant_name']);
        cookie()->queue(cookie()->forget('nodera_remember_collector'));

        return redirect('/kolektor/login')->with('msg', 'Berhasil logout');
    }

    public function earnings(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('earnings')) {
            return redirect('/kolektor/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat data pendapatan.');
        }

        $collector = Collector::findOrFail($collectorId);

        $commType = $collector->commission_type ?? 'fixed';
        $commValue = (float) ($collector->commission_value ?? 0);
        $globalDefault = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', 5000);

        if ($commType === 'percent' || $commType === 'percentage') {
            $rateLabel = number_format($commValue, 0) . '% dari total penagihan';
        } else {
            $rate = $commValue > 0 ? $commValue : $globalDefault;
            $rateLabel = 'Rp ' . number_format($rate, 0, ',', '.') . ' / invoice';
        }

        $cleanName = trim($collector->name);
        $cleanUsername = trim($collector->username);

        $invoices = Invoice::with('customer')
            ->where(function ($q) {
                $q->where('paid', 1)
                  ->orWhere('status', 'paid');
            })
            ->where(function ($q) use ($collectorId, $cleanName, $cleanUsername) {
                $q->where('collector_id', $collectorId)
                  ->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [strtolower($cleanName)])
                  ->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [strtolower($cleanUsername)]);
            })
            ->where(function ($q) {
                $q->whereNull('processed_by')
                  ->orWhereRaw('LOWER(TRIM(processed_by)) NOT LIKE ?', ['%admin%']);
            })
            ->orderByRaw('COALESCE(paid_at, updated_at, created_at) DESC')
            ->get();

        $grouped = [];
        $totalEarningsAllTime = 0;

        foreach ($invoices as $inv) {
            $paidDate = $inv->paid_at ?? $inv->updated_at ?? $inv->created_at ?? now();
            $paidAt = \Carbon\Carbon::parse($paidDate);
            $key = $paidAt->format('Y-m');
            $monthName = $paidAt->translatedFormat('F Y');

            if ($commType === 'percent' || $commType === 'percentage') {
                $rate = $commValue > 0 ? $commValue : 0;
                $comm = (float) $inv->amount * ($rate / 100);
            } else {
                $rate = $commValue > 0 ? $commValue : $globalDefault;
                $comm = $rate;
            }

            $totalEarningsAllTime += $comm;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'month_key' => $key,
                    'month_name' => $monthName,
                    'total_invoices' => 0,
                    'total_collected' => 0,
                    'total_commission' => 0,
                    'invoices' => [],
                ];
            }

            $grouped[$key]['total_invoices'] += 1;
            $grouped[$key]['total_collected'] += (float) $inv->amount;
            $grouped[$key]['total_commission'] += $comm;
            $grouped[$key]['invoices'][] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?? $inv->customer_name ?? 'Pelanggan',
                'customer_code' => $inv->customer?->code ?? '-',
                'amount' => (float) $inv->amount,
                'commission' => $comm,
                'paid_at' => $paidAt->format('d M Y H:i'),
                'period' => $inv->period,
            ];
        }

        $monthlyEarnings = array_values($grouped);

        return Inertia::render('Collector/Earnings', [
            'collector' => [
                'name' => $collector->name,
                'username' => $collector->username,
                'commission_type' => $commType,
                'commission_value' => $commValue,
                'commission_rate_label' => $rateLabel,
            ],
            'totalEarningsAllTime' => $totalEarningsAllTime,
            'totalInvoicesAllTime' => count($invoices),
            'monthlyEarnings' => $monthlyEarnings,
        ]);
    }

    public function sendWaReminder(Request $request, $id)
    {
        $collectorId = session('collector_id');
        if (!$collectorId) return redirect('/kolektor/login');

        $collector = Collector::findOrFail($collectorId);
        $invoice = Invoice::findOrFail($id);
        
        if ($collector->tenant_id && (int) $invoice->tenant_id !== (int) $collector->tenant_id) {
            return back()->with('error', 'Akses ditolak: Tagihan di luar wilayah tenant Anda.');
        }

        $customer = $invoice->customer;
        if (!$customer || empty($customer->phone)) {
            return back()->with('error', 'Pelanggan tidak memiliki nomor WhatsApp terdaftar.');
        }

        $wa = new \App\Services\WhatsappService($invoice->tenant_id);
        if (!$wa->isEnabled()) {
            return back()->with('error', 'WhatsApp Gateway belum aktif di Pengaturan WhatsApp.');
        }

        $isPaid = (string) $invoice->status === 'paid' || !empty($invoice->paid);

        // Kirim notifikasi WA & Push sesuai status tagihan
        if ($isPaid) {
            $waSent = $wa->sendPaymentReceipt($invoice);
            try {
                app(\App\Services\PushNotificationService::class)->sendToCustomer(
                    $customer,
                    "✅ Pembayaran Diterima",
                    "Pembayaran tagihan " . ($invoice->invoice_number ?? 'Internet') . " sebesar Rp " . number_format($invoice->amount, 0, ',', '.') . " telah kami terima. Terima kasih!",
                    ['url' => '/portal/invoices', 'invoice_id' => (string) $invoice->id]
                );
            } catch (\Throwable $e) {}
            $successMsg = 'Kuitansi / bukti pembayaran lunas berhasil dikirim ke ' . $customer->name;
        } else {
            $waSent = $wa->sendInvoiceReminder($customer, $invoice);
            try {
                app(\App\Services\PushNotificationService::class)->sendToCustomer(
                    $customer,
                    "⏰ Pengingat Tagihan Internet",
                    "Tagihan " . ($invoice->invoice_number ?? 'Internet') . " sebesar Rp " . number_format($invoice->amount, 0, ',', '.') . " belum dibayar. Mohon segera melunasi.",
                    ['url' => '/portal/invoices', 'invoice_id' => (string) $invoice->id]
                );
            } catch (\Throwable $e) {}
            $successMsg = 'Notifikasi WhatsApp pengingat tagihan berhasil dikirim ke ' . $customer->name;
        }

        if ($waSent) {
            return back()->with('msg', $successMsg);
        }

        return back()->with('error', 'Gagal mengirim WhatsApp ke ' . $customer->name . '. Periksa status gateway di Pengaturan WhatsApp.');
    }

    public function profile(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $collector = Collector::with('router')->findOrFail($collectorId);

        return Inertia::render('Collector/Profile', [
            'collector' => [
                'id' => $collector->id,
                'name' => $collector->name,
                'username' => $collector->username,
                'phone' => $collector->phone,
                'email' => $collector->email,
                'router_name' => $collector->router?->name ?? 'Semua Wilayah',
                'commission_type' => $collector->commission_type,
                'commission_value' => (float) $collector->commission_value,
                'balance' => (float) $collector->balance,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        $collector = Collector::findOrFail($collectorId);

        if ($request->filled('new_password')) {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:6|confirmed',
            ]);

            if (! Hash::check($request->current_password, $collector->password)) {
                return back()->with('error', 'Password saat ini salah.');
            }

            $collector->password = Hash::make($request->new_password);
            $collector->save();

            return back()->with('msg', 'Password berhasil diperbarui.');
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:collectors,username,' . $collector->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $collector->update($validated);
        session(['collector_username' => $collector->username]);

        return back()->with('msg', 'Username & kontak berhasil diperbarui.');
    }

    public function map()
    {
        $collectorId = session('collector_id');
        if (! $collectorId) {
            return redirect('/kolektor/login');
        }

        if (! $this->checkPermission('map')) {
            return redirect('/kolektor/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat peta jaringan.');
        }

        $mapData = \App\Services\GisMapService::getMapData();

        return Inertia::render('Collector/Map', array_merge($mapData, [
            'readOnly' => true,
            'role' => 'collector',
        ]));
    }
}
