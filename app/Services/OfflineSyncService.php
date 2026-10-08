<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncService
{
    /**
     * Process offline payment collected by field agent/collector.
     * Uses Optimistic Concurrency Control: if invoice is already paid, converts to Customer Overpayment Credit Deposit.
     */
    public function syncOfflinePayment(array $paymentData, int $collectorId, ?string $collectorName = null): array
    {
        return DB::transaction(function () use ($paymentData, $collectorId, $collectorName) {
            $invoiceId = $paymentData['invoice_id'] ?? null;
            $customerId = $paymentData['customer_id'] ?? null;
            $amount = (float) ($paymentData['amount'] ?? 0);
            $collectedAt = $paymentData['collected_at'] ?? now()->toIso8601String();
            $reference = $paymentData['reference'] ?? ('OFFLINE-' . uniqid());

            $invoice = $invoiceId ? Invoice::lockForUpdate()->find($invoiceId) : null;
            $customer = $customerId ? Customer::lockForUpdate()->find($customerId) : ($invoice ? $invoice->customer : null);

            if (!$customer) {
                return ['success' => false, 'error' => 'Customer not found.'];
            }

            // Case A: Invoice is already PAID (e.g. customer paid via QRIS/PG while collector was offline)
            if ($invoice && (bool) $invoice->paid) {
                // Convert cash to Customer Balance Deposit
                $customer->increment('balance', $amount);

                // Create Audit Trail
                AuditLog::create([
                    'tenant_id' => $customer->tenant_id,
                    'user_id' => $collectorId,
                    'action' => 'offline_payment_converted_to_deposit',
                    'entity_type' => 'customer',
                    'entity_id' => $customer->id,
                    'new_values' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $amount,
                        'reason' => 'Invoice already paid via online gateway. Converted to deposit credit.',
                        'collector' => $collectorName ?? "Collector #{$collectorId}",
                        'reference' => $reference,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => 'Nodera Field App / Offline Sync',
                ]);

                Log::info("[OfflineSyncService] Invoice #{$invoice->id} was already paid. Converted {$amount} to deposit for Customer #{$customer->id}.");

                return [
                    'success' => true,
                    'status' => 'CONVERTED_TO_DEPOSIT',
                    'message' => 'Tagihan sudah dibayar online sebelumnya. Uang tunai otomatis masuk ke Saldo Deposit pelanggan.',
                    'customer_id' => $customer->id,
                    'new_balance' => $customer->balance,
                ];
            }

            // Case B: Invoice is UNPAID — Mark as paid by collector
            if ($invoice) {
                $invoice->update([
                    'paid' => true,
                    'status' => 'paid',
                    'paid_at' => $collectedAt,
                    'payment_method' => 'cash',
                ]);

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'tenant_id' => $customer->tenant_id,
                    'amount' => $amount,
                    'method' => 'cash',
                    'reference' => $reference,
                    'status' => 'success',
                    'paid_at' => $collectedAt,
                    'collector_id' => $collectorId,
                ]);

                // Auto-unisolate if customer was isolated
                if ($customer->status === 'isolated') {
                    app(IsolationService::class)->unisolateCustomer($customer, "Collector ({$collectorName})");
                }

                return [
                    'success' => true,
                    'status' => 'PAID',
                    'message' => 'Pembayaran tagihan tunai berhasil dicatat dan disinkronkan.',
                    'invoice_id' => $invoice->id,
                ];
            }

            return ['success' => false, 'error' => 'Invalid invoice or payment payload.'];
        });
    }
}
