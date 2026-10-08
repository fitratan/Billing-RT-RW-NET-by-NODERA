<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Models\ShopOrder;
use App\Services\MikrotikService;
use App\Services\TenantTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShopOrderController extends Controller
{
    private function getTenantId(): int
    {
        return (int) (auth()->user()->tenant_id ?? session('tenant_id'));
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        $search = $request->query('search');
        $status = $request->query('status');

        $query = ShopOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('items');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_phone', 'LIKE', "%{$search}%")
                    ->orWhere('voucher_username', 'LIKE', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            $query->where('order_status', $status);
        }

        $orders = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => ShopOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'pending' => ShopOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_status', 'pending')->count(),
            'completed' => ShopOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_status', 'completed')->count(),
            'cancelled' => ShopOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_status', 'cancelled')->count(),
            'total_revenue' => (float) ShopOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_status', 'completed')->sum('total_amount'),
        ];

        return Inertia::render('Admin/Shop/Orders', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function approve(Request $request, $id, TenantTelegramService $telegramService)
    {
        $tenantId = $this->getTenantId();
        $order = ShopOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($id);

        if ($order->order_status === 'completed') {
            return redirect()->back()->with('msg', 'Pesanan ini sudah disetujui sebelumnya.');
        }

        // If voucher order, create users in MikroTik
        $voucherList = TenantTelegramService::parseVoucherList($order);
        $mikrotikSuccessCount = 0;
        $mikrotikErrors = [];

        if (!empty($voucherList)) {
            $router = Mikrotik::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first()
                ?? Mikrotik::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

            if ($router) {
                try {
                    $mikrotikService = new MikrotikService($router);
                    if ($mikrotikService->isConnected()) {
                        foreach ($voucherList as $vch) {
                            $vUser = $vch['username'] ?? null;
                            $vPass = $vch['password'] ?? $vUser;
                            $vProf = $vch['profile'] ?? ($order->voucher_profile ?: 'default');
                            if ($vUser) {
                                $added = $mikrotikService->addHotspotUser(
                                    $vUser,
                                    $vPass,
                                    $vProf,
                                    '',
                                    0,
                                    'all',
                                    "Order #{$order->order_number}"
                                );
                                if ($added) {
                                    $mikrotikSuccessCount++;
                                } else {
                                    $err = $mikrotikService->getLastError() ?: 'Hak akses MikroTik terbatas (Read-Only) atau gagal menambahkan user';
                                    $mikrotikErrors[] = "User {$vUser}: {$err}";
                                }
                            }
                        }

                        $order->voucher_created_in_mikrotik = ($mikrotikSuccessCount === count($voucherList) && $mikrotikSuccessCount > 0);
                    } else {
                        $order->voucher_created_in_mikrotik = false;
                        $connErr = $mikrotikService->getLastError() ?: 'Gagal terhubung ke port API MikroTik';
                        $mikrotikErrors[] = "Koneksi Router: {$connErr}";
                    }
                } catch (\Exception $e) {
                    $order->voucher_created_in_mikrotik = false;
                    $mikrotikErrors[] = $e->getMessage();
                    Log::error("Manual ACC MikroTik error: " . $e->getMessage());
                }
            } else {
                $order->voucher_created_in_mikrotik = false;
                $mikrotikErrors[] = 'Tidak ada router MikroTik yang aktif untuk toko ini.';
            }
        }

        if (!empty($mikrotikErrors)) {
            $order->admin_notes = implode(' | ', $mikrotikErrors);
        }

        $order->payment_status = 'paid';
        $order->order_status = 'completed';
        $order->save();

        // Update telegram message if exists
        $telegramService->updateOrderAccMessage($order, auth()->user()->name ?? 'Admin Web');

        if (!empty($mikrotikErrors)) {
            $msg = 'Pesanan disetujui, namun gagal membuat akun di MikroTik: ' . implode('; ', array_unique($mikrotikErrors));
            return redirect()->back()->with('msg', $msg);
        }

        return redirect()->back()->with('msg', 'Pesanan berhasil disetujui dan seluruh akun hotspot telah dibuat di MikroTik!');
    }

    public function reject(Request $request, $id, TenantTelegramService $telegramService)
    {
        $tenantId = $this->getTenantId();
        $order = ShopOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $order->payment_status = 'rejected';
        $order->order_status = 'cancelled';
        $order->admin_notes = $request->input('admin_notes', 'Dibatalkan oleh Admin');
        $order->save();

        $telegramService->updateOrderRejectMessage($order, auth()->user()->name ?? 'Admin Web');

        return redirect()->back()->with('msg', 'Pesanan telah dibatalkan / ditolak.');
    }

    public function updateStatus(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $order = ShopOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $validated = $request->validate([
            'order_status' => 'required|in:pending,processing,shipped,completed,cancelled',
            'payment_status' => 'required|in:unpaid,paid,verified,rejected',
            'tracking_number' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $order->update($validated);

        return redirect()->back()->with('msg', "Status pesanan #{$order->order_number} berhasil diperbarui!");
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        $order = ShopOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $order->delete();

        return redirect()->back()->with('msg', 'Pesanan berhasil dihapus!');
    }
}
