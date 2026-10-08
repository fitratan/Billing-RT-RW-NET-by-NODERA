<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    /**
     * Display the E-Commerce Storefront.
     */
    public function index(Request $request)
    {
        $selectedCategorySlug = $request->query('category');
        $search = $request->query('q');
        $sortBy = $request->query('sort', 'featured');

        if (!\Illuminate\Support\Facades\Schema::hasTable('product_categories') || !\Illuminate\Support\Facades\Schema::hasTable('products')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Shop auto migration notice: " . $e->getMessage());
            }
        }

        // Auto-seed template-hotspot category & products, or update if photos are missing
        if (\Illuminate\Support\Facades\Schema::hasTable('product_categories') && \Illuminate\Support\Facades\Schema::hasTable('products')) {
            try {
                $hasHotspotCat = ProductCategory::withoutGlobalScopes()->where('slug', 'template-hotspot')->exists();
                $hasUpdatedImages = Product::withoutGlobalScopes()
                    ->where('slug', 'template-hotspot-glass-dock')
                    ->where('image', 'LIKE', '%/hotspot-themes/%')
                    ->exists();

                if (!$hasHotspotCat || !$hasUpdatedImages) {
                    if (class_exists(\Database\Seeders\ShopSeeder::class)) {
                        (new \Database\Seeders\ShopSeeder())->run();
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Shop seeder auto-run notice: " . $e->getMessage());
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('product_categories')) {
            $hasCatTenant = \Illuminate\Support\Facades\Schema::hasColumn('product_categories', 'tenant_id');
            $hasProdTenant = \Illuminate\Support\Facades\Schema::hasColumn('products', 'tenant_id');

            $categories = ProductCategory::withoutGlobalScopes()
                ->when($hasCatTenant, function ($q) {
                    $q->where(function ($sq) {
                        $sq->whereNull('tenant_id')->orWhere('tenant_id', 0);
                    });
                })
                ->where('is_active', true)
                ->withCount(['activeProducts' => function ($q) use ($hasProdTenant) {
                    $q->withoutGlobalScopes()
                        ->when($hasProdTenant, function ($sq) {
                            $sq->where(function ($ssq) {
                                $ssq->whereNull('tenant_id')->orWhere('tenant_id', 0);
                            });
                        });
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } else {
            $categories = collect();
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $hasProdTenant = \Illuminate\Support\Facades\Schema::hasColumn('products', 'tenant_id');

            $query = Product::withoutGlobalScopes()
                ->when($hasProdTenant, function ($q) {
                    $q->where(function ($sq) {
                        $sq->whereNull('tenant_id')->orWhere('tenant_id', 0);
                    });
                })
                ->where('is_active', true)
                ->with('category');

            if (!empty($selectedCategorySlug)) {
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

            $products->transform(function ($product) {
                $slug = $product->slug ?? '';
                $catSlug = $product->category?->slug ?? '';

                if ($catSlug === 'template-hotspot' || str_starts_with($slug, 'template-hotspot-') || $slug === 'bundle-30-template-hotspot-mikrotik' || str_contains(strtolower($product->name ?? ''), 'template hotspot')) {
                    $themePath = self::resolveThemePathFromProduct($product);
                    if ($themePath) {
                        $loginImg = "/hotspot-themes/{$themePath}/preview-login.png";
                        $statusImg = "/hotspot-themes/{$themePath}/preview-status.png";

                        if (empty($product->image) || !str_contains($product->image, '/hotspot-themes/')) {
                            $product->image = $loginImg;
                        }
                        $currentGallery = is_array($product->gallery) ? $product->gallery : (is_string($product->gallery) ? json_decode($product->gallery, true) : []);
                        if (empty($currentGallery) || !str_contains(json_encode($currentGallery), '/hotspot-themes/')) {
                            $product->gallery = [$loginImg, $statusImg];
                        }
                    }
                }
                return $product;
            });
        } else {
            $products = collect();
        }

        // Bank Accounts & QRIS from SuperAdmin settings
        $bankAccounts = self::resolveSuperadminBankAccounts();
        $qrisImage = self::resolveSuperadminQris();
        $qris = $qrisImage ? ['image' => $qrisImage] : null;

        $company = Setting::company();

        // Resolve SuperAdmin WhatsApp
        $superadmin = User::where('role', 'superadmin')->first();
        if (empty($company['phone_wa']) && $superadmin && !empty($superadmin->phone)) {
            $phone = preg_replace('/[^0-9]/', '', $superadmin->phone);
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            }
            $company['phone_wa'] = $phone;
        }

        $shopSlides = null;
        $isTenantShop = false;
        $tenant = null;
        $checkoutUrl = '/shop/checkout';

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
     * Resolve relative theme directory path from Product model.
     */
    public static function resolveThemePathFromProduct($product): ?string
    {
        if (!$product) return null;

        $spec = $product->specifications ?? '';
        if (preg_match('/Preview URL:\s*\/shop\/preview-hotspot\/([^\/]+\/[^\/]+)\//i', $spec, $m)) {
            return $m[1];
        }

        $desc = $product->description ?? '';
        if (preg_match('/\/hotspot-themes\/([^\/]+\/[^\/]+)\//i', $desc, $m)) {
            return $m[1];
        }

        $slug = str_replace('template-hotspot-', '', $product->slug ?? '');

        $signatures = [
            'ocean-abyss', 'glass-dock', 'bento-grid', 'cyber-matrix', 'anime-mecha',
            'fintech-wallet', 'tokyo-night', 'gaming-hud', 'aurora-glow', 'neo-brutalist',
            'retro-vaporwave', 'soft-neumorphic', 'isometric-station', 'paper-origami',
            'space-orbital', 'pixel-arcade', 'stealth-tactical', 'artisan-cafe',
            'steampunk-industrial', 'swiss-minimal', 'black-gold', 'enterprise-pro',
        ];
        $classics = ['biru-t2', 'biru-t4'];
        $colorways = ['tema-cyan', 'tema-emerald', 'tema-amber', 'tema-crimson', 'tema-monochrome'];

        if (in_array($slug, $signatures)) {
            return "01_SIGNATURE_STYLES/dgtlnet-{$slug}";
        }
        if (in_array($slug, $classics)) {
            return "02_CLASSIC_BESTSELLER/dgtlnet-{$slug}";
        }
        if (in_array($slug, $colorways)) {
            return "03_COLORWAY_EDITIONS/dgtlnet-{$slug}";
        }

        return '01_SIGNATURE_STYLES/dgtlnet-ocean-abyss';
    }

    /**
     * Get Product detail as JSON for Quick View modal.
     */
    public function getProductDetail(Request $request, $id): JsonResponse
    {
        $product = Product::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', 0);
            })
            ->with('category')
            ->findOrFail($id);

        $slug = $product->slug ?? '';
        $catSlug = $product->category?->slug ?? '';
        $image = $product->image;
        $gallery = is_array($product->gallery) ? $product->gallery : (is_string($product->gallery) ? json_decode($product->gallery, true) : []);

        if ($catSlug === 'template-hotspot' || str_starts_with($slug, 'template-hotspot-') || $slug === 'bundle-30-template-hotspot-mikrotik' || str_contains(strtolower($product->name ?? ''), 'template hotspot')) {
            $themePath = self::resolveThemePathFromProduct($product);
            if ($themePath) {
                $loginImg = "/hotspot-themes/{$themePath}/preview-login.png";
                $statusImg = "/hotspot-themes/{$themePath}/preview-status.png";

                if (empty($image) || !str_contains($image, '/hotspot-themes/')) {
                    $image = $loginImg;
                }
                if (empty($gallery) || !str_contains(json_encode($gallery), '/hotspot-themes/')) {
                    $gallery = [$loginImg, $statusImg];
                }
            }
        }

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
                'image' => $image ?: '/images/logo.png',
                'gallery' => $gallery ?: [],
                'short_description' => $product->short_description,
                'description' => nl2br(e($product->description)),
                'specifications' => $product->specifications,
                'category_name' => $product->category?->name,
            ],
        ]);
    }

    /**
     * Display standalone checkout page or redirect to shop with checkout modal opened.
     */
    public function checkout(Request $request)
    {
        return redirect()->to('/shop?checkout=1');
    }

    /**
     * Process checkout order submission.
     */
    public function storeOrder(Request $request): JsonResponse
    {

        $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'nullable|email|max:150',
            'shipping_address' => 'nullable|string|max:1000',
            'voucher_username' => 'nullable|string|max:100',
            'voucher_password' => 'nullable|string|max:100',
            'customer_notes' => 'nullable|string|max:500',
            'payment_method' => 'required|string|max:50',
            'payment_bank' => 'nullable|string|max:150',
            'items' => 'required',
            'payment_proof' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
        ]);

        $rawItems = $request->input('items');
        if (is_string($rawItems)) {
            $items = json_decode($rawItems, true);
        } else {
            $items = $rawItems;
        }

        if (empty($items) || !is_array($items)) {
            return response()->json(['success' => false, 'message' => 'Keranjang belanja kosong.'], 422);
        }

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $productId = $item['id'] ?? null;
                $qty = max(1, (int) ($item['quantity'] ?? 1));

                if (!$productId) {
                    continue;
                }

                $product = Product::withoutGlobalScopes()
                    ->where(function ($q) {
                        $q->whereNull('tenant_id')->orWhere('tenant_id', 0);
                    })
                    ->lockForUpdate()
                    ->find($productId);
                if (!$product) {
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

                // Reduce stock
                if ($product->stock >= $qty) {
                    $product->decrement('stock', $qty);
                }
            }

            if (empty($orderItemsData)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada item valid di dalam keranjang.'], 422);
            }

            $shippingFee = 0; // Free shipping or flat
            $baseTotal = $subtotal + $shippingFee;

            // Handle payment proof upload
            $paymentProofPath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = 'proof_shop_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $paymentProofPath = $file->storeAs('payment_proofs', $filename, 'public');
            }

            $isQris = stripos((string) $request->payment_method, 'qris') !== false;
            $uniqueCode = 0;
            $orderTotal = $baseTotal;
            $dynamicQrisString = null;
            $expiresAt = null;
            $tempOrderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            if ($isQris) {
                $pltRes = \App\Services\PlatformPaymentService::createPlatformTransaction([
                    'ref_id'         => $tempOrderNumber,
                    'amount'         => (float) $baseTotal,
                    'payment_method' => 'qris',
                    'customer_name'  => $request->customer_name,
                    'customer_email' => $request->customer_email,
                    'customer_phone' => $request->customer_phone ?? '',
                    'description'    => 'Order Toko ' . $tempOrderNumber,
                ]);

                if ($pltRes['success'] && !empty($pltRes['dynamic_qris'])) {
                    $dynamicQrisString = $pltRes['dynamic_qris'];
                    $orderTotal = (float) ($pltRes['total_amount'] ?? $baseTotal);
                    $expiresAt = $pltRes['expires_at'] ?? now()->addMinutes(15);
                } else {
                    $expiresAt = now()->addMinutes(15);
                }
            }

            $order = ShopOrder::create([
                'tenant_id'           => null,
                'order_number'        => $tempOrderNumber,
                'customer_name'       => $request->customer_name,
                'customer_phone'      => $request->customer_phone,
                'customer_email'      => $request->customer_email,
                'shipping_address'    => $request->shipping_address,
                'customer_notes'      => $request->customer_notes,
                'subtotal'            => $subtotal,
                'shipping_fee'        => $shippingFee,
                'total_amount'        => $orderTotal,
                'unique_code'         => $uniqueCode > 0 ? $uniqueCode : null,
                'dynamic_qris_string' => $dynamicQrisString,
                'expires_at'          => $expiresAt,
                'payment_method'      => $request->payment_method,
                'payment_bank'        => $isQris ? 'QRIS Dinamis' : $request->payment_bank,
                'payment_proof'       => $paymentProofPath,
                'payment_status'      => $paymentProofPath ? 'paid' : 'unpaid',
                'order_status'        => 'pending',
            ]);

            foreach ($orderItemsData as $orderItem) {
                $orderItem['order_id'] = $order->id;
                ShopOrderItem::create($orderItem);
            }

            DB::commit();

            // Build rich WhatsApp template message
            $company = Setting::company();
            $adminPhone = !empty($company['phone_wa']) ? $company['phone_wa'] : '6281234567890';

            $waMessage = self::buildWhatsAppMessage($order, $orderItemsData, $company);
            $waUrl = "https://wa.me/{$adminPhone}?text=" . urlencode($waMessage);

            // Send formatted Telegram alert to Topic Notifikasi Umum
            try {
                $telegram = app(\App\Services\TelegramService::class);
                if ($telegram->isConfigured()) {
                    $itemsList = [];
                    $totalItems = count($orderItemsData);
                    foreach ($orderItemsData as $i => $item) {
                        $prefix = ($i === $totalItems - 1) ? '└' : '├';
                        $itemsList[] = "{$prefix} " . htmlspecialchars($item['product_name']) . " ({$item['quantity']}x) : Rp " . number_format($item['subtotal'], 0, ',', '.');
                    }
                    $itemsText = implode("\n", $itemsList);
                    $payMethod = strtoupper(str_replace('_', ' ', (string) $order->payment_method));
                    $proofStatus = $paymentProofPath ? "Bukti Terlampir" : "Menunggu Pembayaran";

                    $tgMsg = "<b>🛍️ PESANAN TOKO BARU MASUK — NODERA</b>\n\n"
                        . "┌ Detail Pesanan\n"
                        . "├ No. Pesanan: <code>#{$order->order_number}</code>\n"
                        . "├ Pelanggan: " . htmlspecialchars($order->customer_name) . "\n"
                        . "├ WhatsApp: <code>" . htmlspecialchars($order->customer_phone) . "</code>\n"
                        . (!empty($order->customer_email) ? "├ Email: " . htmlspecialchars($order->customer_email) . "\n" : "")
                        . "├ Alamat: " . htmlspecialchars($order->shipping_address) . "\n"
                        . "├ Total Bayar: Rp " . number_format($order->total_amount, 0, ',', '.') . "\n"
                        . "├ Metode: {$payMethod} ({$proofStatus})\n"
                        . (!empty($order->customer_notes) ? "├ Catatan: " . htmlspecialchars($order->customer_notes) . "\n" : "")
                        . "└ Waktu: " . date('d/m/Y H:i') . " WIB\n\n"
                        . "┌ Rincian Item\n"
                        . $itemsText . "\n\n"
                        . "<a href=\"" . url('/superadmin/shop/orders') . "\">Kelola Pesanan di Panel Superadmin</a>";

                    $telegram->sendAdminNotification($tgMsg, 'general', 'HTML');
                }
            } catch (\Throwable $tgError) {
                Log::warning('Telegram Shop Order Notification Error: ' . $tgError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'wa_url' => $waUrl,
                'redirect_url' => route('shop.order.success', $order->order_number),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Shop Checkout Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal memproses pesanan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display order success page with WhatsApp link.
     */
    public function orderSuccess($orderNumber)
    {
        $order = ShopOrder::withoutGlobalScopes()->with(['items.product', 'tenant'])->where('order_number', $orderNumber)->firstOrFail();
        $company = Setting::company();

        $tenant = $order->tenant;
        $storeName = $tenant ? $tenant->name : ($company['name'] ?? 'NODERA');
        $adminPhone = $order->tenant_id ? Setting::getTenantPhone($order->tenant_id) : Setting::getSuperadminPhone();
        $cleanPhone = Setting::waNumber($adminPhone) ?: '6281234567890';

        $waMessage = $order->buildWhatsAppCheckoutMessage($storeName);
        $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($waMessage);

        $bankAccounts = $tenant 
            ? \App\Models\BankAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->get() 
            : self::resolveSuperadminBankAccounts();

        $isQris = stripos((string) $order->payment_method, 'qris') !== false;
        $qrisSvg = null;
        $merchantInfo = [];
        if (!empty($order->dynamic_qris_string) && str_starts_with($order->dynamic_qris_string, '000201')) {
            $qrisSvg = \App\Services\QrisDynamicService::generateQrSvg($order->dynamic_qris_string);
            $merchantInfo = \App\Services\QrisDynamicService::extractMerchantInfo($order->dynamic_qris_string);
        }

        $secondsLeft = $order->expires_at ? max(0, (int) round(now()->diffInSeconds($order->expires_at, false))) : 15 * 60;

        $qris = [
            'image'            => $qrisSvg,
            'is_dynamic'       => !empty($order->dynamic_qris_string),
            'merchant_name'    => $merchantInfo['merchant_name'] ?? ($tenant ? $tenant->name : 'CV. DIGITAL NETWORK SOLUTION'),
            'merchant_city'    => $merchantInfo['merchant_city'] ?? 'SITUBONDO',
            'nmid'             => $merchantInfo['nmid'] ?? 'ID1024366211885',
            'amount'           => (float) $order->total_amount,
            'amount_formatted' => $order->formatted_total,
            'seconds_left'     => $secondsLeft,
        ];

        return view('shop.success', compact('order', 'company', 'waUrl', 'waMessage', 'bankAccounts', 'qris', 'secondsLeft'));
    }

    /**
     * Resolve SuperAdmin bank accounts safely.
     */
    public static function resolveSuperadminBankAccounts()
    {
        return BankAccount::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', 0);
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Resolve SuperAdmin QRIS Image / Static code safely with guaranteed fallback.
     */
    public static function resolveSuperadminQris(): string
    {
        $gw = \App\Models\PaymentGateway::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', 0);
            })
            ->where('gateway', 'manual')
            ->first();

        if ($gw && !empty($gw->config_json)) {
            $img = \App\Http\Controllers\QRISController::resolveQrisImageUrl($gw->config_json);
            if ($img) {
                return $img;
            }
            if (!empty($gw->config_json['qris_text'])) {
                return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($gw->config_json['qris_text']);
            }
        }

        $qrisSetting = Setting::getValue('QRIS_IMAGE') 
            ?: Setting::getValue('qris_image') 
            ?: Setting::getValue('QRIS_STATIC') 
            ?: Setting::getValue('qris_static');

        if ($qrisSetting) {
            if (str_starts_with($qrisSetting, 'http') || str_starts_with($qrisSetting, 'data:image')) {
                return $qrisSetting;
            }
            return '/storage/' . ltrim($qrisSetting, '/');
        }

        $fallbackGw = \App\Models\PaymentGateway::withoutGlobalScopes()
            ->where('gateway', 'manual')
            ->first();

        if ($fallbackGw && !empty($fallbackGw->config_json)) {
            $img = \App\Http\Controllers\QRISController::resolveQrisImageUrl($fallbackGw->config_json);
            if ($img) {
                return $img;
            }
            if (!empty($fallbackGw->config_json['qris_text'])) {
                return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($fallbackGw->config_json['qris_text']);
            }
        }

        // Guaranteed dynamic fallback QR
        $company = Setting::company();
        $phone = $company['phone_wa'] ?? '6281234567890';
        return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode("https://wa.me/{$phone}?text=" . urlencode("Halo Admin, saya ingin konfirmasi pembayaran via QRIS NODERA Shop"));
    }

    /**
     * Helper to build clean, professional WhatsApp order template.
     */
    public static function buildWhatsAppMessage(ShopOrder $order, array $items, array $company): string
    {
        $companyName = $company['name'] ?? 'NODERA';
        return $order->buildWhatsAppCheckoutMessage($companyName, $items);
    }

    /**
     * Live Hotspot Template Preview Engine
     * Parses RouterOS template variables and renders pure, authentic loginpage.
     */
    public function previewHotspot(Request $request, string $themePath, ?string $page = null)
    {
        // If $themePath has .html at the end, separate theme path and page
        if (str_ends_with($themePath, '.html')) {
            $lastSlash = strrpos($themePath, '/');
            if ($lastSlash !== false) {
                $page = substr($themePath, $lastSlash + 1);
                $themePath = substr($themePath, 0, $lastSlash);
            } else {
                $page = $themePath;
                $themePath = '';
            }
        }

        if (empty($page)) {
            $page = 'login.html';
        }

        // Clean themePath
        $themePath = trim($themePath, '/');
        $themePath = str_replace('hotspot-themes/', '', $themePath);

        // Resolve absolute directory on disk
        $baseThemesDir = public_path('hotspot-themes');
        $resolvedDir = null;
        $relativeThemePath = '';

        // 1. Direct path check
        if (is_dir($baseThemesDir . '/' . $themePath)) {
            $resolvedDir = $baseThemesDir . '/' . $themePath;
            $relativeThemePath = $themePath;
        } else {
            // 2. Search in subfolders (01_SIGNATURE_STYLES, 02_CLASSIC_BESTSELLER, 03_COLORWAY_EDITIONS)
            $cleanSlug = str_replace(['template-hotspot-', 'template-hotspot/'], '', $themePath);
            $cleanSlug2 = 'dgtlnet-' . $cleanSlug;

            $subdirs = glob($baseThemesDir . '/*/*', GLOB_ONLYDIR) ?: [];
            foreach ($subdirs as $dir) {
                $folder = basename($dir);
                if ($folder === $themePath || $folder === $cleanSlug || $folder === $cleanSlug2) {
                    $resolvedDir = $dir;
                    $relativeThemePath = str_replace($baseThemesDir . '/', '', $dir);
                    break;
                }
            }
        }

        if (!$resolvedDir || !file_exists($resolvedDir . '/' . $page)) {
            // Fallback: search for any login.html in signature styles
            if (file_exists($baseThemesDir . '/01_SIGNATURE_STYLES/dgtlnet-bento-grid/' . $page)) {
                $resolvedDir = $baseThemesDir . '/01_SIGNATURE_STYLES/dgtlnet-bento-grid';
                $relativeThemePath = '01_SIGNATURE_STYLES/dgtlnet-bento-grid';
            } elseif (file_exists($baseThemesDir . '/01_SIGNATURE_STYLES/dgtlnet-bento-grid/login.html')) {
                $resolvedDir = $baseThemesDir . '/01_SIGNATURE_STYLES/dgtlnet-bento-grid';
                $relativeThemePath = '01_SIGNATURE_STYLES/dgtlnet-bento-grid';
                $page = 'login.html';
            } else {
                abort(404, 'Template Hotspot tidak ditemukan.');
            }
        }

        $filePath = $resolvedDir . '/' . $page;
        $html = file_get_contents($filePath);

        // If raw=1 or embed=1 requested, return the processed pure HTML template
        if ($request->query('raw') == '1' || $request->query('embed') == '1') {
            $processedHtml = $this->renderHotspotSimulatorHtml($html, $relativeThemePath, $page, $request);

            return response($processedHtml, 200)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('X-Frame-Options', 'SAMEORIGIN');
        }

        // Otherwise, render the interactive Live Preview Shell with Desktop & Mobile Mode Icons
        $viewerHtml = $this->renderLivePreviewShell($relativeThemePath, $page, $request);

        return response($viewerHtml, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Render the Live Preview Shell with Desktop & Mobile Mode Switcher Icons.
     */
    /**
     * Render the Live Preview Shell with strictly ONE toggle button for Desktop / Mobile mode.
     */
    protected function renderLivePreviewShell(string $relativeThemePath, string $page, Request $request): string
    {
        $previewBaseUrl = url('/shop/preview-hotspot/' . $relativeThemePath);
        $rawIframeUrl = $previewBaseUrl . '/' . $page . '?raw=1';
        $themeName = ucwords(str_replace(['01_SIGNATURE_STYLES/', '02_CLASSIC_BESTSELLER/', '03_COLORWAY_EDITIONS/', 'dgtlnet-', '-'], ['', '', '', '', ' '], $relativeThemePath));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="{$request->getLocale()}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$themeName} - Live Preview</title>
  <link rel="icon" type="image/png" href="/images/logo.png?v=33">
  <link href="/titan/assets/lib/components-font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      width: 100%;
      height: 100%;
      background: #04060A;
      overflow: hidden;
    }

    /* Single Floating Device Toggle Button (Bottom-Right FAB) */
    .btn-toggle-device {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 999999;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: #0B111E;
      border: 1.5px solid #00E5FF;
      color: #00E5FF;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.85), 0 0 18px rgba(0, 229, 255, 0.4);
      backdrop-filter: blur(12px);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      outline: none;
    }
    .btn-toggle-device:hover {
      background: #00E5FF;
      color: #070B11;
      transform: scale(1.1) translateY(-2px);
      box-shadow: 0 14px 35px rgba(0, 229, 255, 0.65);
    }
    .btn-toggle-device:active {
      transform: scale(0.95);
    }

    /* Preview Stage */
    .preview-stage {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #04060A;
      overflow: auto;
      transition: all 0.3s ease;
    }

    /* Desktop Mode (Full Screen) */
    .preview-stage.desktop iframe {
      width: 100%;
      height: 100%;
      border: none;
      background: #000;
      display: block;
    }

    /* Mobile Mode (Smartphone Frame 390px) */
    .preview-stage.mobile {
      padding: 24px 16px;
      background: radial-gradient(circle at 50% 50%, #0F172A 0%, #04060A 100%);
      min-height: 100%;
    }
    .preview-stage.mobile iframe {
      width: 390px;
      height: 844px;
      max-height: calc(100vh - 48px);
      border-radius: 36px;
      border: 8px solid #1E293B;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9), 0 0 30px rgba(0, 229, 255, 0.2);
      background: #000;
      display: block;
    }

    @media (max-width: 768px) {
      .btn-toggle-device {
        bottom: 20px;
        right: 18px;
        width: 44px;
        height: 44px;
      }
      .preview-stage.mobile {
        padding: 0;
        background: #04060A;
      }
      .preview-stage.mobile iframe {
        width: 100%;
        height: 100%;
        max-height: 100%;
        border: none;
        border-radius: 0;
      }
    }
  </style>
</head>
<body>
  <!-- Single Floating Toggle Button -->
  <button type="button" class="btn-toggle-device" id="btnToggleDevice" onclick="toggleDeviceMode()" title="Ganti Mode Tampilan (Desktop / Mobile)">
    <i class="fa fa-mobile-phone" id="deviceIcon" style="font-size: 24px;"></i>
  </button>

  <!-- Preview Container -->
  <div class="preview-stage desktop" id="previewStage">
    <iframe id="previewIframe" src="{$rawIframeUrl}" allow="fullscreen"></iframe>
  </div>

  <script>
    var currentMode = 'desktop';

    function toggleDeviceMode() {
      var stage = document.getElementById('previewStage');
      var icon = document.getElementById('deviceIcon');

      if (currentMode === 'desktop') {
        currentMode = 'mobile';
        stage.className = 'preview-stage mobile';
        icon.className = 'fa fa-desktop';
        icon.style.fontSize = '17px';
      } else {
        currentMode = 'desktop';
        stage.className = 'preview-stage desktop';
        icon.className = 'fa fa-mobile-phone';
        icon.style.fontSize = '24px';
      }
    }
  </script>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Parse MikroTik RouterOS template syntax for a 100% clean, pure live preview.
     */
    protected function renderHotspotSimulatorHtml(string $html, string $relativeThemePath, string $page, Request $request): string
    {
        $previewBaseUrl = url('/shop/preview-hotspot/' . $relativeThemePath);
        $themePublicUrl = asset('hotspot-themes/' . $relativeThemePath) . '/';

        // 1. Process Conditionals (Support both <!-- $(if ...) --> and raw $(if ...))
        // $(if trial == 'yes') ... $(endif) -> Keep inner content, strip if/endif tags
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+trial\s*==\s*[\'"]yes[\'"]\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '$1', $html);

        // $(if session-time-left) ... $(endif) -> Keep inner
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+session-time-left\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '$1', $html);

        // $(if remain-bytes-total) ... $(endif) -> Keep inner
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+remain-bytes-total\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '$1', $html);

        // $(if error) ... $(endif) -> Remove error block by default
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+error\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // $(if chap-id) ... $(endif) -> Remove CHAP block
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+chap-id\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // $(if refresh-timeout) ... $(endif) -> Remove
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+refresh-timeout\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // $(if advert-pending == 'yes') ... $(endif) -> Remove
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+advert-pending\s*==\s*[\'"]yes[\'"]\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // $(if blocked == 'yes') ... $(endif) -> Remove
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+blocked\s*==\s*[\'"]yes[\'"]\)(?:\s*-->)?(.*?)(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // Catch-all for any leftover $(if ...) and $(endif)
        $html = preg_replace('/(?:<!--\s*)?\$\(if\s+[^\)>]+\)(?:\s*-->)?/is', '', $html);
        $html = preg_replace('/(?:<!--\s*)?\$\(endif\)(?:\s*-->)?/is', '', $html);

        // 2. Replace MikroTik Variables with Realistic Simulated Values
        $isAfrica = str_contains(config('app.name', ''), 'Africa') || str_contains(url('/'), 'airnetsolution') || str_contains(url('/'), 'africa');

        $replacements = [
            '$(username)' => $isAfrica ? 'client-demo' : 'user-demo-voucher',
            '$(ip)' => '192.168.88.105',
            '$(mac)' => 'DC:2C:6E:8A:4F:1B',
            '$(mac-esc)' => 'DC-2C-6E-8A-4F-1B',
            '$(bytes-in-nice)' => '248.5 MB',
            '$(bytes-out-nice)' => '1.42 GB',
            '$(bytes-in)' => '260571136',
            '$(bytes-out)' => '1524629504',
            '$(uptime)' => '02:15:30',
            '$(session-time-left)' => $isAfrica ? '05 Heures 44 Min' : '05 Jam 44 Menit',
            '$(remain-bytes-total-nice)' => '4.2 GB',
            '$(identity)' => $isAfrica ? 'NODERA-AFRICA-ROUTER' : 'NODERA-MIKROTIK-CORE',
            '$(server-name)' => 'WiFi-Hotspot',
            '$(hostname)' => 'hotspot.local',
            '$(link-login-only)' => $previewBaseUrl . '/status.html',
            '$(link-logout)' => $previewBaseUrl . '/logout.html',
            '$(link-status)' => $previewBaseUrl . '/status.html',
            '$(link-login)' => $previewBaseUrl . '/login.html',
            '$(link-redirect)' => $previewBaseUrl . '/status.html',
            '$(link-orig)' => 'https://google.com',
            '$(link-orig-esc)' => 'https%3A%2F%2Fgoogle.com',
            '$(error)' => '',
            '$(chap-id)' => '',
            '$(chap-challenge)' => '',
            '$(refresh-timeout)' => '0',
        ];

        $html = str_ireplace(array_keys($replacements), array_values($replacements), $html);

        // Catch-all for any leftover RouterOS variable
        $html = preg_replace('/\$\([a-zA-Z0-9_\-]+\)/', '', $html);

        // 3. Inject base href so relative assets (js/images) load accurately
        if (str_contains($html, '<head>')) {
            $baseTag = '<head><base href="' . $themePublicUrl . '">';
            $html = preg_replace('/<head>/i', $baseTag, $html, 1);
        }

        return $html;
    }
}
