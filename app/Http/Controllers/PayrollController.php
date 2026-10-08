<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List payroll with period filter
     */
    public function index(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;

        $payrollsQuery = Payroll::with('user')
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($tenantId) {
            $payrollsQuery->where('tenant_id', $tenantId);
        }

        $payrolls = $payrollsQuery->orderBy('created_at', 'desc')->get();

        $usersQuery = User::whereIn('role', ['admin', 'technician', 'collector', 'superadmin']);
        if ($tenantId) {
            $usersQuery->where('tenant_id', $tenantId);
        }
        $users = $usersQuery->orderBy('name')->get();

        $summary = [
            'total_paid' => (float) $payrolls->whereNotNull('paid_at')->sum('total'),
            'total_unpaid' => (float) $payrolls->whereNull('paid_at')->sum('total'),
            'total_amount' => (float) $payrolls->sum('total'),
            'total_salary' => (float) $payrolls->sum('salary'),
            'total_bonus' => (float) $payrolls->sum('bonus'),
            'total_deductions' => (float) $payrolls->sum('deductions'),
            'paid_count' => $payrolls->whereNotNull('paid_at')->count(),
            'unpaid_count' => $payrolls->whereNull('paid_at')->count(),
            'total_count' => $payrolls->count(),
        ];

        return Inertia::render('Admin/Payroll', [
            'create' => (bool) request('create'),
            'month' => $month,
            'year' => $year,
            'summary' => $summary,
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role,
            ]),
            'payrolls' => $payrolls->map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'user' => $p->user?->name ?? 'User #' . $p->user_id,
                'user_role' => $p->user?->role ?? 'Staff',
                'salary' => (float) ($p->salary ?? 0),
                'deductions' => (float) ($p->deductions ?? 0),
                'bonus' => (float) ($p->bonus ?? 0),
                'total' => (float) ($p->total ?? 0),
                'status' => $p->paid_at ? 'paid' : 'pending',
                'paid_at' => $p->paid_at ? $p->paid_at->format('d/m/Y H:i') : null,
                'period' => sprintf('%02d/%d', $p->period_month, $p->period_year),
                'period_month' => $p->period_month,
                'period_year' => $p->period_year,
            ]),
        ]);
    }

    /**
     * Create new payroll record
     */
    public function create(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_month' => 'required|integer|between:1,12',
            'period_year' => 'required|integer|min:2020',
            'salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
        ]);

        $tenantId = session('tenant_id');

        // Check if payroll already exists for this period
        $existingQuery = Payroll::where('user_id', $data['user_id'])
            ->where('period_month', $data['period_month'])
            ->where('period_year', $data['period_year']);

        if ($tenantId) {
            $existingQuery->where('tenant_id', $tenantId);
        }

        if ($existingQuery->exists()) {
            return redirect()->to('/admin/payroll?month=' . $data['period_month'] . '&year=' . $data['period_year'])
                ->with('error', 'Slip Gaji / Payroll sudah dibuat untuk karyawan ini pada periode yang dipilih.');
        }

        $total = ($data['salary'] + ($data['bonus'] ?? 0)) - ($data['deductions'] ?? 0);

        Payroll::create([
            'user_id' => $data['user_id'],
            'period_month' => (int) $data['period_month'],
            'period_year' => (int) $data['period_year'],
            'salary' => $data['salary'],
            'bonus' => $data['bonus'] ?? 0,
            'deductions' => $data['deductions'] ?? 0,
            'total' => max(0, $total),
            'tenant_id' => $tenantId,
        ]);

        return redirect()->to('/admin/payroll?month=' . $data['period_month'] . '&year=' . $data['period_year'])
            ->with('msg', 'Slip Gaji berhasil dibuat.');
    }

    /**
     * Mark payroll as paid
     */
    public function pay($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->paid_at) {
            return redirect()->back()->with('error', 'Slip Gaji ini sudah dibayar sebelumnya.');
        }

        $payroll->update([
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('msg', 'Slip Gaji berhasil ditandai sebagai LUNAS / DIBAYAR.');
    }

    /**
     * Delete payroll record
     */
    public function delete($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->paid_at) {
            return redirect()->back()->with('error', 'Slip Gaji yang sudah dibayar tidak dapat dihapus.');
        }

        $payroll->delete();

        return redirect()->back()->with('msg', 'Slip Gaji berhasil dihapus.');
    }

    /**
     * Print single payslip
     */
    public function printPayslip($id)
    {
        $payroll = Payroll::with('user')->findOrFail($id);
        return view('admin.print_payslip', compact('payroll'));
    }
}
