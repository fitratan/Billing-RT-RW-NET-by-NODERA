<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;

/**
 * NominalUnikService — assigns and validates "unique amount" (nominal unik)
 * for automated reconciliation of QRIS and Bank Transfer payments.
 *
 * A small unique code (1-999) is appended to the regular invoice amount so the
 * resulting number uniquely identifies which invoice is being settled.
 */
class NominalUnikService
{
    public const MIN_CODE = 1;
    public const MAX_CODE = 999;

    /**
     * Assign (or reuse) a unique code for an unpaid invoice. Reserves a code
     * not currently used by any other unpaid invoice of the same tenant.
     *
     * @return array{code: int, amount: float}
     */
    public function assign(Invoice $invoice): array
    {
        $tenantId = $invoice->tenant_id;

        // Reuse existing assignment if still unpaid and valid.
        if ($invoice->unique_code && $invoice->status !== 'paid' && !$invoice->paid) {
            return [
                'code' => (int) $invoice->unique_code,
                'amount' => (float) ($invoice->unique_amount ?: ((float) $invoice->amount + (int) $invoice->unique_code)),
            ];
        }

        $query = Invoice::withoutGlobalScopes()
            ->where('status', '!=', 'paid')
            ->where('paid', false)
            ->whereNotNull('unique_code')
            ->where('id', '!=', $invoice->id);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $used = $query->pluck('unique_code');
        $code = $this->firstFreeCode($used);

        $amount = ((float) $invoice->amount) + $code;

        $invoice->update([
            'unique_code' => $code,
            'unique_amount' => $amount,
        ]);

        return ['code' => $code, 'amount' => $amount];
    }

    /**
     * Force reassign a fresh unique code for an unpaid invoice.
     *
     * @return array{code: int, amount: float}
     */
    public function reassign(Invoice $invoice): array
    {
        $tenantId = $invoice->tenant_id;

        $query = Invoice::withoutGlobalScopes()
            ->where('status', '!=', 'paid')
            ->where('paid', false)
            ->whereNotNull('unique_code')
            ->where('id', '!=', $invoice->id);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $used = $query->pluck('unique_code');
        $code = $this->firstFreeCode($used);

        $amount = ((float) $invoice->amount) + $code;

        $invoice->update([
            'unique_code' => $code,
            'unique_amount' => $amount,
        ]);

        return ['code' => $code, 'amount' => $amount];
    }

    /**
     * Match a received amount against pending invoices by unique_amount or fallback exact amount.
     * Returns the matched invoice or null.
     */
    public function matchAmount(float $amount, ?int $tenantId = null): ?Invoice
    {
        // 1. Exact match on unique_amount
        $query = Invoice::withoutGlobalScopes()
            ->where('unique_amount', $amount)
            ->where('status', '!=', 'paid')
            ->where('paid', false);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $invoice = $query->orderByDesc('updated_at')->first();
        if ($invoice) {
            return $invoice;
        }

        // 2. Fallback: match base amount if only one single unpaid invoice exists with this exact base amount
        $baseQuery = Invoice::withoutGlobalScopes()
            ->where('amount', $amount)
            ->where('status', '!=', 'paid')
            ->where('paid', false);

        if ($tenantId !== null) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        $baseMatches = $baseQuery->get();
        if ($baseMatches->count() === 1) {
            return $baseMatches->first();
        }

        return null;
    }

    /**
     * Verify the shared webhook secret for QRIS & unique-amount callback.
     */
    public function verifySecret(?string $received, ?int $tenantId = null): bool
    {
        $candidates = [];

        // 1. Check Setting table
        $settingSecret = Setting::apiValue('NOMINAL_UNIK_SECRET', '');
        if ($settingSecret) {
            $candidates[] = $settingSecret;
        }

        // 2. Check QRIS Gateway settings
        $qrisGateway = PaymentGateway::withoutGlobalScopes()
            ->where('gateway', 'manual')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $gwSecret = $qrisGateway?->config_json['webhook_secret'] ?? null;
        if ($gwSecret) {
            $candidates[] = $gwSecret;
        }

        // 3. Check environment variable
        $envSecret = env('QRIS_WEBHOOK_SECRET') ?: env('NOMINAL_UNIK_SECRET');
        if ($envSecret) {
            $candidates[] = $envSecret;
        }

        // Fail-Closed: If no secret configured anywhere in system or no secret received, reject immediately
        if (empty($candidates) || empty($received)) {
            \Illuminate\Support\Facades\Log::warning('[NominalUnikService] Webhook rejected: no secret configured or secret missing in request.');
            return false;
        }

        foreach ($candidates as $cand) {
            if (hash_equals((string) $cand, (string) $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Release unique code on an invoice.
     */
    public function release(Invoice $invoice): void
    {
        $invoice->update([
            'unique_code' => null,
            'unique_amount' => null,
        ]);
    }

    /**
     * Find the smallest code in [1,999] not present in the used set.
     */
    private function firstFreeCode($used): int
    {
        $usedSet = collect($used)
            ->map(fn ($v) => (int) (is_object($v) ? ($v->unique_code ?? $v->value ?? 0) : $v))
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->toArray();
        $usedMap = array_flip($usedSet);

        for ($i = self::MIN_CODE; $i <= self::MAX_CODE; $i++) {
            if (!isset($usedMap[$i])) {
                return $i;
            }
        }

        // Rollback / wrap around
        return rand(100, 999);
    }
}