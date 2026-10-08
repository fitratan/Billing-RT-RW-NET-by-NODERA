<?php

namespace App\Http\Controllers;

use App\Models\Collector;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollectorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List all collectors
     */
    public function index()
    {
        $tenantId = session('tenant_id');
        $collectors = Collector::orderBy('created_at', 'desc')->get();

        $tenant = Tenant::find($tenantId);
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
        $tenantHost = $tenant ? $tenant->slug . '.' . $baseDomain : request()->getHost();
        $scheme = request()->getScheme();
        $port = in_array(request()->getPort(), [80, 443]) ? '' : ':' . request()->getPort();
        $collectorLoginUrl = $scheme . '://' . $tenantHost . $port . '/kolektor/login';

        // Load invoice stats per collector
        $invoices = \App\Models\Invoice::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'collector_id', 'processed_by', 'paid', 'status', 'amount', 'customer_id']);

        $customers = \App\Models\Customer::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'collector_id']);
        $custCollectorMap = $customers->pluck('collector_id', 'id')->toArray();

        return Inertia::render('Admin/Collectors', [
            'create' => (bool) request('create'),
            'collectors' => $collectors->map(function ($c) use ($invoices, $custCollectorMap) {
                $cId = $c->id;
                $cNameLower = strtolower(trim($c->name));
                $cUserLower = strtolower(trim($c->username ?? ''));

                $paidInvoices = $invoices->filter(function ($i) use ($cId, $cNameLower, $cUserLower) {
                    if (!$i->paid || $i->status !== 'paid') return false;
                    if ((int)$i->collector_id === (int)$cId) return true;
                    $proc = strtolower(trim((string)$i->processed_by));
                    if ($proc && ($proc === $cNameLower || $proc === $cUserLower)) return true;
                    return false;
                });

                $pendingInvoices = $invoices->filter(function ($i) use ($cId, $custCollectorMap) {
                    if ($i->paid && $i->status === 'paid') return false;
                    $custColl = $custCollectorMap[$i->customer_id] ?? null;
                    return ((int)$i->collector_id === (int)$cId) || ((int)$custColl === (int)$cId);
                });

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'username' => $c->username,
                    'phone' => $c->phone,
                    'email' => $c->email,
                    'collection_area' => $c->collection_area,
                    'is_active' => (bool) $c->is_active,
                    'commission_type' => $c->commission_type ?? 'percent',
                    'commission_value' => (string) ($c->commission_value ?? 0),
                    'router' => $c->router?->name,
                    'balance' => (float) ($c->balance ?? 0),
                    'paid_count' => $paidInvoices->count(),
                    'paid_amount' => (float) $paidInvoices->sum('amount'),
                    'pending_count' => $pendingInvoices->count(),
                    'pending_amount' => (float) $pendingInvoices->sum('amount'),
                ];
            }),
            'loginUrl' => $collectorLoginUrl,
        ]);
    }

    /**
     * Add new collector
     */
    public function add(Request $request)
    {
        if (!\App\Services\LicenseService::canAddStaff()) {
            return redirect()->back()->withInput()->with('error', 'Batas kuota Community Edition tercapai (Maksimal ' . \App\Services\LicenseService::MAX_FREE_STAFF . ' Staff). Silakan aktivasi lisensi Pro.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:collectors,username',
            'password' => 'required|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'collection_area' => 'nullable|string|max:255',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
        ]);

        Collector::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'collection_area' => $data['collection_area'] ?? '',
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'balance' => 0,
            'is_active' => true,
            'tenant_id' => session('tenant_id'),
        ]);

        return redirect()->to('/admin/collectors')->with('msg', 'Kolektor berhasil ditambahkan');
    }

    /**
     * Edit collector
     */
    public function edit(Request $request, $id)
    {
        $collector = Collector::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:collectors,username,' . $id,
            'password' => 'nullable|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'collection_area' => 'nullable|string|max:255',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $update = [
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'collection_area' => $data['collection_area'] ?? '',
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'is_active' => $data['is_active'],
        ];

        if (!empty($data['password'])) {
            if (session('tenant_slug') === 'demo' || in_array($collector->username ?? '', ['kolektor_demo', 'demo', 'teknisi_demo'])) {
                return redirect()->to('/admin/collectors')->with('error', 'Pengubahan password akun demo dinonaktifkan.');
            }
            $update['password'] = bcrypt($data['password']);
        }

        $collector->update($update);

        return redirect()->to('/admin/collectors')->with('msg', 'Data kolektor berhasil diperbarui');
    }

    /**
     * Delete collector
     */
    public function delete($id)
    {
        $collector = Collector::findOrFail($id);
        $collector->delete();

        return redirect()->to('/admin/collectors')->with('msg', 'Kolektor berhasil dihapus');
    }
}
