<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AgentPortalController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $slug = session('tenant_slug');
        if (! $slug) {
            $host = $request->getHost();
            $baseDomain = config('app.base_domain', 'dgtlnetsolution.com');
            if ($host !== $baseDomain && ! str_starts_with($host, 'panel.') && ! str_starts_with($host, 'vpn.')) {
                $subdomain = explode('.', $host)[0] ?? '';
                if ($subdomain && $subdomain !== 'www') {
                    $tenant = Tenant::where('slug', $subdomain)->where('is_active', true)->first();
                    if ($tenant) {
                        session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug, 'tenant_name' => $tenant->name]);
                    }
                }
            }
        }

        return Inertia::render('Auth/AgentLogin', ['tenantName' => session('tenant_name', 'NODERA')]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = is_string($request->username) ? trim($request->username) : '';
        $password = is_string($request->password) ? (string) $request->password : '';

        $agent = Agent::with('tenant')
            ->where('username', $username)
            ->where('is_active', true)
            ->first();

        if (! $agent || ! Hash::check($password, $agent->password)) {
            return back()->with('error', 'Username atau password salah')->withInput();
        }

        if (! $agent->tenant || ! $agent->tenant->is_active || $agent->tenant->isExpired()) {
            return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
        }

        session([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'agent_username' => $agent->username,
            'agent_logged_in' => true,
            'tenant_id' => $agent->tenant_id,
            'tenant_slug' => $agent->tenant->slug,
            'tenant_name' => $agent->tenant->name,
        ]);

        return redirect('/agent/dashboard');
    }

    public function dashboard(Request $request)
    {
        $agentId = session('agent_id');
        if (! $agentId) {
            return redirect('/agent/login');
        }

        $agent = Agent::findOrFail($agentId);

        $type = $request->input('type', 'payment');
        $query = $request->input('query', '');

        $transactions = DB::table('agent_transactions')
            ->where('agent_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $unpaidInvoices = collect();
        if ($type === 'payment' && ! empty($query)) {
            $customer = Customer::where('tenant_id', $agent->tenant_id)
                ->where(function ($q) use ($query) {
                    $q->where('phone', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('pppoe_username', $query);
                })
                ->first();
            if ($customer) {
                $unpaidInvoices = Invoice::where('customer_id', $customer->id)
                    ->where('tenant_id', $agent->tenant_id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date')->get();
            }
        }

        $voucherPackages = DB::table('voucher_packages')
            ->where('tenant_id', $agent->tenant_id)
            ->where('is_active', true)
            ->orderBy('price')->get();

        $invoices = Invoice::where('tenant_id', $agent->tenant_id)
            ->where('processed_by', 'Agen: '.$agent->name)
            ->orderBy('paid_at', 'desc')
            ->limit(10)
            ->get(['id', 'invoice_number', 'customer_id', 'amount', 'paid_at']);

        return Inertia::render('Agent/Dashboard', [
            'agent' => ['name' => $agent->name, 'username' => $agent->username],
            'type' => $type,
            'query' => $query,
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id, 'type' => $t->type, 'amount' => (float) $t->amount,
                'description' => $t->description, 'created_at' => $t->created_at,
            ]),
            'unpaidInvoices' => $unpaidInvoices->map(fn ($i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'amount' => (float) $i->amount,
                'customer_name' => $i->customer?->name, 'customer_id' => $i->customer_id, 'due_date' => $i->due_date?->toIso8601String(),
            ]),
            'voucherPackages' => $voucherPackages->map(fn ($v) => [
                'id' => $v->id, 'name' => $v->name, 'price' => (float) $v->price, 'duration_days' => (int) $v->duration_days,
            ]),
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'amount' => (float) $i->amount, 'paid_at' => $i->paid_at?->toIso8601String(),
            ]),
        ]);
    }

    public function payInvoice(Request $request)
    {
        $agentId = session('agent_id');
        if (! $agentId) {
            return redirect('/agent/login');
        }

        $request->validate([
            'customer_id' => 'required|integer',
            'invoice_id' => 'required|integer',
        ]);

        $agent = Agent::findOrFail($agentId);
        $invoice = Invoice::where('id', $request->invoice_id)
            ->where('customer_id', $request->customer_id)
            ->where('tenant_id', $agent->tenant_id)
            ->where('status', '!=', 'paid')
            ->first();

        if (! $invoice) {
            return back()->with('error', 'Invoice tidak ditemukan atau sudah lunas.');
        }
        if ($invoice->amount > $agent->balance) {
            return back()->with('error', 'Saldo agen tidak mencukupi. Saldo: Rp '.number_format($agent->balance, 0, ',', '.'));
        }

        DB::transaction(function () use ($agent, $invoice) {
            $before = $agent->balance;
            $agent->decrement('balance', $invoice->amount);

            $invoice->update([
                'paid' => true,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'Agen',
                'payment_ref' => 'agent-'.$agent->id,
                'processed_by' => 'agent_id: '.$agent->id,
            ]);

            DB::table('agent_transactions')->insert([
                'agent_id' => $agent->id,
                'type' => 'payment',
                'amount' => $invoice->amount,
                'balance_before' => $before,
                'balance_after' => $agent->fresh()->balance,
                'note' => 'Pembayaran invoice '.$invoice->invoice_number.' untuk pelanggan #'.$invoice->customer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('msg', 'Invoice '.$invoice->invoice_number.' berhasil dibayar. Sisa saldo: Rp '.number_format($agent->fresh()->balance, 0, ',', '.'));
    }

    public function sellVoucher(Request $request)
    {
        if (! session('agent_id')) {
            return redirect('/agent/login');
        }
        $agent = Agent::findOrFail(session('agent_id'));

        $request->validate([
            'voucher_package_id' => 'required|integer',
            'qty' => 'required|integer|min:1|max:100',
        ]);

        $pkg = DB::table('voucher_packages')
            ->where('id', $request->voucher_package_id)
            ->where('tenant_id', $agent->tenant_id)
            ->where('is_active', true)
            ->first();

        if (! $pkg) {
            return back()->with('error', 'Paket voucher tidak ditemukan.');
        }

        $qty = (int) $request->qty;
        $total = $pkg->price * $qty;

        if ($total > $agent->balance) {
            return back()->with('error', 'Saldo agen tidak mencukupi. Saldo: Rp '.number_format($agent->balance, 0, ',', ',').' | Total: Rp '.number_format($total, 0, ',', '.'));
        }

        $codes = [];

        DB::transaction(function () use ($agent, $pkg, $qty, $total, &$codes) {
            $before = $agent->balance;
            $agent->decrement('balance', $total);

            for ($i = 0; $i < $qty; $i++) {
                $code = strtoupper(substr(md5(uniqid($agent->id.rand(), true)), 0, 10));
                Voucher::create([
                    'username' => $code,
                    'password' => strtoupper(substr(md5(uniqid((string) microtime(), true)), 0, 6)),
                    'profile' => $pkg->name,
                    'created_by' => null,
                    'tenant_id' => $agent->tenant_id,
                    'agent_id' => $agent->id,
                ]);
                $codes[] = $code;
            }

            DB::table('agent_transactions')->insert([
                'agent_id' => $agent->id,
                'type' => 'voucher',
                'amount' => $total,
                'balance_before' => $before,
                'balance_after' => $agent->fresh()->balance,
                'note' => 'Jual voucher '.$pkg->name.' x'.$qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('msg', 'Berhasil menjual '.$qty.' voucher '.$pkg->name.'. Saldo tersisa: Rp '.number_format($agent->fresh()->balance, 0, ',', '.'))
            ->with('created_codes', $codes);
    }

    public function transactions()
    {
        if (! session('agent_id')) {
            return redirect('/agent/login');
        }
        $agent = Agent::findOrFail(session('agent_id'));
        $transactions = DB::table('agent_transactions')
            ->where('agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Agent/Transactions', [
            'agent' => ['name' => $agent->name, 'balance' => (float) ($agent->balance ?? 0)],
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id, 'type' => $t->type, 'amount' => (float) $t->amount,
                'balance_before' => (float) ($t->balance_before ?? 0), 'balance_after' => (float) ($t->balance_after ?? 0),
                'note' => $t->note, 'created_at' => $t->created_at,
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'agent_id', 'agent_name', 'agent_username', 'agent_logged_in',
        ]);

        return redirect('/agent/login');
    }
}
