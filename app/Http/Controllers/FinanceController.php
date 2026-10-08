<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\PaymentTransaction;
use App\Models\RevenueReport;
use App\Models\Invoice;
use App\Models\Collector;
use App\Models\User;
use App\Models\Voucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinanceController extends Controller
{
    /**
     * Finance dashboard / revenue report.
     */
    public function index()
    {
        $period = request('period');
        $date = request('date');
        $day = request('day');
        $year = request('year');
        $month = request('month');

        if ($period && $period !== 'all') {
            $parts = explode('-', $period);
            if (count($parts) === 2) {
                $year = $parts[0];
                $month = $parts[1];
            }
        } elseif ($date) {
            try {
                $parsed = \Carbon\Carbon::parse($date);
                $year = $parsed->year;
                $month = $parsed->month;
                $day = $parsed->day;
            } catch (\Exception $e) {}
        }

        $year = $year ?: date('Y');
        $month = $month ?: date('m');
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $targetPeriod = sprintf('%04d-%02d', (int) $year, (int) $month);

        $isSpecificDay = ($day && $day !== 'all');
        if ($isSpecificDay) {
            $specificDate = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
            $periodStart = \Carbon\Carbon::parse($specificDate)->startOfDay();
            $periodEnd = \Carbon\Carbon::parse($specificDate)->endOfDay();
        } else {
            $periodStart = \Carbon\Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
            $periodEnd = \Carbon\Carbon::create((int) $year, (int) $month, 1)->endOfMonth();
        }

        // Revenue from paid invoices (including advance payments / uang di muka for this period)
        $paidInvoicesList = Invoice::where('paid', 1)
            ->where('status', 'paid')
            ->where(function ($q) use ($periodStart, $periodEnd, $targetPeriod, $isSpecificDay) {
                if ($isSpecificDay) {
                    $q->whereBetween('paid_at', [$periodStart, $periodEnd]);
                } else {
                    $q->whereBetween('paid_at', [$periodStart, $periodEnd])
                      ->orWhere('period', $targetPeriod)
                      ->orWhere('periods_breakdown', 'like', "%\"{$targetPeriod}\"%");
                }
            })
            ->get();

        $invoiceRevenue = (float) $paidInvoicesList->sum(function ($inv) use ($targetPeriod) {
            if (!empty($inv->periods_breakdown)) {
                $bd = is_array($inv->periods_breakdown) ? $inv->periods_breakdown : json_decode($inv->periods_breakdown, true);
                if (is_array($bd) && count($bd) > 0) {
                    $matched = 0;
                    $found = false;
                    foreach ($bd as $item) {
                        if (isset($item['period']) && $item['period'] === $targetPeriod) {
                            $matched += (float) ($item['amount'] ?? 0);
                            $found = true;
                        }
                    }
                    if ($found) return $matched;
                }
            }
            return (float) ($inv->amount ?? 0);
        });

        // Revenue from used/active Hotspot Vouchers
        $voucherRevenue = (float) Voucher::where('used', true)
            ->where(function ($q) use ($year, $month) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->whereNotNull('used_at')
                        ->whereBetween('used_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                })->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNull('used_at')
                        ->whereBetween('created_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                });
            })
            ->sum('price');

        $totalRevenue = $invoiceRevenue + $voucherRevenue;

        // Rekapitulasi Komisi Petugas / Kolektor / Teknisi
        $commissionFeePerInvoice = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', env('COLLECTOR_COMMISSION_PER_INVOICE', 5000));
        $collectorCommission = $this->getCollectorCommissionData($year, $month);
        $totalCollectorCommission = (float) $collectorCommission->sum('estimated_commission');

        // Expenses (Operasional + Komisi Petugas & Teknisi)
        $operationalExpenses = (float) Expense::forPeriod($year, $month)->sum('amount');
        $totalExpenses = $operationalExpenses + $totalCollectorCommission;
        $expenses = Expense::forPeriod($year, $month)->orderBy('date', 'desc')->latest()->take(20)->get();

        // Profit / Loss
        $profitLoss = $totalRevenue - $totalExpenses;

        // Daily revenue breakdown for current month (invoices + vouchers)
        $dailyInvoices = Invoice::where('paid', 1)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->get();

        $dailyVouchers = Voucher::where('used', true)
            ->where(function ($q) use ($year, $month) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->whereNotNull('used_at')
                        ->whereBetween('used_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                })->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNull('used_at')
                        ->whereBetween('created_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                });
            })
            ->select(DB::raw('DATE(COALESCE(used_at, created_at)) as date'), DB::raw('SUM(price) as total'))
            ->groupBy(DB::raw('DATE(COALESCE(used_at, created_at))'))
            ->get();

        $dailyMap = [];
        foreach ($dailyInvoices as $d) {
            $dailyMap[$d->date] = ($dailyMap[$d->date] ?? 0) + (float) $d->total;
        }
        foreach ($dailyVouchers as $d) {
            $dailyMap[$d->date] = ($dailyMap[$d->date] ?? 0) + (float) $d->total;
        }
        ksort($dailyMap);
        $dailyRevenue = collect($dailyMap)->map(fn ($total, $date) => (object) ['date' => $date, 'total' => (float) $total])->values();

        // Expense breakdown by category
        $categoryBreakdown = Expense::forPeriod($year, $month)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Recent paid invoices (top 20)
        $recentRevenue = Invoice::join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.paid', 1)
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.paid_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()])
            ->select('invoices.*', 'customers.name as customer_name')
            ->orderBy('invoices.paid_at', 'desc')
            ->take(20)
            ->get();

        // Recent active/used vouchers (top 20)
        $recentVouchers = Voucher::where('used', true)
            ->where(function ($q) use ($year, $month) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->whereNotNull('used_at')
                        ->whereBetween('used_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                })->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNull('used_at')
                        ->whereBetween('created_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                });
            })
            ->orderByDesc(DB::raw('COALESCE(used_at, created_at)'))
            ->take(20)
            ->get();

        // Rekapitulasi Komisi Petugas / Kolektor
        $collectorCommission = $this->getCollectorCommissionData($year, $month);
        $commissionFeePerInvoice = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', env('COLLECTOR_COMMISSION_PER_INVOICE', 5000));

        return Inertia::render('Admin/Finance', [
            'year' => (int) $year,
            'month' => (int) $month,
            'selectedPeriod' => ($period === 'all') ? 'all' : $targetPeriod,
            'selectedDay' => $day ?: ($date ? (string) \Carbon\Carbon::parse($date)->day : 'all'),
            'selectedDate' => $date ?: ($isSpecificDay ? $specificDate : ''),
            'totalRevenue' => (float) $totalRevenue,
            'invoiceRevenue' => (float) $invoiceRevenue,
            'voucherRevenue' => (float) $voucherRevenue,
            'operationalExpenses' => (float) $operationalExpenses,
            'totalCollectorCommission' => (float) $totalCollectorCommission,
            'totalExpenses' => (float) $totalExpenses,
            'profitLoss' => (float) $profitLoss,
            'expenses' => $expenses->map(fn ($e) => [
                'id' => $e->id, 'description' => $e->description, 'category' => $e->category,
                'amount' => (float) $e->amount, 'date' => $e->date?->toIso8601String(),
            ]),
            'dailyRevenue' => $dailyRevenue->map(fn ($d) => ['date' => $d->date, 'total' => (float) $d->total]),
            'categoryBreakdown' => $categoryBreakdown->map(fn ($c) => ['category' => $c->category, 'total' => (float) $c->total]),
            'recentRevenue' => $recentRevenue->map(fn ($i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'customer_name' => $i->customer_name,
                'amount' => (float) $i->amount, 'paid_at' => $i->paid_at?->toIso8601String(),
            ]),
            'recentVouchers' => $recentVouchers->map(fn ($v) => [
                'id' => $v->id, 'username' => $v->username, 'profile' => $v->profile,
                'amount' => (float) $v->price, 'used_at' => ($v->used_at ?? $v->created_at)?->toIso8601String(),
            ]),
            'collectorCommission' => $collectorCommission,
            'commissionFeePerInvoice' => $commissionFeePerInvoice,
        ]);
    }

    /**
     * Export Finance Summary & Transactions to PDF (replacing legacy CSV).
     */
    public function exportCsv(Request $request)
    {
        return $this->exportPdf($request);
    }

    /**
     * Export Collector Commission to PDF (replacing legacy CSV).
     */
    public function exportCollectorCommissionCsv(Request $request)
    {
        return $this->exportPdf($request);
    }

    /**
     * Printable PDF / HTML Report View for Finance.
     */
    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $monthName = $monthNames[(int)$month] ?? $month;

        $targetPeriod = sprintf('%04d-%02d', (int) $year, (int) $month);
        $periodStart = \Carbon\Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
        $periodEnd = \Carbon\Carbon::create((int) $year, (int) $month, 1)->endOfMonth();

        $paidInvoicesList = Invoice::where('paid', 1)
            ->where('status', 'paid')
            ->where(function ($q) use ($periodStart, $periodEnd, $targetPeriod) {
                $q->whereBetween('paid_at', [$periodStart, $periodEnd])
                  ->orWhere('period', $targetPeriod)
                  ->orWhere('periods_breakdown', 'like', "%\"{$targetPeriod}\"%");
            })
            ->get();

        $invoiceRevenue = (float) $paidInvoicesList->sum(function ($inv) use ($targetPeriod) {
            if (!empty($inv->periods_breakdown)) {
                $bd = is_array($inv->periods_breakdown) ? $inv->periods_breakdown : json_decode($inv->periods_breakdown, true);
                if (is_array($bd) && count($bd) > 0) {
                    $matched = 0;
                    $found = false;
                    foreach ($bd as $item) {
                        if (isset($item['period']) && $item['period'] === $targetPeriod) {
                            $matched += (float) ($item['amount'] ?? 0);
                            $found = true;
                        }
                    }
                    if ($found) return $matched;
                }
            }
            return (float) ($inv->amount ?? 0);
        });
        $voucherRevenue = (float) Voucher::where('used', true)
            ->where(function ($q) use ($year, $month) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->whereNotNull('used_at')
                        ->whereBetween('used_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                })->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNull('used_at')
                        ->whereBetween('created_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                });
            })
            ->sum('price');
        $totalRevenue = $invoiceRevenue + $voucherRevenue;

        // Rekapitulasi Komisi Petugas / Kolektor / Teknisi
        $commissionFeePerInvoice = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', env('COLLECTOR_COMMISSION_PER_INVOICE', 5000));
        $collectorCommission = $this->getCollectorCommissionData($year, $month);
        $totalCollectorCommission = (float) $collectorCommission->sum('estimated_commission');

        // Expenses (Operasional + Komisi Petugas & Teknisi)
        $operationalExpenses = (float) Expense::forPeriod($year, $month)->sum('amount');
        $totalExpenses = $operationalExpenses + $totalCollectorCommission;
        $profitLoss = $totalRevenue - $totalExpenses;

        $invoices = Invoice::join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.paid', 1)
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.paid_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()])
            ->select('invoices.*', 'customers.name as customer_name', 'customers.code as customer_code')
            ->orderBy('invoices.paid_at', 'desc')
            ->get();

        $vouchers = Voucher::where('used', true)
            ->where(function ($q) use ($year, $month) {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->whereNotNull('used_at')
                        ->whereBetween('used_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                })->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNull('used_at')
                        ->whereBetween('created_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()]);
                });
            })
            ->orderByDesc(DB::raw('COALESCE(used_at, created_at)'))
            ->get();

        $expenses = Expense::forPeriod($year, $month)->orderBy('date', 'desc')->get();

        $companyName = \App\Models\Setting::getValue('COMPANY_NAME', config('app.name', 'NODERA BILLING'));
        $companyPhone = \App\Models\Setting::getTenantPhone(session('tenant_id'));
        $companyAddress = \App\Models\Setting::getValue('COMPANY_ADDRESS', 'Layanan Internet & Billing Management');
        $isPdf = $request->has('download');

        $data = compact(
            'year', 'month', 'monthName', 'totalRevenue', 'invoiceRevenue', 'voucherRevenue',
            'operationalExpenses', 'totalCollectorCommission', 'totalExpenses', 'profitLoss',
            'invoices', 'vouchers', 'expenses', 'collectorCommission', 'commissionFeePerInvoice',
            'companyName', 'companyPhone', 'companyAddress', 'isPdf'
        );

        if ($isPdf) {
            try {
                $fontPath = storage_path('fonts');
                if (!file_exists($fontPath)) {
                    @mkdir($fontPath, 0775, true);
                }

                $filename = "laporan-keuangan-{$year}-{$month}.pdf";
                $pdf = app('dompdf.wrapper')->loadView('finance.pdf-report', $data);
                $pdf->setPaper('a4', 'portrait');
                $pdf->getDomPDF()->getOptions()->set('isRemoteEnabled', true);
                $pdf->getDomPDF()->getOptions()->set('isHtml5ParserEnabled', true);
                $pdf->getDomPDF()->getOptions()->set('fontDir', $fontPath);
                $pdf->getDomPDF()->getOptions()->set('fontCache', $fontPath);

                return response()->streamDownload(
                    function () use ($pdf) {
                        echo $pdf->output();
                    },
                    $filename,
                    ['Content-Type' => 'application/pdf']
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[FinanceController] Dompdf fallback: ' . $e->getMessage());
            }
        }

        return view('finance.pdf-report', $data);
    }

    private function getCollectorCommissionData($year, $month)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();

        $collectorsMap = Collector::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->keyBy(fn ($c) => strtolower(trim($c->name)));

        $usersMap = User::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->keyBy(fn ($u) => strtolower(trim($u->name)));

        $globalDefault = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', env('COLLECTOR_COMMISSION_PER_INVOICE', 5000));

        $rows = Invoice::where('paid', 1)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()])
            ->whereNotNull('processed_by')
            ->leftJoin('collectors', 'collectors.id', '=', 'invoices.collector_id')
            ->select(
                DB::raw('COALESCE(collectors.name, invoices.processed_by) as processed_by'),
                DB::raw('COUNT(invoices.id) as total_invoices'),
                DB::raw('SUM(invoices.amount) as total_amount')
            )
            ->groupBy(DB::raw('COALESCE(collectors.name, invoices.processed_by)'))
            ->orderByDesc('total_amount')
            ->get();

        return $rows->filter(function ($row) use ($collectorsMap, $usersMap) {
            $nameKey = strtolower(trim($row->processed_by));
            $isCollector = $collectorsMap->has($nameKey);
            $user = $usersMap->get($nameKey);
            $isStaff = $user && in_array(strtolower($user->role ?? ''), ['collector', 'kolektor', 'technician', 'teknisi', 'kasir', 'cashier', 'staff', 'employee']);
            $isAdmin = str_contains($nameKey, 'admin') || ($user && in_array(strtolower($user->role ?? ''), ['admin', 'superadmin']));

            return ($isCollector || $isStaff || ($user && (float)($user->commission_value ?? 0) > 0)) && ! $isAdmin;
        })->map(function ($row) use ($collectorsMap, $usersMap, $globalDefault) {
            $nameKey = strtolower(trim($row->processed_by));
            $collector = $collectorsMap->get($nameKey) ?? $usersMap->get($nameKey);
            $user = $usersMap->get($nameKey);

            $roleLabel = 'Kolektor Lapangan';
            if ($user && in_array(strtolower($user->role ?? ''), ['technician', 'teknisi'])) {
                $roleLabel = 'Teknisi Lapangan';
            } elseif ($user && in_array(strtolower($user->role ?? ''), ['cashier', 'kasir'])) {
                $roleLabel = 'Kasir / Loket';
            } elseif ($user && !empty($user->role)) {
                $roleLabel = ucfirst($user->role);
            }

            $commType = $collector->commission_type ?? ($user->commission_type ?? 'fixed');
            $commValue = (float) ($collector->commission_value ?? ($user->commission_value ?? 0));

            if ($commType === 'percent' || $commType === 'percentage') {
                $rate = $commValue > 0 ? $commValue : 0;
                $estimatedComm = (float) $row->total_amount * ($rate / 100);
                $commRateLabel = number_format($rate, 0) . '%';
            } else {
                $rate = $commValue > 0 ? $commValue : $globalDefault;
                $estimatedComm = (float) $row->total_invoices * $rate;
                $commRateLabel = 'Rp ' . number_format($rate, 0, ',', '.') . ' / invoice';
            }

            return [
                'collector_name' => $row->processed_by,
                'role_label' => $roleLabel,
                'total_invoices' => (int) $row->total_invoices,
                'total_amount' => (float) $row->total_amount,
                'estimated_commission' => $estimatedComm,
                'commission_rate_label' => $commRateLabel,
            ];
        })->values();
    }

    /**
     * Generate / update revenue report for a period.
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'period_year' => 'required|integer|min:2020|max:2099',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $year = $request->period_year;
        $month = $request->period_month;
        $tenantId = session('tenant_id');

        $revenue = PaymentTransaction::success()->forPeriod($year, $month)->sum('amount');
        $invPayments = \App\Models\Payment::whereBetween('paid_at', [\Carbon\Carbon::create($year, $month, 1)->startOfMonth(), \Carbon\Carbon::create($year, $month, 1)->endOfMonth()])
            ->sum('amount');
        $revenue = max($revenue, $invPayments);
        $expenses = Expense::forPeriod($year, $month)->sum('amount');

        RevenueReport::updateOrCreate(
            ['period_year' => $year, 'period_month' => $month],
            ['total_revenue' => $revenue, 'total_expenses' => $expenses]
        );

        return redirect()->to('/admin/finance')->with('msg', 'Laporan periode ' . $month . '/' . $year . ' berhasil digenerate.');
    }

    // ========== EXPENSES ==========

    /**
     * Display expenses page with CRUD and date/calendar filter.
     */
    public function expenses(Request $request)
    {
        $period = $request->input('period');
        $day = $request->input('day');
        $date = $request->input('date');
        $year = $request->input('year');
        $month = $request->input('month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $category = $request->input('category', '');
        $search = $request->input('search', '');

        if ($period && $period !== 'all') {
            $parts = explode('-', $period);
            if (count($parts) === 2) {
                $year = (int) $parts[0];
                $month = (int) $parts[1];
            }
        } elseif ($date) {
            try {
                $parsed = \Carbon\Carbon::parse($date);
                $year = $parsed->year;
                $month = $parsed->month;
                $day = $parsed->day;
            } catch (\Exception $e) {}
        }

        $query = Expense::query();

        if ($day && $day !== 'all' && $year && $month) {
            $specificDate = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
            $query->whereDate('date', $specificDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        } elseif ($year && $month) {
            $query->forPeriod($year, $month);
        } elseif ($date) {
            $query->whereDate('date', $date);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderBy('date', 'desc')->latest()->paginate(50)->withQueryString();

        $totalFiltered = (float) (clone $query)->sum('amount');
        $totalThisMonth = (float) Expense::forPeriod(date('Y'), date('m'))->sum('amount');
        $totalAll = (float) Expense::sum('amount');

        $categories = Expense::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category');

        $targetPeriod = ($year && $month) ? sprintf('%04d-%02d', (int)$year, (int)$month) : date('Y-m');
        $isSpecificDay = ($day && $day !== 'all');
        $specificDate = ($isSpecificDay && $year && $month) ? sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day) : '';

        return Inertia::render('Admin/Expenses', [
            'totalFiltered' => $totalFiltered,
            'totalThisMonth' => $totalThisMonth,
            'totalAll' => $totalAll,
            'categories' => $categories,
            'year' => $year ? (int) $year : (int) date('Y'),
            'month' => $month ? (int) $month : (int) date('m'),
            'selectedPeriod' => ($period === 'all') ? 'all' : $targetPeriod,
            'selectedDay' => $day ?: ($date ? (string) \Carbon\Carbon::parse($date)->day : 'all'),
            'selectedDate' => $date ?: $specificDate,
            'startDate' => $startDate ?: '',
            'endDate' => $endDate ?: '',
            'category' => $category,
            'search' => $search,
            'expenses' => [
                'data' => $expenses->map(fn ($e) => [
                    'id' => $e->id,
                    'description' => $e->description,
                    'category' => $e->category,
                    'amount' => (float) $e->amount,
                    'date' => $e->date?->format('Y-m-d'),
                    'notes' => $e->notes,
                    'vendor' => $e->vendor,
                    'payment_method' => $e->payment_method,
                ]),
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'total' => $expenses->total(),
                    'next_page_url' => $expenses->nextPageUrl(),
                    'prev_page_url' => $expenses->previousPageUrl(),
                ],
            ],
        ]);
    }

    /**
     * Store a new expense.
     */
    public function storeExpense(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|gt:0',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'vendor' => 'nullable|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
        ]);

        Expense::create([
            'description' => $request->description,
            'amount' => str_replace(['.', ','], ['', ''], $request->amount),
            'category' => $request->category,
            'date' => $request->date,
            'notes' => $request->notes,
            'vendor' => $request->vendor,
            'receipt_number' => $request->receipt_number,
            'payment_method' => $request->payment_method ?? 'cash',
            'recorded_by' => Auth::id(),
            'tenant_id' => session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId(),
        ]);

        return redirect()->back()->with('msg', 'Pengeluaran berhasil dicatat.');
    }

    /**
     * Get expense data for edit modal.
     */
    public function editExpense($id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $expense = Expense::where('id', $id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $expense]);
    }

    /**
     * Update an expense.
     */
    public function updateExpense(Request $request, $id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $expense = Expense::where('id', $id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        $request->validate([
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|gt:0',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'vendor' => 'nullable|string|max:200',
            'payment_method' => 'nullable|string|max:50',
        ]);

        $expense->update([
            'description' => $request->description,
            'amount' => str_replace(['.', ','], ['', ''], $request->amount),
            'category' => $request->category,
            'date' => $request->date,
            'notes' => $request->notes,
            'vendor' => $request->vendor,
            'payment_method' => $request->payment_method ?? 'cash',
        ]);

        return redirect()->back()->with('msg', 'Pengeluaran berhasil diperbarui.');
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense($id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $expense = Expense::where('id', $id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        $expense->delete();

        return redirect()->back()->with('msg', 'Pengeluaran berhasil dihapus.');
    }

    /**
     * Print finance report as PDF (print-friendly Corporate Printable view).
     */
    public function printReport(Request $request)
    {
        return $this->exportPdf($request);
    }
}

