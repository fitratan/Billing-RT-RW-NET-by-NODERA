<?php

namespace App\Actions\Finance;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class GenerateSubscriptionInvoice
{
    public function execute(Tenant $tenant, string $period, ?int $amount = null, ?string $dueDate = null): Invoice
    {
        $price = $amount ?? (int) ($tenant->settings['subscribed_price'] ?? 0);
        $due = $dueDate ? now()->parse($dueDate) : now()->addDays(7);

        $existing = Invoice::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('customer_name', $tenant->name)
            ->where('period', $period)
            ->first();

        if ($existing) {
            throw new \RuntimeException("Invoice untuk {$tenant->name} periode {$period} sudah ada");
        }

        $invoice = Invoice::create([
            'tenant_id' => null,
            'customer_id' => null,
            'customer_name' => $tenant->name,
            'invoice_number' => 'SUB-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'amount' => $price,
            'due_date' => $due,
            'period' => $period,
            'status' => 'pending',
            'paid' => false,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_invoice.generated',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'new_values' => ['tenant_id' => $tenant->id, 'period' => $period, 'amount' => $price],
        ]);

        Cache::forget('superadmin.dashboard.stats');

        return $invoice;
    }
}
