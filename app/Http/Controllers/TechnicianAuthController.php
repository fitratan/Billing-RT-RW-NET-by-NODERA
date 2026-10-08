<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\OdpLocation;
use App\Models\OnuLocation;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Services\MikrotikService;
use App\Services\GenieacsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class TechnicianAuthController extends Controller
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
            $techId = session('technician_id') ?? session('collector_id') ?? auth()->id();
            if (! $techId) return false;

            $staff = User::withoutGlobalScopes()->find($techId)
                ?? Collector::withoutGlobalScopes()->find($techId);
            if (! $staff) return false;

            $allDefault = [
                'collect_payment' => true,
                'customers' => true,
                'dashboard' => true,
                'pool' => true,
                'create_customer' => true,
                'pppoe' => true,
                'arp' => true,
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
        $rememberId = $request->cookie('nodera_remember_technician');
        if ($rememberId) {
            $userQuery = User::withoutGlobalScopes()
                ->where('role', 'technician');
            if ($tenant) {
                $userQuery->where('tenant_id', $tenant->id);
            }
            $user = $userQuery->find($rememberId);
            if ($user && ($user->is_active ?? true)) {
                $userTenant = $user->tenant_id ? Tenant::withoutGlobalScopes()->find($user->tenant_id) : $tenant;
                session([
                    'technician_id' => $user->id,
                    'technician_name' => $user->name ?? $user->username,
                    'technician_username' => $user->username,
                    'technician_logged_in' => true,
                    'tenant_id' => $user->tenant_id,
                    'tenant_slug' => $userTenant?->slug,
                    'tenant_name' => $userTenant?->name,
                ]);
                return redirect('/teknisi/dashboard');
            }
        }

        if ($tenant) {
            session([
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
            ]);
        }

        return Inertia::render('Auth/TeknisiLogin', [
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

        // Cari user teknisi / staf (prioritaskan tenant context jika ada)
        $userQuery = User::withoutGlobalScopes()
            ->whereIn('role', ['technician', 'collector', 'cashier'])
            ->where(function ($q) use ($username) {
                $q->where('username', $username)
                    ->orWhere('email', $username)
                    ->orWhere('phone', $username);
            });

        if ($tenant) {
            $userQuery->where('tenant_id', $tenant->id);
        }

        $user = $userQuery->first();

        // Jika tidak ditemukan di User, cari di Collector
        if (! $user) {
            $collQuery = Collector::withoutGlobalScopes()
                ->where('is_active', true)
                ->where(function ($q) use ($request) {
                    $q->where('username', $request->username)
                        ->orWhere('email', $request->username)
                        ->orWhere('phone', $request->username);
                });
            if ($tenant) {
                $collQuery->where('tenant_id', $tenant->id);
            }
            $coll = $collQuery->first();
            if ($coll && Hash::check($request->password, $coll->password)) {
                $user = $coll;
            }
        }

        if (!$tenant && $user && $user->tenant_id) {
            $tenant = Tenant::withoutGlobalScopes()->where('id', $user->tenant_id)->first();
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Username atau password salah')->withInput();
        }

        // Cek akun aktif
        if (isset($user->is_active) && ! $user->is_active) {
            return back()->with('error', 'Akun Anda dinonaktifkan');
        }

        // Cek tenant masih ada & aktif
        if (! $tenant || ! $tenant->is_active || $tenant->isExpired()) {
            return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
        }

        // Set session terpadu (keduanya aktif)
        session([
            'technician_id' => $user->id,
            'technician_name' => $user->name ?? $user->username,
            'technician_username' => $user->username,
            'technician_logged_in' => true,
            'collector_id' => $user->id,
            'collector_name' => $user->name ?? $user->username,
            'collector_username' => $user->username,
            'collector_logged_in' => true,
            'tenant_id' => $user->tenant_id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
        ]);

        if ($request->boolean('remember', true)) {
            cookie()->queue('nodera_remember_technician', $user->id, 525600);
        }

        if ($user instanceof User) {
            $user->last_login = now();
            $user->save();
        }

        return redirect('/teknisi/dashboard');
    }

    public function dashboard()
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        // Ambil data teknisi
        $technician = User::find($technicianId);
        if (! $technician) {
            session()->forget(['technician_id', 'technician_name', 'technician_logged_in', 'tenant_id']);

            return redirect('/teknisi/login')->with('error', 'Akun teknisi tidak ditemukan');
        }

        // Tickets: assigned to this technician OR for their assigned routers
        $routerIds = $technician->technicianRouters()->pluck('router_id');
        $tickets = TroubleTicket::with(['customer', 'router'])->where(function ($q) use ($technicianId, $routerIds) {
            $q->where('assigned_to', $technicianId);
            if ($routerIds->isNotEmpty()) {
                $q->orWhereIn('router_id', $routerIds);
            }
        })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'in_progress' THEN 2 WHEN status = 'resolved' THEN 3 WHEN status = 'closed' THEN 4 ELSE 5 END")
            ->latest()
            ->get();

        $stats = [
            'total' => $tickets->count(),
            'pending' => $tickets->where('status', 'pending')->count(),
            'in_progress' => $tickets->where('status', 'in_progress')->count(),
            'resolved' => $tickets->where('status', 'resolved')->count(),
            'closed' => $tickets->where('status', 'closed')->count(),
        ];

        return Inertia::render('Technician/Dashboard', [
            'technician' => [
                'name' => $technician->name,
                'username' => $technician->username,
            ],
            'stats' => $stats,
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'customer_id' => $t->customer_id,
                'customer_name' => $t->customer?->name,
                'customer_phone' => $t->customer?->phone,
                'pppoe_username' => $t->customer?->pppoe_username,
                'onu_serial' => $t->customer?->onu_serial ?? $t->customer?->mac_address,
                'router_name' => $t->router?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'permissions' => (!empty($technician->permissions) && is_array($technician->permissions))
                ? array_merge([
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
                ], $technician->permissions)
                : [
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
                ],
        ]);
    }

    public function logout()
    {
        session()->forget([
            'technician_id', 'technician_name', 'technician_username',
            'technician_logged_in', 'tenant_id', 'tenant_slug', 'tenant_name',
        ]);
        cookie()->queue(cookie()->forget('nodera_remember_technician'));

        return redirect('/teknisi/login')->with('msg', 'Berhasil logout');
    }

    public function checkIn()
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        $existing = Attendance::where('user_id', $technicianId)
            ->whereDate('date', today())->first();

        if ($existing) {
            return back()->with('error', 'Sudah check-in hari ini');
        }

        Attendance::create([
            'user_id' => $technicianId,
            'date' => today(),
            'check_in' => now(),
            'tenant_id' => session('tenant_id'),
        ]);

        return back()->with('msg', 'Check-in berhasil');
    }

    public function checkOut()
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        $attendance = Attendance::where('user_id', $technicianId)
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

    public function map()
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        if (! $this->checkPermission('map')) {
            return redirect('/teknisi/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat peta ODP/ONU.');
        }

        $mapData = \App\Services\GisMapService::getMapData();

        return Inertia::render('Technician/Map', array_merge($mapData, [
            'readOnly' => true,
            'role' => 'technician',
        ]));
    }

    public function takeTicket($id)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        $technician = User::with('technicianRouters')->find($technicianId);
        $routerIds = $technician?->technicianRouters->pluck('id') ?? collect();

        $ticket = TroubleTicket::where('id', $id)
            ->where('status', 'pending')
            ->where(function ($q) use ($routerIds) {
                if ($routerIds->isNotEmpty()) {
                    $q->whereIn('router_id', $routerIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->first();

        if (! $ticket) {
            return back()->with('error', 'Tiket tidak ditemukan atau sudah diambil teknisi lain');
        }

        $ticket->update([
            'assigned_to' => $technicianId,
            'status' => 'in_progress',
        ]);

        try {
            if ($technician) {
                app(\App\Services\PushNotificationService::class)->notifyTicketAssigned($ticket, $technician);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Push notif failed on takeTicket: " . $e->getMessage());
        }

        return back()->with('msg', 'Tiket #'.$id.' berhasil diambil');
    }

    public function resolveTicket($id)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        $ticket = TroubleTicket::where('id', $id)
            ->where(function ($q) use ($technicianId) {
                $q->where('assigned_to', $technicianId);
            })
            ->first();

        if (! $ticket) {
            return back()->with('error', 'Tiket tidak ditemukan');
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $technicianId,
            'resolution_notes' => request('resolution_notes', 'Diselesaikan oleh teknisi'),
        ]);

        try {
            app(\App\Services\PushNotificationService::class)->notifyTicketResolved($ticket);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Push notif failed on resolveTicket: " . $e->getMessage());
        }

        return back()->with('msg', 'Tiket #'.$id.' ditandai selesai');
    }

    public function customers()
    {
        $staffId = session('technician_id') ?: session('collector_id');
        if (! $staffId) {
            return redirect(request()->is('kolektor/*') ? '/kolektor/login' : '/teknisi/login');
        }

        if (! $this->checkPermission('customers')) {
            $dash = request()->is('kolektor/*') ? '/kolektor/dashboard' : '/teknisi/dashboard';
            return redirect($dash)->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengelola pelanggan.');
        }

        $tenantId = session('tenant_id');
        $customerQuery = Customer::with('package', 'router', 'odp')
            ->withCount(['invoices as unpaid_invoices' => fn ($q) => $q->where('paid', 0)])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $customers = $customerQuery->get()
            ->sort(fn ($a, $b) => strnatcasecmp($a->name ?? '', $b->name ?? ''))
            ->values();

        $packages = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $routers = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)->get();

        $odps = OdpLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->withCount('customers')->orderBy('name')->get();
        $technicians = User::whereIn('role', ['technician', 'teknisi'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $isCollector = request()->is('kolektor/*');

        return Inertia::render('Admin/Customers', [
            'create' => (bool) request('create'),
            'customers' => $customers->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'connection_type' => $c->connection_type ?? 'pppoe',
                'pppoe_username' => $c->pppoe_username,
                'ip_address' => $c->ip_address,
                'mac_address' => $c->mac_address,
                'arp_interface' => $c->arp_interface,
                'auto_arp' => (bool) $c->auto_arp,
                'phone' => $c->phone,
                'email' => $c->email,
                'address' => $c->address,
                'status' => $c->status,
                'isolation_date' => $c->isolation_date,
                'lat' => $c->lat,
                'lng' => $c->lng,
                'package_id' => $c->package_id,
                'package' => $c->package?->name,
                'odp_id' => $c->odp_id,
                'odp_port' => $c->odp_port,
                'odp_name' => $c->odp?->name,
                'router' => $c->router?->name,
                'router_id' => $c->router_id,
                'unpaid_invoices' => (int) $c->unpaid_invoices,
            ]),
            'packages' => $packages->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) ($p->price ?? 0)]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'host' => $r->host]),
            'odps' => $odps->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'capacity' => (int) ($o->capacity ?? 8),
                'used_ports' => (int) ($o->customers_count ?? 0),
                'available_ports' => max(0, (int) ($o->capacity ?? 8) - (int) ($o->customers_count ?? 0)),
            ]),
            'technicians' => $technicians->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'role' => $isCollector ? 'collector' : 'technician',
        ]);
    }

    public function createCustomer()
    {
        $staffId = session('technician_id') ?: session('collector_id');
        if (! $staffId) {
            return redirect(request()->is('kolektor/*') ? '/kolektor/login' : '/teknisi/login');
        }

        if (! $this->checkPermission('create_customer')) {
            $dash = request()->is('kolektor/*') ? '/kolektor/dashboard' : '/teknisi/dashboard';
            return redirect($dash)->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk menambah pelanggan.');
        }

        $technician = User::with('technicianRouters')->find($staffId);
        $packages = DB::table('packages')->where('tenant_id', session('tenant_id'))->where('is_active', true)->get();

        // Router yang dihandle teknisi / kolektor
        $routerIds = $technician?->technicianRouters?->pluck('id') ?? collect();
        if ($routerIds->isEmpty()) {
            $collector = Collector::find($staffId);
            if ($collector && $collector->router_id) {
                $routerIds = collect([$collector->router_id]);
            }
        }

        $routersQuery = Mikrotik::where('tenant_id', session('tenant_id'))->where('is_active', true);
        if ($routerIds->isNotEmpty()) {
            $routersQuery->whereIn('id', $routerIds);
        }
        $routers = $routersQuery->orderBy('name')->get();

        $odps = OdpLocation::where('tenant_id', session('tenant_id'))->orderBy('name')->get();
        $customers = Customer::where('tenant_id', session('tenant_id'))->select('id', 'name', 'odp_id', 'odp_port')->get();

        return Inertia::render('Technician/CreateCustomer', [
            'packages' => $packages->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) ($p->price ?? 0)]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'host' => $r->host]),
            'odps' => $odps->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'capacity' => (int) ($o->capacity ?? 8),
            ]),
            'customers' => $customers,
        ]);
    }

    public function storeCustomer(Request $request)
    {
        if (! $this->checkPermission('create_customer')) {
            return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk menambah pelanggan.');
        }

        $connectionType = $request->input('connection_type', 'pppoe');

        $rules = [
            'name' => 'required|min:3|max:100',
            'phone' => 'nullable|string|min:9|max:20',
            'package_id' => 'required|numeric',
            'odp_id' => 'nullable|numeric',
            'odp_port' => 'nullable|numeric',
            'isolation_date' => 'required|numeric|gt:0|lt:32',
            'connection_type' => 'nullable|in:pppoe,static,radius,hotspot',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:30',
            'arp_interface' => 'nullable|string|max:50',
            'address' => 'nullable|max:500',
            'email' => 'nullable|email|max:100',
            'lat' => 'nullable',
            'lng' => 'nullable',
            'router_id' => 'nullable|exists:mikrotiks,id',
        ];

        if ($connectionType === 'static') {
            $rules['pppoe_username'] = 'nullable|string|max:50';
            $rules['ip_address'] = 'required|string|max:45';
        } else {
            $rules['pppoe_username'] = 'required|min:3|max:50|regex:/^[a-zA-Z0-9@._-]+$/|unique:customers,pppoe_username';
        }

        $validated = $request->validate($rules);

        $tenantId = session('tenant_id') ?? auth()->user()?->tenant_id;
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant && $tenant->max_customers > 0) {
                $currentCount = Customer::where('tenant_id', $tenantId)->count();
                if ($currentCount >= $tenant->max_customers) {
                    return redirect()->back()->withInput()->with('error', "Kapasitas pelanggan telah mencapai batas maksimal paket Anda ({$tenant->max_customers} pelanggan pada {$tenant->package_name}). Silakan hubungi Superadmin untuk upgrade paket!");
                }
            }
        }

        $data = $request->only('name', 'phone', 'package_id', 'odp_id', 'odp_port', 'isolation_date', 'lat', 'lng', 'address', 'email', 'router_id', 'ip_address', 'mac_address', 'arp_interface');
        $data['connection_type'] = $connectionType;
        $data['auto_arp'] = $request->boolean('create_arp');
        $data['status'] = 'active';
        $data['tenant_id'] = $tenantId;
        $passwordSecret = $request->input('pppoe_password') ?: '123456';
        $data['portal_password'] = bcrypt($passwordSecret);
        $data['code'] = 'CUS-'.strtoupper(substr(md5(time().uniqid()), 0, 6));

        if (isset($data['lat']) && is_string($data['lat'])) {
            $data['lat'] = str_replace(',', '.', trim($data['lat']));
        }
        if (isset($data['lng']) && is_string($data['lng'])) {
            $data['lng'] = str_replace(',', '.', trim($data['lng']));
        }
        if (empty($data['lat']) || (float)$data['lat'] == 0 || !is_numeric($data['lat'])) {
            $data['lat'] = null;
        } else {
            $data['lat'] = (float) $data['lat'];
        }
        if (empty($data['lng']) || (float)$data['lng'] == 0 || !is_numeric($data['lng'])) {
            $data['lng'] = null;
        } else {
            $data['lng'] = (float) $data['lng'];
        }

        if ($connectionType === 'static' && empty($request->pppoe_username)) {
            $data['pppoe_username'] = 'static_' . str_replace('.', '_', $data['ip_address'] ?? uniqid());
        } else {
            $data['pppoe_username'] = $request->pppoe_username;
        }

        // Cegah duplikasi port ODP
        if (!empty($data['odp_id']) && !empty($data['odp_port'])) {
            $existingPortCust = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('odp_id', $data['odp_id'])
                ->where('odp_port', $data['odp_port'])
                ->first();
            if ($existingPortCust) {
                return redirect()->back()->withInput()->with('error', "Port ODP {$data['odp_port']} sudah digunakan oleh pelanggan \"{$existingPortCust->name}\".");
            }
        }

        // ==== Sinkronisasi ke MikroTik (opsional via checkbox) ====
        $mikrotikSuccess = false;
        $mikrotikSkipped = false;
        $mikrotikError = '';

        $router = null;
        if ($request->filled('router_id')) {
            $router = Mikrotik::find($request->router_id);
        }
        if (! $router) {
            $package = Package::where('type', '!=', 'subscription')->find($data['package_id']);
            if ($package && $package->router_id) {
                $router = Mikrotik::find($package->router_id);
            }
        }
        if (! $router) {
            $router = Mikrotik::where('tenant_id', $data['tenant_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->first();
        }

        $shouldSyncPppoe = ($request->boolean('create_pppoe') || $request->create_pppoe === '1') && $connectionType === 'pppoe';
        $isStatic = in_array(strtolower((string)$connectionType), ['static', 'arp', 'static_ip', 'ip_static', 'ip_statis'], true)
            || (!empty($data['ip_address']) && (empty($data['pppoe_username']) || str_starts_with((string)$data['pppoe_username'], 'static_') || str_starts_with((string)$data['pppoe_username'], 'arp_')));

        $shouldSyncStatic = ($request->boolean('create_arp') || $request->create_arp === '1' || !empty($data['ip_address'])) && $isStatic;

        if (($shouldSyncPppoe || $shouldSyncStatic) && $router && $router->is_active) {
            $mik = new MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) $router->port,
            ]);
            try {
                $package = Package::where('type', '!=', 'subscription')->find($data['package_id']);
                if ($isStatic) {
                    $interface = $data['arp_interface'] ?: 'bridge';
                    $mac = $data['mac_address'] ?: '00:00:00:00:00:00';
                    $arpRes = true;
                    if ($request->boolean('create_arp') || !empty($data['mac_address'])) {
                        $arpRes = $mik->addArpEntry($data['ip_address'], $mac, $interface, "NODERA - {$data['name']}");
                    }
                    $maxLimit = $package->profile_normal ?? '10M/10M';
                    $queueRes = $mik->addSimpleQueue("STATIC - {$data['name']}", $data['ip_address'], $maxLimit, "NODERA Static IP");
                    if ($arpRes && $queueRes) {
                        $mikrotikSuccess = true;
                        $data['router_id'] = $router->id;
                    } elseif ($arpRes || $queueRes) {
                        $mikrotikSuccess = true;
                        $data['router_id'] = $router->id;
                    } else {
                        $mikrotikError = $mik->getLastError() ?: 'Gagal menambahkan entri ARP / Simple Queue di MikroTik';
                    }
                } else {
                    $result = $mik->addPppoeSecret($data['pppoe_username'], $passwordSecret, $package->profile_normal ?? 'default');
                    if ($result) {
                        $mikrotikSuccess = true;
                        $data['router_id'] = $router->id;
                    } else {
                        $mikrotikError = $mik->getLastError();
                    }
                }
            } catch (\Exception $e) {
                $mikrotikError = $e->getMessage();
            }
        } elseif ($shouldSyncPppoe || $shouldSyncStatic) {
            $mikrotikError = 'Router tidak ditemukan atau tidak aktif';
        } else {
            $mikrotikSkipped = true;
        }

        Customer::create($data);

        $dash = (request()->is('kolektor/*') || (session('collector_id') && !session('technician_id')))
            ? '/kolektor/dashboard'
            : '/teknisi/dashboard';

        if ($mikrotikSkipped) {
            return redirect($dash)->with('msg', 'Pelanggan '.$data['name'].' berhasil didaftarkan.');
        }
        if ($mikrotikSuccess) {
            $syncMsg = $connectionType === 'static' ? 'entri ARP & Simple Queue aktif di MikroTik.' : 'akun secret PPPoE aktif di MikroTik.';
            return redirect($dash)->with('msg', 'Pelanggan '.$data['name'].' berhasil didaftarkan dan '.$syncMsg);
        }

        return redirect($dash)->with('error', 'Pelanggan '.$data['name'].' tersimpan, TAPI gagal dibuat di MikroTik: '.$mikrotikError);
    }

    public function history()
    {
        $staffId = session('technician_id') ?: session('collector_id');
        if (! $staffId) {
            return redirect(request()->is('kolektor/*') ? '/kolektor/login' : '/teknisi/login');
        }

        if (! $this->checkPermission('history')) {
            $dash = request()->is('kolektor/*') ? '/kolektor/dashboard' : '/teknisi/dashboard';
            return redirect($dash)->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat riwayat.');
        }

        $resolvedTickets = TroubleTicket::with(['customer', 'router'])
            ->where(function ($q) use ($staffId) {
                $q->where('resolved_by', $staffId)->orWhere('assigned_to', $staffId);
            })
            ->orderBy('resolved_at', 'desc')
            ->limit(50)
            ->get();

        $attendance = Attendance::where('user_id', $staffId)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        return Inertia::render('Technician/History', [
            'resolvedTickets' => $resolvedTickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'resolved_at' => $t->resolved_at?->toIso8601String(),
                'customer_name' => $t->customer?->name,
                'router_name' => $t->router?->name,
            ]),
            'attendance' => $attendance->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date?->toIso8601String(),
                'check_in' => $a->check_in?->toIso8601String(),
                'check_out' => $a->check_out?->toIso8601String(),
            ]),
        ]);
    }

    public function pool()
    {
        $staffId = session('technician_id') ?: session('collector_id');
        if (! $staffId) {
            return redirect(request()->is('kolektor/*') ? '/kolektor/login' : '/teknisi/login');
        }

        if (! $this->checkPermission('pool')) {
            $dash = request()->is('kolektor/*') ? '/kolektor/dashboard' : '/teknisi/dashboard';
            return redirect($dash)->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses pool pekerjaan.');
        }

        $technician = User::with('technicianRouters')->find($staffId);
        $routerIds = $technician?->technicianRouters?->pluck('id') ?? collect();
        if ($routerIds->isEmpty()) {
            $collector = Collector::find($staffId);
            if ($collector && $collector->router_id) {
                $routerIds = collect([$collector->router_id]);
            } else {
                $routerIds = Mikrotik::where('tenant_id', session('tenant_id'))->pluck('id');
            }
        }

        // Tiket yang belum diassign, untuk router teknisi
        $openTicketsQuery = TroubleTicket::where('status', 'pending')
            ->where('tenant_id', session('tenant_id'));
        if ($routerIds->isNotEmpty()) {
            $openTicketsQuery->whereIn('router_id', $routerIds);
        }
        $openTickets = $openTicketsQuery->orderBy('created_at', 'desc')->get();

        // Tiket yang sedang ditangani teknisi lain
        $inProgressTicketsQuery = TroubleTicket::where('status', 'in_progress')
            ->where('tenant_id', session('tenant_id'));
        if ($routerIds->isNotEmpty()) {
            $inProgressTicketsQuery->whereIn('router_id', $routerIds);
        }
        $inProgressTickets = $inProgressTicketsQuery->orderBy('created_at', 'desc')->get();

        return Inertia::render('Technician/Pool', [
            'openTickets' => $openTickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'customer_name' => $t->customer?->name,
                'router_name' => $t->router?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'inProgressTickets' => $inProgressTickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'customer_name' => $t->customer?->name,
                'router_name' => $t->router?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function saveFcmToken(Request $request)
    {
        $techId = session('technician_id');
        if ($techId && $request->filled('token')) {
            User::where('id', $techId)->update(['fcm_token' => $request->token]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }

    public function earnings(Request $request)
    {
        $techId = session('technician_id');
        if (! $techId) {
            return redirect('/teknisi/login');
        }

        if (! $this->checkPermission('earnings')) {
            return redirect('/teknisi/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat data pendapatan.');
        }

        $user = User::findOrFail($techId);

        $commType = $user->commission_type ?? 'fixed';
        $commValue = (float) ($user->commission_value ?? 0);
        $globalDefault = (float) \App\Models\Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', 5000);

        if ($commType === 'percent' || $commType === 'percentage') {
            $rateLabel = number_format($commValue, 0) . '% dari total penagihan';
        } else {
            $rate = $commValue > 0 ? $commValue : $globalDefault;
            $rateLabel = 'Rp ' . number_format($rate, 0, ',', '.') . ' / invoice';
        }

        $cleanName = trim($user->name);
        $cleanUsername = trim($user->username);
        $tenantId = session('tenant_id') ?? $user->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId();

        $invoices = Invoice::with('customer')
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where(function ($q) {
                $q->where('paid', 1)
                  ->orWhere('status', 'paid');
            })
            ->where(function ($q) use ($cleanName, $cleanUsername) {
                $q->whereRaw('LOWER(TRIM(processed_by)) = ?', [strtolower($cleanName)])
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

        return Inertia::render('Technician/Earnings', [
            'technician' => [
                'name' => $user->name,
                'username' => $user->username,
                'commission_type' => $commType,
                'commission_value' => $commValue,
                'commission_rate_label' => $rateLabel,
            ],
            'totalEarningsAllTime' => $totalEarningsAllTime,
            'totalInvoicesAllTime' => count($invoices),
            'monthlyEarnings' => $monthlyEarnings,
        ]);
    }

    /**
     * Get live Optical Rx/Tx Power & Device Info for customer ONT from GenieACS TR-069
     */
    public function onuSignal($ticketId)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket = TroubleTicket::with('customer')->findOrFail($ticketId);
        $customer = $ticket->customer;

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
        }

        $pppoeUsername = $customer->pppoe_username ?? '';
        $onuSerial = $customer->onu_serial ?? $customer->mac_address ?? '';

        $genieacs = app(\App\Services\GenieacsService::class);
        $device = null;

        if (! empty($pppoeUsername)) {
            $device = $genieacs->getDeviceByPppoeUsername($pppoeUsername);
        }

        if (empty($device) && ! empty($onuSerial)) {
            $device = $genieacs->getDevice($onuSerial);
        }

        if (empty($device)) {
            return response()->json([
                'success' => false,
                'message' => 'Perangkat ONT TR-069 belum terhubung ke GenieACS untuk pelanggan ini (' . ($pppoeUsername ?: 'Tanpa PPPoE') . ').',
                'customer' => [
                    'name' => $customer->name,
                    'pppoe_username' => $customer->pppoe_username,
                    'ip_address' => $customer->ip_address,
                    'connection_type' => $customer->connection_type ?? 'pppoe',
                ],
            ]);
        }

        $summary = $device['summary'] ?? [];
        $rawRx = $summary['rx_power'] ?? null;
        $rxNum = null;
        if ($rawRx !== null) {
            preg_match('/-?\d+(\.\d+)?/', (string) $rawRx, $matches);
            $rxNum = isset($matches[0]) ? (float) $matches[0] : null;
        }

        $signalStatus = 'good'; // good (-15 to -24), warning (-24 to -27), critical (< -27), strong (> -14)
        if ($rxNum !== null) {
            if ($rxNum < -27) {
                $signalStatus = 'critical';
            } elseif ($rxNum < -24) {
                $signalStatus = 'warning';
            } elseif ($rxNum > -14) {
                $signalStatus = 'strong';
            } else {
                $signalStatus = 'good';
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'serial_number' => $summary['serial_number'] ?? $onuSerial ?: 'Unknown',
                'model' => $summary['product_class'] ?? $summary['model'] ?? 'ONT Router',
                'manufacturer' => $summary['manufacturer'] ?? 'Generic',
                'rx_power' => $rxNum !== null ? "{$rxNum} dBm" : ($rawRx ?: '-'),
                'rx_raw' => $rxNum,
                'signal_status' => $signalStatus,
                'temperature' => $summary['temperature'] ?? '-',
                'uptime' => $summary['uptime'] ?? '-',
                'ssid' => $summary['wlan_ssid'] ?? '-',
                'active_clients' => $summary['wlan_active_clients'] ?? 0,
                'ip_tr069' => $summary['ip_tr069'] ?? '-',
                'last_inform' => $summary['last_inform'] ?? '-',
                'customer_name' => $customer->name,
                'pppoe_username' => $customer->pppoe_username,
            ],
        ]);
    }

    /**
     * Remote Reboot ONT Device from Technician Portal
     */
    public function rebootOnu($ticketId)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket = TroubleTicket::with('customer')->findOrFail($ticketId);
        $customer = $ticket->customer;
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
        }

        $genieacs = app(\App\Services\GenieacsService::class);
        $device = null;
        if (! empty($customer->pppoe_username)) {
            $device = $genieacs->getDeviceByPppoeUsername($customer->pppoe_username);
        }
        if (empty($device) && ! empty($customer->onu_serial)) {
            $device = $genieacs->getDevice($customer->onu_serial);
        }

        $serial = $device['summary']['serial_number'] ?? $device['_deviceId']['_SerialNumber'] ?? $customer->onu_serial;
        $rebootSuccess = false;
        $messages = [];

        // 1. Try GenieACS if serial exists and GenieACS is configured
        if ($serial && $genieacs->isConfigured()) {
            $genieRes = $genieacs->rebootDevice($serial);
            if (($genieRes['code'] ?? 0) >= 200 && ($genieRes['code'] ?? 0) < 300) {
                $rebootSuccess = true;
                $messages[] = 'TR-069 GenieACS: Task reboot dikirim';
            }
        }

        // 2. Try OLT Web API, SNMP SET & Telnet if customer is linked to an Onu record
        $onu = \App\Models\Onu::where('customer_id', $customer->id)
            ->when($serial, fn ($q) => $q->orWhere('serial_number', $serial))
            ->first();

        if ($onu && $onu->olt) {
            $nms = app(\App\Services\OltNmsService::class);
            $olt = $onu->olt;

            // Try HTTP Web API if OLT credentials exist
            if ($olt->username && $olt->password) {
                $httpRes = $nms->rebootOnuViaHttp($olt, $onu);
                if ($httpRes['success']) {
                    $rebootSuccess = true;
                    $messages[] = 'OLT Web API: ' . $httpRes['message'];
                }
            }

            // Try SNMP SET
            $snmpRes = $nms->rebootOnuViaSnmp($olt, $onu);
            if ($snmpRes['success']) {
                $rebootSuccess = true;
                $messages[] = 'OLT SNMP: ' . $snmpRes['message'];
            }

            // Fallback to Telnet CLI if not yet successful
            if (!$rebootSuccess && ($olt->telnet_port || $olt->connection_mode !== 'snmp')) {
                $oltRes = $nms->rebootOnuViaTelnet($olt, $onu);
                if ($oltRes['success']) {
                    $rebootSuccess = true;
                    $messages[] = 'OLT Telnet: ' . $oltRes['message'];
                }
            }
        }

        if (!$serial && !$onu) {
            return response()->json(['success' => false, 'message' => 'Serial perangkat ONT atau data OLT tidak ditemukan untuk pelanggan ini.'], 400);
        }

        // Audit log
        try {
            AuditLog::create([
                'tenant_id' => session('tenant_id'),
                'user_id' => $technicianId,
                'action' => 'onu_reboot',
                'entity_type' => 'customer',
                'entity_id' => $customer->id,
                'new_values' => [
                    'customer_name' => $customer->name,
                    'ticket_id' => $ticket->id,
                    'serial' => $serial ?: ($onu->serial_number ?? null),
                    'status' => $rebootSuccess ? 'success' : 'failed',
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Technician Portal',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        $summary = implode(' | ', $messages);

        if ($rebootSuccess) {
            return response()->json(['success' => true, 'message' => "Perintah reboot ONT berhasil dikirim ({$summary})."]);
        }

        return response()->json(['success' => false, 'message' => "Gagal reboot ONT: {$summary}"], 400);
    }

    /**
     * PPPoE monitoring for technicians with ONU optical power & customer link.
     */
    public function pppoe(Request $request)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        if (! $this->checkPermission('pppoe')) {
            return redirect('/teknisi/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses menu PPPoE & Optik.');
        }

        $technician = User::find($technicianId);
        $tenantId   = $technician?->tenant_id ?? session('tenant_id');
        $routers    = Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('is_active', true)->orderBy('name')->get();
        $defaultRouterId = $routers->first()?->id ? (string) $routers->first()->id : null;
        $routerId   = $request->get('router_id', $defaultRouterId);
        $users      = [];
        $active     = [];
        $inactive   = [];
        $ifaceByName = [];
        $errors     = [];
        $forceRefresh = $request->boolean('refresh') || $request->has('refresh');

        if ($routerId === 'all') {
            $targetRouters = $routers;
        } elseif (!empty($routerId)) {
            $targetRouters = $routers->where('id', (int) $routerId);
        } else {
            $targetRouters = collect();
        }

        foreach ($targetRouters as $router) {
            try {
                $cacheKey = "pppoe_router_cache_{$router->id}";
                $routerData = $forceRefresh ? null : \Illuminate\Support\Facades\Cache::get($cacheKey);

                if (!$routerData) {
                    $mik = new MikrotikService($router);
                    if (! $mik->isConnected()) {
                        $errors[] = 'Tidak dapat terhubung ke ' . $router->name . ($mik->getLastError() ? " ({$mik->getLastError()})" : '');
                        continue;
                    }

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

                $rUsers = $routerData['users'] ?? [];
                $rActive = $routerData['active'] ?? [];
                $rInactive = $routerData['inactive'] ?? [];
                if (!empty($routerData['ifaces'])) {
                    $ifaceByName = array_merge($ifaceByName, $routerData['ifaces']);
                }

                foreach ($rUsers as &$u) {
                    $u['router_id'] = $router->id;
                    $u['router_name'] = $router->name;
                }
                unset($u);

                foreach ($rActive as &$a) {
                    $a['router_id'] = $router->id;
                    $a['router_name'] = $router->name;
                }
                unset($a);

                foreach ($rInactive as &$inact) {
                    $inact['router_id'] = $router->id;
                    $inact['router_name'] = $router->name;
                }
                unset($inact);

                $users    = array_merge($users, $rUsers);
                $active   = array_merge($active, $rActive);
                $inactive = array_merge($inactive, $rInactive);
            } catch (\Throwable $e) {
                $errors[] = "Error {$router->name}: " . $e->getMessage();
            }
        }

        $error = !empty($errors) ? implode(' | ', $errors) : null;

        // Load customers & their linked ONUs dengan caching 45s
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

            // Also check unlinked ONUs
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
            'mode'     => 'technician',
            'routerId' => $routerId,
            'error'    => $error,
            'routers'  => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'users'    => collect($users)->map(function ($u) use ($resolveMeta, $resolveTraffic) {
                $username = $u['name'] ?? '';
                $meta = $resolveMeta($username);
                $traffic = $resolveTraffic($username);
                return [
                    'name'            => $username,
                    'router_id'       => $u['router_id'] ?? null,
                    'router_name'     => $u['router_name'] ?? null,
                    'password'        => $u['password'] ?? '',
                    'profile'         => $u['profile'] ?? '',
                    'disabled'        => ($u['disabled'] ?? '') === 'true',
                    'last_logged_out' => $u['last-logged-out'] ?? null,
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
            'active' => collect($active)->map(function ($a) use ($resolveMeta, $resolveTraffic) {
                $username = $a['name'] ?? '';
                $meta = $resolveMeta($username);
                $traffic = $resolveTraffic($username);
                return [
                    'name'          => $username,
                    'router_id'     => $a['router_id'] ?? null,
                    'router_name'   => $a['router_name'] ?? null,
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
                    'router_id'       => $i['router_id'] ?? null,
                    'router_name'     => $i['router_name'] ?? null,
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

    /**
     * ARP Static & Host Monitoring untuk teknisi.
     */
    public function arp(Request $request)
    {
        $technicianId = session('technician_id');
        if (! $technicianId) {
            return redirect('/teknisi/login');
        }

        if (! $this->checkPermission('arp')) {
            return redirect('/teknisi/dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses menu ARP.');
        }

        return app(ArpController::class)->index($request, 'technician');
    }

    public function profile(Request $request)
    {
        $techId = session('technician_id');
        if (! $techId) {
            return redirect('/teknisi/login');
        }

        $user = User::with('technicianRouters')->findOrFail($techId);

        return Inertia::render('Technician/Profile', [
            'technician' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'routers' => $user->technicianRouters->pluck('name')->join(', ') ?: 'Semua Wilayah',
                'commission_type' => $user->commission_type,
                'commission_value' => (float) $user->commission_value,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $techId = session('technician_id');
        if (! $techId) {
            return redirect('/teknisi/login');
        }

        $user = User::findOrFail($techId);

        if ($request->filled('new_password')) {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:6|confirmed',
            ]);

            if (! Hash::check($request->current_password, $user->password)) {
                return back()->with('error', 'Password saat ini salah.');
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return back()->with('msg', 'Password berhasil diperbarui.');
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);
        session(['technician_username' => $user->username]);

        return back()->with('msg', 'Username & kontak berhasil diperbarui.');
    }
}
