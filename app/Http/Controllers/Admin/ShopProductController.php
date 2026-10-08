<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ShopProductController extends Controller
{
    private function getTenantId(): int
    {
        return (int) (auth()->user()->tenant_id ?? session('tenant_id'));
    }

    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'tenant_id') || !\Illuminate\Support\Facades\Schema::hasColumn('product_categories', 'tenant_id')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Auto migration notice: " . $e->getMessage());
            }
        }

        $tenantId = $this->getTenantId();
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $hasCatCol = \Illuminate\Support\Facades\Schema::hasColumn('product_categories', 'tenant_id');
        $hasProdCol = \Illuminate\Support\Facades\Schema::hasColumn('products', 'tenant_id');

        if ($hasCatCol && $hasProdCol) {
            $categories = ProductCategory::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->withCount(['products' => function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } else {
            $categories = collect();
        }

        $query = $hasProdCol ? Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['category', 'voucherPackage']) : Product::whereRaw('1=0');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('is_featured', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Hotspot Packages for linking
        $voucherPackages = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'hotspot')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Shop/Products', [
            'products' => $products,
            'categories' => $categories,
            'voucherPackages' => $voucherPackages,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function storeCategory(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        ProductCategory::create($validated);

        return redirect()->back()->with('msg', 'Kategori produk berhasil ditambahkan!');
    }

    public function updateCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $category = ProductCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . substr(uniqid(), -4);
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $category->update($validated);

        return redirect()->back()->with('msg', 'Kategori produk berhasil diperbarui!');
    }

    public function destroyCategory($id)
    {
        $tenantId = $this->getTenantId();
        $category = ProductCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $category->delete();

        return redirect()->back()->with('msg', 'Kategori produk berhasil dihapus!');
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'category_id' => 'nullable|integer',
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:200',
            'sku' => 'nullable|string|max:100',
            'product_type' => 'nullable|in:general,voucher',
            'voucher_package_id' => 'nullable|integer',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'badge' => 'nullable|string|max:50',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'weight_gram' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);

        $validated['tenant_id'] = $tenantId;
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tenant-products', 'public');
        }

        $validated['is_featured'] = $request->boolean('is_featured', false);
        $validated['is_active'] = $request->boolean('is_active', true);

        Product::create($validated);

        return redirect()->back()->with('msg', 'Produk berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|integer',
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:200',
            'sku' => 'nullable|string|max:100',
            'product_type' => 'nullable|in:general,voucher',
            'voucher_package_id' => 'nullable|integer',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'badge' => 'nullable|string|max:50',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'weight_gram' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . substr(uniqid(), -4);
        }

        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('tenant-products', 'public');
        }

        $validated['is_featured'] = $request->boolean('is_featured', false);
        $validated['is_active'] = $request->boolean('is_active', true);

        $product->update($validated);

        return redirect()->back()->with('msg', 'Produk berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->back()->with('msg', 'Produk berhasil dihapus!');
    }

    public function toggleActive($id)
    {
        $tenantId = $this->getTenantId();
        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $product->is_active = !$product->is_active;
        $product->save();

        return redirect()->back()->with('msg', 'Status produk berhasil diubah!');
    }
}
