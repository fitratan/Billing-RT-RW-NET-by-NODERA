<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMonthlyInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout per batch

    protected ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    public function handle(WhatsappService $waService): void
    {
        Log::info('[ProcessMonthlyInvoicesJob] Starting batch invoice generation', ['tenant_id' => $this->tenantId]);

        $query = Customer::with('package')->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            });
        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        $now = now();
        $monthYear = $now->format('Y-m');

        $createdCount = 0;

        // Process in chunks of 100 to prevent memory spikes
        $query->chunk(100, function ($customers) use ($now, $monthYear, $waService, &$createdCount) {
            foreach ($customers as $customer) {
                if (!$customer->package) {
                    continue;
                }

                $isoDay = (int) ($customer->isolation_date ?: 20);
                if ($isoDay < 1 || $isoDay > 31) {
                    $isoDay = 20;
                }

                $targetDue = $now->copy()->day(min($isoDay, $now->daysInMonth));
                if ($customer->created_at) {
                    $createdAt = \Carbon\Carbon::parse($customer->created_at);
                    if ($createdAt->format('Y-m') === $now->format('Y-m') && $createdAt->day >= $isoDay) {
                        $targetDue = $now->copy()->addMonth()->day(min($isoDay, $now->copy()->addMonth()->daysInMonth));
                    }
                }
                $dueDate = $targetDue->format('Y-m-d');
                $amount = (float) ($customer->package->price ?? 0);

                DB::transaction(function () use ($customer, $monthYear, $dueDate, $amount, $waService, &$createdCount) {
                    // Check if customer has an unpaid invoice
                    $unpaidInvoice = Invoice::withoutGlobalScopes()
                        ->where('customer_id', $customer->id)
                        ->where('paid', false)
                        ->where('status', '!=', 'paid')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($unpaidInvoice) {
                        $breakdown = $unpaidInvoice->periods_breakdown ? json_decode($unpaidInvoice->periods_breakdown, true) : [];
                        if (empty($breakdown) || !is_array($breakdown)) {
                            $origPeriod = $unpaidInvoice->period ?? \Carbon\Carbon::parse($unpaidInvoice->created_at)->format('Y-m');
                            $origAmount = (float) $unpaidInvoice->amount;
                            $origLabel = \Carbon\Carbon::parse($origPeriod . '-01')->translatedFormat('F Y');
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
                            if (($bd['period'] ?? '') === $monthYear) {
                                return;
                            }
                        }

                        $newLabel = \Carbon\Carbon::parse($monthYear . '-01')->translatedFormat('F Y');
                        $breakdown[] = [
                            'period' => $monthYear,
                            'amount' => $amount,
                            'label'  => $newLabel,
                        ];

                        $totalAmount = (float) array_sum(array_column($breakdown, 'amount'));
                        $periodLabels = array_map(fn($b) => $b['label'] ?? $b['period'], $breakdown);

                        $unpaidInvoice->update([
                            'amount'            => $totalAmount,
                            'period'            => $monthYear,
                            'due_date'          => $dueDate,
                            'periods_breakdown' => json_encode($breakdown),
                            'accumulated_from'  => $unpaidInvoice->accumulated_from ?: ($unpaidInvoice->period ?? \Carbon\Carbon::parse($unpaidInvoice->created_at)->format('Y-m')),
                            'description'       => "Tagihan Internet {$customer->package->name} (" . count($breakdown) . " Periode: " . implode(', ', $periodLabels) . ")",
                        ]);

                        $createdCount++;

                        if ($customer->phone) {
                            SendWhatsappNotificationJob::dispatch(
                                $customer->phone,
                                "Halo *{$customer->name}*,\n\nTagihan internet Anda periode *{$newLabel}* telah terbit dan diakumulasikan.\nTotal Tagihan Saat Ini (" . count($breakdown) . " Periode): *Rp " . number_format($totalAmount, 0, ',', '.') . "*.\nNomor Tagihan: *{$unpaidInvoice->invoice_number}*\nJatuh Tempo: *{$dueDate}*\n\nSilakan lakukan pembayaran melalui portal pelanggan Anda.",
                                $customer->tenant_id
                            );
                        }
                        return;
                    }

                    // Check if already paid for this period
                    $alreadyPaid = Invoice::withoutGlobalScopes()
                        ->where('customer_id', $customer->id)
                        ->where(function ($q) use ($monthYear) {
                            $q->where('period', $monthYear)
                              ->orWhere('created_at', 'like', "{$monthYear}%");
                        })
                        ->exists();

                    if ($alreadyPaid) {
                        return;
                    }

                    // Generate unique invoice number
                    $invoiceNumber = 'INV-' . $now->format('Ym') . '-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT);
                    $breakdown = [
                        [
                            'period' => $monthYear,
                            'amount' => $amount,
                            'label'  => \Carbon\Carbon::parse($monthYear . '-01')->translatedFormat('F Y'),
                        ]
                    ];

                    $invoice = Invoice::create([
                        'invoice_number'    => $invoiceNumber,
                        'customer_id'       => $customer->id,
                        'amount'            => $amount,
                        'due_date'          => $dueDate,
                        'status'            => 'pending',
                        'paid'              => false,
                        'period'            => $monthYear,
                        'tenant_id'         => $customer->tenant_id,
                        'periods_breakdown' => json_encode($breakdown),
                        'description'       => "Tagihan Internet {$customer->package->name} Periode " . \Carbon\Carbon::parse($monthYear . '-01')->translatedFormat('F Y'),
                    ]);

                    $createdCount++;

                    // Send WhatsApp invoice notification asynchronously
                    if ($customer->phone) {
                        SendWhatsappNotificationJob::dispatch(
                            $customer->phone,
                            "Halo *{$customer->name}*,\n\nTagihan internet Anda bulan *{$monthYear}* sebesar *Rp " . number_format($amount, 0, ',', '.') . "* telah terbit.\nNomor Tagihan: *{$invoiceNumber}*\nJatuh Tempo: *{$dueDate}*\n\nSilakan lakukan pembayaran melalui portal pelanggan Anda.",
                            $customer->tenant_id
                        );
                    }
                });
            }
        });

        Log::info('[ProcessMonthlyInvoicesJob] Finished invoice generation', [
            'tenant_id' => $this->tenantId,
            'created' => $createdCount,
        ]);
    }
}
