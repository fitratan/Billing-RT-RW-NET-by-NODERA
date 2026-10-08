<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CollectorApiController extends Controller
{
    /**
     * Collector Dashboard Metrics.
     * GET /api/v1/collector/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $today = Carbon::today();
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;

        // Find linked collector profile
        $collectorQuery = Collector::where('user_id', $user->id)
            ->orWhere('name', $user->name);
        if ($tenantId) {
            $collectorQuery->where('tenant_id', $tenantId);
        }
        $collector = $collectorQuery->first();

        $collectorName = $collector?->name ?? $user->name;

        // Today's collected cash
        $todayInvoices = Invoice::where('paid', 1)
            ->whereDate('paid_at', $today)
            ->where(function ($q) use ($collectorName, $user) {
                $q->where('collector_name', $collectorName)
                    ->orWhere('processed_by', $user->name)
                    ->orWhere('collector_id', $user->id);
            });
        if ($tenantId) {
            $todayInvoices->where('tenant_id', $tenantId);
        }

        $todayTotalAmount = (float) (clone $todayInvoices)->sum('amount');
        $todayCount = (int) (clone $todayInvoices)->count();

        // Month total and commission calculation
        $monthInvoices = Invoice::where('paid', 1)
            ->whereYear('paid_at', $thisYear)
            ->whereMonth('paid_at', $thisMonth)
            ->where(function ($q) use ($collectorName, $user) {
                $q->where('collector_name', $collectorName)
                    ->orWhere('processed_by', $user->name)
                    ->orWhere('collector_id', $user->id);
            });
        if ($tenantId) {
            $monthInvoices->where('tenant_id', $tenantId);
        }

        $monthTotalAmount = (float) (clone $monthInvoices)->sum('amount');
        $monthCount = (int) (clone $monthInvoices)->count();

        $commissionPerInvoice = (float) Setting::getValue('COLLECTOR_COMMISSION_PER_INVOICE', env('COLLECTOR_COMMISSION_PER_INVOICE', 5000));
        $estimatedCommission = $monthCount * $commissionPerInvoice;

        // Pending unpaid customers count
        $unpaidQuery = Invoice::where('paid', 0);
        if ($tenantId) {
            $unpaidQuery->where('tenant_id', $tenantId);
        }
        $unpaidCount = $unpaidQuery->count();

        return response()->json([
            'success' => true,
            'data' => [
                'collector' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                ],
                'today' => [
                    'total_collected' => $todayTotalAmount,
                    'invoices_count' => $todayCount,
                ],
                'this_month' => [
                    'total_collected' => $monthTotalAmount,
                    'invoices_count' => $monthCount,
                    'commission_rate' => $commissionPerInvoice,
                    'estimated_commission' => $estimatedCommission,
                ],
                'total_unpaid_customers' => $unpaidCount,
            ],
        ]);
    }

    /**
     * Unpaid Customers List for Field Collection (GPS & Route Sorted).
     * GET /api/v1/collector/unpaid-customers
     */
    public function unpaidCustomers(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $search = trim($request->get('search', ''));
        $lat = $request->get('lat');
        $lng = $request->get('lng');

        $query = Invoice::join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.paid', 0)
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoices.amount',
                'invoices.period',
                'invoices.due_date',
                'customers.id as customer_id',
                'customers.code as customer_code',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'customers.address as customer_address',
                'customers.lat',
                'customers.lng',
                'customers.pppoe_username',
                'customers.status as customer_status'
            );

        if ($tenantId) {
            $query->where('invoices.tenant_id', $tenantId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('customers.address', 'like', "%{$search}%")
                    ->orWhere('invoices.invoice_number', 'like', "%{$search}%");
            });
        }

        $results = $query->orderBy('invoices.due_date', 'asc')->limit(100)->get();

        // Calculate distance if collector coordinates provided
        $data = $results->map(function ($row) use ($lat, $lng) {
            $distanceKm = null;
            if ($lat && $lng && $row->lat && $row->lng) {
                $theta = $lng - $row->lng;
                $dist = sin(deg2rad($lat)) * sin(deg2rad($row->lat)) + cos(deg2rad($lat)) * cos(deg2rad($row->lat)) * cos(deg2rad($theta));
                $dist = acos(max(-1.0, min(1.0, $dist)));
                $dist = rad2deg($dist);
                $distanceKm = round($dist * 60 * 1.1515 * 1.609344, 2);
            }

            return [
                'invoice_id' => $row->invoice_id,
                'invoice_number' => $row->invoice_number,
                'amount' => (float) $row->amount,
                'period' => $row->period,
                'due_date' => $row->due_date ? Carbon::parse($row->due_date)->format('d/m/Y') : null,
                'is_overdue' => $row->due_date && Carbon::parse($row->due_date)->isPast(),
                'customer' => [
                    'id' => $row->customer_id,
                    'code' => $row->customer_code,
                    'name' => $row->customer_name,
                    'phone' => $row->customer_phone,
                    'address' => $row->customer_address,
                    'lat' => $row->lat ? (float) $row->lat : null,
                    'lng' => $row->lng ? (float) $row->lng : null,
                    'status' => $row->customer_status,
                ],
                'distance_km' => $distanceKm,
            ];
        });

        // If coordinates provided, sort by distance
        if ($lat && $lng) {
            $data = $data->sortBy('distance_km')->values();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Accept Cash Payment on Field & Produce Thermal Receipt.
     * POST /api/v1/collector/pay-cash
     */
    public function payCash(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Input tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invoiceQuery = Invoice::with('customer.package');
        if ($tenantId) {
            $invoiceQuery->where('tenant_id', $tenantId);
        }
        $invoice = $invoiceQuery->find($request->invoice_id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice tidak ditemukan'], 404);
        }

        if ($invoice->paid) {
            return response()->json(['success' => false, 'message' => 'Invoice ini sudah lunas'], 400);
        }

        $customer = $invoice->customer;

        DB::beginTransaction();
        try {
            $now = Carbon::now();

            $invoice->update([
                'paid' => 1,
                'status' => 'paid',
                'paid_at' => $now,
                'payment_method' => 'cash',
                'payment_channel' => 'CASH_KOLEKTOR',
                'collector_name' => $user->name,
                'processed_by' => $user->name,
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
                'payment_method' => 'cash',
                'payment_date' => $now,
                'notes' => $request->notes ?? "Pembayaran tunai diterima oleh {$user->name}",
                'tenant_id' => $invoice->tenant_id,
            ]);

            // Auto-activate customer if currently isolated and restore MikroTik connection
            if ($customer && $customer->status === 'isolated') {
                $customer->update(['status' => 'active']);
                try {
                    app(\App\Services\IsolationService::class)->unisolateCustomer($customer, "Kolektor: {$user->name}");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[CollectorApi] Auto un-isolate error on MikroTik: ' . $e->getMessage());
                }
            }

            DB::commit();

            // Build structured printable thermal receipt data (58mm / 80mm ESC/POS)
            $receiptData = $this->formatThermalReceipt($invoice, $customer, $user);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran tunai berhasil dicatat!',
                'receipt' => $receiptData,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Printable Thermal Receipt Data by Invoice ID.
     * GET /api/v1/collector/receipt/{invoice_id}
     */
    public function receipt(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $invoiceQuery = Invoice::with('customer.package');
        if ($tenantId) {
            $invoiceQuery->where('tenant_id', $tenantId);
        }
        $invoice = $invoiceQuery->find($id);

        if (!$invoice || !$invoice->paid) {
            return response()->json(['success' => false, 'message' => 'Invoice belum lunas atau tidak ditemukan'], 404);
        }

        $receiptData = $this->formatThermalReceipt($invoice, $invoice->customer, $user);

        return response()->json([
            'success' => true,
            'receipt' => $receiptData,
        ]);
    }

    /**
     * Helper to Format Thermal Receipt payload.
     */
    private function formatThermalReceipt(Invoice $invoice, ?Customer $customer, User $collector): array
    {
        $tenantId = $invoice->tenant_id ?? $customer?->tenant_id;
        $companyName = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'COMPANY_NAME')->value('value') ?: ($invoice->tenant?->name ?? 'NODERA BILLING');
        $companyPhone = Setting::getTenantPhone($tenantId) ?: '0812-3456-7890';
        $companyAddress = Setting::getValue('COMPANY_ADDRESS', 'Layanan Internet Broadband');

        return [
            'header' => [
                'title' => $companyName,
                'subtitle' => $companyAddress,
                'phone' => $companyPhone,
                'divider' => '================================',
            ],
            'details' => [
                'no_struk' => $invoice->invoice_number,
                'tanggal' => $invoice->paid_at ? Carbon::parse($invoice->paid_at)->format('d/m/Y H:i') : date('d/m/Y H:i'),
                'id_pelanggan' => $customer?->code ?? '-',
                'nama' => $customer?->name ?? 'Pelanggan',
                'paket' => $customer?->package?->name ?? 'Internet Broadband',
                'periode' => $invoice->period ?? date('m/Y'),
                'petugas' => $collector->name,
            ],
            'payment' => [
                'tagihan' => (float) $invoice->amount,
                'total_bayar' => (float) $invoice->amount,
                'metode' => 'TUNAI (LUNAS)',
                'tagihan_formatted' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
            ],
            'footer' => [
                'thank_you' => 'Terima kasih atas pembayaran Anda.',
                'note' => 'Simpan struk ini sebagai bukti pembayaran yang sah.',
                'generated_by' => 'NODERA Mobile POS System',
            ],
        ];
    }
}
