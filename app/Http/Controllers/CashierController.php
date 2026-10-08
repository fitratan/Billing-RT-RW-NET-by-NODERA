<?php

namespace App\Http\Controllers;

use App\Models\Cashier;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CashierController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Auth/CashierLogin', [
            'tenantName' => session('tenant_name', 'NODERA'),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $username = is_string($request->username) ? trim($request->username) : '';
        $password = is_string($request->password) ? (string) $request->password : '';
        
        $throttleKey = 'cashier_login_' . strtolower($username) . '_' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            return back()->with('error', "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.");
        }

        $cashier = Cashier::where('username', $username)->where('is_active', true)->first();

        if (! $cashier || ! Hash::check($password, $cashier->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);
            return back()->with('error', 'Username atau password salah');
        }

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        session([
            'cashier_id' => $cashier->id, 'cashier_name' => $cashier->name,
            'cashier_logged_in' => true, 'tenant_id' => $cashier->tenant_id,
        ]);

        // Buka sesi kasir
        $session = DB::table('cashier_sessions')->insertGetId([
            'cashier_id' => $cashier->id, 'opened_at' => now(),
            'opening_balance' => $cashier->opening_balance,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        session(['cashier_session_id' => $session]);

        return redirect('/cashier/dashboard');
    }

    public function dashboard()
    {
        $sessionId = session('cashier_session_id');
        $todayTransactions = DB::table('cashier_transactions')
            ->where('cashier_session_id', $sessionId)
            ->sum('amount');

        $todayCount = DB::table('cashier_transactions')
            ->where('cashier_session_id', $sessionId)
            ->count();

        $recentTx = DB::table('cashier_transactions')
            ->where('cashier_session_id', $sessionId)
            ->orderBy('created_at', 'desc')->limit(10)->get();

        return Inertia::render('Cashier/Dashboard', [
            'todayTransactions' => (float) $todayTransactions,
            'todayCount' => (int) $todayCount,
            'recentTx' => $recentTx->map(fn ($t) => ['id' => $t->id, 'invoice_number' => $t->invoice_number ?? '', 'amount' => (float) $t->amount, 'method' => $t->method ?? '', 'created_at' => $t->created_at]),
        ]);
    }

    public function payInvoice(Request $request)
    {
        $request->validate(['invoice_id' => 'required|exists:invoices,id']);
        $tenantId = session('tenant_id');

        return DB::transaction(function () use ($request, $tenantId) {
            $inv = Invoice::where('id', $request->invoice_id)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->lockForUpdate()
                ->firstOrFail();

            if ($inv->paid || $inv->status === 'paid') {
                return back()->with('msg', 'Tagihan ' . $inv->invoice_number . ' sudah lunas sebelumnya.');
            }

            $cashierName = session('cashier_name', 'Kasir');
            $inv->update([
                'paid'           => true,
                'status'         => 'paid',
                'paid_at'        => now(),
                'payment_method' => $request->payment_method ?? 'cash',
                'processed_by'   => $cashierName,
            ]);

            DB::table('cashier_transactions')->insert([
                'cashier_session_id' => session('cashier_session_id'),
                'customer_id'        => $inv->customer_id,
                'invoice_id'         => $inv->id,
                'type'               => 'payment',
                'amount'             => $inv->amount,
                'payment_method'     => $request->payment_method ?? 'cash',
                'notes'              => $request->notes,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Dispatch background unisolation if customer was isolated
            $customer = $inv->customer;
            if ($customer && $customer->status === 'isolated') {
                $unpaidCount = Invoice::where('customer_id', $customer->id)
                    ->where('id', '!=', $inv->id)
                    ->where('paid', false)
                    ->where('due_date', '<', now()->startOfDay())
                    ->count();

                if ($unpaidCount === 0) {
                    \App\Jobs\UnisolateCustomerJob::dispatch($customer->id, 'Kasir (' . $cashierName . ')');
                }
            }

            return back()->with('msg', 'Pembayaran ' . $inv->invoice_number . ' berhasil.');
        });
    }

    public function closeSession(Request $request)
    {
        $sessionId = session('cashier_session_id');
        $total = DB::table('cashier_transactions')
            ->where('cashier_session_id', $sessionId)->sum('amount');

        DB::table('cashier_sessions')->where('id', $sessionId)->update([
            'closed_at' => now(), 'closing_balance' => $request->closing_balance ?? $total,
            'total_cash_in' => $total, 'status' => 'closed', 'notes' => $request->notes,
            'updated_at' => now(),
        ]);

        session()->forget(['cashier_id', 'cashier_name', 'cashier_logged_in', 'cashier_session_id']);

        return redirect('/cashier/login')->with('msg', 'Sesi kasir ditutup');
    }

    public function logout()
    {
        session()->forget(['cashier_id', 'cashier_name', 'cashier_logged_in', 'cashier_session_id']);

        return redirect('/cashier/login');
    }

    // Admin: laporan kasir
    public function reports()
    {
        $sessions = DB::table('cashier_sessions')
            ->join('cashiers', 'cashiers.id', '=', 'cashier_sessions.cashier_id')
            ->where('cashiers.tenant_id', session('tenant_id'))
            ->select('cashier_sessions.*', 'cashiers.name as cashier_name')
            ->orderBy('cashier_sessions.created_at', 'desc')->limit(50)->get();

        return Inertia::render('Admin/CashierReports', ['sessions' => $sessions]);
    }
}
