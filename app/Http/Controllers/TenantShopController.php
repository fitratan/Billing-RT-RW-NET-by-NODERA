<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Tenant;
use App\Services\TenantTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantShopController extends Controller
{
    /**
     * Resolve tenant from subdomain or request attribute.
     */
    private function resolveTenant(Request $request, ?string $tenantSlug = null): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
        $subdomain = explode('.', $host)[0] ?? '';

        // Subdomain shop.* is strictly Superadmin shop
        if ($subdomain === 'shop') {
            return null;
        }

        if ($tenantSlug) {
            return Tenant::withoutGlobalScopes()->where('slug', $tenantSlug)->where('is_active', true)->first();
        }

        if ($subdomain && $host !== $baseDomain && !in_array($subdomain, ['www', 'panel', 'api', 'admin', 'shop'])) {
            $tenant = Tenant::withoutGlobalScopes()->where('slug', $subdomain)->where('is_active', true)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        if ($request->has('tenant')) {
            return Tenant::withoutGlobalScopes()->where('slug', $request->query('tenant'))->where('is_active', true)->first();
        }

        if ($request->filled('tenant_id')) {
            return Tenant::withoutGlobalScopes()->where('id', (int) $request->input('tenant_id'))->where('is_active', true)->first();
        }

        $tenantId = $request->attributes->get('tenant_id');
        if ($tenantId) {
            return Tenant::withoutGlobalScopes()->find($tenantId);
        }

        return null;
    }

    /**
     * Display Tenant Landing & E-Commerce Storefront (Identical to shop.dgtlnetsolution.com).
     */
    public function index(Request $request, ?string $tenantSlug = null)
    {
        $tenant = $this->resolveTenant($request, $tenantSlug);

        if (!$tenant) {
            return app(LandingController::class)->landing();
        }

        session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug, 'tenant_name' => $tenant->name]);

        $settings = $tenant->settings ?? [];
        $selectedCategorySlug = $request->query('category');
        $search = $request->query('q');
        $sortBy = $request->query('sort', 'featured');

        // 1. Categories for Tenant
        try {
            $categories = ProductCategory::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->withCount(['activeProducts' => fn($q) => $q->where('tenant_id', $tenant->id)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            $categories = collect();
        }

        // 2. Fetch Hotspot Voucher Packages from packages table
        $voucherPackages = collect();
        try {
            $voucherPackages = Package::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) {
                    $q->where('type', 'hotspot')
                      ->orWhereNotNull('profile_normal')
                      ->orWhere('profile_normal', '!=', '');
                })
                ->where('is_active', true)
                ->get();
        } catch (\Throwable $e) {
        }

        // Add virtual "Voucher WiFi Hotspot" category if tenant has packages
        $voucherCat = null;
        if ($voucherPackages->isNotEmpty()) {
            $voucherCat = new ProductCategory([
                'id' => 999999,
                'name' => 'Voucher WiFi Hotspot',
                'slug' => 'voucher-wifi',
                'icon' => 'fa-wifi',
                'description' => 'Paket voucher internet WiFi hotspot instan',
                'is_active' => true,
                'sort_order' => -1,
            ]);
            $voucherCat->id = 999999;
            $voucherCat->products_count = $voucherPackages->count();
            $voucherCat->active_products_count = $voucherPackages->count();
            $categories->prepend($voucherCat);
        }

        // 3. Products for Tenant
        $products = collect();
        try {
            $query = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->with('category');

            if (!empty($selectedCategorySlug) && $selectedCategorySlug !== 'voucher-wifi') {
                $query->whereHas('category', function ($q) use ($selectedCategorySlug) {
                    $q->where('slug', $selectedCategorySlug);
                });
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('sku', 'LIKE', "%{$search}%")
                        ->orWhere('short_description', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            match ($sortBy) {
                'price_asc' => $query->orderBy('price', 'asc'),
                'price_desc' => $query->orderBy('price', 'desc'),
                'newest' => $query->orderBy('created_at', 'desc'),
                default => $query->orderBy('is_featured', 'desc')->orderBy('sort_order', 'asc')->orderBy('id', 'desc'),
            };

            $products = $query->get();
        } catch (\Throwable $e) {
            $products = collect();
        }

        // Include Hotspot Packages as Products in Storefront Grid
        if ($voucherCat && $voucherPackages->isNotEmpty()) {
            foreach ($voucherPackages as $pkg) {
                $pkgId = $pkg->id;
                $pkgName = $pkg->name ?? 'Hotspot Voucher';
                $profile = !empty($pkg->profile) ? $pkg->profile : (!empty($pkg->profile_normal) ? $pkg->profile_normal : $pkgName);
                $timeLabel = !empty($pkg->time_limit) ? $pkg->time_limit : (!empty($pkg->validity) ? $pkg->validity : (!empty($pkg->duration_days) ? "{$pkg->duration_days} Hari" : "Aktif"));
                
                $bwDown = $pkg->bandwidth_down ?? null;
                $bwUp = $pkg->bandwidth_up ?? null;
                $rateLimit = ($bwUp && $bwDown) ? "{$bwUp}M/{$bwDown}M" : ($bwDown ? "{$bwDown}M" : ($pkg->rate_limit ?? ''));
                $price = (float) ($pkg->price ?? 0);

                $desc = "⚡ Voucher Hotspot Online • Masa Aktif: {$timeLabel}" . ($rateLimit ? " • Speed: {$rateLimit}" : "") . " • Profil: {$profile}";

                $virtualProd = new Product([
                    'tenant_id' => $tenant->id,
                    'category_id' => 999999,
                    'name' => "Voucher WiFi: {$pkgName}",
                    'slug' => 'voucher-' . Str::slug($pkgName) . '-' . $pkgId,
                    'sku' => "VCH-{$pkgId}",
                    'product_type' => 'voucher',
                    'voucher_package_id' => $pkgId,
                    'price' => $price,
                    'original_price' => $price,
                    'stock' => 999,
                    'badge' => 'WIFI',
                    'image' => '/images/logo.png',
                    'short_description' => $desc,
                    'description' => "Paket Voucher WiFi {$pkgName}. Profil MikroTik: {$profile}. Masa Aktif: {$timeLabel}. Akun otomatis aktif setelah pesanan di-ACC.",
                    'specifications' => "Profil Hotspot: {$profile}\nMasa Aktif: {$timeLabel}\nKecepatan: " . ($rateLimit ?: 'Standar'),
                    'is_featured' => true,
                    'is_active' => true,
                    'sort_order' => -1,
                ]);
                $virtualProd->id = 900000 + $pkgId;
                $virtualProd->voucher_profile = $profile;
                $virtualProd->setRelation('category', $voucherCat);
                $products->prepend($virtualProd);
            }
        }

        // 4. Bank Accounts & QRIS of Tenant
        $bankAccounts = BankAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $qrisGateway = PaymentGateway::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('gateway', 'manual')
            ->where('is_active', true)
            ->first();

        $qrisConfig = $qrisGateway?->config_json ?? [];
        $qrisImageUrl = QRISController::resolveQrisImageUrl($qrisConfig);
        $qris = $qrisImageUrl ? ['image' => $qrisImageUrl, 'merchant_name' => $qrisConfig['merchant_name'] ?? $tenant->name] : null;

        // 5. Hotspot Router Info
        $router = Mikrotik::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        $dnsName = $router?->dns_name ?? 'wifi.hotspot';

        // 6. Tenant Company Info for Titan Header & Footer
        $adminPhone = $settings['landing_whatsapp'] ?? $tenant->phone ?? '';
        $cleanPhone = preg_replace('/[^0-9]/', '', $adminPhone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $company = [
            'name' => $tenant->name,
            'logo' => \App\Models\Setting::resolveLogoUrl($tenant->logo, asset('images/logo.png?v=33')),
            'phone_wa' => $cleanPhone,
            'address' => $settings['landing_address'] ?? $tenant->address ?? 'Layanan WiFi Hotspot & Internet',
            'landing_title' => $settings['landing_title'] ?? "Selamat Datang di {$tenant->name}",
            'landing_tagline' => $settings['landing_tagline'] ?? 'Solusi Internet Cepat, Stabil, & Terjangkau',
            'landing_description' => $settings['landing_description'] ?? 'Penyedia layanan internet WiFi Hotspot dan paket berkualitas tinggi.',
            'landing_banner' => $settings['landing_banner'] ?? null,
            'dns_name' => $dnsName,
        ];

        $shopSlides = $settings['shop_slides'] ?? null;
        $isTenantShop = true;
        $checkoutUrl = '/tenant-shop/checkout';

        return view('shop.index', compact(
            'categories',
            'products',
            'selectedCategorySlug',
            'search',
            'sortBy',
            'bankAccounts',
            'company',
            'qris',
            'shopSlides',
            'isTenantShop',
            'tenant',
            'checkoutUrl'
        ));
    }

    /**
     * Get Product detail as JSON for Quick View modal on tenant subdomain.
     */
    public function getProductDetail(Request $request, $id): JsonResponse
    {
        $tenantId = session('tenant_id') ?? $request->attributes->get('tenant_id');
        
        // Handle virtual voucher product
        if ($id >= 900000) {
            $pkgId = $id - 900000;
            $pkg = null;
            if (Schema::hasTable('voucher_packages')) {
                $pkg = DB::table('voucher_packages')->where('id', $pkgId)->first();
            }
            if (!$pkg && Schema::hasTable('packages')) {
                $pkg = Package::withoutGlobalScopes()->find($pkgId);
            }

            if ($pkg) {
                $pkgName = $pkg->name ?? 'Hotspot Voucher';
                $profile = !empty($pkg->profile) ? $pkg->profile : (!empty($pkg->profile_normal) ? $pkg->profile_normal : $pkgName);
                $timeLabel = !empty($pkg->time_limit) ? $pkg->time_limit : (!empty($pkg->validity) ? $pkg->validity : (!empty($pkg->duration_days) ? "{$pkg->duration_days} Hari" : "Aktif"));
                $bwDown = $pkg->bandwidth_down ?? null;
                $bwUp = $pkg->bandwidth_up ?? null;
                $rateLimit = ($bwUp && $bwDown) ? "{$bwUp}M/{$bwDown}M" : ($bwDown ? "{$bwDown}M" : ($pkg->rate_limit ?? ''));
                $price = (float) ($pkg->price ?? 0);

                return response()->json([
                    'success' => true,
                    'product' => [
                        'id' => $id,
                        'name' => "Voucher WiFi: {$pkgName}",
                        'slug' => 'voucher-' . Str::slug($pkgName) . '-' . $pkgId,
                        'sku' => "VCH-{$pkgId}",
                        'price' => $price,
                        'formatted_price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'original_price' => $price,
                        'formatted_original_price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'discount_percent' => 0,
                        'stock' => 999,
                        'badge' => 'WIFI',
                        'image' => '/images/logo.png',
                        'gallery' => [],
                        'short_description' => "⚡ Voucher Hotspot Online • Masa Aktif: {$timeLabel}" . ($rateLimit ? " • Speed: {$rateLimit}" : "") . " • Profil: {$profile}",
                        'description' => "Paket Voucher WiFi {$pkgName}. Profil MikroTik: <b>{$profile}</b>. Masa Aktif: {$timeLabel}. Akun otomatis aktif setelah admin meng-ACC pesanan.",
                        'specifications' => "Profil Hotspot: {$profile}\nMasa Aktif: {$timeLabel}\nKecepatan: " . ($rateLimit ?: 'Standar'),
                        'category_name' => 'Voucher WiFi Hotspot',
                        'category' => ['id' => 999999, 'name' => 'Voucher WiFi Hotspot', 'slug' => 'voucher-wifi'],
                    ],
                ]);
            }
        }

        $product = Product::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with('category')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => $product->price,
                'formatted_price' => $product->formatted_price,
                'original_price' => $product->original_price,
                'formatted_original_price' => $product->formatted_original_price,
                'discount_percent' => $product->discount_percent,
                'stock' => $product->stock,
                'badge' => $product->badge,
                'image' => $product->image ? (str_starts_with($product->image, 'http') ? $product->image : "/storage/{$product->image}") : '/images/logo.png',
                'gallery' => $product->gallery ?: [],
                'short_description' => $product->short_description,
                'description' => $product->description,
                'specifications' => $product->specifications,
                'category_name' => $product->category?->name,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
            ],
        ]);
    }

    /**
     * Process checkout for tenant shop & trigger Telegram approval notification.
     */
    public function checkout(Request $request, TenantTelegramService $telegramService)
    {
        return $this->storeOrder($request, $telegramService);
    }

    public function storeOrder(Request $request, TenantTelegramService $telegramService)
    {
        $tenant = $this->resolveTenant($request);
        $tenantId = $tenant?->id ?? (int) $request->input('tenant_id');

        if (!$tenant && $tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        }

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Toko tenant tidak ditemukan atau tidak aktif.'], 404);
        }

        $tenantId = $tenant->id;

        $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:50',
            'shipping_address' => 'nullable|string|max:500',
            'voucher_username' => 'nullable|string|max:100',
            'voucher_password' => 'nullable|string|max:100',
            'payment_method' => 'required|string|max:50',
        ]);

        $rawItems = $request->input('cart_items') ?? $request->input('items');
        if (is_string($rawItems)) {
            $items = json_decode($rawItems, true);
        } else {
            $items = $rawItems;
        }

        if (empty($items) || !is_array($items)) {
            return response()->json(['success' => false, 'message' => 'Keranjang belanja kosong.'], 422);
        }

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $orderItemsData = [];
            $voucherUsername = null;
            $voucherPassword = null;
            $voucherProfile = null;

            // Check if multi-voucher accounts submitted
            $voucherAccounts = $request->input('voucher_accounts');
            if (is_string($voucherAccounts)) {
                $voucherAccounts = json_decode($voucherAccounts, true);
            }

            $detectedProfiles = [];

            foreach ($items as $item) {
                $productId = (int) ($item['id'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));

                // Virtual Voucher item
                if ($productId >= 900000) {
                    $pkgId = $productId - 900000;
                    $pkg = null;
                    if (Schema::hasTable('voucher_packages')) {
                        $pkg = DB::table('voucher_packages')->where('id', $pkgId)->first();
                    }
                    if (!$pkg && Schema::hasTable('packages')) {
                        $pkg = Package::withoutGlobalScopes()->find($pkgId);
                    }

                    if ($pkg) {
                        $price = (float) ($pkg->price ?? 0);
                        $itemSubtotal = $price * $qty;
                        $subtotal += $itemSubtotal;
                        $profile = !empty($pkg->profile) ? $pkg->profile : (!empty($pkg->profile_normal) ? $pkg->profile_normal : $pkg->name);
                        $detectedProfiles[] = $profile;

                        $orderItemsData[] = [
                            'product_id' => null,
                            'product_name' => "Voucher WiFi: {$pkg->name} (Profil: {$profile})",
                            'product_image' => '/images/logo.png',
                            'price' => $price,
                            'quantity' => $qty,
                            'subtotal' => $itemSubtotal,
                        ];
                    }
                    continue;
                }

                $product = Product::withoutGlobalScopes()->find($productId);
                if (!$product || !$product->is_active) {
                    continue;
                }

                $itemSubtotal = $product->price * $qty;
                $subtotal += $itemSubtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => $itemSubtotal,
                ];

                if ($product->stock >= $qty) {
                    $product->decrement('stock', $qty);
                }
            }

            if (empty($orderItemsData)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada item valid di dalam keranjang.'], 422);
            }

            // Parse voucher credentials
            $generatedVouchers = [];
            if (!empty($voucherAccounts) && is_array($voucherAccounts)) {
                $generatedVouchers = $voucherAccounts;
            } elseif (!empty($detectedProfiles)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $request->customer_phone);
                $phoneSuffix = substr($cleanPhone, -4) ?: rand(1000, 9999);
                $vIdx = 0;
                foreach ($orderItemsData as $it) {
                    if (str_contains($it['product_name'] ?? '', 'Voucher') || !empty($detectedProfiles)) {
                        $qty = (int) ($it['quantity'] ?? 1);
                        $pkgName = $it['product_name'] ?? 'Voucher WiFi';
                        for ($q = 0; $q < $qty; $q++) {
                            $vIdx++;
                            $randCode = substr(md5(uniqid((string) mt_rand(), true)), 0, 6);
                            $vUser = $vIdx === 1 ? ($request->input('voucher_username') ?: 'user' . $phoneSuffix) : 'user' . $phoneSuffix . '_' . $vIdx;
                            $vPass = $vIdx === 1 ? ($request->input('voucher_password') ?: $randCode) : $randCode;
                            $prof = !empty($detectedProfiles) ? ($detectedProfiles[$q % count($detectedProfiles)] ?? 'default') : 'default';
                            $generatedVouchers[] = [
                                'package_name' => $pkgName,
                                'username' => $vUser,
                                'password' => $vPass,
                                'profile' => $prof,
                            ];
                        }
                    }
                }
            }

            if (!empty($generatedVouchers)) {
                if (count($generatedVouchers) === 1) {
                    $voucherUsername = $generatedVouchers[0]['username'] ?? null;
                    $voucherPassword = $generatedVouchers[0]['password'] ?? null;
                    $voucherProfile = $generatedVouchers[0]['profile'] ?? 'default';
                } else {
                    $voucherUsername = json_encode($generatedVouchers);
                    $voucherPassword = json_encode(array_column($generatedVouchers, 'password'));
                    $voucherProfile = implode(', ', array_unique(array_filter(array_column($generatedVouchers, 'profile'))));
                }
            }

            $shippingAddress = $request->input('shipping_address');
            if (empty($shippingAddress) || trim($shippingAddress) === '') {
                $shippingAddress = !empty($voucherUsername) ? 'Layanan Voucher Online' : 'Alamat belum diisi';
            }

            $paymentProofPath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = 'proof_shop_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $paymentProofPath = $file->storeAs('payment_proofs', $filename, 'public');
            }

            $isQris = stripos((string) $request->payment_method, 'qris') !== false;
            $uniqueCode = 0;
            $orderTotal = $subtotal;
            $dynamicQrisString = null;
            $expiresAt = null;
            $tempOrderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            if ($isQris) {
                $pltRes = \App\Services\PlatformPaymentService::createPlatformTransaction([
                    'ref_id'         => $tempOrderNumber,
                    'amount'         => (float) $subtotal,
                    'payment_method' => 'qris',
                    'customer_name'  => $request->customer_name,
                    'customer_email' => $request->customer_email,
                    'customer_phone' => $request->customer_phone ?? '',
                    'description'    => 'Order Toko ' . $tempOrderNumber,
                ]);

                if ($pltRes['success'] && !empty($pltRes['dynamic_qris'])) {
                    $dynamicQrisString = $pltRes['dynamic_qris'];
                    $orderTotal = (float) ($pltRes['total_amount'] ?? $subtotal);
                    $expiresAt = $pltRes['expires_at'] ?? now()->addMinutes(15);
                } else {
                    $expiresAt = now()->addMinutes(15);
                }
            }

            $order = ShopOrder::create([
                'tenant_id'                   => $tenantId,
                'order_number'                => $tempOrderNumber,
                'customer_name'               => $request->customer_name,
                'customer_phone'              => $request->customer_phone,
                'customer_email'              => $request->customer_email,
                'shipping_address'            => $request->shipping_address,
                'customer_notes'              => $request->customer_notes,
                'voucher_username'            => $voucherUsername,
                'voucher_password'            => $voucherPassword,
                'voucher_profile'             => $voucherProfile,
                'voucher_created_in_mikrotik' => false,
                'subtotal'                    => $subtotal,
                'shipping_fee'                => 0,
                'total_amount'                => $orderTotal,
                'unique_code'                 => $uniqueCode > 0 ? $uniqueCode : null,
                'dynamic_qris_string'         => $dynamicQrisString,
                'expires_at'                  => $expiresAt,
                'payment_method'              => $request->payment_method,
                'payment_bank'                => $isQris ? 'QRIS Dinamis' : $request->payment_bank,
                'payment_proof'               => $paymentProofPath,
                'payment_status'              => $paymentProofPath ? 'paid' : 'unpaid',
                'order_status'                => 'pending',
            ]);

            foreach ($orderItemsData as $orderItem) {
                $orderItem['order_id'] = $order->id;
                ShopOrderItem::create($orderItem);
            }

            DB::commit();

            // Send notification to tenant's Telegram Bot / Group Topic Asynchronously
            try {
                if (config('queue.default') !== 'sync') {
                    \App\Jobs\SendTelegramOrderNotificationJob::dispatch($order->id);
                } else {
                    $telegramService->sendOrderNotification($order);
                }
            } catch (\Throwable $e) {
                Log::warning("Telegram notification dispatch error: " . $e->getMessage());
            }

            // WhatsApp direct confirmation message
            $adminPhone = $tenant?->settings['landing_whatsapp'] ?? $tenant?->phone ?? '6281234567890';
            $cleanAdminPhone = preg_replace('/[^0-9]/', '', $adminPhone);
            if (str_starts_with($cleanAdminPhone, '0')) {
                $cleanAdminPhone = '62' . substr($cleanAdminPhone, 1);
            }

            $storeName = $tenant?->name ?? 'NODERA';
            $waMsg = $order->buildWhatsAppCheckoutMessage($storeName, $orderItemsData);
            $waUrl = "https://wa.me/{$cleanAdminPhone}?text=" . urlencode($waMsg);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'wa_url' => $waUrl,
                'redirect_url' => route('shop.order.success', $order->order_number),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Tenant storeOrder error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Order status JSON for polling on tenant storefront.
     */
    public function getOrderStatus(Request $request, string $orderNumber): JsonResponse
    {
        $order = ShopOrder::withoutGlobalScopes()->where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        $isPaid = ($order->payment_status === 'paid' || $order->order_status === 'completed' || (bool) $order->voucher_created_in_mikrotik);

        $voucher = null;
        if (!empty($order->voucher_username)) {
            $voucher = [
                'username' => $order->voucher_username,
                'password' => $order->voucher_password ?? '',
                'profile'  => $order->voucher_profile ?? 'default',
            ];
        }

        return response()->json([
            'success'        => true,
            'order_number'   => $order->order_number,
            'order_status'   => $order->order_status,
            'payment_status' => $order->payment_status,
            'is_paid'        => $isPaid,
            'voucher'        => $voucher,
            'total_amount'   => (float) $order->total_amount,
            'formatted_total'=> $order->formatted_total,
        ]);
    }
}
