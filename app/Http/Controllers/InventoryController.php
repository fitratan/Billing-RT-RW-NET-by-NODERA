<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::with('category')->orderBy('name')->get();
        $categories = InventoryCategory::orderBy('name')->get();

        return Inertia::render('Admin/Inventory', [
            'create' => (bool) request('create'),
            'items' => $items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'category' => $i->category?->name,
                'category_id' => $i->category_id, 'unit' => $i->unit ?? 'unit',
                'quantity' => (int) $i->quantity, 'price' => (float) ($i->price ?? 0), 'status' => $i->status,
            ]),
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    public function addCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['tenant_id'] = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        InventoryCategory::create($data);

        return redirect()->back()->with('success', 'Kategori berhasil ditambahkan');
    }

    public function editCategory(Request $request, $id)
    {
        $cat = InventoryCategory::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $cat->update($data);

        return redirect()->back()->with('success', 'Kategori berhasil diperbarui');
    }

    public function deleteCategory($id)
    {
        $cat = InventoryCategory::findOrFail($id);
        $cat->items()->update(['category_id' => null]);
        $cat->delete();

        return redirect()->back()->with('success', 'Kategori berhasil dihapus');
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        $data['tenant_id'] = session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? auth()->user()?->tenant_id;
        InventoryItem::create($data);

        return redirect()->back()->with('success', 'Barang berhasil ditambahkan');
    }

    public function editItem(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        $item->update($data);

        return redirect()->back()->with('success', 'Barang berhasil diperbarui');
    }

    public function deleteItem($id)
    {
        $item = InventoryItem::findOrFail($id);
        $item->delete();

        return redirect()->back()->with('success', 'Barang berhasil dihapus');
    }

    public function adjustStock(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);

        $data = $request->validate([
            'adjustment' => 'required|integer',
            'reason' => 'nullable|string|max:1000',
        ]);

        $newQty = max(0, $item->quantity + $data['adjustment']);
        $item->update(['quantity' => $newQty]);

        $action = $data['adjustment'] >= 0 ? 'ditambahkan' : 'dikurangi';
        $message = "Stok {$item->name} berhasil {$action} (".abs($data['adjustment'])." {$item->unit})";

        if (!empty($data['reason'])) {
            $message .= " — {$data['reason']}";
        }

        return redirect()->back()->with('success', $message);
    }
}
