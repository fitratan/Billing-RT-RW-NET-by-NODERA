<?php

namespace App\Actions\Finance;

use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class MarkInvoiceAsPaid
{
    public function execute(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => 'paid',
            'paid' => true,
            'paid_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_invoice.paid',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
        ]);

        Cache::forget('superadmin.dashboard.stats');

        return $invoice->fresh();
    }
}
