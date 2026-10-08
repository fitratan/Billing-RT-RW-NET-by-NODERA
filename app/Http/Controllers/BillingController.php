<?php

namespace App\Http\Controllers;

use App\Models\Collector;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Services\MikrotikService;
use App\Services\WhatsappService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class BillingController extends Controller
{
    protected MikrotikService $mikrotik;
    protected WhatsappService $wa;

    public function __construct(MikrotikService $mikrotik, WhatsappService $wa)
    {
        $this->mikrotik = $mikrotik;
        $this->wa = $wa;
    }

    public function index()
    {
        return redirect()->to('/admin/billing/invoices');
    }

    // ========== PACKAGES ==========
    public function packages()
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $packagesQuery = Package::where('type', '!=', 'subscription');
        if ($tenantId) {
            $packagesQuery->where('tenant_id', $tenantId);
        } else {
            $packagesQuery->whereNotNull('tenant_id');
        }
        $packages = $packagesQuery->with('router')->withCount('customers')->get();
        $routers = \App\Models\Mikrotik::where('is_active', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        // TIDAK konek MikroTik di page-load — dulu fsockopen nge-block 2 detik
        // tiap buka halaman (router ga kebaca → timeout). Profile paket datang
        // dari hasil sync (paket di router), bukan koneksi live.
        $profiles = [];
        $mikrotikConnected = false;
        $mikrotikError = '';

        if (empty($profiles)) {
            $profiles = [
                ['name' => 'default'],
                ['name' => 'up-10Mbps'],
                ['name' => 'up-20Mbps'],
                ['name' => 'up-50Mbps'],
                ['name' => 'isolir'],
            ];
        }

        $isolir = \DB::table('settings')->where('tenant_id', $tenantId)->whereIn('key', ['ISOLIR_HOUR', 'ISOLIR_MINUTE'])->pluck('value', 'key');

        return Inertia::render('Admin/Packages', [
            'create' => (bool) request('create'),
            'packages' => $packages->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) ($p->price ?? 0),
                'promo_price' => (float) ($p->promo_price ?? 0),
                'promo_cycles' => (int) ($p->promo_cycles ?? 0),
                'admin_fee' => (float) ($p->admin_fee ?? 0),
                'late_fee' => (float) ($p->late_fee ?? 0),
                'materai' => (float) ($p->materai ?? 0),
                'customers_count' => (int) ($p->customers_count ?? 0),
                'is_active' => (bool) $p->is_active,
                'router_id' => $p->router_id ? (int) $p->router_id : null,
                'router_name' => $p->router?->name,
                'profile_normal' => $p->profile_normal,
                'profile_isolir' => $p->profile_isolir,
                'isolir_address_list' => $p->isolir_address_list ?? 'ISOLIR_LIST',
                'auto_isolir' => (bool) $p->auto_isolir,
                'use_ppn' => (bool) $p->use_ppn,
                'use_uso' => (bool) $p->use_uso,
                'use_night_speed' => (bool) $p->use_night_speed,
                'use_fup' => (bool) $p->use_fup,
            ]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'host' => $r->host]),
            'profiles' => $profiles,
            'mikrotikConnected' => $mikrotikConnected,
            'mikrotikError' => $mikrotikError,
            'isolir' => $isolir->toArray(),
        ]);
    }

    public function addPackage(Request $request)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();

        // Sanitasi harga: hapus titik (ribuan), ubah koma ke titik (desimal)
        if ($request->has('price')) {
            $request->merge(['price' => $this->sanitizePrice($request->price)]);
        }
        if ($request->has('promo_price')) {
            $request->merge(['promo_price' => $this->sanitizePrice($request->promo_price)]);
        }
        if ($request->has('admin_fee')) {
            $request->merge(['admin_fee' => $this->sanitizePrice($request->admin_fee)]);
        }
        if ($request->has('late_fee')) {
            $request->merge(['late_fee' => $this->sanitizePrice($request->late_fee)]);
        }
        if ($request->has('materai')) {
            $request->merge(['materai' => $this->sanitizePrice($request->materai)]);
        }

        if (!$request->filled('profile_isolir')) {
            $request->merge(['profile_isolir' => 'isolir']);
        }
        if (!$request->filled('profile_normal')) {
            $request->merge(['profile_normal' => $request->input('name', 'default')]);
        }

        $validated = $request->validate([
            'name' => 'required|min:2|max:100|unique:packages,name,NULL,id,tenant_id,' . ($tenantId ?? 'NULL') . ',router_id,' . ($request->router_id ?? 'NULL'),
            'price' => 'required|numeric|gte:0',
            'profile_normal' => ['nullable', 'max:50'],
            'profile_isolir' => ['nullable', 'max:50'],
        ]);

        $type = $request->input('type', 'pppoe');
        if ($type === 'subscription') {
            $type = 'pppoe';
        }

        $this->ensureIsolirAddressListColumn();

        $packageData = array_merge(
            $request->only('name', 'price', 'router_id', 'profile_normal', 'profile_isolir', 'description',
                'promo_price', 'promo_cycles', 'prorate_first_invoice',
                'use_ppn', 'ppn_percentage', 'use_uso', 'uso_percentage',
                'admin_fee', 'late_fee', 'materai', 'isolir_interval_months',
                'night_profile_name', 'fup_limit_gb', 'fup_profile_name'),
            [
                'type' => $type,
                'tenant_id' => $tenantId,
                'auto_isolir' => $request->boolean('auto_isolir', true),
                'use_night_speed' => $request->boolean('use_night_speed', false),
                'use_fup' => $request->boolean('use_fup', false),
            ]
        );

        if (Schema::hasColumn('packages', 'isolir_address_list')) {
            $packageData['isolir_address_list'] = $request->input('isolir_address_list', 'ISOLIR_LIST') ?: 'ISOLIR_LIST';
        }

        Package::create($packageData);

        return redirect()->to('/admin/billing/packages')->with('msg', ' Paket berhasil ditambahkan');
    }

    private function ensureIsolirAddressListColumn(): void
    {
        try {
            if (!Schema::hasColumn('packages', 'isolir_address_list')) {
                Schema::table('packages', function (Blueprint $table) {
                    $table->string('isolir_address_list', 50)->nullable()->default('ISOLIR_LIST')->after('profile_isolir');
                });
            }
        } catch (\Throwable $e) {
            // Silently catch in case table cannot be altered or DB permissions are restricted
        }
    }

    public function updatePackage(Request $request, $id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();

        // Sanitasi harga
        if ($request->has('price')) {
            $request->merge(['price' => $this->sanitizePrice($request->price)]);
        }
        if ($request->has('promo_price')) {
            $request->merge(['promo_price' => $this->sanitizePrice($request->promo_price)]);
        }
        if ($request->has('admin_fee')) {
            $request->merge(['admin_fee' => $this->sanitizePrice($request->admin_fee)]);
        }
        if ($request->has('late_fee')) {
            $request->merge(['late_fee' => $this->sanitizePrice($request->late_fee)]);
        }
        if ($request->has('materai')) {
            $request->merge(['materai' => $this->sanitizePrice($request->materai)]);
        }

        if (!$request->filled('profile_isolir')) {
            $request->merge(['profile_isolir' => 'isolir']);
        }
        if (!$request->filled('profile_normal')) {
            $request->merge(['profile_normal' => $request->input('name', 'default')]);
        }

        $validated = $request->validate([
            'name' => 'required|min:2|max:100|unique:packages,name,' . $id . ',id,tenant_id,' . ($tenantId ?? 'NULL') . ',router_id,' . ($request->router_id ?? 'NULL'),
            'price' => 'required|numeric|gte:0',
            'profile_normal' => ['nullable', 'max:50'],
            'profile_isolir' => ['nullable', 'max:50'],
        ]);

        $old = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($id);

        if (!$old) {
            return redirect()->to('/admin/billing/packages')->with('error', ' Paket tidak ditemukan');
        }

        $this->ensureIsolirAddressListColumn();

        $updateData = array_merge(
            $request->only(
                'name', 'price', 'router_id', 'profile_normal', 'profile_isolir', 'description',
                'promo_price', 'promo_cycles', 'prorate_first_invoice',
                'use_ppn', 'ppn_percentage', 'use_uso', 'uso_percentage',
                'admin_fee', 'late_fee', 'materai', 'isolir_interval_months',
                'night_profile_name', 'fup_limit_gb', 'fup_profile_name'
            ),
            [
                'auto_isolir' => $request->boolean('auto_isolir', true),
                'use_night_speed' => $request->boolean('use_night_speed', false),
                'use_fup' => $request->boolean('use_fup', false),
            ]
        );

        if (Schema::hasColumn('packages', 'isolir_address_list')) {
            $updateData['isolir_address_list'] = $request->input('isolir_address_list', 'ISOLIR_LIST') ?: 'ISOLIR_LIST';
        }

        Package::where('id', $id)
            ->where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->update($updateData);

        // Sync ke MikroTik kalo profile berubah
        if ($old && $old->profile_normal !== $request->profile_normal) {
            $customers = \App\Models\Customer::with('router')
                ->where('package_id', $id)
                ->get();

            $synced = 0;
            $failed = 0;
            foreach ($customers as $c) {
                try {
                    if ($c->router && $c->router->is_active) {
                        $mik = new \App\Services\MikrotikService([
                            'host' => $c->router->host,
                            'user' => $c->router->username,
                            'pass' => $c->router->password ?? '',
                            'port' => (int) $c->router->port,
                        ]);
                        if ($mik->isConnected()) {
                            if ($c->connection_type === 'static' || $c->connection_type === 'arp' || empty($c->pppoe_username) || str_starts_with($c->pppoe_username, 'arp_')) {
                                if (!empty($c->ip_address)) {
                                    $normRate = $this->_normalizeRateLimit($request->profile_normal);
                                    $mik->setSimpleQueueRate($c->ip_address, !empty($normRate) ? $normRate : $request->profile_normal);
                                    $synced++;
                                }
                            } else {
                                $mik->setPppoeUserProfile($c->pppoe_username, $request->profile_normal);
                                $mik->kickPppoeUser($c->pppoe_username);
                                $synced++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $failed++;
                    \Illuminate\Support\Facades\Log::error("Gagal sync package ke MikroTik untuk {$c->name}: {$e->getMessage()}");
                }
            }

            $msg = " Paket berhasil diperbarui. {$synced} pelanggan disinkronkan ke MikroTik";
            if ($failed > 0) $msg .= ", {$failed} gagal";
            return redirect()->to('/admin/billing/packages')->with('msg', $msg);
        }

        return redirect()->to('/admin/billing/packages')->with('msg', ' Paket berhasil diperbarui');
    }

    public function deletePackage(Request $request, $id)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $customerCount = Customer::where('package_id', $id)->count();
        if ($customerCount > 0) {
            return redirect()->to('/admin/billing/packages')->with('error', " Tidak dapat menghapus paket yang masih memiliki {$customerCount} pelanggan");
        }

        $deleteRouter = $request->get('delete_router') === '1' || $request->boolean('delete_router') || $request->boolean('delete_mikrotik') || $request->get('delete_mikrotik') === '1';
        $package = Package::with('router')->where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($id);

        if (!$package) {
            return redirect()->to('/admin/billing/packages')->with('error', ' Paket tidak ditemukan');
        }

        if ($deleteRouter && $package->profile_normal) {
            try {
                // Koneksi ke ROUTER PAKET (bukan config global per-tenant)
                $router = $package->router;
                if ($router && $router->is_active) {
                    $mik = new \App\Services\MikrotikService([
                        'host' => $router->host,
                        'user' => $router->username,
                        'pass' => $router->password ?? '',
                        'port' => (int) $router->port,
                    ]);
                    if ($mik->isConnected()) {
                        if ($mik->deletePppoeProfile($package->profile_normal)) {
                            session()->flash('msg', ' Paket berhasil dihapus dari database dan profile MikroTik "' . $package->profile_normal . '" juga dihapus');
                        } else {
                            session()->flash('warning', ' Paket dihapus dari database, tapi gagal hapus profile dari MikroTik: ' . $mik->getLastError());
                        }
                    } else {
                        session()->flash('warning', ' Paket dihapus dari database, tapi gagal terhubung ke router ' . $router->name . ': ' . $mik->getLastError());
                    }
                }
            } catch (\Exception $e) {
                session()->flash('warning', ' Paket dihapus dari database, tapi gagal hapus profile dari MikroTik: ' . $e->getMessage());
            }
        }

        $package->delete();

        if (!$deleteRouter) {
            session()->flash('msg', ' Paket berhasil dihapus dari database. Profile MikroTik tidak dihapus.');
        }

        return redirect()->to('/admin/billing/packages');
    }

    public function deletePackageBatch(Request $request)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $rawIds = $request->input('ids', []);
        if (empty($rawIds)) {
            return redirect()->to('/admin/billing/packages')->with('error', ' Tidak ada paket yang dipilih.');
        }

        if (is_string($rawIds)) {
            $ids = array_map('intval', explode(',', $rawIds));
        } elseif (is_array($rawIds)) {
            $ids = array_map('intval', $rawIds);
        } else {
            $ids = [(int) $rawIds];
        }

        $ids = array_filter($ids, fn($id) => $id > 0);
        if (empty($ids)) {
            return redirect()->to('/admin/billing/packages')->with('error', ' Tidak ada paket yang dipilih.');
        }

        $deleteRouter = $request->get('delete_router') === '1' || $request->boolean('delete_router') || $request->boolean('delete_mikrotik') || $request->get('delete_mikrotik') === '1';
        $deleted = 0;
        $skipped = 0;
        $routerDeleted = 0;
        $routerErrors = [];

        foreach ($ids as $id) {
            $package = Package::with('router')->where('type', '!=', 'subscription')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->find($id);

            if (!$package) {
                continue;
            }

            // Check if any customers are still using this package
            $customerCount = Customer::where('package_id', $id)->count();
            if ($customerCount > 0) {
                $skipped++;
                continue;
            }

            // Optionally delete profile from MikroTik
            if ($deleteRouter && $package->profile_normal) {
                try {
                    $router = $package->router;
                    if ($router && $router->is_active) {
                        $mik = new \App\Services\MikrotikService([
                            'host' => $router->host,
                            'user' => $router->username,
                            'pass' => $router->password ?? '',
                            'port' => (int) $router->port,
                        ]);
                        if ($mik->isConnected()) {
                            if ($mik->deletePppoeProfile($package->profile_normal)) {
                                $routerDeleted++;
                            }
                        } else {
                            $routerErrors[] = $package->name . ' (Router tidak terhubung)';
                        }
                    }
                } catch (\Exception $e) {
                    $routerErrors[] = $package->name . ' (' . $e->getMessage() . ')';
                }
            }

            $package->delete();
            $deleted++;
        }

        $parts = [];
        if ($deleted > 0) {
            $parts[] = " {$deleted} paket berhasil dihapus";
        }
        if ($skipped > 0) {
            $parts[] = " {$skipped} paket dilewati (masih memiliki pelanggan)";
        }
        if ($deleteRouter && $routerDeleted > 0) {
            $parts[] = " {$routerDeleted} profile MikroTik dihapus";
        }
        if (!empty($routerErrors)) {
            $parts[] = " Gagal hapus profile MikroTik: " . implode(', ', $routerErrors);
        }

        $msg = implode('. ', $parts);
        return redirect()->to('/admin/billing/packages')->with('msg', $msg);
    }

    public function deleteAllPackages(Request $request)
    {
        $types = $request->input('types', []);
        $deleteMikrotik = $request->boolean('delete_mikrotik', false);

        if (!is_array($types) || empty($types)) {
            return redirect()->back()->with('error', 'Pilih minimal satu kategori paket yang ingin dihapus.');
        }

        $tenantId = \App\Models\Scopes\TenantScope::rawTenantId() ?? session('tenant_id');

        $query = Package::withoutGlobalScopes()
            ->where('type', '!=', 'subscription')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNotNull('tenant_id'));

        $query->where(function ($q) use ($types) {
            if (in_array('hotspot', $types)) {
                $q->orWhere('type', 'hotspot')
                  ->orWhere('name', 'like', 'Hotspot%')
                  ->orWhere('name', 'like', 'hotspot%');
            }
            if (in_array('unused', $types)) {
                $q->orWhereDoesntHave('customers');
            }
            if (in_array('all', $types)) {
                $q->orWhereRaw('1=1');
            }
        });

        $packages = $query->with('router')->get();
        $total = $packages->count();

        if ($total === 0) {
            return redirect()->back()->with('msg', 'Tidak ada paket yang cocok dengan kriteria yang dipilih.');
        }

        $deletedCount = 0;
        foreach ($packages as $pkg) {
            if ($deleteMikrotik) {
                try {
                    $router = $pkg->router ?: \App\Models\Mikrotik::withoutGlobalScopes()->find($pkg->router_id);
                    if ($router && $router->is_active) {
                        $mik = new \App\Services\MikrotikService([
                            'host' => $router->host,
                            'user' => $router->username,
                            'pass' => $router->password ?? '',
                            'port' => (int) ($router->port ?: 8728),
                        ]);
                        if ($mik->isConnected()) {
                            if ($pkg->type === 'hotspot' || str_starts_with(strtolower($pkg->name), 'hotspot')) {
                                $cleanProfile = preg_replace('/^hotspot\s+/i', '', $pkg->name);
                                $mik->deleteHotspotProfile($cleanProfile);
                            } else {
                                $mik->deletePppoeProfile($pkg->profile_normal);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Gagal hapus profile MikroTik untuk paket #{$pkg->id}: {$e->getMessage()}");
                }
            }

            $pkg->delete();
            $deletedCount++;
        }

        return redirect()->back()->with('msg', "Berhasil menghapus <strong>{$deletedCount}</strong> paket.");
    }

    // ========== CUSTOMERS ==========

    /**
     * Toggle auto isolir paket (ON/OFF dari card paket).
     */
    public function toggleAutoIsolir(Request $request, $id)
    {
        $pkg = Package::findOrFail($id);
        $pkg->update(['auto_isolir' => $request->boolean('auto_isolir')]);

        return back();
    }

    public function syncPackages(Request $request)
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $routerId = $request->router_id;
        $syncType = $request->input('sync_type', 'all'); // 'all', 'pppoe', 'hotspot', 'queue', 'static'
        $router = \App\Models\Mikrotik::find($routerId);

        if (!$router || !$router->is_active) {
            return redirect()->to('/admin/billing/packages')
                ->with('error', 'Router tidak ditemukan atau tidak aktif.');
        }

        try {
            $mik = new \App\Services\MikrotikService($router);
            if (!$mik->isConnected()) {
                return redirect()->to('/admin/billing/packages')
                    ->with('error', 'Tidak dapat terhubung ke ' . $router->name . ': ' . $mik->getLastError());
            }

            $count = 0;
            $queueGroupsCount = 0;
            $totalQueuesFound = 0;

            // 1. Sync PPPoE Profiles (/ppp/profile/print)
            if (in_array($syncType, ['all', 'pppoe'])) {
                $profiles = $mik->getPppoeProfiles();
                $ignoredProfiles = ['default-encryption'];
                foreach ($profiles as $profile) {
                    $profileName = is_array($profile) ? ($profile['name'] ?? '') : (is_object($profile) ? ($profile->name ?? '') : (string)$profile);
                    if (empty($profileName)) continue;
                    if (in_array(strtolower($profileName), $ignoredProfiles)) continue;

                    $existing = Package::where('name', $profileName)
                        ->where('type', '!=', 'subscription')
                        ->where('tenant_id', $tenantId)
                        ->where('router_id', $routerId)
                        ->first();

                    Package::updateOrCreate(
                        ['name' => $profileName, 'tenant_id' => $tenantId, 'router_id' => $routerId],
                        [
                            'type' => 'pppoe',
                            'price' => $existing ? $existing->price : 0,
                            'router_id' => $routerId,
                            'profile_normal' => $existing && $existing->profile_normal ? $existing->profile_normal : $profileName,
                            'profile_isolir' => $existing && $existing->profile_isolir ? $existing->profile_isolir : 'isolir',
                            'tenant_id' => $tenantId,
                        ]
                    );

                    $count++;
                }
            }

            // 2. Sync Simple Queues (/queue/simple/print) Grouped by Speed / Rate Limit
            if (in_array($syncType, ['all', 'queue', 'static'])) {
                try {
                    $queues = $mik->query('/queue/simple/print');
                    $rateGroups = [];

                    foreach ($queues as $q) {
                        $qName = $q['name'] ?? '';
                        $dynamic = $q['dynamic'] ?? 'false';
                        $invalid = $q['invalid'] ?? 'false';

                        if (empty($qName)) continue;
                        if ($dynamic === 'true' || $invalid === 'true') continue;
                        if (str_starts_with($qName, '<') || str_starts_with($qName, '*') || str_starts_with(strtolower($qName), 'hs-') || str_starts_with(strtolower($qName), 'hotspot')) continue;

                        $isVoucher = \DB::table('vouchers')
                            ->where('username', $qName)
                            ->when($tenantId, fn ($vq) => $vq->where('tenant_id', $tenantId))
                            ->exists();
                        if ($isVoucher) continue;

                        $isVoucherPkg = \DB::table('voucher_packages')
                            ->where('name', $qName)
                            ->when($tenantId, fn ($vq) => $vq->where('tenant_id', $tenantId))
                            ->exists();
                        if ($isVoucherPkg) continue;

                        $totalQueuesFound++;

                        // Parse rate limit / max-limit (upload/download)
                        $maxLimit = $q['max-limit'] ?? ($q['rate-limit'] ?? '');
                        $normRate = $this->_normalizeRateLimit($maxLimit);
                        if ($normRate === '0/0') {
                            if (preg_match('/(\d+)\s*(m|mbps|mb|k|kbps|g|gbps)/i', $qName, $m)) {
                                $normRate = $this->_normalizeRateLimit($m[0]);
                            } else {
                                $normRate = '10M/10M';
                            }
                        }

                        if (!isset($rateGroups[$normRate])) {
                            $rateGroups[$normRate] = [
                                'count' => 0,
                                'queues' => [],
                            ];
                        }
                        $rateGroups[$normRate]['count']++;
                        $rateGroups[$normRate]['queues'][] = $qName;
                    }

                    foreach ($rateGroups as $speed => $groupInfo) {
                        $pkgName = "Paket Static {$speed}";

                        // Cek apakah sudah ada paket static untuk router ini dengan profile_normal atau nama serupa
                        $existing = Package::where('tenant_id', $tenantId)
                            ->where('router_id', $routerId)
                            ->where(function ($q) use ($speed, $pkgName) {
                                $q->where('profile_normal', $speed)
                                  ->orWhere('name', $pkgName);
                            })
                            ->first();

                        if ($existing) {
                            // Jangan timpa harga & nama kustom yang sudah diatur tenant admin
                            $existing->update([
                                'type' => 'static_ip',
                                'profile_normal' => $speed,
                                'is_active' => true,
                            ]);
                        } else {
                            Package::create([
                                'name' => $pkgName,
                                'type' => 'static_ip',
                                'price' => 0,
                                'profile_normal' => $speed,
                                'profile_isolir' => '128k/128k',
                                'auto_isolir' => true,
                                'is_active' => true,
                                'router_id' => $routerId,
                                'tenant_id' => $tenantId,
                                'description' => "Dikelompokkan otomatis dari {$groupInfo['count']} Simple Queue di router {$router->name}.",
                            ]);
                        }
                        $count++;
                        $queueGroupsCount++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Sync simple queues error: ' . $e->getMessage());
                }
            }

            $detailMsg = $queueGroupsCount > 0
                ? " ({$queueGroupsCount} kelompok paket Static IP dari {$totalQueuesFound} Simple Queue)"
                : "";

            return redirect()->to('/admin/billing/packages')
                ->with('msg', "Sinkronisasi selesai: {$count} profil paket dari {$router->name} berhasil disinkronkan{$detailMsg}.");
        } catch (\Exception $e) {
            return redirect()->to('/admin/billing/packages')
                ->with('error', 'Gagal sinkronisasi: ' . $e->getMessage());
        }
    }

    public function getRouterProfiles($routerId)
    {
        $router = \App\Models\Mikrotik::find($routerId);
        if (!$router || !$router->is_active) {
            return response()->json(['profiles' => [], 'hotspot_profiles' => [], 'queue_profiles' => [], 'error' => 'Router tidak ditemukan atau tidak aktif']);
        }

        try {
            $mik = new \App\Services\MikrotikService($router);
            if (!$mik->isConnected()) {
                return response()->json(['profiles' => [], 'hotspot_profiles' => [], 'queue_profiles' => [], 'error' => $mik->getLastError()]);
            }

            $pppoeProfiles = $mik->getPppoeProfiles();
            $hotspotProfiles = $mik->getHotspotProfiles();

            $formattedPpp = collect($pppoeProfiles)->map(function ($p) {
                return [
                    'name' => $p['name'] ?? '',
                    'local_address' => $p['local-address'] ?? '',
                    'remote_address' => $p['remote-address'] ?? '',
                    'rate_limit' => $p['rate-limit'] ?? '',
                ];
            })->filter(fn($p) => !empty($p['name']))->values();

            $formattedHotspot = collect($hotspotProfiles)->map(function ($p) {
                return [
                    'name' => $p['name'] ?? '',
                    'rate_limit' => $p['rate-limit'] ?? '',
                ];
            })->filter(fn($p) => !empty($p['name']))->values();

            // Queue profiles grouped by normalized max-limit
            $queueProfiles = [];
            try {
                $queues = $mik->query('/queue/simple/print');
                $rates = [];
                foreach ($queues as $q) {
                    $dynamic = $q['dynamic'] ?? 'false';
                    $invalid = $q['invalid'] ?? 'false';
                    if ($dynamic === 'true' || $invalid === 'true') continue;
                    $rawLimit = $q['max-limit'] ?? ($q['rate-limit'] ?? '');
                    $normRate = $this->_normalizeRateLimit($rawLimit);
                    if ($normRate !== '0/0' && !isset($rates[$normRate])) {
                        $rates[$normRate] = true;
                        $queueProfiles[] = [
                            'name' => "Paket Static {$normRate}",
                            'rate_limit' => $normRate,
                        ];
                    }
                }
            } catch (\Throwable $e) {}

            // Address Lists from Firewall
            $addressLists = [];
            try {
                $rawLists = $mik->query('/ip/firewall/address-list/print');
                $seenLists = [];
                foreach ($rawLists as $al) {
                    $lName = trim($al['list'] ?? '');
                    if (!empty($lName) && !isset($seenLists[$lName])) {
                        $seenLists[$lName] = true;
                        $addressLists[] = $lName;
                    }
                }
            } catch (\Throwable $e) {}

            return response()->json([
                'profiles' => $formattedPpp,
                'hotspot_profiles' => $formattedHotspot,
                'queue_profiles' => $queueProfiles,
                'address_lists' => $addressLists,
            ]);
        } catch (\Exception $e) {
            return response()->json(['profiles' => [], 'hotspot_profiles' => [], 'queue_profiles' => [], 'address_lists' => [], 'error' => $e->getMessage()]);
        }
    }

    // ========== CUSTOMERS ==========
    public function customers()
    {
        $tenantId = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId();
        $hasOdpCol = \Illuminate\Support\Facades\Cache::rememberForever('schema_has_col_customers_odp_id', fn() => \Illuminate\Support\Facades\Schema::hasColumn('customers', 'odp_id'));

        $customerQuery = Customer::select([
            'id', 'name', 'code', 'connection_type', 'pppoe_username', 'ip_address',
            'mac_address', 'arp_interface', 'auto_arp', 'phone', 'email', 'address',
            'status', 'isolation_date', 'lat', 'lng', 'package_id', 'odp_id', 'odp_port',
            'router_id', 'tenant_id', 'created_at'
        ])
        ->with(['package:id,name,price', 'router:id,name,host']);
        if ($hasOdpCol) {
            $customerQuery->with('odp:id,name,capacity');
        }
        $customers = $customerQuery
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->sort(fn ($a, $b) => strnatcasecmp($a->name ?? '', $b->name ?? ''))
            ->values();

        // Calculate actual unpaid invoice count & period count (taking multi-period breakdown into account)
        $unpaidInvoices = Invoice::where('paid', 0)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'customer_id', 'amount', 'periods_breakdown', 'status']);

        $customerUnpaidMap = [];
        foreach ($unpaidInvoices as $inv) {
            $cId = $inv->customer_id;
            if (!$cId) continue;

            $periodCount = 1;
            if (!empty($inv->periods_breakdown)) {
                $bd = is_array($inv->periods_breakdown) ? $inv->periods_breakdown : json_decode($inv->periods_breakdown, true);
                if (is_array($bd) && count($bd) > 0) {
                    $periodCount = count($bd);
                }
            }

            if (!isset($customerUnpaidMap[$cId])) {
                $customerUnpaidMap[$cId] = [
                    'unpaid_invoices' => 0,
                    'unpaid_periods' => 0,
                    'unpaid_amount' => 0,
                ];
            }

            $customerUnpaidMap[$cId]['unpaid_invoices'] += 1;
            $customerUnpaidMap[$cId]['unpaid_periods'] += $periodCount;
            $customerUnpaidMap[$cId]['unpaid_amount'] += (float) $inv->amount;
        }

        $packages = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->get();

        $routers = \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)->get();

        $odpQuery = \App\Models\OdpLocation::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        if ($hasOdpCol) {
            $odpQuery->withCount('customers');
        }
        $odps = $odpQuery->orderBy('name')->get();
        $technicians = User::whereIn('role', ['technician', 'teknisi'])
            ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
            ->get();

        $interfaces = [];
        $firstRouter = $routers->first();
        if ($firstRouter) {
            $cacheKey = "arp_data_{$firstRouter->id}";
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (!empty($cached['interfaces']) && is_array($cached['interfaces'])) {
                $interfaces = $cached['interfaces'];
            }
        }

        return Inertia::render('Admin/Customers', [
            'create' => (bool) request('create'),
            'customers' => $customers->map(function ($c) use ($customerUnpaidMap) {
                $uStat = $customerUnpaidMap[$c->id] ?? null;
                $unpaidPeriods = $uStat ? (int) $uStat['unpaid_periods'] : 0;
                $unpaidAmount = $uStat ? (float) $uStat['unpaid_amount'] : 0;
                return [
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
                    'unpaid_invoices' => $unpaidPeriods,
                    'unpaid_periods' => $unpaidPeriods,
                    'unpaid_amount' => $unpaidAmount,
                ];
            }),
            'packages' => $packages->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) ($p->price ?? 0)]),
            'routers' => $routers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'host' => $r->host]),
            'interfaces' => $interfaces,
            'odps' => $odps->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'capacity' => (int) ($o->capacity ?? 8),
                'used_ports' => (int) ($o->customers_count ?? 0),
                'available_ports' => max(0, (int) ($o->capacity ?? 8) - (int) ($o->customers_count ?? 0)),
            ]),
            'technicians' => $technicians->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    public function addCustomer(Request $request)
    {
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
        ];

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $routerId = $request->input('router_id');

        if ($connectionType === 'static') {
            $rules['pppoe_username'] = 'nullable|string|max:50';
            $rules['ip_address'] = 'required|string|max:45';
        } else {
            $rules['pppoe_username'] = [
                'required',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9@._-]+$/',
                \Illuminate\Validation\Rule::unique('customers', 'pppoe_username')->where(function ($q) use ($tenantId, $routerId) {
                    return $q->when($tenantId, fn ($sq) => $sq->where('tenant_id', $tenantId))
                             ->when($routerId, fn ($sq) => $sq->where('router_id', $routerId));
                }),
            ];
        }

        $validated = $request->validate($rules);

        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant && $tenant->max_customers > 0) {
                $currentCount = Customer::where('tenant_id', $tenantId)->count();
                if ($currentCount >= $tenant->max_customers) {
                    return redirect()->back()->withInput()->with('error', "Kapasitas pelanggan telah mencapai batas maksimal paket Anda ({$tenant->max_customers} pelanggan pada {$tenant->package_name}). Silakan hubungi Superadmin untuk upgrade paket!");
                }
            }
        }

        $data = $request->only('name', 'phone', 'package_id', 'odp_id', 'odp_port', 'isolation_date', 'lat', 'lng', 'address', 'email', 'router_id', 'ip_address', 'mac_address', 'arp_interface');
        if (empty($data['router_id'])) $data['router_id'] = null;
        if (empty($data['odp_id'])) $data['odp_id'] = null;
        if (empty($data['odp_port'])) $data['odp_port'] = null;
        if (empty($data['phone'])) $data['phone'] = null;
        if (empty($data['email'])) $data['email'] = null;
        if (empty($data['address'])) $data['address'] = null;
        if (empty($data['mac_address'])) $data['mac_address'] = null;
        if (empty($data['ip_address'])) $data['ip_address'] = null;
        $data['connection_type'] = $connectionType;
        $data['auto_arp'] = $request->boolean('create_arp');
        $data['status'] = 'active';
        $data['tenant_id'] = session('tenant_id');

        if (isset($data['lat']) && is_string($data['lat'])) {
            $data['lat'] = str_replace(',', '.', trim($data['lat']));
        }
        if (isset($data['lng']) && is_string($data['lng'])) {
            $data['lng'] = str_replace(',', '.', trim($data['lng']));
        }

        // Pastikan lat/lng bernilai null jika kosong / 0 (jangan buat koordinat sintetis)
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

        if (!\App\Services\LicenseService::canAddCustomer()) {
            return redirect()->back()->withInput()->with('error', 'Batas kuota Community Edition tercapai (Maksimal ' . \App\Services\LicenseService::MAX_FREE_CUSTOMERS . ' Pelanggan). Silakan aktivasi lisensi Pro untuk kapasitas Unlimited.');
        }

        $package = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($request->package_id);
        if (!$package) {
            return redirect()->back()->withInput()->with('error', ' Paket tidak ditemukan');
        }

        // Cegah duplikasi port ODP untuk pelanggan berbeda pada ODP yang sama
        if (!empty($data['odp_id']) && !empty($data['odp_port'])) {
            $existingPortCust = Customer::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('odp_id', $data['odp_id'])
                ->where('odp_port', $data['odp_port'])
                ->first();

            if ($existingPortCust) {
                return redirect()->back()->withInput()->with('error', "Port {$data['odp_port']} pada ODP yang dipilih sudah digunakan oleh pelanggan '{$existingPortCust->name}'. Silakan pilih nomor port lain.");
            }
        }

        $mikrotikSuccess = false;
        $mikrotikError = '';
        $mikrotikSkipped = false;

        $createPppoe = $request->create_pppoe === '1' || $request->create_pppoe === true;
        $createArp = $request->create_arp === '1' || $request->create_arp === true;
        $data['pppoe_password'] = $request->input('pppoe_password') ?: '123456';
        $pppoePassword = $data['pppoe_password'];

        $isStatic = in_array(strtolower((string)$connectionType), ['static', 'arp', 'static_ip', 'ip_static', 'ip_statis'], true)
            || (!empty($data['ip_address']) && (empty($data['pppoe_username']) || str_starts_with((string)$data['pppoe_username'], 'static_') || str_starts_with((string)$data['pppoe_username'], 'arp_')));

        $router = \App\Models\Mikrotik::find($data['router_id'] ?? null);

        if ($isStatic && ($createArp || !empty($data['ip_address'])) && $router) {
            $mik = new \App\Services\MikrotikService([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '',
                'port' => (int) $router->port,
            ]);
            if ($mik->isConnected()) {
                try {
                    $interface = $data['arp_interface'] ?: 'bridge';
                    $mac = $data['mac_address'] ?: '00:00:00:00:00:00';
                    
                    $arpRes = true;
                    if ($createArp || !empty($data['mac_address'])) {
                        $arpRes = $mik->addArpEntry($data['ip_address'], $mac, $interface, "NODERA - {$data['name']}");
                    }
                    
                    // Also create Simple Queue for static rate limiting
                    $maxLimit = $package->profile_normal ?? '10M/10M';
                    $queueRes = $mik->addSimpleQueue("STATIC - {$data['name']}", $data['ip_address'], $maxLimit, "NODERA Static IP");

                    if ($arpRes && $queueRes) {
                        $mikrotikSuccess = true;
                    } elseif ($arpRes || $queueRes) {
                        $mikrotikSuccess = true;
                    } else {
                        $mikrotikError = $mik->getLastError() ?: 'Gagal menambahkan entri ARP / Simple Queue di MikroTik';
                    }
                } catch (\Exception $e) {
                    $mikrotikError = $e->getMessage();
                }
            } else {
                $mikrotikError = 'Tidak dapat terhubung ke router ' . $router->name . ': ' . $mik->getLastError();
            }
        } elseif ($connectionType === 'pppoe' && $createPppoe) {
            if ($router) {
                $mik = new \App\Services\MikrotikService([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '',
                    'port' => (int) $router->port,
                ]);
                if ($mik->isConnected()) {
                    try {
                        $result = $mik->addPppoeSecret($data['pppoe_username'], $pppoePassword, $package->profile_normal ?? 'default');
                        if ($result) {
                            $mikrotikSuccess = true;
                        } else {
                            $mikrotikError = $mik->getLastError();
                        }
                    } catch (\Exception $e) {
                        $mikrotikError = $e->getMessage();
                    }
                } else {
                    $mikrotikError = 'Tidak dapat terhubung ke router ' . $router->name . ': ' . $mik->getLastError();
                }
            } else {
                $mikrotikError = 'Router tidak ditemukan — pilih router dulu.';
            }
        } else {
            $mikrotikSkipped = true;
        }

        $customer = Customer::create($data);

        // Sync with RADIUS if tenant has RADIUS active and auto_sync enabled
        try {
            $radiusService = app(\App\Services\RadiusService::class);
            $radSetting = $radiusService->getSetting($customer->tenant_id);
            if ($radSetting && $radSetting->is_active && $radSetting->auto_sync_on_create) {
                $radiusService->syncCustomer($customer, $package);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[BillingController] RADIUS sync on create error: " . $e->getMessage());
        }

        if (!empty($data['lat']) && !empty($data['lng'])) {
            DB::table('onu_locations')->insert([
                'name' => $data['name'],
                'serial_number' => ($data['pppoe_username'] ?? $customer->id) . '-ONU',
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'customer_id' => $customer->id,
                'tenant_id' => session('tenant_id'),
                'created_at' => now(),
            ]);
        }

        if ($connectionType === 'radius') {
            session()->flash('msg', " Pelanggan berhasil ditambahkan & disinkronkan ke FreeRADIUS AAA Server (User: '<strong>{$data['pppoe_username']}</strong>').");
        } elseif ($mikrotikSkipped) {
            session()->flash('msg', " Pelanggan berhasil ditambahkan.");
        } elseif ($mikrotikSuccess) {
            if ($isStatic) {
                session()->flash('msg', " Pelanggan Static IP berhasil ditambahkan & disinkronkan ke ARP & Simple Queue MikroTik (IP: {$data['ip_address']}).");
            } else {
                session()->flash('msg', " Pelanggan berhasil ditambahkan. PPPoE user '<strong>{$data['pppoe_username']}</strong>' dibuat di MikroTik.");
            }
        } else {
            session()->flash('warning', " Pelanggan tersimpan di database, TAPI gagal sync ke MikroTik: {$mikrotikError}.");
        }

        return redirect()->to('/admin/billing/customers');
    }

    public function getCustomer($id)
    {
        $tenantId = session('tenant_id');
        $customer = Customer::where('tenant_id', $tenantId)->find($id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found']);
        }
        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function editCustomer(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|min:3|max:100',
            'phone' => 'nullable|string|min:9|max:20',
            'package_id' => 'required|numeric',
            'odp_id' => 'nullable|numeric',
            'odp_port' => 'nullable|numeric',
            'isolation_date' => 'required|numeric|gt:0|lt:32',
            'status' => 'nullable|in:active,isolated,inactive',
            'connection_type' => 'nullable|in:pppoe,static,radius,hotspot',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:30',
            'arp_interface' => 'nullable|string|max:50',
            'router_id' => 'nullable|numeric',
        ]);

        $oldCustomer = Customer::with('package')->find($id);
        if (!$oldCustomer) {
            return redirect()->to('/admin/billing/customers')->with('error', ' Customer tidak ditemukan');
        }

        $tenantId = $oldCustomer->tenant_id ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $oldPackageId = $oldCustomer->package_id;
        $oldStatus = $oldCustomer->status;
        $oldUsername = $oldCustomer->pppoe_username;
        $data = $request->only('name', 'phone', 'package_id', 'odp_id', 'odp_port', 'isolation_date', 'lat', 'lng', 'address', 'email', 'status', 'connection_type', 'ip_address', 'mac_address', 'arp_interface', 'router_id', 'pppoe_password');

        if (empty($data['router_id'])) $data['router_id'] = null;
        if (empty($data['odp_id'])) $data['odp_id'] = null;
        if (empty($data['odp_port'])) $data['odp_port'] = null;
        if (empty($data['phone'])) $data['phone'] = null;
        if (empty($data['email'])) $data['email'] = null;
        if (empty($data['address'])) $data['address'] = null;
        if (empty($data['mac_address'])) $data['mac_address'] = null;
        if (empty($data['ip_address'])) $data['ip_address'] = null;

        $passwordChanged = false;
        if (!empty($request->pppoe_password)) {
            $data['pppoe_password'] = $request->pppoe_password;
            if ($data['pppoe_password'] !== $oldCustomer->pppoe_password) {
                $passwordChanged = true;
            }
        } else {
            unset($data['pppoe_password']);
        }

        if (isset($data['lat']) && is_string($data['lat'])) {
            $data['lat'] = str_replace(',', '.', trim($data['lat']));
        }
        if (isset($data['lng']) && is_string($data['lng'])) {
            $data['lng'] = str_replace(',', '.', trim($data['lng']));
        }

        // Koordinat latitude & longitude SELALU dikunci paten:
        // Jika request mengirimkan nilai kosong / 0 / tidak diset, pertahankan koordinat asli dari database
        if ((!isset($data['lat']) || $data['lat'] === '' || $data['lat'] === null || (float)$data['lat'] == 0 || !is_numeric($data['lat'])) && !empty($oldCustomer->lat)) {
            $data['lat'] = $oldCustomer->lat;
        }
        if ((!isset($data['lng']) || $data['lng'] === '' || $data['lng'] === null || (float)$data['lng'] == 0 || !is_numeric($data['lng'])) && !empty($oldCustomer->lng)) {
            $data['lng'] = $oldCustomer->lng;
        }

        // Jika form secara eksplisit mengosongkan dan memang belum ada koordinat lama, pastikan bernilai null
        if (empty($data['lat']) || (float)$data['lat'] == 0 || !is_numeric($data['lat'])) {
            $data['lat'] = !empty($oldCustomer->lat) ? (float) $oldCustomer->lat : null;
        } else {
            $data['lat'] = (float) $data['lat'];
        }
        if (empty($data['lng']) || (float)$data['lng'] == 0 || !is_numeric($data['lng'])) {
            $data['lng'] = !empty($oldCustomer->lng) ? (float) $oldCustomer->lng : null;
        } else {
            $data['lng'] = (float) $data['lng'];
        }

        $newUsername = $request->pppoe_username ?: null;
        $usernameChanged = false;
        if (!empty($newUsername)) {
            $data['pppoe_username'] = $newUsername;
            if ($newUsername !== $oldUsername) {
                $usernameChanged = true;
            }
        }

        if (empty($data['status'])) {
            unset($data['status']);
        }

        // Cegah duplikasi port ODP untuk pelanggan berbeda pada ODP yang sama
        if (!empty($data['odp_id']) && !empty($data['odp_port'])) {
            $existingPortCust = Customer::withoutGlobalScopes()
                ->when($tenantId, fn ($q) => $q->where(fn ($sq) => $sq->where('tenant_id', $tenantId)->orWhereNull('tenant_id')))
                ->where('odp_id', $data['odp_id'])
                ->where('odp_port', $data['odp_port'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingPortCust) {
                return redirect()->back()->withInput()->with('error', "Port {$data['odp_port']} pada ODP yang dipilih sudah digunakan oleh pelanggan '{$existingPortCust->name}'. Silakan pilih nomor port lain.");
            }
        }

        $packageChanged = isset($data['package_id']) && $data['package_id'] != $oldPackageId;

        $oldCustomer->update($data);
        $oldCustomer->refresh();

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $customerArr = $oldCustomer->toArray();
            $customerArr['profile_normal'] = $oldCustomer->package?->profile_normal ?? null;
            $customerArr['profile_isolir'] = $oldCustomer->package?->profile_isolir ?? null;

            if ($data['status'] === 'active') {
                $this->_unisolateCustomer($customerArr);
                session()->flash('msg', "Status pelanggan <strong>{$oldCustomer->name}</strong> diubah ke Aktif (Buka Isolir).");
            } elseif ($data['status'] === 'isolated') {
                $this->_isolateCustomer($customerArr);
                session()->flash('msg', "Status pelanggan <strong>{$oldCustomer->name}</strong> diubah ke Isolir.");
            }
        } else {
            $mikrotikUpdated = false;
            $effectiveUsername = $oldCustomer->pppoe_username ?: $oldUsername;
            if (!empty($effectiveUsername) && ($oldCustomer->connection_type ?? 'pppoe') === 'pppoe') {
                try {
                    $mik = $this->mikrotikForCustomer($oldCustomer->toArray());
                    if ($mik && $mik->isConnected()) {
                        $secretUpdate = [];
                        if ($passwordChanged && !empty($data['pppoe_password'])) {
                            $secretUpdate['password'] = $data['pppoe_password'];
                        }
                        if ($usernameChanged && !empty($newUsername)) {
                            $secretUpdate['name'] = $newUsername;
                        }
                        if ($packageChanged) {
                            $newPackage = Package::where('type', '!=', 'subscription')
                                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                                ->find($data['package_id']);
                            if ($newPackage) {
                                $targetProfile = ($oldCustomer->status === 'isolated' && !empty($newPackage->profile_isolir))
                                    ? $newPackage->profile_isolir
                                    : $newPackage->profile_normal;
                                if (!empty($targetProfile)) {
                                    $secretUpdate['profile'] = $targetProfile;
                                }
                            }
                        }

                        if (!empty($secretUpdate)) {
                            $mik->updatePppoeSecret($oldUsername, $secretUpdate);
                            $mik->kickPppoeUser($newUsername ?: $oldUsername);
                            $mikrotikUpdated = true;
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal update secret MikroTik: " . $e->getMessage());
                }
            } elseif (\App\Services\IsolationService::isStaticCustomer($oldCustomer) && !empty($oldCustomer->ip_address)) {
                try {
                    $mik = $this->mikrotikForCustomer($oldCustomer->toArray());
                    if ($mik && $mik->isConnected()) {
                        $newPackage = Package::where('type', '!=', 'subscription')
                            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                            ->find($oldCustomer->package_id);
                        $targetSpeed = ($oldCustomer->status === 'isolated')
                            ? ($newPackage?->profile_isolir ?? '128k/128k')
                            : ($newPackage?->profile_normal ?? '10M/10M');
                        if ($targetSpeed === 'isolir' || empty($targetSpeed)) $targetSpeed = '128k/128k';
                        $mik->addSimpleQueue("STATIC - {$oldCustomer->name}", $oldCustomer->ip_address, $targetSpeed, "NODERA Static IP");
                        if (!empty($oldCustomer->mac_address)) {
                            $interface = $oldCustomer->arp_interface ?: 'bridge';
                            $mik->addArpEntry($oldCustomer->ip_address, $oldCustomer->mac_address, $interface, "NODERA - {$oldCustomer->name}");
                        }
                        $mikrotikUpdated = true;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal update Static/ARP MikroTik: " . $e->getMessage());
                }
            }

            if ($mikrotikUpdated) {
                session()->flash('msg', ' Customer & akun MikroTik berhasil diupdate.');
            } else {
                session()->flash('msg', ' Customer berhasil diupdate');
            }
        }

        // Sync with RADIUS if tenant has RADIUS active and auto_sync enabled
        try {
            $radiusService = app(\App\Services\RadiusService::class);
            $radSetting = $radiusService->getSetting($oldCustomer->tenant_id);
            if ($radSetting && $radSetting->is_active && $radSetting->auto_sync_on_create) {
                $freshCustomer = Customer::find($id);
                if ($freshCustomer) {
                    $radiusService->syncCustomer($freshCustomer);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[BillingController] RADIUS sync on edit error: " . $e->getMessage());
        }

        return redirect()->to('/admin/billing/customers');
    }

    public function deleteCustomer(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return redirect()->to('/admin/billing/customers')->with('error', ' Customer tidak ditemukan');
        }

        $unpaidCount = Invoice::where('customer_id', $id)->where('paid', 0)->count();
        $force = $request->get('force') === '1';

        if ($unpaidCount > 0 && ! $force) {
            return redirect()->to('/admin/billing/customers')->with('error', "Customer masih memiliki {$unpaidCount} invoice belum lunas. Konfirmasi di dialog untuk melanjutkan penghapusan.");
        }

        $deletePppoe = $request->get('delete_pppoe') === '1';

        if ($deletePppoe) {
            $isStatic = \App\Services\IsolationService::isStaticCustomer($customer);
            try {
                // Koneksi ke ROUTER SI PELANGGAN. Pakai withoutGlobalScopes:
                // relasi $customer->router bisa NULL karena TenantScope
                // (router tenant_id null/mismatch → relation ke-filter).
                $router = \App\Models\Mikrotik::withoutGlobalScopes()->find($customer->router_id);
                if ($router) {
                    $mik = new \App\Services\MikrotikService([
                        'host' => $router->host,
                        'user' => $router->username,
                        'pass' => $router->password ?? '',
                        'port' => (int) $router->port,
                    ]);
                    if ($mik->isConnected()) {
                        if ($isStatic && !empty($customer->ip_address)) {
                            $mik->removeArpEntry($customer->ip_address);
                            $mik->removeSimpleQueue("STATIC - " . $customer->name);
                            $addrList = !empty($customer->package?->isolir_address_list) ? $customer->package->isolir_address_list : 'ISOLIR_LIST';
                            $mik->removeAddressList($addrList, $customer->ip_address);
                            if ($addrList !== 'ISOLIR_LIST') {
                                $mik->removeAddressList('ISOLIR_LIST', $customer->ip_address);
                            }
                            session()->flash('msg', ' Customer & entri ARP/Simple Queue "' . $customer->name . '" di MikroTik berhasil dihapus.');
                        } elseif (!empty($customer->pppoe_username)) {
                            $res = $mik->deletePppoeSecret($customer->pppoe_username);
                            $mik->kickPppoeUser($customer->pppoe_username);
                            if ($res) {
                                session()->flash('msg', ' Customer & PPPoE user "' . $customer->pppoe_username . '" di MikroTik berhasil dihapus.');
                            } else {
                                session()->flash('warning', ' Customer dihapus dari database, tapi PPPoE user "' . $customer->pppoe_username . '" tidak ditemukan di router.');
                            }
                        }
                    } else {
                        session()->flash('warning', ' Customer dihapus dari database, tapi gagal terhubung ke router ' . $router->name . ' (' . $router->host . ':' . ($router->port ?: 8728) . '): ' . $mik->getLastError());
                    }
                } else {
                    session()->flash('warning', ' Customer dihapus dari database, tapi router pelanggan (id ' . ($customer->router_id ?? '-') . ') tidak ditemukan.');
                }
            } catch (\Exception $e) {
                session()->flash('warning', ' Customer dihapus dari database, tapi gagal hapus dari MikroTik: ' . $e->getMessage());
            }
        }

        // Clean from RADIUS
        try {
            $radiusService = app(\App\Services\RadiusService::class);
            if (!empty($customer->pppoe_username)) {
                $radiusService->removeCustomer($customer->pppoe_username, $customer->tenant_id);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[BillingController] RADIUS remove on delete error: " . $e->getMessage());
        }

        if ($customer->router_id) {
            \Illuminate\Support\Facades\Cache::forget("pppoe_router_cache_{$customer->router_id}");
        }
        \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($customer->tenant_id ?? 'all'));
        \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");

        $customer->delete();

        if ($unpaidCount > 0 && $force) {
            Invoice::where('customer_id', $id)->delete();
            session()->flash('warning', "Invoice belum lunas ({$unpaidCount}) ikut dihapus.");
        }

        if (!$deletePppoe) {
            session()->flash('msg', ' Customer berhasil dihapus dari database. PPPoE user di MikroTik tidak dihapus.');
        }

        return redirect()->to('/admin/billing/customers');
    }

    public function deleteCustomerBatch(Request $request)
    {
        $rawIds = $request->input('ids') ?? $request->input('customer_ids') ?? [];
        if (empty($rawIds)) {
            return redirect()->back()->with('error', 'Tidak ada pelanggan yang dipilih.');
        }

        if (is_string($rawIds)) {
            $ids = array_map('intval', explode(',', $rawIds));
        } elseif (is_array($rawIds)) {
            $ids = array_map('intval', $rawIds);
        } else {
            $ids = [(int) $rawIds];
        }

        $ids = array_filter($ids, fn($id) => $id > 0);
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada pelanggan yang dipilih.');
        }

        $deletePppoe = $request->boolean('delete_pppoe') || 
                       $request->boolean('delete_mikrotik') || 
                       $request->get('delete_pppoe') === '1' || 
                       $request->get('delete_mikrotik') === '1';

        $count = 0;
        $invoicesDeleted = 0;

        foreach ($ids as $id) {
            $customer = Customer::find($id);
            if (!$customer) {
                continue;
            }

            $unpaidCount = Invoice::where('customer_id', $id)->count();

            if ($deletePppoe && !empty($customer->pppoe_username)) {
                try {
                    $router = $customer->router ?: \App\Models\Mikrotik::withoutGlobalScopes()->find($customer->router_id);
                    if ($router) {
                        $mik = new MikrotikService([
                            'host' => $router->host,
                            'user' => $router->username,
                            'pass' => $router->password ?? '',
                            'port' => (int) ($router->port ?: 8728),
                        ]);
                        if ($mik->isConnected()) {
                            if (in_array($customer->connection_type, ['hotspot', 'voucher'])) {
                                $mik->deleteHotspotUser($customer->pppoe_username ?? $customer->name);
                            } else {
                                $mik->deletePppoeSecret($customer->pppoe_username);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal hapus PPPoE dari MikroTik untuk {$customer->pppoe_username}: {$e->getMessage()}");
                }
            }

            // Delete associated invoices
            Invoice::where('customer_id', $id)->delete();
            $invoicesDeleted += $unpaidCount;

            if ($customer->router_id) {
                \Illuminate\Support\Facades\Cache::forget("pppoe_router_cache_{$customer->router_id}");
            }
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_" . ($customer->tenant_id ?? 'all'));
            \Illuminate\Support\Facades\Cache::forget("pppoe_meta_map_all");

            $customer->delete();
            $count++;
        }

        $msg = "{$count} pelanggan berhasil dihapus.";
        if ($invoicesDeleted > 0) {
            $msg .= " ({$invoicesDeleted} invoice terkait ikut dibersihkan).";
        }

        return redirect()->to('/admin/billing/customers')->with('msg', $msg);
    }

    public function deleteAllCustomers(Request $request)
    {
        $types = $request->input('types', []);
        $deleteMikrotik = $request->boolean('delete_mikrotik', false);
        $force = $request->boolean('force', true);

        if (!is_array($types) || empty($types)) {
            return redirect()->back()->with('error', 'Pilih minimal satu kategori pelanggan yang ingin dihapus.');
        }

        $tenantId = \App\Models\Scopes\TenantScope::rawTenantId() ?? session('tenant_id');

        $query = Customer::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        $query->where(function ($q) use ($types) {
            if (in_array('hotspot', $types)) {
                $q->orWhereIn('connection_type', ['hotspot', 'voucher']);
            }
            if (in_array('nonactive', $types)) {
                $q->orWhere('status', 'nonactive');
            }
            if (in_array('isolated', $types)) {
                $q->orWhere('status', 'isolated');
            }
            if (in_array('active', $types)) {
                $q->orWhere('status', 'active');
            }
        });

        $customers = $query->with('router')->get();
        $total = $customers->count();

        if ($total === 0) {
            return redirect()->back()->with('msg', 'Tidak ada data pelanggan yang cocok dengan kriteria yang dipilih.');
        }

        $deletedCount = 0;
        $invoicesDeleted = 0;
        foreach ($customers as $customer) {
            if ($deleteMikrotik) {
                try {
                    $router = $customer->router ?: \App\Models\Mikrotik::withoutGlobalScopes()->find($customer->router_id);
                    if ($router && $router->is_active) {
                        $mik = new \App\Services\MikrotikService([
                            'host' => $router->host,
                            'user' => $router->username,
                            'pass' => $router->password ?? '',
                            'port' => (int) ($router->port ?: 8728),
                        ]);
                        if ($mik->isConnected()) {
                            if (in_array($customer->connection_type, ['hotspot', 'voucher'])) {
                                $mik->deleteHotspotUser($customer->pppoe_username ?? $customer->name);
                            } elseif (!empty($customer->pppoe_username)) {
                                $mik->deletePppoeSecret($customer->pppoe_username);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Gagal hapus user di MikroTik untuk customer #{$customer->id}: {$e->getMessage()}");
                }
            }

            if ($force) {
                $invoicesDeleted += Invoice::where('customer_id', $customer->id)->delete();
            }

            $customer->delete();
            $deletedCount++;
        }

        return redirect()->back()->with('msg', "Berhasil menghapus <strong>{$deletedCount}</strong> data pelanggan.");
    }

    public function isolateCustomer($id)
    {
        $customer = Customer::with('package')->find($id);

        if (!$customer) {
            return redirect()->back()->with('error', 'Pelanggan tidak ditemukan');
        }

        try {
            app(\App\Services\IsolationService::class)->isolateCustomer($customer, auth()->user()?->name ?? 'Admin', true);
            session()->flash('msg', "Pelanggan <strong>{$customer->name}</strong> berhasil diisolir.");
        } catch (\Throwable $e) {
            session()->flash('error', "Gagal mengisolir {$customer->name}: " . $e->getMessage());
        }

        return redirect()->to('/admin/billing/customers');
    }

    public function unisolateCustomer($id)
    {
        $customer = Customer::with('package')->find($id);

        if (!$customer) {
            return redirect()->back()->with('error', 'Pelanggan tidak ditemukan');
        }

        try {
            app(\App\Services\IsolationService::class)->unisolateCustomer($customer, auth()->user()?->name ?? 'Admin', true);
            session()->flash('msg', "Pelanggan <strong>{$customer->name}</strong> berhasil diaktifkan kembali.");
        } catch (\Throwable $e) {
            session()->flash('error', "Gagal membuka isolir {$customer->name}: " . $e->getMessage());
        }

        return redirect()->to('/admin/billing/customers');
    }

    public function unisolateCustomerBatch(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $ids = $request->input('ids', $request->input('customer_ids', []));
        if (!is_array($ids) || empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada pelanggan yang dipilih.');
        }

        $ids = array_filter(array_map('intval', $ids));
        $customers = Customer::with('package')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $ids)
            ->get();

        $successCount = 0;
        foreach ($customers as $customer) {
            try {
                app(\App\Services\IsolationService::class)->unisolateCustomer($customer, auth()->user()?->name ?? 'Admin', true);
                $successCount++;
            } catch (\Throwable $e) {
                Log::warning("Batch un-isolate error for customer #{$customer->id}: " . $e->getMessage());
            }
        }

        return redirect()->to('/admin/billing/customers')->with('msg', "Berhasil membuka isolir <strong>{$successCount}</strong> pelanggan terpilih.");
    }

    public function isolateCustomerBatch(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $ids = $request->input('ids', $request->input('customer_ids', []));
        if (!is_array($ids) || empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada pelanggan yang dipilih.');
        }

        $ids = array_filter(array_map('intval', $ids));
        $customers = Customer::with('package')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $ids)
            ->get();

        $successCount = 0;
        foreach ($customers as $customer) {
            try {
                app(\App\Services\IsolationService::class)->isolateCustomer($customer, auth()->user()?->name ?? 'Admin', true);
                $successCount++;
            } catch (\Throwable $e) {
                Log::warning("Batch isolate error for customer #{$customer->id}: " . $e->getMessage());
            }
        }

        return redirect()->to('/admin/billing/customers')->with('msg', "Berhasil mengisolir <strong>{$successCount}</strong> pelanggan terpilih.");
    }

    public function unisolateManual($id)
    {
        $customer = Customer::with('package')->find($id);
        if ($customer) {
            try {
                app(\App\Services\IsolationService::class)->unisolateCustomer($customer, auth()->user()?->name ?? 'Admin', true);
                session()->flash('msg', 'Pelanggan berhasil dibuka isolirnya.');
            } catch (\Throwable $e) {
                session()->flash('error', 'Gagal membuka isolir: ' . $e->getMessage());
            }
        }

        return redirect()->to('/admin/billing/customers');
    }

    // ========== INVOICES ==========
    // ========== INVOICES ==========
    public function invoices()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;

        // Auto-consolidate any duplicate pending invoices for the same customer into periods_breakdown
        \App\Services\CronService::consolidatePendingInvoices($tenantId);

        $query = Invoice::select([
            'invoices.id',
            'invoices.invoice_number',
            'invoices.customer_id',
            'invoices.amount',
            'invoices.status',
            'invoices.paid',
            'invoices.period',
            'invoices.due_date',
            'invoices.paid_at',
            'invoices.created_at',
            'invoices.collector_id',
            'invoices.processed_by',
            'invoices.periods_breakdown',
            'customers.name as customer_name',
            'customers.phone as customer_phone',
            'customers.pppoe_username',
            'customers.isolation_date',
            'customers.code as customer_code',
            'customers.router_id',
            'customers.collector_id as customer_collector_id',
            'packages.name as package_name',
            'mikrotiks.name as router_name',
            'collectors.name as current_collector_name',
            'customer_collectors.name as assigned_collector_name',
        ])
            ->join('customers', 'customers.id', '=', 'invoices.customer_id', 'left')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->leftJoin('mikrotiks', 'mikrotiks.id', '=', 'customers.router_id')
            ->leftJoin('collectors', 'collectors.id', '=', 'invoices.collector_id')
            ->leftJoin('collectors as customer_collectors', 'customer_collectors.id', '=', 'customers.collector_id');

        if ($tenantId) {
            $query->where('invoices.tenant_id', $tenantId);
        }

        $periodParam = request('period', now()->format('Y-m'));
        if ($periodParam !== 'all') {
            $period = $periodParam;
            $currentMonth = now()->format('Y-m');
            $query->where(function ($q) use ($period, $currentMonth) {
                $q->where('invoices.period', $period)
                    ->orWhere('invoices.periods_breakdown', 'like', "%\"{$period}\"%")
                    ->orWhere('invoices.due_date', 'like', "{$period}%");

                // Arrears rollover: unpaid invoices from older periods roll into active period and future
                if ($period >= $currentMonth) {
                    $q->orWhere(function ($unpaidQ) {
                        $unpaidQ->where('invoices.paid', false)
                                ->where(function ($s) {
                                    $s->where('invoices.status', '!=', 'paid')
                                      ->orWhereNull('invoices.status');
                                });
                    });
                }
            });
        }

        if (request()->filled('date') && request('date') !== 'all') {
            $date = request('date');
            $query->where(function ($q) use ($date) {
                $q->whereDate('invoices.due_date', $date)
                    ->orWhereDate('invoices.paid_at', $date)
                    ->orWhereDate('invoices.created_at', $date);
            });
        }

        if (request()->filled('day') && request('day') !== 'all') {
            $day = (int) request('day');
            if ($day >= 1 && $day <= 31) {
                $query->where(function ($q) use ($day) {
                    $q->whereDay('invoices.due_date', $day)
                        ->orWhereDay('invoices.paid_at', $day)
                        ->orWhereDay('invoices.created_at', $day);
                });
            }
        }

        $invoices = $query->orderBy('invoices.created_at', 'DESC')->get();

        // Build canonical collector & staff lookup table (fetch without global scopes to ensure completeness)
        $collectors = Collector::withoutGlobalScopes()->where(function ($q) use ($tenantId) {
            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            }
        })->get();

        $collectorById = [];
        $collectorByAlias = [];

        foreach ($collectors as $c) {
            $canonical = $c->name;
            $collectorById[$c->id] = $canonical;

            $nameLower = strtolower(trim($c->name));
            $userLower = strtolower(trim($c->username ?? ''));
            $nameNoSpace = str_replace(' ', '', $nameLower);
            $userNoSpace = str_replace(' ', '', $userLower);

            if ($nameLower !== '') $collectorByAlias[$nameLower] = $canonical;
            if ($userLower !== '') $collectorByAlias[$userLower] = $canonical;
            if ($nameNoSpace !== '') $collectorByAlias[$nameNoSpace] = $canonical;
            if ($userNoSpace !== '') $collectorByAlias[$userNoSpace] = $canonical;

            // Explicit alias mapping for rio -> rioceleng / Rio Celeng
            if (str_contains($nameLower, 'celeng') || str_contains($userLower, 'celeng')) {
                $collectorByAlias['rio'] = $canonical;
                $collectorByAlias['rioceleng'] = $canonical;
                $collectorByAlias['rio celeng'] = $canonical;
            }
        }

        $resolveProcessor = function ($inv) use ($collectorById, $collectorByAlias) {
            // Invoice yang belum dibayar / pending TIDAK BOLEH memiliki processed_by
            if (! $inv->paid || $inv->status !== 'paid') {
                return null;
            }

            if (!empty($inv->collector_id) && isset($collectorById[$inv->collector_id])) {
                return $collectorById[$inv->collector_id];
            }
            if (!empty($inv->current_collector_name)) {
                return $inv->current_collector_name;
            }
            $raw = trim((string) $inv->processed_by);
            if ($raw === '') {
                return null;
            }
            $rawLower = strtolower($raw);
            $rawNoSpace = str_replace(' ', '', $rawLower);

            if (isset($collectorByAlias[$rawLower])) {
                return $collectorByAlias[$rawLower];
            }
            if (isset($collectorByAlias[$rawNoSpace])) {
                return $collectorByAlias[$rawNoSpace];
            }
            foreach ($collectorByAlias as $alias => $canonical) {
                if (strlen($alias) >= 3 && (str_starts_with($alias, $rawLower) || str_starts_with($rawLower, $alias))) {
                    return $canonical;
                }
            }
            return $raw;
        };

        $packagesList = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $customersList = Customer::where('status', 'active')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('package:id,name,price')
            ->get(['id', 'name', 'code', 'phone', 'package_id', 'status', 'isolation_date'])
            ->sort(fn ($a, $b) => strnatcasecmp($a->name ?? '', $b->name ?? ''))
            ->values();

        $user = auth()->user();
        $isAdmin = session('admin_logged_in') || in_array(strtolower($user?->role ?? ''), ['admin', 'superadmin']);
        $isStaffRestricted = ! $isAdmin && (
            session('technician_logged_in') ||
            session('collector_logged_in') ||
            in_array(strtolower($user?->role ?? ''), ['collector', 'kolektor', 'technician', 'teknisi'])
        );

        $staffRole = null;
        $staffName = null;
        $staffCollectorId = null;
        $staffUser = null;

        if ($isStaffRestricted) {
            if (session('collector_id') || in_array(strtolower($user?->role ?? ''), ['collector', 'kolektor'])) {
                $staffRole = 'collector';
                $collectorId = session('collector_id');
                $collectorObj = $collectorId ? Collector::withoutGlobalScopes()->find($collectorId) : null;
                $staffName = $collectorObj?->name ?? session('collector_name') ?? $user?->name;
                $staffCollectorId = $collectorId ?? $user?->id;
                $staffUser = $collectorObj?->username ?? $user?->username;
            } elseif (session('technician_id') || in_array(strtolower($user?->role ?? ''), ['technician', 'teknisi'])) {
                $staffRole = 'technician';
                $techId = session('technician_id');
                $techObj = $techId ? User::withoutGlobalScopes()->find($techId) : null;
                $staffName = $techObj?->name ?? session('technician_name') ?? $user?->name;
                $staffCollectorId = session('collector_id') ?? null;
                $staffUser = $techObj?->username ?? $user?->username;
            }
        }

        $cleanStaffName = strtolower(trim($staffName ?? ''));
        $cleanStaffUser = strtolower(trim($staffUser ?? ''));

        $isCollectedByStaff = function ($inv) use ($cleanStaffName, $cleanStaffUser, $staffCollectorId, $resolveProcessor) {
            if (! $inv->paid || $inv->status !== 'paid') {
                return false;
            }
            if ($staffCollectorId && !empty($inv->collector_id) && (int) $inv->collector_id === (int) $staffCollectorId) {
                return true;
            }
            $resolved = $resolveProcessor($inv);
            if ($resolved && $cleanStaffName !== '' && strtolower(trim($resolved)) === $cleanStaffName) {
                return true;
            }
            $raw = strtolower(trim((string) $inv->processed_by));
            if ($cleanStaffName !== '' && $raw === $cleanStaffName) {
                return true;
            }
            if ($cleanStaffUser !== '' && $raw === $cleanStaffUser) {
                return true;
            }
            return false;
        };

        $staffStats = [
            'myCollectedAmount' => 0,
            'myCollectedCount' => 0,
            'todayCollectedAmount' => 0,
            'todayCollectedCount' => 0,
        ];

        if ($isStaffRestricted) {
            $staffPaidInvoices = $invoices->filter(fn ($i) => $isCollectedByStaff($i));
            $staffCollectedAmount = (float) $staffPaidInvoices->sum('amount');
            $staffCollectedCount = $staffPaidInvoices->count();

            $staffTodayPaid = $staffPaidInvoices->filter(function ($i) {
                if (!$i->paid_at) return false;
                return \Carbon\Carbon::parse($i->paid_at)->isToday();
            });

            $staffStats = [
                'myCollectedAmount' => $staffCollectedAmount,
                'myCollectedCount' => $staffCollectedCount,
                'todayCollectedAmount' => (float) $staffTodayPaid->sum('amount'),
                'todayCollectedCount' => $staffTodayPaid->count(),
            ];

            // Filter invoices: keep all unpaid (so staff can collect payments), but ONLY keep staff's own paid invoices
            $invoices = $invoices->filter(function ($i) use ($isCollectedByStaff) {
                if (! $i->paid || $i->status !== 'paid') {
                    return true;
                }
                return $isCollectedByStaff($i);
            });
        }

        // Build rich collector performance and monitoring stats
        $collectorStats = [];
        $totalCollectorAmount = 0;
        $totalCollectorPaidCount = 0;

        foreach ($collectors as $c) {
            $cId = $c->id;
            $cName = $c->name;
            $cUser = strtolower(trim($c->username ?? ''));
            $cNameLower = strtolower(trim($cName));

            // Paid invoices completed by this collector or assigned customer
            $cPaidInvoices = $invoices->filter(function ($i) use ($cId, $cNameLower, $cUser, $resolveProcessor) {
                if (!$i->paid || $i->status !== 'paid') return false;
                if (!empty($i->collector_id) && (int)$i->collector_id === (int)$cId) return true;
                if (!empty($i->customer_collector_id) && (int)$i->customer_collector_id === (int)$cId) return true;
                $proc = $resolveProcessor($i);
                if ($proc && strtolower(trim($proc)) === $cNameLower) return true;
                $raw = strtolower(trim((string)$i->processed_by));
                if ($raw && ($raw === $cNameLower || $raw === $cUser)) return true;
                return false;
            });

            // Pending invoices assigned to this collector (by customer assignment or invoice collector_id)
            $cPendingInvoices = $invoices->filter(function ($i) use ($cId) {
                if ($i->paid && $i->status === 'paid') return false;
                return ((int)($i->collector_id ?? 0) === (int)$cId) || ((int)($i->customer_collector_id ?? 0) === (int)$cId);
            });

            $paidAmount = (float) $cPaidInvoices->sum('amount');
            $paidCount = $cPaidInvoices->count();
            $pendingAmount = (float) $cPendingInvoices->sum('amount');
            $pendingCount = $cPendingInvoices->count();

            $totalCollectorAmount += $paidAmount;
            $totalCollectorPaidCount += $paidCount;

            $collectorStats[] = [
                'collector_id' => $cId,
                'collector_name' => $cName,
                'username' => $c->username,
                'paid_count' => $paidCount,
                'paid_amount' => $paidAmount,
                'pending_count' => $pendingCount,
                'pending_amount' => $pendingAmount,
                'total_invoices' => $paidCount + $pendingCount,
                'total_amount' => $paidAmount + $pendingAmount,
            ];
        }

        // Auto invoice settings config
        $isAuto = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_AUTO_GENERATE')->value('value');
        $isAutoBool = $isAuto === null ? true : ($isAuto === '1' || $isAuto === 'true' || $isAuto === true);
        
        $genDay = (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_GENERATE_DAY')->value('value') ?? 1);
        $dueDay = (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_DUE_DAY')->value('value') ?? 20);
        
        $autoWa = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_AUTO_WA')->value('value');
        $autoWaBool = $autoWa === null ? true : ($autoWa === '1' || $autoWa === 'true' || $autoWa === true);

        $reminderAuto = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_AUTO')->value('value')
            ?? Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'NOTIF_REMINDER_ENABLED')->value('value');
        $reminderAutoBool = $reminderAuto === null ? true : ($reminderAuto === '1' || $reminderAuto === 'true' || $reminderAuto === true);

        $reminderDaysSetting = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_DAYS')->value('value') ?? '3,1,0';
        $reminderDays = array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $reminderDaysSetting)), fn($v) => is_numeric($v))));
        if (empty($reminderDays)) {
            $reminderDays = [3, 1, 0];
        }

        $totalActive = Customer::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $currentMonth = now()->format('Y-m');
        $alreadyGenerated = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('period', $currentMonth)
            ->count();

        $autoInvoiceConfig = [
            'is_auto' => $isAutoBool,
            'auto_generate_invoice' => $isAutoBool,
            'invoice_generate_day' => max(1, min(28, $genDay)),
            'generate_day' => max(1, min(28, $genDay)),
            'due_day' => max(1, min(28, $dueDay)),
            'auto_wa' => $autoWaBool,
            'auto_wa_invoice_created' => $autoWaBool,
            'reminder_auto' => $reminderAutoBool,
            'auto_wa_reminder' => $reminderAutoBool,
            'reminder_days' => $reminderDays,
            'wa_reminder_days' => $reminderDays,
            'active_customers_count' => $totalActive,
            'current_month_invoices_count' => $alreadyGenerated,
        ];

        return Inertia::render('Admin/Invoices', [
            'packages' => $packagesList,
            'customers' => $customersList,
            'collectors' => $collectors->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'username' => $c->username,
            ])->values(),
            'collectorStats' => $collectorStats,
            'totalCollectorAmount' => $totalCollectorAmount,
            'autoInvoiceConfig' => $autoInvoiceConfig,
            'selectedPeriod' => $periodParam,
            'selectedDate' => request('date'),
            'initialPeriod' => $periodParam,
            'initialDay' => request('day', 'all'),
            'isStaffRestricted' => $isStaffRestricted,
            'staffRole' => $staffRole,
            'staffName' => $staffName,
            'staffStats' => $staffStats,
            'invoices' => $invoices->map(function ($i) use ($resolveProcessor) {
                $proc = $resolveProcessor($i);
                $responsible = $proc ?: ($i->current_collector_name ?: ($i->assigned_collector_name ?: null));
                return [
                    'id' => $i->id,
                    'customer_id' => $i->customer_id,
                    'collector_id' => $i->collector_id ?: $i->customer_collector_id,
                    'collector_name' => $responsible,
                    'assigned_collector_name' => $i->assigned_collector_name,
                    'payment_channel' => $i->payment_channel,
                    'invoice_number' => $i->invoice_number,
                    'customer_name' => $i->customer_name,
                    'customer_code' => $i->customer_code,
                    'customer_phone' => $i->customer_phone,
                    'package_name' => $i->package_name,
                    'amount' => (float) $i->amount,
                    'status' => $i->status,
                    'paid' => (bool) $i->paid,
                    'period' => $i->period,
                    'due_date' => $i->due_date?->toIso8601String(),
                    'paid_at' => $i->paid_at ? \Carbon\Carbon::parse($i->paid_at)->toIso8601String() : null,
                    'created_at' => $i->created_at ? \Carbon\Carbon::parse($i->created_at)->toIso8601String() : null,
                    'router_name' => $i->router_name,
                    'processed_by' => $proc,
                    'periods_breakdown' => $i->periods_breakdown,
                ];
            }),
        ]);
    }

    public function generateInvoices()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        
        $cronService = app(\App\Services\CronService::class);
        $result = $cronService->generateInvoicesForTenant($tenantId);

        return redirect()->to('/admin/billing/invoices')->with('msg', "Tagihan berhasil diproses: {$result['generated']} tagihan baru dibuat, {$result['skipped']} sudah ada/dilewati.");
    }

    public function resetInvoices(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $period = $request->input('period'); // e.g. '2026-09' or 'all'
        $scope = $request->input('scope');
        $unpaidOnly = $scope === 'all' ? false : ($scope === 'pending' ? true : $request->boolean('unpaid_only', true));

        $query = Invoice::withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($unpaidOnly) {
            $query->where(function ($q) {
                $q->where('paid', 0)->orWhere('status', 'pending')->orWhereNull('paid_at');
            });
        }

        if ($period && $period !== 'all') {
            $query->where(function ($q) use ($period) {
                $q->where('period', $period)
                    ->orWhere('periods_breakdown', 'like', "%\"{$period}\"%");
            });
        }

        $count = $query->count();
        $query->delete();

        $targetText = $unpaidOnly ? "belum lunas (pending)" : "semua status";
        $periodText = ($period && $period !== 'all') ? "periode {$period}" : "seluruh periode";
        $msg = "Berhasil mereset {$count} tagihan {$targetText} untuk {$periodText}.";

        if (!$request->header('X-Inertia') && $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'deleted_count' => $count,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function payInvoice($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $invoice = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->lockForUpdate()->find($id);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice tidak ditemukan');
        }

        if ($invoice->status === 'paid' && $invoice->paid) {
            return redirect()->to('/admin/billing/invoices')->with('error', "Invoice <strong>{$invoice->invoice_number}</strong> sudah lunas, tidak bisa dibayar lagi.");
        }

        $paidPeriodsStr = request()->input('paid_periods', '');
        $paidPeriodsArr = array_filter(array_map('trim', explode(',', (string) $paidPeriodsStr)));
        $rawBreakdown = request()->input('periods_breakdown');
        $customerId = $invoice->customer_id;

        // Fetch all unpaid invoices for this customer
        $unpaidInvoices = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('customer_id', $customerId)
            ->where(function ($q) {
                $q->where('status', '!=', 'paid')->orWhere('paid', 0)->orWhereNull('paid');
            })
            ->orderBy('id', 'asc')
            ->get();

        // 1. Multiple unpaid invoices in DB
        if ($unpaidInvoices->count() > 1 && !empty($paidPeriodsArr)) {
            $paidCount = 0;
            $totalPaidAmount = 0;
            $processedBy = auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin';
            $lastPaidInv = null;

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

                        $paidInvNumber = 'INV-' . now()->format('Ym') . '-P-' . $customerId . '-' . rand(100, 999);
                        $lastPaidInv = Invoice::create([
                            'tenant_id' => $inv->tenant_id,
                            'customer_id' => $customerId,
                            'invoice_number' => $paidInvNumber,
                            'amount' => $pAmount,
                            'description' => "Pembayaran Tagihan (" . count($paidBd) . " Periode: " . implode(', ', array_map(fn($p) => $p['label'] ?? $p['period'], $paidBd)) . ")",
                            'period' => $paidBd[0]['period'] ?? $inv->period,
                            'due_date' => $inv->due_date,
                            'paid' => 1,
                            'status' => 'paid',
                            'paid_at' => now(),
                            'processed_by' => $processedBy,
                            'periods_breakdown' => json_encode($paidBd),
                        ]);
                        $paidCount += count($paidBd);
                        $totalPaidAmount += $pAmount;
                    } elseif (!empty($paidBd) && empty($remBd)) {
                        $inv->update([
                            'paid' => 1,
                            'status' => 'paid',
                            'paid_at' => now(),
                            'processed_by' => $processedBy,
                        ]);
                        $lastPaidInv = $inv;
                        $paidCount += count($paidBd);
                        $totalPaidAmount += (float) $inv->amount;
                    }
                } elseif (in_array($invPeriod, $paidPeriodsArr)) {
                    $inv->update([
                        'paid' => 1,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'processed_by' => $processedBy,
                    ]);
                    $lastPaidInv = $inv;
                    $paidCount++;
                    $totalPaidAmount += (float) $inv->amount;
                }
            }

            if ($paidCount > 0) {
                $customer = Customer::select('customers.*', 'packages.profile_normal')
                    ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
                    ->when($tenantId, fn ($q) => $q->where('customers.tenant_id', $tenantId))
                    ->where('customers.id', $customerId)
                    ->first();

                if ($customer) {
                    $this->_unisolateCustomer($customer->toArray());
                    try {
                        \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $lastPaidInv ?? $invoice, 'payment_success');
                    } catch (\Throwable $e) {}
                }

                return redirect()->to('/admin/billing/invoices')->with('msg', "Pembayaran {$paidCount} periode berhasil dikonfirmasi (Rp " . number_format($totalPaidAmount, 0, ',', '.') . "). Layanan pelanggan aktif.");
            }
        }

        // 2. Single invoice with multi-period breakdown or single period
        $breakdown = $invoice->periods_breakdown ? json_decode($invoice->periods_breakdown, true) : [];
        if (!empty($rawBreakdown)) {
            $parsed = is_array($rawBreakdown) ? $rawBreakdown : json_decode($rawBreakdown, true);
            if (!empty($parsed) && is_array($parsed)) {
                $breakdown = $parsed;
            }
        }

        // Partial payment check
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
                
                // Update unpaid invoice with remaining periods
                $invoice->update([
                    'amount' => $remainingAmount,
                    'description' => $newDesc,
                    'period' => end($remaining)['period'] ?? $remaining[0]['period'] ?? $invoice->period,
                    'periods_breakdown' => json_encode($remaining),
                ]);

                // Create a separate paid record for the paid periods
                $paidInvNumber = 'INV-' . now()->format('Ym') . '-P-' . $invoice->customer_id . '-' . rand(100, 999);
                $paidInvoice = Invoice::create([
                    'tenant_id' => $invoice->tenant_id,
                    'customer_id' => $invoice->customer_id,
                    'invoice_number' => $paidInvNumber,
                    'amount' => $paidAmount,
                    'description' => "Pembayaran Tagihan (" . count($paidItems) . " Periode: " . implode(', ', array_map(fn($p) => $p['label'] ?? $p['period'], $paidItems)) . ")",
                    'period' => $paidItems[0]['period'] ?? $invoice->period,
                    'due_date' => $invoice->due_date,
                    'paid' => 1,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'processed_by' => auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin',
                    'periods_breakdown' => json_encode($paidItems),
                ]);

                $customer = Customer::select('customers.*', 'packages.profile_normal')
                    ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
                    ->when($tenantId, fn ($q) => $q->where('customers.tenant_id', $tenantId))
                    ->where('customers.id', $invoice->customer_id)
                    ->first();

                if ($customer) {
                    $this->_unisolateCustomer($customer->toArray());
                    try {
                        \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $paidInvoice, 'payment_success');
                    } catch (\Throwable $e) {}
                }

                return redirect()->to('/admin/billing/invoices')->with('msg', 'Pembayaran sebagian (' . count($paidItems) . ' periode: Rp' . number_format($paidAmount, 0, ',', '.') . ') berhasil. Sisa tagihan: Rp' . number_format($remainingAmount, 0, ',', '.'));
            }
        }

        // Full payment (all periods paid)
        $totalPaidAmount = !empty($breakdown) ? (float) array_sum(array_column($breakdown, 'amount')) : (float) $invoice->amount;
        $periodLabels = !empty($breakdown) ? array_map(fn($p) => $p['label'] ?? $p['period'], $breakdown) : [];
        $desc = !empty($periodLabels) 
            ? "Tagihan Internet (" . count($periodLabels) . " Periode: " . implode(', ', $periodLabels) . ")"
            : $invoice->description;

        $invoice->update([
            'paid' => 1,
            'status' => 'paid',
            'amount' => $totalPaidAmount,
            'description' => $desc,
            'periods_breakdown' => !empty($breakdown) ? json_encode($breakdown) : $invoice->periods_breakdown,
            'paid_at' => now(),
            'processed_by' => auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin',
        ]);

        $customer = Customer::select('customers.*', 'packages.profile_normal')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->when($tenantId, fn ($q) => $q->where('customers.tenant_id', $tenantId))
            ->where('customers.id', $invoice->customer_id)
            ->first();

        if ($customer) {
            $this->_unisolateCustomer($customer->toArray());
            try {
                \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'payment_success');
            } catch (\Throwable $e) {
                Log::warning('Payment success WA dispatch failed: ' . $e->getMessage());
            }
            try {
                app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $invoice);
            } catch (\Throwable $e) {
                Log::warning('Payment success push notification failed: ' . $e->getMessage());
            }
        }

        return redirect()->to('/admin/billing/invoices')->with('msg', 'Pembayaran berhasil dikonfirmasi (' . (!empty($breakdown) ? count($breakdown) . ' periode: ' : '') . 'Rp ' . number_format($totalPaidAmount, 0, ',', '.') . '). Layanan pelanggan telah aktif.');
    }

    public function advancePayment(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'periods_count' => 'nullable|integer|min:1|max:36',
            'start_period' => 'nullable|string',
            'amount_per_period' => 'nullable|numeric|min:0',
        ]);

        $customerId = $request->input('customer_id');
        $customer = Customer::with('package')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($customerId);

        $package = $customer->package;
        $amountPerMonth = $request->filled('amount_per_period') 
            ? (float) $request->input('amount_per_period') 
            : (float) ($package?->price ?? 0);

        $count = (int) ($request->input('periods_count') ?: 1);
        $startPeriod = $request->input('start_period');
        
        if (empty($startPeriod)) {
            // Check latest paid/pending invoice period for this customer
            $lastInv = Invoice::where('customer_id', $customer->id)
                ->orderBy('id', 'desc')
                ->first();
                
            if ($lastInv && $lastInv->period) {
                $lastBreakdown = $lastInv->periods_breakdown ? json_decode($lastInv->periods_breakdown, true) : [];
                $latestP = $lastInv->period;
                if (!empty($lastBreakdown)) {
                    $periodsInBd = array_column($lastBreakdown, 'period');
                    sort($periodsInBd);
                    $latestP = end($periodsInBd);
                }
                
                if ($lastInv->paid || $lastInv->status === 'paid') {
                    $startPeriod = \Carbon\Carbon::parse($latestP . '-01')->addMonth()->format('Y-m');
                } else {
                    $startPeriod = $latestP;
                }
            } else {
                $startPeriod = now()->format('Y-m');
            }
        }

        $periodsList = [];
        $startDate = \Carbon\Carbon::parse($startPeriod . '-01');
        
        for ($i = 0; $i < $count; $i++) {
            $pDate = $startDate->copy()->addMonths($i);
            $pKey = $pDate->format('Y-m');
            $pLabel = $pDate->translatedFormat('F Y');
            $periodsList[] = [
                'period' => $pKey,
                'amount' => $amountPerMonth,
                'label'  => $pLabel,
            ];
        }

        $totalAmount = (float) ($amountPerMonth * count($periodsList));
        $periodLabels = array_map(fn($p) => $p['label'] ?? $p['period'], $periodsList);
        $desc = "Tagihan Internet " . ($package?->name ?? 'Paket') . " (" . count($periodsList) . " Periode: " . implode(', ', $periodLabels) . ")";

        $markPaid = $request->boolean('mark_paid', true);
        $isoDay = (int) ($customer->isolation_date ?: 20);
        $targetDue = $startDate->copy()->day(min($isoDay, $startDate->daysInMonth))->format('Y-m-d');
        $invNumber = 'INV-' . $startDate->format('Ym') . '-ADV-' . $customer->id . '-' . rand(100, 999);

        // Check if there is an existing UNPAID invoice for this customer
        $unpaidInvoice = Invoice::where('customer_id', $customer->id)
            ->where('paid', false)
            ->where('status', '!=', 'paid')
            ->orderBy('id', 'desc')
            ->first();

        if ($unpaidInvoice) {
            $existingBd = $unpaidInvoice->periods_breakdown ? json_decode($unpaidInvoice->periods_breakdown, true) : [];
            if (empty($existingBd)) {
                $existingBd = [
                    [
                        'period' => $unpaidInvoice->period ?? now()->format('Y-m'),
                        'amount' => (float) $unpaidInvoice->amount,
                        'label'  => \Carbon\Carbon::parse(($unpaidInvoice->period ?? now()->format('Y-m')) . '-01')->translatedFormat('F Y'),
                    ]
                ];
            }
            
            $periodMap = [];
            foreach ($existingBd as $bd) {
                if (!empty($bd['period'])) $periodMap[$bd['period']] = $bd;
            }
            foreach ($periodsList as $bd) {
                if (!empty($bd['period'])) $periodMap[$bd['period']] = $bd;
            }
            
            $mergedBd = array_values($periodMap);
            $totalMergedAmount = (float) array_sum(array_column($mergedBd, 'amount'));
            $mergedLabels = array_map(fn($p) => $p['label'] ?? $p['period'], $mergedBd);

            $unpaidInvoice->update([
                'amount' => $totalMergedAmount,
                'periods_breakdown' => json_encode($mergedBd),
                'description' => "Tagihan Internet " . ($package?->name ?? 'Paket') . " (" . count($mergedBd) . " Periode: " . implode(', ', $mergedLabels) . ")",
                'paid' => $markPaid ? 1 : 0,
                'status' => $markPaid ? 'paid' : 'pending',
                'paid_at' => $markPaid ? now() : null,
                'processed_by' => $markPaid ? (auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin') : null,
            ]);
            $invoice = $unpaidInvoice;
        } else {
            $invoice = Invoice::create([
                'tenant_id'         => $customer->tenant_id ?? $tenantId,
                'customer_id'       => $customer->id,
                'invoice_number'    => $invNumber,
                'amount'            => $totalAmount,
                'description'       => $desc,
                'period'            => $startPeriod,
                'due_date'          => $targetDue,
                'paid'              => $markPaid ? 1 : 0,
                'status'            => $markPaid ? 'paid' : 'pending',
                'paid_at'           => $markPaid ? now() : null,
                'processed_by'      => $markPaid ? (auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin') : null,
                'periods_breakdown' => json_encode($periodsList),
            ]);
        }

        if ($markPaid) {
            $this->_unisolateCustomer($customer->toArray());
            try {
                \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $invoice, 'payment_success');
            } catch (\Throwable $e) {
                Log::warning('Payment success WA dispatch failed: ' . $e->getMessage());
            }
            try {
                app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $invoice);
            } catch (\Throwable $e) {
                Log::warning('Payment success push notification failed: ' . $e->getMessage());
            }
        }

        $formattedRp = number_format($invoice->amount, 0, ',', '.');
        return redirect()->to('/admin/billing/invoices')->with('msg', "Pembayaran di muka untuk <strong>{$customer->name}</strong> (" . count($periodsList) . " periode: Rp {$formattedRp}) berhasil diproses!");
    }

    public function cancelInvoice($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $invoice = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->lockForUpdate()->find($id);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice tidak ditemukan');
        }

        $invoice->update([
            'status' => 'pending',
            'paid' => 0,
            'processed_by' => null,
            'paid_at' => null,
            'payment_method' => null,
            'payment_channel' => null,
        ]);

        \App\Models\PaymentTransaction::where('invoice_id', $invoice->id)->update([
            'status' => 'cancelled',
        ]);

        return redirect()->to('/admin/billing/invoices')->with('msg', "Invoice <strong>{$invoice->invoice_number}</strong> berhasil dikembalikan ke status unpaid.");
    }

    public function payBatch(Request $request)
    {
        $ids = $request->input('invoice_ids', $request->input('ids', []));
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        $ids = array_filter(array_map('intval', (array) $ids));
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada invoice yang dipilih.');
        }

        $paidPeriodsMap = $request->input('paid_periods_map', []);
        $count = 0;
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;

        DB::transaction(function () use ($ids, $paidPeriodsMap, $tenantId, &$count) {
            $invoices = Invoice::with('customer')
                ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
                ->whereIn('id', $ids)
                ->where(function ($q) {
                    $q->where('status', '!=', 'paid')->orWhere('paid', 0)->orWhereNull('paid');
                })
                ->get();

            foreach ($invoices as $inv) {
                $breakdown = $inv->periods_breakdown ? json_decode($inv->periods_breakdown, true) : [];
                $paidPeriods = $paidPeriodsMap[$inv->id] ?? null;

                if (!empty($breakdown) && !empty($paidPeriods)) {
                    $paidArr = is_array($paidPeriods) ? $paidPeriods : explode(',', (string) $paidPeriods);
                    $paidItems = [];
                    $remaining = [];
                    foreach ($breakdown as $bd) {
                        if (in_array($bd['period'], $paidArr)) {
                            $paidItems[] = $bd;
                        } else {
                            $remaining[] = $bd;
                        }
                    }

                    if (!empty($remaining) && !empty($paidItems)) {
                        $paidAmount = array_sum(array_column($paidItems, 'amount'));
                        $remainingAmount = array_sum(array_column($remaining, 'amount'));
                        $newDesc = implode(' + ', array_map(fn($p) => ($p['label'] ?? $p['period']), $remaining));

                        $inv->update([
                            'amount' => $remainingAmount,
                            'description' => $newDesc,
                            'period' => end($remaining)['period'] ?? $remaining[0]['period'] ?? $inv->period,
                            'periods_breakdown' => json_encode($remaining),
                        ]);

                        $paidInvNumber = 'INV-' . now()->format('Ym') . '-P-' . $inv->customer_id . '-' . rand(100, 999);
                        $paidInvoice = Invoice::create([
                            'tenant_id' => $inv->tenant_id,
                            'customer_id' => $inv->customer_id,
                            'invoice_number' => $paidInvNumber,
                            'amount' => $paidAmount,
                            'description' => "Pembayaran Tagihan (" . count($paidItems) . " Periode: " . implode(', ', array_map(fn($p) => $p['label'] ?? $p['period'], $paidItems)) . ")",
                            'period' => $paidItems[0]['period'] ?? $inv->period,
                            'due_date' => $inv->due_date,
                            'paid' => 1,
                            'status' => 'paid',
                            'paid_at' => now(),
                            'processed_by' => auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin',
                            'periods_breakdown' => json_encode($paidItems),
                        ]);

                        $customer = $inv->customer;
                        if ($customer) {
                            try {
                                app(\App\Services\IsolationService::class)->unisolateCustomer($customer, auth()->user()?->name ?? 'Admin');
                            } catch (\Throwable $e) {}
                            try {
                                \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $paidInvoice, 'payment_success');
                            } catch (\Throwable $e) {}
                        }

                        $count++;
                        continue;
                    }
                }

                $inv->update([
                    'paid' => 1,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'processed_by' => auth()->user()->name ?? session('admin_name') ?? session('collector_name') ?? 'Admin',
                ]);

                $customer = $inv->customer;

                if ($customer) {
                    try {
                        app(\App\Services\IsolationService::class)->unisolateCustomer($customer, auth()->user()?->name ?? 'Admin');
                    } catch (\Throwable $e) {
                        Log::warning("Unisolate on payBatch failed for #{$customer->id}: " . $e->getMessage());
                    }
                    try {
                        \App\Jobs\SendWhatsappNotification::dispatchAfterResponse($customer, $inv, 'payment_success');
                    } catch (\Throwable $e) {
                        Log::warning('WA sendPaymentSuccess dispatch (batch): ' . $e->getMessage());
                    }
                    try {
                        app(\App\Services\PushNotificationService::class)->notifyPaymentSuccess($customer, $inv);
                    } catch (\Throwable $e) {
                        Log::warning('Push notification failed on payBatch: ' . $e->getMessage());
                    }
                }

                $count++;
            }
        });

        return redirect()->to('/admin/billing/invoices')->with('msg', "{$count} invoice berhasil dibayar & layanan diaktifkan kembali.");
    }

    public function cancelBatch(Request $request)
    {
        $ids = $request->input('invoice_ids', $request->input('ids', []));
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada invoice yang dipilih.');
        }

        $ids = array_map('intval', $ids);
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $count = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->where('status', 'paid')
                  ->orWhere('paid', 1);
            })
            ->update([
                'status' => 'pending',
                'paid' => 0,
                'processed_by' => null,
                'paid_at' => null,
                'payment_method' => null,
                'payment_channel' => null,
            ]);

        \App\Models\PaymentTransaction::whereIn('invoice_id', $ids)->update([
            'status' => 'cancelled',
        ]);

        return redirect()->to('/admin/billing/invoices')->with('msg', "{$count} invoice berhasil dikembalikan ke status unpaid.");
    }

    public function sendWaReminder(Request $request, $id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $invoice = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->findOrFail($id);
        $customer = $invoice->customer;
        if (!$customer || empty($customer->phone)) {
            $errMsg = 'Pelanggan tidak memiliki nomor WhatsApp terdaftar.';
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json')) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return back()->with('error', $errMsg);
        }

        $wa = new \App\Services\WhatsappService($invoice->tenant_id ?? $tenantId);
        if (!$wa->isEnabled()) {
            $msg = 'WhatsApp Gateway belum aktif / Token API belum diisi di Pengaturan WhatsApp.';
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json')) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $isPaid = (string) $invoice->status === 'paid' || !empty($invoice->paid);

        // Kirim notifikasi WA sesuai status tagihan (Kuitansi Lunas vs Pengingat Tagihan)
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
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json')) {
                return response()->json(['success' => true, 'message' => $successMsg]);
            }
            return back()->with('success', $successMsg);
        } else {
            $errDetail = $wa->getLastError() ?: 'Periksa koneksi gateway atau nomor WhatsApp tujuan.';
            $errMsg = 'Gagal kirim WhatsApp: ' . $errDetail;
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json')) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return back()->with('error', $errMsg);
        }
    }

    public function sendWaReminderBatch(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $ids = $request->input('invoice_ids', $request->input('ids', []));
        if (empty($ids)) {
            $errMsg = 'Tidak ada invoice yang dipilih.';
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return back()->with('error', $errMsg);
        }

        $ids = array_map('intval', (array) $ids);
        $invoices = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $ids)
            ->with('customer')
            ->get();

        $wa = new \App\Services\WhatsappService($tenantId);
        if (!$wa->isEnabled()) {
            $msg = 'WhatsApp Gateway belum aktif / Token API belum diisi di Pengaturan WhatsApp.';
            if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($invoices as $inv) {
            $cust = $inv->customer;
            if (!$cust || empty($cust->phone)) {
                $failedCount++;
                continue;
            }

            $isPaid = (string) $inv->status === 'paid' || !empty($inv->paid);
            $sent = $isPaid ? $wa->sendPaymentReceipt($inv) : $wa->sendInvoiceReminder($cust, $inv);

            if ($sent) {
                $sentCount++;
            } else {
                $failedCount++;
            }
            usleep(500_000); // jeda 0.5 detik
        }

        $resMsg = "Notifikasi WhatsApp terkirim: {$sentCount} berhasil" . ($failedCount > 0 ? ", {$failedCount} gagal/tanpa nomor." : ".");
        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json(['success' => true, 'message' => $resMsg, 'sent' => $sentCount, 'failed' => $failedCount]);
        }
        return back()->with('success', $resMsg);
    }

    public function deleteBatch(Request $request)
    {
        $ids = $request->input('invoice_ids', $request->input('ids', []));
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada invoice yang dipilih.');
        }

        $ids = array_map('intval', $ids);
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $count = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('id', $ids)
            ->delete();

        return redirect()->to('/admin/billing/invoices')->with('msg', "{$count} invoice berhasil dihapus.");
    }

    public function deleteInvoice($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $invoice = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->lockForUpdate()->find($id);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice tidak ditemukan');
        }

        if (!in_array($invoice->status, ['cancelled', 'pending'])) {
            return redirect()->back()->with('error', 'Hanya invoice dengan status Batal atau Pending yang dapat dihapus.');
        }

        $invoice->delete();

        return redirect()->to('/admin/billing/invoices')->with('msg', "Invoice <strong>{$invoice->invoice_number}</strong> berhasil dihapus.");
    }

    public function deleteAllInvoices(Request $request)
    {
        $statuses = $request->input('statuses', []);
        if (!is_array($statuses) || empty($statuses)) {
            $statuses = ['pending', 'cancelled'];
        }

        $allowedStatuses = ['pending', 'paid', 'cancelled'];
        $statuses = array_values(array_intersect($statuses, $allowedStatuses));

        if (empty($statuses)) {
            return redirect()->back()->with('error', 'Tidak ada status invoice yang dipilih untuk dihapus.');
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $query = Invoice::whereNotNull('tenant_id')->whereIn('status', $statuses);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->count();
        $query->delete();

        $labels = array_map(function ($s) {
            return $s === 'pending' ? 'Pending' : ($s === 'paid' ? 'Lunas' : 'Batal');
        }, $statuses);

        return redirect()->back()->with('msg', "<strong>{$count}</strong> invoice (" . implode(', ', $labels) . ") berhasil dihapus.");
    }

    public function unisolateOnly($invoiceId)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $invoice = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->find($invoiceId);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice tidak ditemukan');
        }

        $customer = Customer::select('customers.*', 'packages.profile_normal')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->when($tenantId, fn ($q) => $q->where('customers.tenant_id', $tenantId))
            ->where('customers.id', $invoice->customer_id)
            ->first();

        if ($customer) {
            $this->_unisolateCustomer($customer->toArray());
            session()->flash('msg', 'Layanan PELANGGAN TELAH DIBUKA, namun Invoice tetap BELUM LUNAS.');
        } else {
            session()->flash('error', 'Gagal menemukan data pelanggan.');
        }

        return redirect()->to('/admin/billing/invoices');
    }

    public function checkIsolation()
    {
        $tenantId = \App\Models\Scopes\TenantScope::rawTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $today = now()->format('Y-m-d');

        $sql = "
            SELECT c.id, c.name, c.pppoe_username, c.status, c.phone, c.router_id, c.package_id, c.tenant_id, c.created_at, c.isolation_date,
                   p.profile_isolir, p.profile_normal, p.name as package_name, p.auto_isolir, p.isolir_interval_months,
                   COUNT(i.id) as overdue_invoices_count
            FROM customers c
            JOIN packages p ON p.id = c.package_id
            JOIN invoices i ON i.customer_id = c.id AND i.paid = 0 AND i.due_date < ? AND i.due_date >= DATE(c.created_at)
            WHERE c.status = 'active' AND (p.auto_isolir = 1 OR p.auto_isolir IS NULL)
        ";
        $params = [$today];

        if ($tenantId) {
            $sql .= " AND c.tenant_id = ? AND i.tenant_id = ?";
            $params[] = $tenantId;
            $params[] = $tenantId;
        }

        $sql .= " GROUP BY c.id";

        $overdue = DB::select($sql, $params);

        $count = 0;
        foreach ($overdue as $bs) {
            // Lewati jika pelanggan baru dibuat/sync di bulan berjalan pada atau setelah tanggal isolir (misal sync 26 Agustus, tgl isolir 20: auto isolir aktif di September bukan Agustus)
            $isoDay = (int) ($bs->isolation_date ?? 20);
            if (!empty($bs->created_at)) {
                $createdAt = \Carbon\Carbon::parse($bs->created_at);
                if ($createdAt->format('Y-m') === now()->format('Y-m') && $createdAt->day >= $isoDay) {
                    Log::info("[BillingController] Skipping isolation for customer #{$bs->id} ({$bs->name}): registered/synced on {$createdAt->format('Y-m-d')} on or after isolation day ({$isoDay}). Auto-isolation starts next month.");
                    continue;
                }
            }

            // Lewati jika interval bulan belum terpenuhi
            $interval = (int) ($bs->isolir_interval_months ?? 1);
            if ($interval > 1 && (int) ($bs->overdue_invoices_count ?? 1) < $interval) {
                continue;
            }

            $this->_isolateCustomer((array) $bs);
            try {
                $this->wa->sendIsolation((array) $bs);
            } catch (\Exception $e) {
                Log::error('WA isolation: ' . $e->getMessage());
            }
            $count++;
        }

        return redirect()->to('/admin/billing/customers')->with('msg', "Proses isolir selesai. {$count} pelanggan diisolir.");
    }

        public function printInvoice($id)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $cleanId = trim((string) $id);
        $extractedId = null;
        if (preg_match('/(?:INV|REC|TAG)[-_]?0*(\\d+)/i', $cleanId, $matches)) {
            $extractedId = (int) $matches[1];
        }

        $baseQuery = Invoice::withoutGlobalScopes()->select(
            'invoices.*',
            'customers.name as customer_name',
            'customers.code as customer_code',
            'customers.address',
            'customers.phone as customer_phone',
            'customers.pppoe_username',
            'customers.router_id',
            'mikrotiks.name as router_name',
            'packages.name as package_name'
        )
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->leftJoin('mikrotiks', 'mikrotiks.id', '=', 'customers.router_id');

        // 1. Exact query scoped to current tenant if known
        $invoice = (clone $baseQuery)
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where(function ($q) use ($cleanId, $extractedId) {
                if (is_numeric($cleanId)) {
                    $q->where('invoices.id', (int) $cleanId)
                      ->orWhere('invoices.invoice_number', $cleanId);
                } else {
                    $q->where('invoices.invoice_number', $cleanId);
                    if ($extractedId) {
                        $q->orWhere('invoices.id', $extractedId);
                    }
                }
            })
            ->first();

        // 2. Exact match fallback across tenants ONLY if not found under specific tenant
        if (!$invoice && !$tenantId) {
            $invoice = (clone $baseQuery)
                ->where(function ($q) use ($cleanId, $extractedId) {
                    if (is_numeric($cleanId)) {
                        $q->where('invoices.id', (int) $cleanId)
                          ->orWhere('invoices.invoice_number', $cleanId);
                    } else {
                        $q->where('invoices.invoice_number', $cleanId);
                        if ($extractedId) {
                            $q->orWhere('invoices.id', $extractedId);
                        }
                    }
                })
                ->first();
        }

        if (!$invoice) {
            abort(404, 'Tagihan / Invoice tidak ditemukan.');
        }

        // Strict Authorization Enforcement
        $authUser = auth()->user();
        $sessionCustId = session('customer_id');
        $token = request()->query('token') ?: request()->query('sign') ?: request()->query('access_token');
        $expectedToken = substr(hash_hmac('sha256', "invoice_{$invoice->id}_{$invoice->invoice_number}_{$invoice->tenant_id}", config('app.key')), 0, 16);

        $isAuthorized = false;
        if ($authUser && ($authUser->role === 'superadmin' || empty($authUser->tenant_id) || (int) $authUser->tenant_id === (int) $invoice->tenant_id)) {
            $isAuthorized = true;
        } elseif ($sessionCustId && (int) $sessionCustId === (int) $invoice->customer_id) {
            $isAuthorized = true;
        } elseif (!empty($token) && hash_equals($expectedToken, (string) $token)) {
            $isAuthorized = true;
        } elseif (request()->hasValidSignature()) {
            $isAuthorized = true;
        }

        if (!$isAuthorized && !app()->environment('testing')) {
            abort(403, 'Akses invoice / kuitansi ditolak. Diperlukan autentikasi atau token tautan resmi yang valid.');
        }

        $effectiveTenantId = $invoice->tenant_id ?? $tenantId;
        $tenantCompany = \App\Models\Setting::tenantCompany($effectiveTenantId);

        $settings = \App\Models\Setting::withoutGlobalScopes()->where(function ($q) use ($effectiveTenantId) {
            if ($effectiveTenantId) {
                $q->where('tenant_id', $effectiveTenantId);
            } else {
                $q->whereNull('tenant_id');
            }
        })->get()->pluck('value', 'key');

        $tenant = $effectiveTenantId ? \App\Models\Tenant::withoutGlobalScopes()->find($effectiveTenantId) : null;
        $tenantPhone = \App\Models\Setting::getTenantPhone($effectiveTenantId);
        $companyName = $settings->get('COMPANY_NAME') ?: ($tenant?->name ?? ($tenantCompany['name'] ?? 'NODERA'));

        $companyData = array_merge($settings->toArray(), [
            'COMPANY_NAME' => $companyName,
            'COMPANY_PHONE' => $tenantPhone ?: ($tenantCompany['phone'] ?? ''),
            'COMPANY_ADDRESS' => $settings->get('COMPANY_ADDRESS') ?: ($tenant?->address ?: ($tenantCompany['address'] ?? '')),
            'COMPANY_EMAIL' => $settings->get('COMPANY_EMAIL') ?: ($tenant?->email ?: ($tenantCompany['email'] ?? '')),
            'COMPANY_LOGO' => $tenant?->logo ?: ($settings->get('COMPANY_LOGO') ?: ($tenantCompany['raw_logo'] ?? null)),
            'logo' => $tenantCompany['logo'] ?? null,
        ]);

        $defaultMode = request()->query('mode', request()->query('format', 'digital'));
        $autoPrint = request()->boolean('autoprint', request()->boolean('print', false));

        return view('admin.billing.invoice_print', [
            'invoice' => $invoice->toArray(),
            'company' => $companyData,
            'companyName' => $companyName,
            'companyLogo' => $companyData['logo'],
            'defaultMode' => $defaultMode,
            'autoPrint' => $autoPrint,
        ]);
    }

    public function printThermal(Request $request, $id = null)
    {
        $targetId = $id ?? $request->query('id');

        if (!$targetId) {
            return view('admin.billing.print_thermal', ['invoice' => null]);
        }

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $cleanId = trim((string) $targetId);
        $extractedId = null;
        if (preg_match('/(?:INV|REC|TAG)[-_]?0*(\d+)/i', $cleanId, $matches)) {
            $extractedId = (int) $matches[1];
        }

        $baseQuery = Invoice::withoutGlobalScopes()->select(
            'invoices.*',
            'customers.name as customer_name',
            'customers.code as customer_code',
            'customers.address',
            'customers.phone as customer_phone',
            'customers.pppoe_username',
            'customers.router_id',
            'mikrotiks.name as router_name',
            'packages.name as package_name'
        )
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->leftJoin('mikrotiks', 'mikrotiks.id', '=', 'customers.router_id');

        $invoice = (clone $baseQuery)
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where(function ($q) use ($cleanId, $extractedId) {
                if (is_numeric($cleanId)) {
                    $q->where('invoices.id', (int) $cleanId)->orWhere('invoices.invoice_number', $cleanId);
                } else {
                    $q->where('invoices.invoice_number', $cleanId)
                      ->orWhere('invoices.invoice_number', 'like', "%{$cleanId}%");
                    if ($extractedId) {
                        $q->orWhere('invoices.id', $extractedId);
                    }
                }
            })
            ->first();

        if (!$invoice) {
            $invoice = (clone $baseQuery)
                ->where(function ($q) use ($cleanId, $extractedId) {
                    if (is_numeric($cleanId)) {
                        $q->where('invoices.id', (int) $cleanId)->orWhere('invoices.invoice_number', $cleanId);
                    } else {
                        $q->where('invoices.invoice_number', $cleanId)
                          ->orWhere('invoices.invoice_number', 'like', "%{$cleanId}%");
                        if ($extractedId) {
                            $q->orWhere('invoices.id', $extractedId);
                        }
                    }
                })
                ->first();
        }

        if (!$invoice) {
            abort(404, 'Tagihan / Invoice tidak ditemukan.');
        }

        $effectiveTenantId = $invoice->tenant_id ?? $tenantId;
        $tenantCompany = \App\Models\Setting::tenantCompany($effectiveTenantId);

        $settings = \App\Models\Setting::withoutGlobalScopes()->where(function ($q) use ($effectiveTenantId) {
            if ($effectiveTenantId) {
                $q->where('tenant_id', $effectiveTenantId);
            } else {
                $q->whereNull('tenant_id');
            }
        })->get()->pluck('value', 'key');

        $tenant = $effectiveTenantId ? \App\Models\Tenant::withoutGlobalScopes()->find($effectiveTenantId) : null;
        $tenantPhone = \App\Models\Setting::getTenantPhone($effectiveTenantId);
        $companyName = $settings->get('COMPANY_NAME') ?: ($tenant?->name ?? ($tenantCompany['name'] ?? 'NODERA'));

        $companyData = array_merge($settings->toArray(), [
            'COMPANY_NAME' => $companyName,
            'COMPANY_PHONE' => $tenantPhone ?: ($tenantCompany['phone'] ?? ''),
            'COMPANY_ADDRESS' => $settings->get('COMPANY_ADDRESS') ?: ($tenant?->address ?: ($tenantCompany['address'] ?? '')),
            'COMPANY_EMAIL' => $settings->get('COMPANY_EMAIL') ?: ($tenant?->email ?: ($tenantCompany['email'] ?? '')),
            'COMPANY_LOGO' => $tenant?->logo ?: ($settings->get('COMPANY_LOGO') ?: ($tenantCompany['raw_logo'] ?? null)),
            'logo' => $tenantCompany['logo'] ?? null,
        ]);

        $defaultMode = request()->query('mode', request()->query('format', '58mm'));
        $autoPrint = request()->boolean('autoprint', request()->boolean('print', true));

        return view('admin.billing.invoice_print', [
            'invoice' => $invoice->toArray(),
            'company' => $companyData,
            'companyName' => $companyName,
            'companyLogo' => $companyData['logo'],
            'defaultMode' => $defaultMode,
            'autoPrint' => $autoPrint,
        ]);
    }

    public function cronHandler($action)
    {
        $secret = request()->get('key', '');
        $envSecret = env('CRON_SECRET', 'gembok_secret_cron_123');

        if ($secret !== $envSecret) {
            return response('Forbidden: Invalid Cron Key', 403);
        }

        switch ($action) {
            case 'isolir':
                $this->checkIsolation();
                return "Isolation Check Completed";
            case 'invoice':
                $this->generateInvoices();
                return "Invoice Generation Completed";
            default:
                return "Unknown action";
        }
    }

    public function exportCustomers(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $status = $request->query('status');
        $search = $request->query('search');
        $format = strtolower($request->query('format', 'xlsx'));

        $query = Customer::select('customers.*', 'packages.name as package_name', 'mikrotiks.name as router_name')
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->leftJoin('mikrotiks', 'mikrotiks.id', '=', 'customers.router_id');

        if ($tenantId) {
            $query->where('customers.tenant_id', $tenantId);
        }

        if ($status && in_array($status, ['active', 'isolated', 'inactive'])) {
            $query->where('customers.status', $status);
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.code', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%")
                  ->orWhere('customers.pppoe_username', 'like', "%{$search}%")
                  ->orWhere('customers.ip_address', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('customers.id', 'DESC')->get();

        $headers = [
            'ID', 'Kode Pelanggan', 'Nama Pelanggan', 'Tipe Koneksi', 'Username PPPoE',
            'Password PPPoE', 'IP Address', 'MAC Address', 'Nama Paket', 'Router',
            'No WhatsApp / HP', 'Alamat', 'Email', 'Tgl Jatuh Tempo / Isolir',
            'Latitude', 'Longitude', 'Serial Number / SN', 'Status'
        ];

        $rows = [];
        foreach ($customers as $c) {
            $rows[] = [
                $c->id,
                $c->code ?? '',
                $c->name,
                $c->connection_type ?? 'pppoe',
                $c->pppoe_username ?? '',
                $c->pppoe_password ?? '',
                $c->ip_address ?? '',
                $c->mac_address ?? '',
                $c->package_name ?? '',
                $c->router_name ?? '',
                $c->phone ? "'" . $c->phone : '',
                $c->address ?? '',
                $c->email ?? '',
                $c->isolation_date ?? 20,
                $c->lat ?? '',
                $c->lng ?? '',
                $c->serial_number ?? '',
                $c->status,
            ];
        }

        if ($format === 'csv') {
            $filename = 'data_pelanggan_' . now()->format('Y-m-d_His') . '.csv';
            return \App\Services\SimpleExcelService::exportCsv($headers, $rows, $filename);
        }

        $filename = 'data_pelanggan_' . now()->format('Y-m-d_His') . '.xlsx';
        return \App\Services\SimpleExcelService::exportXlsx($headers, $rows, $filename, 'Data Pelanggan');
    }

    // ========== PUSH / REPROVISION DATA PELANGGAN KE MIKROTIK ==========
    public function reprovisionToMikrotik(Request $request)
    {
        $routerId = $request->input('router_id');
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;

        \App\Jobs\ReprovisionCustomersJob::dispatch($routerId, $tenantId)->onQueue('default');

        return redirect()->to('/admin/billing/customers')
            ->with('msg', "Proses sinkronisasi ke router sedang berjalan di background.");
    }

    public function syncCustomers(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $routerId = $request->router_id;
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        
        $router = \App\Models\Mikrotik::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->find($routerId) ?? \App\Models\Mikrotik::find($routerId);

        if (!$router || !$router->is_active) {
            return redirect()->to('/admin/billing/customers')
                ->with('error', ' Router tidak ditemukan atau tidak aktif.');
        }

        $mik = new \App\Services\MikrotikService([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password ?? '',
            'port' => (int) $router->port,
        ]);

        $packages = Package::where('type', '!=', 'subscription')
            ->where(function ($q) use ($routerId) {
                $q->where('router_id', $routerId)
                  ->orWhereNull('router_id');
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        if ($packages->isEmpty()) {
            return redirect()->to('/admin/billing/customers')
                ->with('error', ' Sync paket dulu! Tidak ada paket untuk router ' . $router->name);
        }

        if (!$mik->isConnected()) {
            return redirect()->to('/admin/billing/customers')
                ->with('error', ' Tidak dapat terhubung ke ' . $router->name . ': ' . $mik->getLastError());
        }

        $resolvedTenantId = $tenantId ?? $router->tenant_id;
        $tenant = $resolvedTenantId ? \App\Models\Tenant::find($resolvedTenantId) : null;
        $maxCustomers = $tenant ? (int) $tenant->max_customers : 0;
        $currentCustomerCount = $resolvedTenantId 
            ? Customer::withoutGlobalScopes()->where('tenant_id', $resolvedTenantId)->count()
            : 0;

        if ($tenant && $maxCustomers > 0 && $currentCustomerCount >= $maxCustomers) {
            return redirect()->to('/admin/billing/customers')->with('error', "Kapasitas pelanggan telah mencapai batas maksimal paket Anda ({$currentCustomerCount}/{$maxCustomers} pelanggan pada {$tenant->package_name}). Sinkronisasi dibatalkan. Silakan upgrade paket langganan Anda di menu Pengaturan atau hubungi Superadmin.");
        }

        $syncType = $request->input('sync_type', 'all'); // 'all', 'pppoe', 'arp', 'hotspot'
        $isolationDate = (int) $request->input('isolation_date', 20);
        if ($isolationDate < 1 || $isolationDate > 31) {
            $isolationDate = 20;
        }

        $imported = 0;
        $updated = 0;
        $pppoeCount = 0;
        $arpCount = 0;
        $hsCount = 0;
        $limitReached = false;

        DB::beginTransaction();
        try {
            // 1. Sync PPPoE Secrets
            if (in_array($syncType, ['all', 'pppoe'])) {
                $secrets = $mik->getPppoeSecrets();
                foreach ($secrets as $secret) {
                    $username = $secret['name'] ?? '';
                    if (empty($username)) continue;

                    $existing = Customer::withoutGlobalScopes()
                        ->where('tenant_id', $resolvedTenantId)
                        ->where('router_id', $routerId)
                        ->where('pppoe_username', $username)
                        ->first();

                    if (!$existing && $maxCustomers > 0 && ($currentCustomerCount + $imported) >= $maxCustomers) {
                        $limitReached = true;
                        break;
                    }

                    $profileName = $secret['profile'] ?? '';
                    $comment = trim((string)($secret['comment'] ?? ''));

                    $matchedPkg = $this->_matchPackageForCustomer($packages, $profileName, null, $comment);
                    $packageId = $matchedPkg?->id ?? ($existing?->package_id ?? $packages->first()?->id);

                    // Preserve existing custom name if not generic, else check secret comment
                    if ($existing && !empty($existing->name) && $existing->name !== $username && !str_starts_with($existing->name, 'pppoe_')) {
                        $name = $existing->name;
                    } elseif (!empty($comment)) {
                        $name = preg_replace('/^(NODERA|PPPOE)\s*-\s*/i', '', $comment);
                    } else {
                        $name = $username;
                    }

                    $customer = Customer::updateOrCreate(
                        [
                            'pppoe_username' => $username,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                        ],
                        [
                            'name' => $name,
                            'connection_type' => 'pppoe',
                            'pppoe_password' => (!empty($secret['password'])) ? $secret['password'] : null,
                            'status' => 'active',
                            'package_id' => $packageId,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                            'isolation_date' => $isolationDate,
                        ]
                    );

                    if ($customer->wasRecentlyCreated) $imported++; else $updated++;
                    $pppoeCount++;
                }
            }

            // 2. Sync ARP / Static IP (Cross-referenced with Simple Queues & DHCP Leases)
            if (!$limitReached && in_array($syncType, ['all', 'arp', 'queue'])) {
                // Fetch Simple Queues to get real customer names & rate limits
                $queues = [];
                try {
                    $queues = $mik->query('/queue/simple/print');
                } catch (\Throwable $e) {}

                $queueByIp = [];
                foreach ($queues as $q) {
                    $dynamic = $q['dynamic'] ?? 'false';
                    $invalid = $q['invalid'] ?? 'false';
                    if ($dynamic === 'true' || $invalid === 'true') continue;

                    $rawTarget = $q['target'] ?? '';
                    $rawLimit = $q['max-limit'] ?? ($q['rate-limit'] ?? '');
                    $qName = trim((string)($q['name'] ?? ''));
                    $qComment = trim((string)($q['comment'] ?? ''));

                    $targetIps = explode(',', $rawTarget);
                    foreach ($targetIps as $tIp) {
                        $cleanIp = trim(explode('/', trim($tIp))[0]);
                        if (filter_var($cleanIp, FILTER_VALIDATE_IP)) {
                            $queueByIp[$cleanIp] = [
                                'name' => $qName,
                                'comment' => $qComment,
                                'max-limit' => $rawLimit,
                            ];
                        }
                    }
                }

                // Fetch DHCP Leases for host-names / comments
                $leases = [];
                try {
                    $leases = $mik->query('/ip/dhcp-server/lease/print');
                } catch (\Throwable $e) {}

                $leaseByIp = [];
                foreach ($leases as $l) {
                    $lIp = trim($l['address'] ?? '');
                    if (filter_var($lIp, FILTER_VALIDATE_IP)) {
                        $leaseByIp[$lIp] = [
                            'comment' => trim((string)($l['comment'] ?? '')),
                            'host-name' => trim((string)($l['host-name'] ?? '')),
                            'mac-address' => trim((string)($l['mac-address'] ?? '')),
                        ];
                    }
                }

                $arpEntries = $mik->getArpTable();
                $defaultPkg = $packages->first();
                $processedIps = [];

                foreach ($arpEntries as $arp) {
                    $ip = trim((string)($arp['address'] ?? ''));
                    $mac = trim((string)($arp['mac_address'] ?? ''));
                    $iface = trim((string)($arp['interface'] ?? ''));
                    $comment = trim((string)($arp['comment'] ?? ''));

                    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) continue;
                    $processedIps[$ip] = true;

                    $existing = Customer::withoutGlobalScopes()
                        ->where('tenant_id', $resolvedTenantId)
                        ->where('router_id', $routerId)
                        ->where('ip_address', $ip)
                        ->first();

                    if (!$existing && $maxCustomers > 0 && ($currentCustomerCount + $imported) >= $maxCustomers) {
                        $limitReached = true;
                        break;
                    }

                    // Resolve Customer Name
                    $name = '';
                    // 1. Preserve existing valid name (if not generic placeholder like "IP 192..." or "Pelanggan 192...")
                    if ($existing && !empty($existing->name) 
                        && !preg_match('/^(IP|Pelanggan|arp_)\s*\d+\.\d+\.\d+\.\d+/i', $existing->name)
                        && !filter_var($existing->name, FILTER_VALIDATE_IP)) {
                        $name = $existing->name;
                    }

                    // 2. Check Queue info (where ISPs usually put client names)
                    if (empty($name) && isset($queueByIp[$ip])) {
                        $qC = preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $queueByIp[$ip]['comment']);
                        $qN = preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $queueByIp[$ip]['name']);
                        if (!empty($qC) && !filter_var($qC, FILTER_VALIDATE_IP)) {
                            $name = $qC;
                        } elseif (!empty($qN) && !filter_var($qN, FILTER_VALIDATE_IP) && !preg_match('/^queue\d+$/i', $qN)) {
                            $name = $qN;
                        }
                    }

                    // 3. Check ARP comment
                    if (empty($name) && !empty($comment)) {
                        $cleanComment = preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $comment);
                        if (!empty($cleanComment) && !filter_var($cleanComment, FILTER_VALIDATE_IP)) {
                            $name = $cleanComment;
                        }
                    }

                    // 4. Check DHCP Lease comment or host-name
                    if (empty($name) && isset($leaseByIp[$ip])) {
                        if (!empty($leaseByIp[$ip]['comment'])) {
                            $name = $leaseByIp[$ip]['comment'];
                        } elseif (!empty($leaseByIp[$ip]['host-name'])) {
                            $name = $leaseByIp[$ip]['host-name'];
                        }
                    }

                    // 5. Fallback
                    if (empty($name)) {
                        $name = "Pelanggan " . $ip;
                    }

                    // Resolve Package by matching Simple Queue bandwidth
                    $qRate = $queueByIp[$ip]['max-limit'] ?? '';
                    $qNameOrComment = ($queueByIp[$ip]['comment'] ?? '') ?: ($queueByIp[$ip]['name'] ?? ($comment ?? ''));
                    $matchedPkg = $this->_matchPackageForCustomer($packages, null, $qRate, $qNameOrComment);
                    $packageId = $matchedPkg?->id ?? ($existing?->package_id ?? $defaultPkg?->id);

                    $dummyUsername = "arp_" . str_replace(['.', ':'], '_', $ip);

                    $customer = Customer::updateOrCreate(
                        [
                            'ip_address' => $ip,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                        ],
                        [
                            'name' => $name,
                            'pppoe_username' => $dummyUsername,
                            'connection_type' => 'static',
                            'ip_address' => $ip,
                            'mac_address' => $mac ?: ($leaseByIp[$ip]['mac-address'] ?? null),
                            'arp_interface' => $iface ?: 'bridge',
                            'auto_arp' => true,
                            'status' => 'active',
                            'package_id' => $packageId,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                            'isolation_date' => $isolationDate,
                        ]
                    );

                    if ($customer->wasRecentlyCreated) $imported++; else $updated++;
                    $arpCount++;
                }

                // Also process static Simple Queues that might not be in ARP table currently
                foreach ($queueByIp as $ip => $qData) {
                    if (isset($processedIps[$ip])) continue;
                    if (!$limitReached && $maxCustomers > 0 && ($currentCustomerCount + $imported) >= $maxCustomers) {
                        $limitReached = true;
                        break;
                    }

                    $existing = Customer::withoutGlobalScopes()
                        ->where('tenant_id', $resolvedTenantId)
                        ->where('router_id', $routerId)
                        ->where('ip_address', $ip)
                        ->first();

                    $name = '';
                    if ($existing && !empty($existing->name) 
                        && !preg_match('/^(IP|Pelanggan|arp_)\s*\d+\.\d+\.\d+\.\d+/i', $existing->name)
                        && !filter_var($existing->name, FILTER_VALIDATE_IP)) {
                        $name = $existing->name;
                    }

                    if (empty($name)) {
                        $qC = preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $qData['comment']);
                        $qN = preg_replace('/^(NODERA|STATIC)\s*-\s*/i', '', $qData['name']);
                        if (!empty($qC) && !filter_var($qC, FILTER_VALIDATE_IP)) {
                            $name = $qC;
                        } elseif (!empty($qN) && !filter_var($qN, FILTER_VALIDATE_IP) && !preg_match('/^queue\d+$/i', $qN)) {
                            $name = $qN;
                        } elseif (isset($leaseByIp[$ip]) && !empty($leaseByIp[$ip]['comment'])) {
                            $name = $leaseByIp[$ip]['comment'];
                        } elseif (isset($leaseByIp[$ip]) && !empty($leaseByIp[$ip]['host-name'])) {
                            $name = $leaseByIp[$ip]['host-name'];
                        } else {
                            $name = "Pelanggan " . $ip;
                        }
                    }

                    $qRate = $qData['max-limit'] ?? '';
                    $qNameOrComment = ($qData['comment'] ?? '') ?: ($qData['name'] ?? '');
                    $matchedPkg = $this->_matchPackageForCustomer($packages, null, $qRate, $qNameOrComment);
                    $packageId = $matchedPkg?->id ?? ($existing?->package_id ?? $defaultPkg?->id);

                    $dummyUsername = "arp_" . str_replace(['.', ':'], '_', $ip);
                    $mac = $leaseByIp[$ip]['mac-address'] ?? null;

                    $customer = Customer::updateOrCreate(
                        [
                            'ip_address' => $ip,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                        ],
                        [
                            'name' => $name,
                            'pppoe_username' => $dummyUsername,
                            'connection_type' => 'static',
                            'ip_address' => $ip,
                            'mac_address' => $mac,
                            'arp_interface' => 'bridge',
                            'auto_arp' => true,
                            'status' => 'active',
                            'package_id' => $packageId,
                            'router_id' => $routerId,
                            'tenant_id' => $resolvedTenantId,
                            'isolation_date' => $isolationDate,
                        ]
                    );

                    if ($customer->wasRecentlyCreated) $imported++; else $updated++;
                    $arpCount++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sync customers transaction failed: ' . $e->getMessage());
            return redirect()->to('/admin/billing/customers')
                ->with('error', 'Gagal melakukan sinkronisasi: ' . $e->getMessage());
        }

        $details = [];
        if ($pppoeCount > 0) $details[] = "PPPoE: {$pppoeCount}";
        if ($arpCount > 0) $details[] = "ARP/Static IP: {$arpCount}";
        $detailStr = !empty($details) ? ' (' . implode(', ', $details) . ')' : '';

        if ($limitReached) {
            $msg = "⚠️ Sinkronisasi dihentikan karena kapasitas kuota pelanggan paket Anda telah mencapai batas ({$maxCustomers} pelanggan pada {$tenant->package_name}). Berhasil mensinkronkan {$imported} pelanggan baru, {$updated} diperbarui{$detailStr}. Silakan upgrade paket langganan Anda di menu Pengaturan untuk melanjutkan.";
            return redirect()->to('/admin/billing/customers')->with('warning', $msg);
        }

        $msg = " Sinkronisasi selesai: {$imported} pelanggan baru, {$updated} diperbarui{$detailStr}.";
        return redirect()->to('/admin/billing/customers')->with('msg', $msg);
    }

    public function downloadTemplate(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $packages = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->get();
        $pkg1 = $packages[0] ?? null;
        $pkg2 = $packages[1] ?? null;

        $routers = \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))->get();
        $r1 = $routers[0] ?? null;
        $r2 = $routers[1] ?? $r1;

        $format = strtolower($request->query('format', 'xlsx'));

        $headers = [
            'Nama Pelanggan*',
            'Tipe Koneksi (pppoe / static / hotspot)',
            'Username PPPoE (Wajib jika PPPoE/Hotspot)',
            'Password PPPoE',
            'IP Address (Wajib jika Static IP)',
            'MAC Address (Opsional untuk ARP)',
            'Nama Paket*',
            'Harga Paket (Rp)',
            'Kecepatan / Profil (cth: 20M/20M)',
            'Router MikroTik (Nama Router)',
            'No HP / WhatsApp',
            'Alamat',
            'Email',
            'Tgl Isolir (1-28)',
            'Latitude',
            'Longitude',
            'Serial Number'
        ];

        $sampleRows = [
            [
                'Budi Santoso', 'pppoe', 'budi_pppoe', 'budi123', '', '',
                $pkg1?->name ?? 'Paket 20 Mbps', (string)($pkg1?->price ?: 150000), $pkg1?->profile_normal ?: '20M/20M',
                $r1?->name ?? 'Router Utama',
                '081234567890', 'Jl. Merdeka No. 10', 'budi@gmail.com', '20', '-6.200000', '106.816666', 'ZTEGC1234567'
            ],
            [
                'Siti Rahma', 'static', '', '', '192.168.1.50', 'AA:BB:CC:DD:EE:FF',
                $pkg2?->name ?? 'Paket 10 Mbps', (string)($pkg2?->price ?: 100000), $pkg2?->profile_normal ?: '10M/10M',
                $r2?->name ?? 'Router Utama',
                '089876543210', 'Jl. Mawar No. 45', 'siti@gmail.com', '15', '', '', ''
            ]
        ];

        if ($format === 'csv') {
            $filename = 'template_import_pelanggan.csv';
            return \App\Services\SimpleExcelService::exportCsv($headers, $sampleRows, $filename);
        }

        $filename = 'template_import_pelanggan.xlsx';
        return \App\Services\SimpleExcelService::exportXlsx($headers, $sampleRows, $filename, 'Template Import');
    }

    public function importCustomers(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file',
        ]);

        $file = $request->file('import_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $allowedExt = ['csv', 'txt', 'xls', 'xlsx'];

        if (!in_array($ext, $allowedExt)) {
            $errMsg = 'Format file tidak didukung. Gunakan file Excel (.xlsx, .xls) atau CSV (.csv).';
            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return redirect()->to('/admin/billing/customers')->with('error', $errMsg);
        }

        $createPppoe = $request->create_pppoe === '1' || $request->create_pppoe === true || $request->create_pppoe === 'true';
        $routerId = $request->input('router_id');
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
        $maxCustomers = $tenant ? (int) $tenant->max_customers : 0;
        $currentCustomerCount = $tenantId 
            ? Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()
            : 0;

        if ($tenant && $maxCustomers > 0 && $currentCustomerCount >= $maxCustomers) {
            $errMsg = "Kapasitas pelanggan telah mencapai batas maksimal paket Anda ({$currentCustomerCount}/{$maxCustomers} pelanggan). Import dibatalkan. Silakan upgrade paket langganan Anda di menu Pengaturan.";
            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return redirect()->to('/admin/billing/customers')->with('error', $errMsg);
        }

        // Get all packages for mapping
        $packages = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->get()
            ->keyBy(fn ($pkg) => strtolower(trim($pkg->name)));

        // Default package fallback if none matches
        $defaultPackage = Package::where('type', '!=', 'subscription')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->first();

        // Get all routers for mapping
        $routers = \App\Models\Mikrotik::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNotNull('tenant_id'))
            ->get();
        $routersByName = $routers->keyBy(fn ($r) => strtolower(trim($r->name)));
        $defaultRouter = $routers->first();

        // Auto-heal schema if serial_number column is missing
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('customers') && !\Illuminate\Support\Facades\Schema::hasColumn('customers', 'serial_number')) {
                \Illuminate\Support\Facades\Schema::table('customers', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('serial_number', 100)->nullable()->after('install_date');
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[importCustomers] Auto-schema ensure serial_number: ' . $e->getMessage());
        }
        $hasSerialCol = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'serial_number');

        $rows = \App\Services\SimpleExcelService::parseFile($file->getPathname(), $ext);

        if (empty($rows)) {
            $errMsg = 'File kosong atau tidak memiliki baris data pelanggan yang valid.';
            if (!$request->header('X-Inertia') && $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return redirect()->to('/admin/billing/customers')->with('error', $errMsg);
        }

        $rowNumber = 0;
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $limitReached = false;
        $colMap = [];

        foreach ($rows as $row) {
            $rowNumber++;

            // Detect and map header row
            if ($rowNumber === 1 && (
                str_contains(strtolower($row[0] ?? ''), 'nama') ||
                str_contains(strtolower($row[0] ?? ''), 'id') ||
                str_contains(strtolower($row[0] ?? ''), 'kode') ||
                str_contains(strtolower($row[1] ?? ''), 'tipe') ||
                str_contains(strtolower($row[0] ?? ''), 'nom')
            )) {
                foreach ($row as $idx => $headerText) {
                    $ht = strtolower(trim((string)$headerText));
                    if (str_contains($ht, 'nama pelanggan') || str_contains($ht, 'nom de') || $ht === 'nama') $colMap['name'] = $idx;
                    elseif (str_contains($ht, 'kode') || str_contains($ht, 'code')) $colMap['code'] = $idx;
                    elseif (str_contains($ht, 'tipe') || str_contains($ht, 'type')) $colMap['conn_type'] = $idx;
                    elseif (str_contains($ht, 'username') || str_contains($ht, 'user')) $colMap['pppoe_user'] = $idx;
                    elseif (str_contains($ht, 'password') || str_contains($ht, 'pass') || str_contains($ht, 'mot de passe')) $colMap['pppoe_pass'] = $idx;
                    elseif (str_contains($ht, 'ip address') || str_contains($ht, 'adresse ip') || $ht === 'ip') $colMap['ip_addr'] = $idx;
                    elseif (str_contains($ht, 'mac') || str_contains($ht, 'adresse mac')) $colMap['mac_addr'] = $idx;
                    elseif (str_contains($ht, 'nama paket') || str_contains($ht, 'nom du forfait') || $ht === 'paket' || $ht === 'forfait') $colMap['pkg_name'] = $idx;
                    elseif (str_contains($ht, 'harga') || str_contains($ht, 'tarif') || str_contains($ht, 'price')) $colMap['pkg_price'] = $idx;
                    elseif (str_contains($ht, 'kecepatan') || str_contains($ht, 'speed') || str_contains($ht, 'profil') || str_contains($ht, 'profile') || str_contains($ht, 'débit')) $colMap['pkg_profile'] = $idx;
                    elseif (str_contains($ht, 'router') || str_contains($ht, 'mikrotik') || str_contains($ht, 'routeur')) $colMap['router_name'] = $idx;
                    elseif (str_contains($ht, 'hp') || str_contains($ht, 'wa') || str_contains($ht, 'phone') || str_contains($ht, 'téléphone')) $colMap['phone'] = $idx;
                    elseif (str_contains($ht, 'alamat') || str_contains($ht, 'address') || str_contains($ht, 'adresse')) $colMap['address'] = $idx;
                    elseif (str_contains($ht, 'email') || str_contains($ht, 'mail')) $colMap['email'] = $idx;
                    elseif (str_contains($ht, 'isolir') || str_contains($ht, 'échéance') || str_contains($ht, 'due')) $colMap['iso_date'] = $idx;
                    elseif (str_contains($ht, 'lat')) $colMap['lat'] = $idx;
                    elseif (str_contains($ht, 'lng') || str_contains($ht, 'lon')) $colMap['lng'] = $idx;
                    elseif (str_contains($ht, 'serial') || str_contains($ht, 'sn') || str_contains($ht, 'série')) $colMap['sn'] = $idx;
                    elseif (str_contains($ht, 'status') || str_contains($ht, 'statut')) $colMap['status'] = $idx;
                }
                continue;
            }

            // Skip instructions
            if (str_starts_with($row[0] ?? '', '===') || str_starts_with($row[0] ?? '', '* =') || str_starts_with($row[0] ?? '', '1.')) {
                continue;
            }

            // Check if export format (15+ cols, col 0 is numeric ID) or standard template format
            $isExportFormat = (count($row) >= 15 && is_numeric($row[0]) && !empty($row[2]));

            if ($isExportFormat) {
                // Export format: ID, Kode, Nama, Tipe, Username, Password, IP, MAC, Paket, Router, Phone, Alamat, Email, Isolir, Lat, Lng, SN, Status
                $code = $row[1] ?? null;
                $name = $row[2] ?? '';
                $connType = strtolower($row[3] ?? 'pppoe');
                $pppoeUser = $row[4] ?? '';
                $pppoePass = $row[5] ?? '';
                $ipAddr = $row[6] ?? '';
                $macAddr = $row[7] ?? '';
                $pkgName = $row[8] ?? '';
                $pkgPrice = null;
                $pkgProfile = null;
                $routerName = $row[9] ?? null;
                $phone = $row[10] ?? '';
                $address = $row[11] ?? '';
                $email = $row[12] ?? '';
                $isoDate = (int) ($row[13] ?? 20);
                $lat = $row[14] ?? null;
                $lng = $row[15] ?? null;
                $sn = $row[16] ?? null;
                $status = strtolower(trim($row[17] ?? 'active'));
            } elseif (!empty($colMap)) {
                // Header-mapped format
                $code = isset($colMap['code']) ? ($row[$colMap['code']] ?? null) : null;
                $name = $row[$colMap['name'] ?? 0] ?? '';
                $connType = strtolower($row[$colMap['conn_type'] ?? 1] ?? 'pppoe');
                $pppoeUser = $row[$colMap['pppoe_user'] ?? 2] ?? '';
                $pppoePass = $row[$colMap['pppoe_pass'] ?? 3] ?? '';
                $ipAddr = $row[$colMap['ip_addr'] ?? 4] ?? '';
                $macAddr = $row[$colMap['mac_addr'] ?? 5] ?? '';
                $pkgName = $row[$colMap['pkg_name'] ?? 6] ?? '';
                $pkgPrice = isset($colMap['pkg_price']) ? ($row[$colMap['pkg_price']] ?? null) : null;
                $pkgProfile = isset($colMap['pkg_profile']) ? ($row[$colMap['pkg_profile']] ?? null) : null;
                $routerName = isset($colMap['router_name']) ? ($row[$colMap['router_name']] ?? null) : null;
                $phone = $row[$colMap['phone'] ?? 10] ?? '';
                $address = $row[$colMap['address'] ?? 11] ?? '';
                $email = $row[$colMap['email'] ?? 12] ?? '';
                $isoDate = (int) ($row[$colMap['iso_date'] ?? 13] ?? 20);
                $lat = $row[$colMap['lat'] ?? 14] ?? null;
                $lng = $row[$colMap['lng'] ?? 15] ?? null;
                $sn = $row[$colMap['sn'] ?? 16] ?? null;
                $status = isset($colMap['status']) ? strtolower(trim($row[$colMap['status']] ?? 'active')) : 'active';
            } elseif (count($row) >= 17) {
                // 17-Column Template format (with Router, Price and Speed profile):
                // 0:Nama*, 1:Tipe, 2:Username, 3:Password, 4:IP, 5:MAC, 6:Paket*, 7:Harga, 8:Kecepatan, 9:Router, 10:Phone, 11:Alamat, 12:Email, 13:Isolir, 14:Lat, 15:Lng, 16:SN
                $code = null;
                $name = $row[0] ?? '';
                $connType = strtolower($row[1] ?? 'pppoe');
                $pppoeUser = $row[2] ?? '';
                $pppoePass = $row[3] ?? '';
                $ipAddr = $row[4] ?? '';
                $macAddr = $row[5] ?? '';
                $pkgName = $row[6] ?? '';
                $pkgPrice = $row[7] ?? null;
                $pkgProfile = $row[8] ?? null;
                $routerName = $row[9] ?? null;
                $phone = $row[10] ?? '';
                $address = $row[11] ?? '';
                $email = $row[12] ?? '';
                $isoDate = (int) ($row[13] ?? 20);
                $lat = $row[14] ?? null;
                $lng = $row[15] ?? null;
                $sn = $row[16] ?? null;
                $status = 'active';
            } elseif (count($row) >= 15) {
                // 15-16 column format (without Router):
                // 0:Nama*, 1:Tipe, 2:Username, 3:Password, 4:IP, 5:MAC, 6:Paket*, 7:Harga, 8:Kecepatan, 9:Phone, 10:Alamat, 11:Email, 12:Isolir, 13:Lat, 14:Lng, 15:SN
                $code = null;
                $name = $row[0] ?? '';
                $connType = strtolower($row[1] ?? 'pppoe');
                $pppoeUser = $row[2] ?? '';
                $pppoePass = $row[3] ?? '';
                $ipAddr = $row[4] ?? '';
                $macAddr = $row[5] ?? '';
                $pkgName = $row[6] ?? '';
                $pkgPrice = $row[7] ?? null;
                $pkgProfile = $row[8] ?? null;
                $routerName = null;
                $phone = $row[9] ?? '';
                $address = $row[10] ?? '';
                $email = $row[11] ?? '';
                $isoDate = (int) ($row[12] ?? 20);
                $lat = $row[13] ?? null;
                $lng = $row[14] ?? null;
                $sn = $row[15] ?? null;
                $status = 'active';
            } else {
                // Legacy Template format (14 cols):
                // 0:Nama*, 1:Tipe, 2:Username, 3:Password, 4:IP, 5:MAC, 6:Paket*, 7:Phone, 8:Alamat, 9:Email, 10:Isolir, 11:Lat, 12:Lng, 13:SN
                $code = null;
                $name = $row[0] ?? '';
                $connType = strtolower($row[1] ?? 'pppoe');
                $pppoeUser = $row[2] ?? '';
                $pppoePass = $row[3] ?? '';
                $ipAddr = $row[4] ?? '';
                $macAddr = $row[5] ?? '';
                $pkgName = $row[6] ?? '';
                $pkgPrice = null;
                $pkgProfile = null;
                $routerName = null;
                $phone = $row[7] ?? '';
                $address = $row[8] ?? '';
                $email = $row[9] ?? '';
                $isoDate = (int) ($row[10] ?? 20);
                $lat = $row[11] ?? null;
                $lng = $row[12] ?? null;
                $sn = $row[13] ?? null;
                $status = 'active';
            }

            $name = trim($name);
            $phone = ltrim(trim($phone), "'");
            $cleanDigits = preg_replace('/\D/', '', (string) $phone);
            $validPhone = strlen($cleanDigits) >= 8 ? $phone : null;

            if (empty($name)) {
                $skipped++;
                continue;
            }

            // Resolve target router ID
            $targetRouterId = $routerId ?: null;
            if (!empty($routerName)) {
                $rKey = strtolower(trim($routerName));
                if (isset($routersByName[$rKey])) {
                    $targetRouterId = $routersByName[$rKey]->id;
                }
            }
            if (!$targetRouterId && $defaultRouter) {
                $targetRouterId = $defaultRouter->id;
            }

            // Parse price
            $parsedPrice = 0;
            if (!empty($pkgPrice)) {
                $rawPrice = trim((string)$pkgPrice);
                $rawPrice = preg_replace('/^[^\d]+/', '', $rawPrice);
                $rawPrice = str_replace(['.', ',', ' '], '', $rawPrice);
                $parsedPrice = (float) $rawPrice;
            }

            $parsedProfile = !empty($pkgProfile) ? trim((string)$pkgProfile) : null;

            // Normalize connection type
            if (str_contains($connType, 'static') || str_contains($connType, 'arp')) {
                $connType = 'static';
            } elseif (str_contains($connType, 'hotspot')) {
                $connType = 'hotspot';
            } else {
                $connType = 'pppoe';
            }

            // Match package by Name or auto-create with price & speed profile
            $matchedPkg = null;
            if (!empty($pkgName)) {
                $pkgKey = strtolower(trim($pkgName));
                if (isset($packages[$pkgKey])) {
                    $matchedPkg = $packages[$pkgKey];
                } else {
                    $speedProfile = !empty($pkgProfile) ? trim($pkgProfile) : '10M/10M';
                    $newPkg = Package::create([
                        'tenant_id'      => $tenantId,
                        'name'           => trim($pkgName),
                        'price'          => $parsedPrice > 0 ? $parsedPrice : 100000,
                        'monthly_price'  => $parsedPrice > 0 ? $parsedPrice : 100000,
                        'type'           => 'internet',
                        'profile_normal' => $speedProfile,
                        'profile_fup'    => $speedProfile,
                        'profile_isolir' => '1M/1M',
                    ]);
                    $packages[$pkgKey] = $newPkg;
                    $matchedPkg = $newPkg;
                }
            } else {
                $matchedPkg = $defaultPackage;
            }

            $pkgId = $matchedPkg ? $matchedPkg->id : null;
            $isoDate = ($isoDate >= 1 && $isoDate <= 28) ? $isoDate : 20;

            // Check capacity limit
            if ($tenant && $maxCustomers > 0 && ($currentCustomerCount + $imported) >= $maxCustomers) {
                $limitReached = true;
                $errors[] = "Batas maksimal paket ({$maxCustomers} pelanggan) tercapai. Baris {$rowNumber} dan seterusnya dilewati.";
                break;
            }

            // Find existing customer by Code, PPPoE Username, IP Address, or Phone (with valid phone length)
            $existing = null;
            if (!empty($code)) {
                $existing = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('code', $code)
                    ->first();
            }
            if (!$existing && !empty($pppoeUser)) {
                $existing = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('pppoe_username', $pppoeUser)
                    ->first();
            }
            if (!$existing && !empty($ipAddr) && $connType === 'static') {
                $existing = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('ip_address', $ipAddr)
                    ->first();
            }
            if (!$existing && !empty($validPhone)) {
                $existing = Customer::withoutGlobalScopes()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('phone', $validPhone)
                    ->first();
            }

            try {
                if ($existing) {
                    $updateData = [
                        'name'            => $name,
                        'connection_type' => $connType,
                        'pppoe_username'  => $pppoeUser ?: $existing->pppoe_username,
                        'pppoe_password'  => $pppoePass ?: $existing->pppoe_password,
                        'ip_address'      => $ipAddr ?: $existing->ip_address,
                        'mac_address'     => $macAddr ?: $existing->mac_address,
                        'package_id'      => $pkgId ?: $existing->package_id,
                        'router_id'       => $targetRouterId ?: $existing->router_id,
                        'phone'           => $validPhone ?: $existing->phone,
                        'address'         => $address ?: $existing->address,
                        'email'           => $email ?: $existing->email,
                        'isolation_date'  => $isoDate,
                        'lat'             => $lat ?: $existing->lat,
                        'lng'             => $lng ?: $existing->lng,
                    ];
                    if ($hasSerialCol && !empty($sn)) {
                        $updateData['serial_number'] = $sn;
                    }
                    if (!empty($status) && in_array($status, ['active', 'inactive', 'isolated', 'paid', 'unpaid'])) {
                        $updateData['status'] = $status;
                    }
                    $existing->update($updateData);
                    $updated++;
                } else {
                    if (!\App\Services\LicenseService::canAddCustomer()) {
                        $errors[] = "Gagal menambahkan {$name}: Batas kuota Community Edition tercapai (Maksimal " . \App\Services\LicenseService::MAX_FREE_CUSTOMERS . " Pelanggan). Silakan aktivasi lisensi Pro.";
                        break;
                    }

                    $createData = [
                        'name'            => $name,
                        'connection_type' => $connType,
                        'pppoe_username'  => $pppoeUser ?: ($connType === 'static' ? "static_" . str_replace(['.', ':'], '_', $ipAddr ?: uniqid()) : null),
                        'pppoe_password'  => $pppoePass ?: ($pppoeUser ? '123456' : null),
                        'ip_address'      => $ipAddr ?: null,
                        'mac_address'     => $macAddr ?: null,
                        'arp_interface'   => 'bridge',
                        'package_id'      => $pkgId,
                        'router_id'       => $targetRouterId ?: null,
                        'phone'           => $validPhone ?: null,
                        'address'         => $address ?: null,
                        'email'           => $email ?: null,
                        'isolation_date'  => $isoDate,
                        'lat'             => $lat ?: null,
                        'lng'             => $lng ?: null,
                        'status'          => in_array($status, ['active', 'inactive', 'isolated', 'paid', 'unpaid']) ? $status : 'active',
                        'tenant_id'       => $tenantId,
                    ];
                    if (!empty($code)) {
                        $createData['code'] = $code;
                    }
                    if ($hasSerialCol && !empty($sn)) {
                        $createData['serial_number'] = $sn;
                    }

                    $newCustomer = Customer::create($createData);

                    // Optionally create secret or ARP / Simple Queue in MikroTik
                    if ($createPppoe && $newCustomer->router_id) {
                        try {
                            $router = \App\Models\Mikrotik::find($newCustomer->router_id);
                            if ($router && $router->is_active) {
                                $mt = new \App\Services\MikrotikService($router);
                                if ($connType === 'static' && !empty($ipAddr)) {
                                    $mt->addArpEntry($ipAddr, $macAddr ?: '00:00:00:00:00:00', 'bridge', "NODERA - {$name}");
                                    $maxLimit = $matchedPkg?->profile_normal ?? '10M/10M';
                                    $mt->addSimpleQueue("STATIC - {$name}", $ipAddr, $maxLimit, "NODERA Static IP");
                                } elseif ($connType === 'pppoe' && !empty($pppoeUser)) {
                                    $mt->createPppoeSecret([
                                        'name' => $pppoeUser,
                                        'password' => $newCustomer->pppoe_password ?: '123456',
                                        'profile' => $matchedPkg?->profile_normal ?: ($matchedPkg?->name ?? 'default'),
                                    ]);
                                }
                            }
                        } catch (\Throwable $e) {
                            \Log::warning("MikroTik provisioning failed on customer import [{$name}]: " . $e->getMessage());
                        }
                    }

                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Baris {$rowNumber} ({$name}): {$e->getMessage()}";
                $skipped++;
            }
        }

        $message = "Import selesai: {$imported} pelanggan baru ditambahkan, {$updated} pelanggan diperbarui.";
        if ($skipped > 0) {
            $message .= " ({$skipped} dilewati/gagal)";
        }

        if (!$request->header('X-Inertia') && $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        }

        session()->flash('msg', $message);
        session()->flash('success', $message);
        if (!empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return redirect()->to('/admin/billing/customers');
    }

    // ========== HELPERS ==========
    private function _unisolateCustomer(array $customer, bool $force = false)
    {
        $wasIsolated = ($customer['status'] ?? '') === 'isolated';

        // 1. Update status di DB
        DB::table('customers')->where('id', $customer['id'])->update(['status' => 'active']);

        // JIKA PELANGGAN SEBELUMNYA TIDAK TERISOLIR (SUDAH AKTIF) DAN BUKAN DIPAKSA:
        if (!$wasIsolated && !$force) {
            return;
        }

        // Dispatch background unisolation job to router
        try {
            \App\Jobs\UnisolateCustomerJob::dispatch($customer['id'], 'Admin Billing');
        } catch (\Throwable $e) {
            Log::error('Unisolate job dispatch error: ' . $e->getMessage());
        }
    }

    private function _isolateCustomer(array $customer, bool $force = false)
    {
        // Lewati jika bukan dipaksa manual dan auto_isolir di paket dimatikan
        if (!$force) {
            if (isset($customer['auto_isolir']) && !$customer['auto_isolir']) {
                Log::info("[BillingController] Skipping isolation for customer #{$customer['id']} ({$customer['name']}): auto_isolir is disabled in payload.");
                return;
            }
            if (!empty($customer['package_id'])) {
                $pkg = DB::table('packages')->where('id', $customer['package_id'])->first();
                if ($pkg && isset($pkg->auto_isolir) && !$pkg->auto_isolir) {
                    Log::info("[BillingController] Skipping isolation for customer #{$customer['id']} ({$customer['name']}): package '{$pkg->name}' auto_isolir is disabled.");
                    return;
                }
            }
        }

        DB::table('customers')->where('id', $customer['id'])->update(['status' => 'isolated']);

        // Dispatch background isolation job to router
        try {
            \App\Jobs\IsolateCustomerJob::dispatch($customer['id'], 'Admin Manual Isolation');
        } catch (\Throwable $e) {
            Log::error('Isolate job dispatch error: ' . $e->getMessage());
        }
    }

    /**
     * Bangun MikrotikService untuk satu pelanggan — pakai router milik customer
     * (router_id) kalau ada, biar isolasi/pemulihan di tenant multi-router
     * nge-sasar router yang benar. Fallback ke $this->mikrotik (config default).
     */
    private function mikrotikForCustomer(array $customer): MikrotikService
    {
        if (!empty($customer['router_id'])) {
            $router = \App\Models\Mikrotik::withoutGlobalScopes()
                ->where('id', $customer['router_id'])
                ->where('is_active', true)
                ->first();

            if ($router) {
                return new MikrotikService([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '',
                    'port' => (int) ($router->port ?: 8728),
                ]);
            }
        }

        return $this->mikrotik;
    }

    // Konfigurasi add-on PAKET ISOLIR tenant aktif (tenant_id dari session)
    private function isolirConfig(): array
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return [];
        }

        $ta = \App\Models\TenantAddon::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('addon', fn ($q) => $q->where('slug', 'paket_isolir'))
            ->first();

        return $ta?->config ?? [];
    }

    // ========== RESET PIN PELANGGAN ==========
    public function resetPin(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $customer->portal_password = bcrypt($pin);
        $customer->save();

        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'success' => true,
                'pin' => $pin,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'code' => $customer->code,
                ],
                'message' => "PIN {$customer->name} berhasil direset ke {$pin}",
            ]);
        }

        return redirect()->back()->with([
            'msg' => "PIN {$customer->name} berhasil direset ke {$pin}",
            'success' => "PIN {$customer->name} berhasil direset ke {$pin}",
            'reset_pin_data' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'pin' => $pin,
            ],
        ]);
    }

    // Sanitasi harga: hapus titik (ribuan), ubah koma jadi titik (desimal)
    private function sanitizePrice(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;
        // Hapus titik (ribuan), ganti koma dengan titik (desimal)
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }

    // ========== EXPORT INVOICES CSV ==========
    public function exportInvoicesCsv(Request $request)
    {
        $status = $request->get('status');
        $processor = $request->get('processor');
        $search = $request->get('search');

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $query = Invoice::query()->with('customer')->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId));

        $user = auth()->user();
        $isAdmin = session('admin_logged_in') || in_array(strtolower($user?->role ?? ''), ['admin', 'superadmin']);
        $isStaffRestricted = ! $isAdmin && (
            session('technician_logged_in') ||
            session('collector_logged_in') ||
            in_array(strtolower($user?->role ?? ''), ['collector', 'kolektor', 'technician', 'teknisi'])
        );

        if ($isStaffRestricted) {
            $staffCollectorId = session('collector_id');
            $staffName = session('collector_name') ?? session('technician_name') ?? $user?->name;
            $cleanName = strtolower(trim($staffName ?? ''));
            $query->where(function ($q) use ($staffCollectorId, $cleanName) {
                $q->where('paid', 0)
                  ->orWhere(function ($sub) use ($staffCollectorId, $cleanName) {
                      $sub->where('paid', 1)
                          ->where(function ($s) use ($staffCollectorId, $cleanName) {
                              if ($staffCollectorId) $s->where('collector_id', $staffCollectorId);
                              if ($cleanName !== '') $s->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanName]);
                          });
                  });
            });
        }

        if ($status && $status !== 'all') {
            if ($status === 'paid') {
                $query->where('paid', 1);
            } elseif ($status === 'pending') {
                $query->where('paid', 0);
            }
        }

        if ($processor && $processor !== 'all') {
            $query->where('processed_by', $processor);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('pppoe_username', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('id', 'desc')->get();

        return view('finance.invoices-pdf', compact('invoices'));
    }

    // ========== MANUAL TRIGGER ISOLATION ==========
    public function triggerAutoIsolation(Request $request)
    {
        try {
            $cron = app(\App\Services\CronService::class);
            $res = $cron->runJob('isolation:check');

            $data = $res['data'] ?? [];
            $isolated = $data['isolated'] ?? 0;
            $unisolated = $data['unisolated'] ?? 0;

            return redirect()->back()->with('msg', "Pengecekan Isolasi MikroTik Selesai: {$isolated} pelanggan di-isolir, {$unisolated} pelanggan di-buka isolir.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Gagal menjalankan isolasi MikroTik: " . $e->getMessage());
        }
    }

    // ========== MANUAL TRIGGER WA REMINDERS ==========
    public function triggerWaReminders(Request $request)
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
            $cron = app(\App\Services\CronService::class);
            $res = $cron->sendInvoiceRemindersForTenant($tenantId, true);

            $sent = $res['sent'] ?? 0;
            $failed = $res['failed'] ?? 0;
            $total = $res['total_processed'] ?? 0;

            $msg = "Pengingat WhatsApp Selesai: {$sent} terkirim, {$failed} gagal dari total {$total} tagihan tertunggak.";

            if ($request->header('X-Inertia') || !$request->expectsJson()) {
                return back()->with('success', $msg);
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $res,
            ]);
        } catch (\Exception $e) {
            if ($request->header('X-Inertia') || !$request->expectsJson()) {
                return back()->with('error', "Gagal mengirim pengingat WhatsApp: " . $e->getMessage());
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== AUTO INVOICE SETTINGS (TOP NAV) ==========
    public function getAutoInvoiceSettings()
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $isAuto = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_AUTO_GENERATE')->value('value') ?? '1';
        $genDay = (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_GENERATE_DAY')->value('value') ?? 1);
        $dueDay = (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_DUE_DAY')->value('value') ?? 20);
        $autoWa = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_AUTO_WA')->value('value') ?? '1';

        $reminderAuto = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_AUTO')->value('value')
            ?? Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'NOTIF_REMINDER_ENABLED')->value('value')
            ?? '1';
        $reminderDaysSetting = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_REMINDER_DAYS')->value('value') ?? '3,1,0';
        $reminderDays = array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $reminderDaysSetting)), fn($v) => is_numeric($v))));
        if (empty($reminderDays)) {
            $reminderDays = [3, 1, 0];
        }

        $totalActive = Customer::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $currentMonth = now()->format('Y-m');
        $alreadyGenerated = Invoice::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('period', $currentMonth)
            ->count();

        return response()->json([
            'success' => true,
            'settings' => [
                'is_auto' => $isAuto === '1' || $isAuto === 'true' || $isAuto === true,
                'generate_day' => max(1, min(28, $genDay)),
                'due_day' => max(1, min(28, $dueDay)),
                'auto_wa' => $autoWa === '1' || $autoWa === 'true' || $autoWa === true,
                'reminder_auto' => $reminderAuto === '1' || $reminderAuto === 'true' || $reminderAuto === true,
                'reminder_days' => $reminderDays,
                'total_active_customers' => $totalActive,
                'current_month_generated_count' => $alreadyGenerated,
                'current_month_label' => now()->translatedFormat('F Y'),
            ],
        ]);
    }

    public function saveAutoInvoiceSettings(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');

        $isAuto = filter_var($request->input('is_auto', $request->input('auto_generate_invoice', false)), FILTER_VALIDATE_BOOLEAN);
        $generateDay = (int) $request->input('generate_day', $request->input('invoice_generate_day', 1));
        if ($generateDay < 1) $generateDay = 1;
        if ($generateDay > 28) $generateDay = 28;

        $dueDay = $request->input('due_day');
        $autoWa = filter_var($request->input('auto_wa', $request->input('auto_wa_invoice_created', false)), FILTER_VALIDATE_BOOLEAN);
        $reminderAuto = filter_var($request->input('reminder_auto', $request->input('auto_wa_reminder', false)), FILTER_VALIDATE_BOOLEAN);
        $reminderDays = $request->input('reminder_days', $request->input('wa_reminder_days', [1, 3]));

        Setting::setValue('INVOICE_AUTO_GENERATE', $isAuto ? '1' : '0', $tenantId);
        Setting::setValue('INVOICE_GENERATE_DAY', (string) $generateDay, $tenantId);
        if ($dueDay !== null) {
            Setting::setValue('INVOICE_DUE_DAY', (string) $dueDay, $tenantId);
        }
        Setting::setValue('INVOICE_AUTO_WA', $autoWa ? '1' : '0', $tenantId);
        Setting::setValue('INVOICE_REMINDER_AUTO', $reminderAuto ? '1' : '0', $tenantId);
        Setting::setValue('NOTIF_REMINDER_ENABLED', $reminderAuto ? '1' : '0', $tenantId);

        if (is_array($reminderDays)) {
            $days = implode(',', $reminderDays);
            Setting::setValue('INVOICE_REMINDER_DAYS', $days, $tenantId);
        }

        $msg = "Pengaturan jadwal auto-generate tagihan berhasil disimpan (Tgl Terbit: {$generateDay}).";

        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function generateInvoicesNow(Request $request)
    {
        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id');
        $cronService = app(\App\Services\CronService::class);
        $result = $cronService->generateInvoicesForTenant($tenantId);

        $msg = "Tagihan bulan ini berhasil diproses: {$result['generated']} tagihan baru dibuat, {$result['skipped']} sudah ada/dilewati.";

        if (!$request->header('X-Inertia') && ($request->wantsJson() || $request->ajax() || $request->isJson())) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $result,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Convert raw bps or shorthand string to human readable bitrate (e.g. 10485760 -> 10M, 512000 -> 512k)
     */
    private function _formatBitrateToHuman($bps): string
    {
        $bps = trim((string)$bps);
        if (empty($bps) || $bps === '0') {
            return '0';
        }

        if (is_numeric($bps)) {
            $num = (int)$bps;
            if ($num <= 0) return '0';

            // Check Gigabits
            if ($num >= 1000000000) {
                if ($num % 1073741824 === 0) return ($num / 1073741824) . 'G';
                if ($num % 1000000000 === 0) return ($num / 1000000000) . 'G';
                $gb = round($num / 1000000000, 1);
                return (string)($gb == (int)$gb ? (int)$gb : $gb) . 'G';
            }

            // Check Megabits
            if ($num >= 1000000) {
                if ($num % 1048576 === 0) return ($num / 1048576) . 'M';
                if ($num % 1000000 === 0) return ($num / 1000000) . 'M';
                if (abs($num - 1048576) < 50000) return '1M';
                $mb = round($num / 1000000, 1);
                return (string)($mb == (int)$mb ? (int)$mb : $mb) . 'M';
            }

            // Check Kilobits
            if ($num >= 1000) {
                if ($num % 1024 === 0) return ($num / 1024) . 'k';
                if ($num % 1000 === 0) return ($num / 1000) . 'k';
                $kb = round($num / 1000);
                return $kb . 'k';
            }

            return (string)$num;
        }

        if (preg_match('/^(\d+)\s*(mbps|mb|m|k|kbps|g|gbps)?$/i', $bps, $m)) {
            $n = $m[1];
            $u = strtoupper($m[2] ?? 'M');
            if (str_starts_with($u, 'M') || $u === '') return "{$n}M";
            if (str_starts_with($u, 'K')) return "{$n}k";
            if (str_starts_with($u, 'G')) return "{$n}G";
        }

        return $bps;
    }

    /**
     * Normalize max-limit or rate-limit into upload/download standard format (e.g. 10M/10M)
     */
    private function _normalizeRateLimit(string $maxLimit): string
    {
        $cleaned = trim($maxLimit);
        if (empty($cleaned) || $cleaned === '0/0' || $cleaned === '0') {
            return '0/0';
        }

        $parts = explode('/', $cleaned);
        if (count($parts) === 2) {
            $up = $this->_formatBitrateToHuman($parts[0]);
            $down = $this->_formatBitrateToHuman($parts[1]);
            if ($up === '0' && $down === '0') {
                return '0/0';
            }
            return "{$up}/{$down}";
        } elseif (count($parts) === 1) {
            $rate = $this->_formatBitrateToHuman($parts[0]);
            if ($rate === '0') return '0/0';
            return "{$rate}/{$rate}";
        }

        return '10M/10M';
    }

    /**
     * Intelligently match a package for a customer from router profile, rate limit, or comment/name
     */
    private function _matchPackageForCustomer($packages, ?string $profileName = null, ?string $rateLimit = null, ?string $commentOrName = null): ?Package
    {
        if (!$packages || $packages->isEmpty()) return null;

        $profileClean = trim(strtolower((string)$profileName));
        $rateNorm = !empty($rateLimit) ? $this->_normalizeRateLimit($rateLimit) : null;
        $commentClean = trim(strtolower((string)$commentOrName));

        // 1. Direct match on profile_normal or package name with profileName
        if (!empty($profileClean)) {
            $found = $packages->first(function ($p) use ($profileClean) {
                return strtolower(trim((string)$p->profile_normal)) === $profileClean
                    || strtolower(trim((string)$p->name)) === $profileClean;
            });
            if ($found) return $found;
        }

        // 2. Match by normalized speed / rate limit (e.g. "10M/10M")
        if (!empty($rateNorm) && $rateNorm !== '0/0') {
            $found = $packages->first(function ($p) use ($rateNorm) {
                $pNorm = $this->_normalizeRateLimit($p->profile_normal ?? '');
                if ($pNorm === $rateNorm) return true;
                if (str_contains(strtolower($p->name), strtolower($rateNorm))) return true;
                return false;
            });
            if ($found) return $found;
        }

        // 3. Match by comment / queue name containing package name or vice versa
        if (!empty($commentClean)) {
            $found = $packages->first(function ($p) use ($commentClean) {
                $pName = strtolower(trim((string)$p->name));
                if (empty($pName)) return false;
                return str_contains($commentClean, $pName) || str_contains($pName, $commentClean);
            });
            if ($found) return $found;
        }

        // 4. Try matching speed extracted from profileName (e.g. "profile-10M" -> "10M/10M")
        if (!empty($profileClean)) {
            $extractedRate = $this->_normalizeRateLimit($profileClean);
            if (!empty($extractedRate) && $extractedRate !== '0/0') {
                $found = $packages->first(function ($p) use ($extractedRate) {
                    $pNorm = $this->_normalizeRateLimit($p->profile_normal ?? '');
                    return $pNorm === $extractedRate || str_contains(strtolower($p->name), strtolower($extractedRate));
                });
                if ($found) return $found;
            }
        }

        return null;
    }
}
