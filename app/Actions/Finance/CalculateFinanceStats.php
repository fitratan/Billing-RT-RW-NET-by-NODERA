<?php

namespace App\Actions\Finance;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CalculateFinanceStats
{
    public function execute(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $yearStart = $now->copy()->startOfYear();

        $activeTenants = Tenant::where('is_active', true)
            ->where(fn($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', $now))
            ->count();

        $monthlyRevenue = Invoice::whereNull('tenant_id')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $yearlyRevenue = Invoice::whereNull('tenant_id')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$yearStart, $monthEnd])
            ->sum('amount');

        $totalRevenue = Invoice::whereNull('tenant_id')
            ->where('status', 'paid')
            ->sum('amount');

        $totalExpenses = Expense::whereNull('tenant_id')->sum('amount');
        $monthlyExpenses = Expense::whereNull('tenant_id')
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('amount');

        $pendingInvoices = Invoice::whereNull('tenant_id')
            ->where('status', 'pending')
            ->count();

        $monthlyTransactions = PaymentTransaction::whereNull('tenant_id')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $avgSubscriptionPrice = Tenant::where('is_active', true)
            ->whereNotNull('settings->subscribed_price')
            ->select(DB::raw("avg(json_extract(settings, '$.subscribed_price')) as avg_price"))
            ->value('avg_price');

        $mrr = (float) ($avgSubscriptionPrice ?? 0) * max($activeTenants, 0);
        $arr = $mrr * 12;

        $expensesByCategory = Expense::whereNull('tenant_id')
            ->select('category', DB::raw('sum(amount) as total'))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->groupBy('category')
            ->get();

        return compact(
            'activeTenants', 'monthlyRevenue', 'yearlyRevenue', 'totalRevenue',
            'totalExpenses', 'monthlyExpenses', 'pendingInvoices',
            'monthlyTransactions', 'mrr', 'arr', 'expensesByCategory',
        );
    }
}
