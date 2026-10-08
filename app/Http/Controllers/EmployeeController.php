<?php

namespace App\Http\Controllers;

use App\Models\Collector;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function tenantId()
    {
        return \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?: auth()->user()?->tenant_id;
    }

    private function defaultPermissions(): array
    {
        return [
            'dashboard' => true,
            'customers' => true,
            'create_customer' => true,
            'collect_payment' => true,
            'earnings' => true,
            'pool' => true,
            'history' => true,
            'pppoe' => true,
            'isolate' => true,
            'map' => true,
            'top_bandwidth' => true,
        ];
    }

    public function index()
    {
        $tenantId = $this->tenantId();
        $routers = \App\Models\Mikrotik::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();

        $collectors = Collector::where('tenant_id', $tenantId)->orderBy('name')->get()->map(function ($c) {
            return (object) [
                'id' => 'collector_' . $c->id,
                'original_id' => $c->id,
                'type' => 'collector',
                'name' => $c->name,
                'username' => $c->username,
                'phone' => $c->phone ?? '',
                'email' => $c->email ?? '',
                'role' => 'collector',
                'is_active' => (bool) ($c->is_active ?? true),
                'status' => ($c->is_active ?? true) ? 'active' : 'inactive',
                'salary' => 0,
                'router_id' => $c->router_id ?? null,
                'collection_area' => $c->collection_area ?? '',
                'commission_type' => $c->commission_type ?? 'fixed',
                'commission_value' => (float) ($c->commission_value ?? 0),
                'permissions' => $c->permissions ?? [],
            ];
        });

        $users = User::where('tenant_id', $tenantId)->whereIn('role', ['technician', 'cashier', 'admin'])
            ->orderBy('name')->get()->map(function ($u) {
                return (object) [
                    'id' => 'user_' . $u->id,
                    'original_id' => $u->id,
                    'type' => 'user',
                    'name' => $u->name,
                    'username' => $u->username,
                    'phone' => $u->phone ?? '',
                    'email' => $u->email ?? '',
                    'role' => $u->role,
                    'is_active' => (bool) ($u->is_active ?? true),
                    'status' => ($u->is_active ?? true) ? 'active' : 'inactive',
                    'salary' => (float) ($u->salary ?? 0),
                    'router_id' => $u->router_id ?? null,
                    'collection_area' => '',
                    'commission_type' => $u->commission_type ?? 'fixed',
                    'commission_value' => (float) ($u->commission_value ?? 0),
                    'permissions' => $u->permissions ?? [],
                ];
            });

        $employees = $collectors->concat($users)->sortBy('name')->values();

        $tenant = Tenant::find($tenantId);
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
        $tenantHost = $tenant ? $tenant->slug . '.' . $baseDomain : request()->getHost();
        $scheme = request()->getScheme();
        $port = in_array(request()->getPort(), [80, 443]) ? '' : ':' . request()->getPort();
        $baseUrl = $scheme . '://' . $tenantHost . $port;

        $unifiedDefaultPerms = $this->defaultPermissions();

        $loginUrls = [
            'collector' => $baseUrl . '/kolektor/login',
            'technician' => $baseUrl . '/teknisi/login',
            'cashier' => $baseUrl . '/kasir/login',
            'admin' => $baseUrl . '/login',
        ];

        return Inertia::render('Admin/Employees', [
            'create' => (bool) request('create'),
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id, 'name' => $e->name,
                'position' => $e->position ?? ($e->role === 'collector' ? 'Kolektor' : ucfirst($e->role ?? 'karyawan')),
                'username' => $e->username ?? '',
                'phone' => $e->phone ?? '', 'email' => $e->email ?? '',
                'role' => $e->role ?? 'technician',
                'is_active' => (bool) ($e->is_active ?? true),
                'status' => $e->status ?? (($e->is_active ?? true) ? 'active' : 'inactive'),
                'salary' => (float) ($e->salary ?? 0),
                'router_id' => $e->router_id ?? null,
                'collection_area' => $e->collection_area ?? '',
                'commission_type' => $e->commission_type ?? 'fixed',
                'commission_value' => (float) ($e->commission_value ?? 0),
                'permissions' => (function () use ($e, $unifiedDefaultPerms) {
                    $raw = $e->permissions ?? null;
                    $userPerms = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
                    if (!empty($userPerms)) {
                        return array_merge($unifiedDefaultPerms, $userPerms);
                    }
                    return $unifiedDefaultPerms;
                })(),
            ]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
            'loginUrls' => $loginUrls,
            'rolePermissions' => [
                'technician' => $unifiedDefaultPerms,
                'collector' => $unifiedDefaultPerms,
            ],
        ]);
    }

    public function add(Request $request)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) {
            return redirect()->to('/admin/employees')->with('error', 'Tenant tidak terdeteksi. Silakan login ke panel tenant.');
        }

        if ($request->role === 'admin') {
            return redirect()->back()->with('error', 'Admin tenant tidak dapat menambahkan akun dengan role admin. Anda hanya dapat menambahkan karyawan operasional (Teknisi, Kolektor, Kasir).');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|regex:/^\S*$/',
            'password' => 'required|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:technician,collector,cashier',
            'router_id' => 'nullable',
            'is_active' => 'nullable',
            'collection_area' => 'nullable|string|max:255',
            'commission_type' => 'nullable|in:fixed,percent,percentage',
            'commission_value' => 'nullable|numeric|min:0',
        ]);

        $isActive = $request->boolean('is_active', true);
        $commType = $request->input('commission_type') ?: 'fixed';
        $commVal = (float) ($request->input('commission_value') ?? 0);
        $routerId = (!empty($request->input('router_id')) && is_numeric($request->input('router_id'))) ? (int) $request->input('router_id') : null;

        $perms = $request->input('permissions');
        if (is_string($perms)) {
            $perms = json_decode($perms, true);
        }

        try {
            if ($data['role'] === 'collector') {
                $request->validate(['username' => 'unique:collectors,username']);
                Collector::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => bcrypt($data['password']),
                    'phone' => $data['phone'] ?? '',
                    'email' => $data['email'] ?? '',
                    'is_active' => $isActive,
                    'collection_area' => $request->input('collection_area') ?? '',
                    'commission_type' => $commType,
                    'commission_value' => $commVal,
                    'balance' => 0,
                    'tenant_id' => $tenantId,
                    'router_id' => $routerId,
                    'permissions' => is_array($perms) ? $perms : $this->defaultPermissions(),
                ]);
            } else {
                $request->validate(['username' => 'unique:users,username']);
                User::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'phone' => $data['phone'] ?? '',
                    'email' => $data['email'] ?? '',
                    'role' => $data['role'],
                    'is_active' => $isActive,
                    'commission_type' => $commType,
                    'commission_value' => $commVal,
                    'tenant_id' => $tenantId,
                    'router_id' => $routerId,
                    'permissions' => is_array($perms) ? $perms : $this->defaultPermissions(),
                ]);
            }

            return redirect()->to('/admin/employees')->with('success', 'Karyawan berhasil ditambahkan');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error adding employee: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan karyawan: ' . $e->getMessage());
        }
    }

    public function edit(Request $request, $id)
    {
        if ($request->role === 'admin') {
            return redirect()->back()->with('error', 'Admin tenant tidak dapat mengubah role karyawan menjadi admin.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|regex:/^\S*$/',
            'password' => 'nullable|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:technician,collector,cashier',
            'router_id' => 'nullable|exists:mikrotiks,id',
            'is_active' => 'required|boolean',
            'collection_area' => 'nullable|string|max:255',
            'commission_type' => 'nullable|in:fixed,percent,percentage',
            'commission_value' => 'nullable|numeric|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $commType = $request->input('commission_type') ?? 'fixed';
        $commVal = (float) ($request->input('commission_value') ?? 0);

        if (strpos($id, 'collector_') === 0) {
            $originalId = (int) substr($id, strlen('collector_'));
            $collector = Collector::where('tenant_id', $this->tenantId())->findOrFail($originalId);

            if ($data['role'] !== 'collector') {
                $tenantId = $this->tenantId();
                DB::transaction(function () use ($collector, $data, $tenantId, $commType, $commVal) {
                    $password = $data['password'] ?? substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
                    $username = $data['username'];
                    if (User::where('username', $username)->exists()) {
                        $username .= rand(10, 99);
                    }
                    User::create([
                        'name' => $data['name'],
                        'username' => $username,
                        'phone' => $data['phone'] ?? '',
                        'email' => $data['email'] ?? '',
                        'role' => $data['role'],
                        'is_active' => $data['is_active'],
                        'password' => bcrypt($password),
                        'commission_type' => $commType,
                        'commission_value' => $commVal,
                        'tenant_id' => $tenantId,
                        'router_id' => $data['router_id'] ?? null,
                    ]);
                    $collector->delete();
                });
                return redirect()->to('/admin/employees')->with('success', 'Role berhasil diubah ke ' . ucfirst($data['role']));
            }

            $request->validate(['username' => 'unique:collectors,username,' . $originalId]);
            $update = [
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'is_active' => $data['is_active'],
                'collection_area' => $request->input('collection_area') ?? '',
                'commission_type' => $commType,
                'commission_value' => $commVal,
                'router_id' => $data['router_id'] ?? null,
            ];
            $perms = $request->input('permissions');
            if (is_string($perms)) {
                $perms = json_decode($perms, true);
            }
            if (is_array($perms)) {
                $update['permissions'] = $perms;
            }
            if (!empty($data['password'])) {
                $update['password'] = bcrypt($data['password']);
            }
            $collector->update($update);
        } elseif (strpos($id, 'user_') === 0) {
            $originalId = (int) substr($id, strlen('user_'));
            $user = User::where('tenant_id', $this->tenantId())->findOrFail($originalId);

            if ($user->role === 'admin' && auth()->user()?->role !== 'superadmin') {
                return redirect()->back()->with('error', 'Hanya superadmin yang bisa mengubah admin');
            }
            if ($user->id === auth()->id() && $data['role'] !== $user->role) {
                return redirect()->back()->with('error', 'Tidak bisa mengubah role sendiri');
            }

            $perms = $request->input('permissions');
            if (is_string($perms)) {
                $perms = json_decode($perms, true);
            }

            if ($data['role'] === 'collector') {
                $tenantId = $this->tenantId();
                DB::transaction(function () use ($user, $data, $tenantId, $commType, $commVal, $perms) {
                    $password = $data['password'] ?? substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
                    Collector::create([
                        'name' => $data['name'],
                        'username' => $data['username'],
                        'phone' => $data['phone'] ?? '',
                        'email' => $data['email'] ?? '',
                        'is_active' => $data['is_active'],
                        'password' => bcrypt($password),
                        'tenant_id' => $tenantId,
                        'router_id' => $data['router_id'] ?? null,
                        'collection_area' => '',
                        'commission_type' => $commType,
                        'commission_value' => $commVal,
                        'balance' => 0,
                        'permissions' => $perms ?? $user->permissions,
                    ]);
                    $user->delete();
                });
                return redirect()->to('/admin/employees')->with('success', 'Role berhasil diubah ke Kolektor');
            }

            $request->validate(['username' => 'unique:users,username,' . $originalId]);
            $update = [
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'role' => $data['role'],
                'is_active' => $data['is_active'],
                'commission_type' => $commType,
                'commission_value' => $commVal,
                'router_id' => $data['router_id'] ?? null,
            ];
            if (is_array($perms)) {
                $update['permissions'] = $perms;
            }
            if (!empty($data['password'])) {
                $update['password'] = Hash::make($data['password']);
            }
            $user->update($update);
        } else {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        return redirect()->to('/admin/employees')->with('success', 'Data karyawan berhasil diperbarui');
    }

    public function delete($id)
    {
        if (strpos($id, 'collector_') === 0) {
            $originalId = (int) substr($id, strlen('collector_'));
            $collector = Collector::where('tenant_id', $this->tenantId())->findOrFail($originalId);
            $collector->delete();
        } elseif (strpos($id, 'user_') === 0) {
            $originalId = (int) substr($id, strlen('user_'));
            $user = User::where('tenant_id', $this->tenantId())->findOrFail($originalId);

            // Admin gabisa hapus admin (diri sendiri atau admin lain)
            if ($user->role === 'admin') {
                if ($user->id === auth()->id()) {
                    return redirect()->back()->with('error', 'Tidak bisa menghapus akun sendiri');
                }
                return redirect()->back()->with('error', 'Hanya superadmin yang bisa menghapus admin');
            }

            $user->delete();
        } else {
            return redirect()->back()->with('error', 'Data tidak valid');
        }

        return redirect()->to('/admin/employees')->with('success', 'Karyawan berhasil dihapus');
    }

    public function savePermissions(Request $request)
    {
        $tenantId = $this->tenantId();
        $tech = $request->input('technician', []);
        $coll = $request->input('collector', []);

        \App\Models\Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ROLE_PERMISSIONS_TECHNICIAN'],
            ['value' => json_encode($tech)]
        );

        \App\Models\Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ROLE_PERMISSIONS_COLLECTOR'],
            ['value' => json_encode($coll)]
        );

        return redirect()->back()->with('msg', 'Hak akses teknisi dan kolektor berhasil diperbarui');
    }

    public function saveEmployeePermissions(Request $request, $id)
    {
        $tenantId = $this->tenantId();
        $perms = $request->input('permissions', []);

        if (strpos((string)$id, 'collector_') === 0) {
            $originalId = (int) substr($id, strlen('collector_'));
            $collector = Collector::where('tenant_id', $tenantId)->findOrFail($originalId);
            $collector->permissions = $perms;
            $collector->save();
            $name = $collector->name;
        } elseif (strpos((string)$id, 'user_') === 0) {
            $originalId = (int) substr($id, strlen('user_'));
            $user = User::where('tenant_id', $tenantId)->findOrFail($originalId);
            $user->permissions = $perms;
            $user->save();
            $name = $user->name;
        } else {
            return redirect()->back()->with('error', 'Karyawan tidak ditemukan');
        }

        return redirect()->back()->with('msg', "Hak akses karyawan {$name} berhasil diperbarui");
    }
}
