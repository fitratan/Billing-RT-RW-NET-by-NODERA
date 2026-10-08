<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceBatchJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingEngineService
{
    /**
     * Generate recurring invoices for all active customers across tenants or for a specific tenant.
     * Uses chunkById(250) and Redis Mutex to prevent double-billing storms.
     */
    public function generateRecurringInvoices(?int $tenantId = null, ?string $targetPeriod = null): array
    {
        $period = $targetPeriod ?? now()->format('Y-m');
        Log::info("[BillingEngineService] Starting recurring billing generation for period: {$period}", ['tenant_id' => $tenantId]);

        $query = Customer::withoutGlobalScopes()
            ->with('package')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            });

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalDispatched = 0;
        $totalCustomers = 0;

        // Process in chunks of 250 rows using chunkById to prevent memory exhaustion and execution timeout
        $query->chunkById(250, function ($customers) use ($period, &$totalDispatched, &$totalCustomers) {
            $customerIds = $customers->pluck('id')->toArray();
            $totalCustomers += count($customerIds);

            // Dispatch batch job to queue
            GenerateInvoiceBatchJob::dispatch($customerIds, $period);
            $totalDispatched++;
        });

        Log::info("[BillingEngineService] Queued {$totalDispatched} batches for {$totalCustomers} customers.");

        return [
            'success' => true,
            'period' => $period,
            'total_customers' => $totalCustomers,
            'batches_dispatched' => $totalDispatched,
        ];
    }

    /**
     * Process a single customer invoice generation with Atomic Redis Lock.
     */
    public function generateSingleCustomerInvoice(Customer $customer, string $period): ?Invoice
    {
        if (!$customer->package) {
            return null;
        }

        $tenantId = $customer->tenant_id ?? 0;
        $customerId = $customer->id;
        $lockKey = "gen_inv_{$tenantId}_{$customerId}_{$period}";

        // Redis Atomic Mutex Lock: 60s TTL
        return Cache::lock($lockKey, 60)->get(function () use ($customer, $period, $tenantId) {
            $now = now();
            $isoDay = (int) ($customer->isolation_date ?: 20);
            if ($isoDay < 1 || $isoDay > 31) {
                $isoDay = 20;
            }

            $targetDue = $now->copy()->day(min($isoDay, $now->daysInMonth));
            if ($customer->created_at) {
                $createdAt = Carbon::parse($customer->created_at);
                if ($createdAt->format('Y-m') === $now->format('Y-m') && $createdAt->day >= $isoDay) {
                    $targetDue = $now->copy()->addMonth()->day(min($isoDay, $now->copy()->addMonth()->daysInMonth));
                }
            }
            $dueDate = $targetDue->format('Y-m-d');
            $amount = (float) ($customer->package->price ?? 0);

            return DB::transaction(function () use ($customer, $period, $dueDate, $amount, $tenantId) {
                // 1. Check if customer has an existing UNPAID invoice
                $unpaidInvoice = Invoice::withoutGlobalScopes()
                    ->where('customer_id', $customer->id)
                    ->where('paid', false)
                    ->where('status', '!=', 'paid')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($unpaidInvoice) {
                    // Parse breakdown
                    $breakdown = $unpaidInvoice->periods_breakdown ? json_decode($unpaidInvoice->periods_breakdown, true) : [];
                    if (empty($breakdown) || !is_array($breakdown)) {
                        $origPeriod = $unpaidInvoice->period ?? Carbon::parse($unpaidInvoice->created_at)->format('Y-m');
                        $origAmount = (float) $unpaidInvoice->amount;
                        $origLabel = Carbon::parse($origPeriod . '-01')->translatedFormat('F Y');
                        $breakdown = [
                            [
                                'period' => $origPeriod,
                                'amount' => $origAmount,
                                'label'  => $origLabel,
                            ]
                        ];
                    }

                    // Check if period already in breakdown
                    foreach ($breakdown as $bd) {
                        if (($bd['period'] ?? '') === $period) {
                            Log::debug("[BillingEngineService] Customer #{$customer->id} already has period {$period} in unpaid invoice. Skipping.");
                            return null;
                        }
                    }

                    $newLabel = Carbon::parse($period . '-01')->translatedFormat('F Y');
                    $breakdown[] = [
                        'period' => $period,
                        'amount' => $amount,
                        'label'  => $newLabel,
                    ];

                    $totalAmount = (float) array_sum(array_column($breakdown, 'amount'));
                    $periodLabels = array_map(fn($b) => $b['label'] ?? $b['period'], $breakdown);

                    $unpaidInvoice->update([
                        'amount'            => $totalAmount,
                        'period'            => $period,
                        'due_date'          => $dueDate,
                        'periods_breakdown' => json_encode($breakdown),
                        'accumulated_from'  => $unpaidInvoice->accumulated_from ?: ($unpaidInvoice->period ?? Carbon::parse($unpaidInvoice->created_at)->format('Y-m')),
                        'description'       => "Tagihan Internet {$customer->package->name} (" . count($breakdown) . " Periode: " . implode(', ', $periodLabels) . ")",
                    ]);

                    // Send WhatsApp notification if configured
                    try {
                        $waService = new WhatsappService($customer->tenant_id);
                        if ($customer->phone && $waService->isConfigured()) {
                            \App\Jobs\SendWhatsappNotificationJob::dispatch(
                                $customer->phone,
                                "Halo *{$customer->name}*,\n\nTagihan internet Anda periode *{$newLabel}* telah terbit dan diakumulasikan.\nTotal Tagihan Saat Ini (" . count($breakdown) . " Periode): *Rp " . number_format($totalAmount, 0, ',', '.') . "*.\nNomor Tagihan: *{$unpaidInvoice->invoice_number}*\nJatuh Tempo: *{$dueDate}*\n\nSilakan lakukan pembayaran sebelum tanggal jatuh tempo. Terima kasih."
                            )->onQueue('low');
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[BillingEngineService] WhatsApp notification failed for customer #{$customer->id}: " . $e->getMessage());
                    }

                    return $unpaidInvoice;
                }

                // Check if already paid invoice for this period exists
                $alreadyPaid = Invoice::withoutGlobalScopes()
                    ->where('customer_id', $customer->id)
                    ->where(function ($q) use ($period) {
                        $q->where('period', $period)
                          ->orWhere('created_at', 'like', "{$period}%");
                    })
                    ->exists();

                if ($alreadyPaid) {
                    Log::debug("[BillingEngineService] Customer #{$customer->id} already has a paid invoice for {$period}. Skipping.");
                    return null;
                }

                $invoiceNumber = 'INV-' . Carbon::parse($period . '-01')->format('Ym') . '-' . str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT);
                $breakdown = [
                    [
                        'period' => $period,
                        'amount' => $amount,
                        'label'  => Carbon::parse($period . '-01')->translatedFormat('F Y'),
                    ]
                ];

                $invoice = Invoice::create([
                    'invoice_number'    => $invoiceNumber,
                    'customer_id'       => $customer->id,
                    'tenant_id'         => $tenantId ?: $customer->tenant_id,
                    'amount'            => $amount,
                    'due_date'          => $dueDate,
                    'period'            => $period,
                    'status'            => 'pending',
                    'paid'              => false,
                    'periods_breakdown' => json_encode($breakdown),
                    'description'       => "Tagihan Internet {$customer->package->name} Periode " . Carbon::parse($period . '-01')->translatedFormat('F Y'),
                ]);

                // Send WhatsApp notification if configured
                try {
                    $waService = new WhatsappService($customer->tenant_id);
                    if ($customer->phone && $waService->isConfigured()) {
                        \App\Jobs\SendWhatsappNotificationJob::dispatch(
                            $customer->phone,
                            "Halo *{$customer->name}*,\n\nTagihan internet Anda untuk periode *{$period}* sebesar *Rp " . number_format($amount, 0, ',', '.') . "* telah terbit.\nNomor Tagihan: *{$invoiceNumber}*\nJatuh Tempo: *{$dueDate}*\n\nSilakan lakukan pembayaran sebelum tanggal jatuh tempo. Terima kasih."
                        )->onQueue('low');
                    }
                } catch (\Throwable $e) {
                    Log::warning("[BillingEngineService] WhatsApp notification failed for customer #{$customer->id}: " . $e->getMessage());
                }

                return $invoice;
            });
        });
    }
}
