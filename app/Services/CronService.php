<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClientLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CronService — manage scheduled jobs for NODERA ISP operations.
 *
 * Handles:
 * - Monthly invoice generation (invoice:generate)
 * - Daily isolation check (isolation:check)
 * - Usage polling trigger (usage:poll)
 * - Weekly database backup (backup:database)
 *
 * Uses Laravel's scheduler via bootstrap/app.php withSchedule(),
 * plus an HTTP fallback trigger at GET /cron/run/{job}.
 *
 * Reference: billing-rtrw-main/services/cronService.js
 */
class CronService
{
    /** Available job names and their descriptions. */
    public const JOBS = [
        'invoice:generate' => 'Generate monthly invoices for all active customers',
        'isolation:check' => 'Auto-isolate overdue customers, auto-unisolate after payment',
        'invoice:remind' => 'Send WhatsApp invoice reminders (H-3, H-1, Due Date)',
        'network:monitor' => 'Monitor router & OLT connectivity, dispatch alert on down/up',
        'usage:poll' => 'Poll MikroTik routers for bandwidth usage data',
        'backup:database' => 'Create a database backup dump',
        'subscription:invoice' => 'Generate subscription invoices for all active tenants',
        'jamkalong:start' => 'Switch PPPoE to night profile (Jam Kalong) at 00:00',
        'jamkalong:end' => 'Restore normal PPPoE profile after Jam Kalong at 06:00',
        'fup:check' => 'Apply FUP profile for customers exceeding monthly quota',
        'logs:prune' => 'Prune old client_logs & audit_logs (older than 90 days)',
        'hotspot:clean-expired' => 'Clean and kick expired Hotspot active sessions and users',
    ];

    /** Cache prefix for job locking. */
    private const JOB_LOCK_PREFIX = 'cron_job_running_';

    /** @var int Lock TTL in seconds (prevents concurrent runs). */
    private const LOCK_TTL = 3600;

    // ------------------------------------------------------------------
    //  Job execution
    // ------------------------------------------------------------------

    /**
     * Run a specific cron job by name.
     *
     * @throws \InvalidArgumentException
     */
    public function runJob(string $jobName): array
    {
        if (! isset(self::JOBS[$jobName])) {
            throw new \InvalidArgumentException("Unknown cron job: {$jobName}");
        }

        if ($this->isJobRunning($jobName)) {
            return [
                'success' => false,
                'message' => "Job '{$jobName}' is already running. Skipped.",
            ];
        }

        $this->acquireLock($jobName);

        try {
            $result = match ($jobName) {
                'invoice:generate' => $this->generateInvoices(),
                'isolation:check' => $this->checkIsolation(),
                'invoice:remind' => $this->sendInvoiceReminders(),
                'network:monitor' => $this->monitorNetwork(),
                'usage:poll' => $this->pollUsage(),
                'backup:database' => $this->backupDatabase(),
                'subscription:invoice' => $this->generateSubscriptionInvoices(),
                'jamkalong:start' => $this->jamKalongStart(),
                'jamkalong:end' => $this->jamKalongEnd(),
                'fup:check' => $this->checkFup(),
                'logs:prune' => $this->pruneLogs(),
                'hotspot:clean-expired' => $this->cleanExpiredHotspot(),
            };

            $this->releaseLock($jobName);

            return [
                'success' => true,
                'message' => "Job '{$jobName}' completed successfully.",
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            $this->releaseLock($jobName);

            Log::error("[CronService] Job '{$jobName}' failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => "Job '{$jobName}' failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check if a cron job is currently running (via cache lock).
     */
    public function isJobRunning(string $jobName): bool
    {
        return Cache::has(self::JOB_LOCK_PREFIX.$jobName);
    }

    /**
     * Get the status of all jobs.
     */
    public function getJobStatus(): array
    {
        $status = [];
        foreach (self::JOBS as $name => $description) {
            $status[$name] = [
                'description' => $description,
                'running' => $this->isJobRunning($name),
            ];
        }

        return $status;
    }

    // ------------------------------------------------------------------
    //  Individual job implementations
    // ------------------------------------------------------------------

    /**
     * Generate monthly invoices for all active customers.
     * Runs on the 1st of each month at 00:01.
     */
    /**
     * Generate monthly invoices for all active customers based on tenant scheduled day.
     * Runs daily at 00:01.
     */
    public function generateInvoices(): array
    {
        $now = now();
        $todayDay = (int) $now->day;
        Log::info("[CronService] Checking scheduled invoice generation for Day {$todayDay}...");

        $tenants = Tenant::withoutGlobalScopes()->where('is_active', true)->get();
        $totalGenerated = 0;
        $totalSkipped = 0;
        $allErrors = [];

        foreach ($tenants as $tenant) {
            $isAuto = Setting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', 'INVOICE_AUTO_GENERATE')->value('value') ?? '1';
            if ($isAuto !== '1' && $isAuto !== 'true' && $isAuto !== true) {
                continue;
            }

            $genDay = (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', 'INVOICE_GENERATE_DAY')->value('value') ?? 1);
            if ($todayDay !== $genDay) {
                continue;
            }

            $res = $this->generateInvoicesForTenant($tenant->id);
            $totalGenerated += $res['generated'];
            $totalSkipped += $res['skipped'];
            $allErrors = array_merge($allErrors, $res['errors']);
        }

        // Global / Standalone Customers without tenant_id
        $globalAuto = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'INVOICE_AUTO_GENERATE')->value('value') ?? '1';
        if ($globalAuto === '1' || $globalAuto === 'true' || $globalAuto === true) {
            $globalGenDay = (int) (Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'INVOICE_GENERATE_DAY')->value('value') ?? 1);
            if ($todayDay === $globalGenDay) {
                $resGlobal = $this->generateInvoicesForTenant(null);
                $totalGenerated += $resGlobal['generated'];
                $totalSkipped += $resGlobal['skipped'];
                $allErrors = array_merge($allErrors, $resGlobal['errors']);
            }
        }

        return [
            'period' => $now->format('Y-m'),
            'generated' => $totalGenerated,
            'skipped' => $totalSkipped,
            'errors' => $allErrors,
        ];
    }

    /**
     * Consolidate duplicate pending invoices for the same customer into a single card with periods_breakdown.
     */
    public static function consolidatePendingInvoices(?int $tenantId = null): int
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('paid', false)
            ->where('status', '!=', 'paid');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $customerIds = (clone $query)->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('count(*) > 1')
            ->pluck('customer_id');

        $mergedCount = 0;
        foreach ($customerIds as $cId) {
            $pendingInvs = Invoice::withoutGlobalScopes()
                ->where('customer_id', $cId)
                ->where('paid', false)
                ->where('status', '!=', 'paid')
                ->orderBy('id', 'asc')
                ->get();

            if ($pendingInvs->count() <= 1) {
                continue;
            }

            $primaryInv = $pendingInvs->first();
            $allBreakdowns = [];

            foreach ($pendingInvs as $inv) {
                $bd = $inv->periods_breakdown ? json_decode($inv->periods_breakdown, true) : [];
                if (!empty($bd) && is_array($bd)) {
                    foreach ($bd as $item) {
                        $p = $item['period'] ?? '';
                        if ($p && !isset($allBreakdowns[$p])) {
                            $allBreakdowns[$p] = [
                                'period' => $p,
                                'amount' => (float) ($item['amount'] ?? $inv->amount),
                                'label'  => $item['label'] ?? \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y'),
                            ];
                        }
                    }
                } else {
                    $p = $inv->period ?? \Carbon\Carbon::parse($inv->created_at)->format('Y-m');
                    if ($p && !isset($allBreakdowns[$p])) {
                        $allBreakdowns[$p] = [
                            'period' => $p,
                            'amount' => (float) $inv->amount,
                            'label'  => \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y'),
                        ];
                    }
                }
            }

            ksort($allBreakdowns);
            $finalBreakdown = array_values($allBreakdowns);
            $totalAmount = (float) array_sum(array_column($finalBreakdown, 'amount'));
            $latestPeriod = array_key_last($allBreakdowns);
            $earliestPeriod = array_key_first($allBreakdowns);
            $periodLabels = array_map(fn($b) => $b['label'] ?? $b['period'], $finalBreakdown);

            $primaryInv->update([
                'amount'            => $totalAmount,
                'period'            => $latestPeriod,
                'periods_breakdown' => json_encode($finalBreakdown),
                'accumulated_from'  => $earliestPeriod,
                'description'       => 'Tagihan Internet (' . count($finalBreakdown) . ' Periode: ' . implode(', ', $periodLabels) . ')',
            ]);

            $otherIds = $pendingInvs->slice(1)->pluck('id');
            Invoice::withoutGlobalScopes()->whereIn('id', $otherIds)->delete();
            $mergedCount += count($otherIds);
        }

        // Step 2: Automatically accumulate past unpaid arrears into the current active month
        $currentPeriod = now()->format('Y-m');
        $pendingInvoices = (clone $query)->get();

        foreach ($pendingInvoices as $inv) {
            $customer = Customer::withoutGlobalScopes()->with('package')->find($inv->customer_id);
            if (!$customer) {
                continue;
            }

            // Only accumulate for active or isolated customers
            if (!in_array($customer->status, ['active', 'isolated'])) {
                continue;
            }

            // Exclude hotspot / voucher customers
            if ($customer->connection_type && in_array($customer->connection_type, ['hotspot', 'voucher'])) {
                continue;
            }

            $bd = $inv->periods_breakdown ? json_decode($inv->periods_breakdown, true) : [];
            $allBreakdowns = [];

            if (!empty($bd) && is_array($bd)) {
                foreach ($bd as $item) {
                    $p = $item['period'] ?? '';
                    if ($p && !isset($allBreakdowns[$p])) {
                        $allBreakdowns[$p] = [
                            'period' => $p,
                            'amount' => (float) ($item['amount'] ?? $inv->amount),
                            'label'  => $item['label'] ?? \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y'),
                        ];
                    }
                }
            } else {
                $p = $inv->period ?? \Carbon\Carbon::parse($inv->created_at)->format('Y-m');
                if ($p && !isset($allBreakdowns[$p])) {
                    $allBreakdowns[$p] = [
                        'period' => $p,
                        'amount' => (float) $inv->amount,
                        'label'  => \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y'),
                    ];
                }
            }

            if (empty($allBreakdowns)) {
                continue;
            }

            ksort($allBreakdowns);
            $latestPeriod = array_key_last($allBreakdowns);

            // If the latest period in this invoice is older than the current month
            if ($latestPeriod < $currentPeriod) {
                $monthlyRate = (float) ($customer->package?->price ?? 0);
                if ($monthlyRate <= 0) {
                    $lastItem = end($allBreakdowns);
                    $monthlyRate = (float) ($lastItem['amount'] ?? $inv->amount);
                }

                // Loop month by month from latestPeriod up to currentPeriod
                $curr = \Carbon\Carbon::parse($latestPeriod . '-01')->addMonth();
                $target = \Carbon\Carbon::parse($currentPeriod . '-01');
                $addedNew = false;

                while ($curr->lte($target)) {
                    $pKey = $curr->format('Y-m');

                    // Check if customer already paid for this period in another invoice
                    $alreadyPaid = Invoice::withoutGlobalScopes()
                        ->where('customer_id', $customer->id)
                        ->where(function ($q) {
                            $q->where('paid', 1)->orWhere('status', 'paid');
                        })
                        ->where(function ($q) use ($pKey) {
                            $q->where('period', $pKey)
                              ->orWhere('periods_breakdown', 'like', "%\"{$pKey}\"%");
                        })
                        ->exists();

                    if (!$alreadyPaid && !isset($allBreakdowns[$pKey])) {
                        $allBreakdowns[$pKey] = [
                            'period' => $pKey,
                            'amount' => $monthlyRate,
                            'label'  => $curr->translatedFormat('F Y'),
                        ];
                        $addedNew = true;
                    }

                    $curr->addMonth();
                }

                if ($addedNew) {
                    ksort($allBreakdowns);
                    $finalBreakdown = array_values($allBreakdowns);
                    $totalAmount = (float) array_sum(array_column($finalBreakdown, 'amount'));
                    $newLatestPeriod = array_key_last($allBreakdowns);
                    $earliestPeriod = array_key_first($allBreakdowns);
                    $periodLabels = array_map(fn($b) => $b['label'] ?? $b['period'], $finalBreakdown);

                    $isoDay = (int) ($customer->isolation_date ?: 20);
                    if ($isoDay < 1 || $isoDay > 31) {
                        $isoDay = 20;
                    }
                    $targetDue = \Carbon\Carbon::parse($newLatestPeriod . '-01')->day(min($isoDay, \Carbon\Carbon::parse($newLatestPeriod . '-01')->daysInMonth));
                    $dueDate = $targetDue->format('Y-m-d');

                    $inv->update([
                        'amount'            => $totalAmount,
                        'period'            => $newLatestPeriod,
                        'due_date'          => $dueDate,
                        'periods_breakdown' => json_encode($finalBreakdown),
                        'accumulated_from'  => $inv->accumulated_from ?: $earliestPeriod,
                        'description'       => 'Tagihan Internet ' . ($customer->package ? $customer->package->name . ' ' : '') . '(' . count($finalBreakdown) . ' Periode: ' . implode(', ', $periodLabels) . ')',
                    ]);
                }
            }
        }

        return $mergedCount;
    }

    /**
     * Generate invoices for a specific tenant (or global customers if null).
     */
    public function generateInvoicesForTenant(?int $tenantId = null): array
    {
        // 1. Consolidate any existing duplicate pending invoices first
        self::consolidatePendingInvoices($tenantId);

        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');
        $periodKey = $now->format('Y-m');
        $altPeriodKey = "{$year}-{$month}";

        $dueDaySetting = $tenantId
            ? (int) (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_DUE_DAY')->value('value') ?? 20)
            : 20;

        $autoWa = $tenantId
            ? (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'INVOICE_AUTO_WA')->value('value') ?? '1')
            : '1';
        $shouldSendWa = $autoWa === '1' || $autoWa === 'true' || $autoWa === true;

        $generated = 0;
        $skipped = 0;
        $errors = [];

        Customer::withoutGlobalScopes()
            ->with('package')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('connection_type')
                  ->orWhereNotIn('connection_type', ['hotspot', 'voucher']);
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->chunkById(250, function ($customers) use (
                $now, $year, $month, $periodKey, $altPeriodKey, $dueDaySetting, $shouldSendWa,
                &$generated, &$skipped, &$errors
            ) {
                foreach ($customers as $customer) {
                    if (! $customer->package) {
                        $skipped++;
                        continue;
                    }

                    $lockKey = "gen_inv_{$customer->tenant_id}_{$customer->id}_{$periodKey}";
                    Cache::lock($lockKey, 30)->get(function () use (
                        $customer, $dueDaySetting, $now, $periodKey, $altPeriodKey, $year, $month, $shouldSendWa,
                        &$generated, &$skipped, &$errors
                    ) {
                        try {
                            $package = $customer->package;
                            $amount = (float) $package->price;

                            $isoDay = (int) ($customer->isolation_date ?: $dueDaySetting);
                            if ($isoDay < 1 || $isoDay > 31) {
                                $isoDay = 20;
                            }

                            // Jika pelanggan baru dibuat/disinkronkan di bulan berjalan dan tanggal pendaftarannya >= tanggal isolir:
                            // Maka jatuh tempo / auto-isolirnya dialihkan ke bulan berikutnya
                            $targetDue = $now->copy()->day(min($isoDay, $now->daysInMonth));
                            if ($customer->created_at) {
                                $createdAt = \Carbon\Carbon::parse($customer->created_at);
                                if ($createdAt->format('Y-m') === $now->format('Y-m') && $createdAt->day >= $isoDay) {
                                    $targetDue = $now->copy()->addMonth()->day(min($isoDay, $now->copy()->addMonth()->daysInMonth));
                                }
                            }
                            $dueDate = $targetDue->format('Y-m-d');
                            $invNumber = 'INV-'.$now->format('Ym').'-'.$customer->id;

                            // 0. Check if this period was already PAID in advance or in a previous invoice
                            $alreadyPaid = Invoice::withoutGlobalScopes()
                                ->where('customer_id', $customer->id)
                                ->where(function ($q) {
                                    $q->where('paid', 1)->orWhere('status', 'paid');
                                })
                                ->where(function ($q) use ($year, $month, $periodKey, $altPeriodKey) {
                                    $q->where('period', $periodKey)
                                        ->orWhere('period', $altPeriodKey)
                                        ->orWhere('periods_breakdown', 'like', "%\"{$periodKey}\"%")
                                        ->orWhere('periods_breakdown', 'like', "%\"{$altPeriodKey}\"%")
                                        ->orWhere(function ($q2) use ($year, $month) {
                                            $q2->whereYear('created_at', $year)->whereMonth('created_at', $month);
                                        });
                                })
                                ->exists();

                            if ($alreadyPaid) {
                                $skipped++;
                                return;
                            }

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

                                // Check if this period already exists in breakdown or in invoice period
                                $hasPeriod = false;
                                foreach ($breakdown as $bd) {
                                    if (($bd['period'] ?? '') === $periodKey || ($bd['period'] ?? '') === $altPeriodKey) {
                                        $hasPeriod = true;
                                        break;
                                    }
                                }

                                if ($hasPeriod) {
                                    $skipped++;
                                    return;
                                }

                                // Append new period to existing unpaid invoice
                                $newLabel = \Carbon\Carbon::parse($periodKey . '-01')->translatedFormat('F Y');
                                $breakdown[] = [
                                    'period' => $periodKey,
                                    'amount' => $amount,
                                    'label'  => $newLabel,
                                ];

                                $totalAmount = (float) array_sum(array_column($breakdown, 'amount'));
                                $periodLabels = array_map(fn($b) => $b['label'] ?? $b['period'], $breakdown);

                                $unpaidInvoice->update([
                                    'amount'            => $totalAmount,
                                    'period'            => $periodKey,
                                    'due_date'          => $dueDate,
                                    'periods_breakdown' => json_encode($breakdown),
                                    'accumulated_from'  => $unpaidInvoice->accumulated_from ?: ($unpaidInvoice->period ?? \Carbon\Carbon::parse($unpaidInvoice->created_at)->format('Y-m')),
                                    'description'       => "Tagihan Internet {$package->name} (" . count($breakdown) . " Periode: " . implode(', ', $periodLabels) . ")",
                                ]);

                                $generated++;

                                if ($shouldSendWa && ! empty($customer->phone)) {
                                    try {
                                        $wa = new \App\Services\WhatsappService($customer->tenant_id);
                                        $wa->sendMessage(
                                            $customer->phone,
                                            "Halo *{$customer->name}*,\n\nTagihan internet Anda periode *{$newLabel}* telah terbit dan diakumulasikan.\nTotal Tagihan Saat Ini (" . count($breakdown) . " Periode): *Rp " . number_format($totalAmount, 0, ',', '.') . "*.\nNomor Tagihan: *{$unpaidInvoice->invoice_number}*\nJatuh Tempo: *" . date('d F Y', strtotime($dueDate)) . "*\n\nSilakan lakukan pembayaran melalui portal pelanggan Anda."
                                        );
                                    } catch (\Throwable $e) {
                                        Log::warning("[CronService] Direct WA notification failed for customer #{$customer->id}: " . $e->getMessage());
                                    }
                                }
                            } else {
                                // Create a fresh new invoice
                                $breakdown = [
                                    [
                                        'period' => $periodKey,
                                        'amount' => $amount,
                                        'label'  => \Carbon\Carbon::parse($periodKey . '-01')->translatedFormat('F Y'),
                                    ]
                                ];

                                $invoice = Invoice::create([
                                    'customer_id'       => $customer->id,
                                    'invoice_number'    => $invNumber,
                                    'amount'            => $amount,
                                    'description'       => 'Tagihan Internet ' . $package->name . ' Periode ' . \Carbon\Carbon::parse($periodKey . '-01')->translatedFormat('F Y'),
                                    'due_date'          => $dueDate,
                                    'paid'              => false,
                                    'status'            => 'pending',
                                    'period'            => $periodKey,
                                    'tenant_id'         => $customer->tenant_id,
                                    'periods_breakdown' => json_encode($breakdown),
                                ]);

                                $generated++;

                                if ($shouldSendWa && ! empty($customer->phone)) {
                                    try {
                                        $wa = new \App\Services\WhatsappService($customer->tenant_id);
                                        $wa->sendMessage(
                                            $customer->phone,
                                            "Halo *{$customer->name}*,\n\nTagihan internet Anda periode *{$now->translatedFormat('F Y')}* telah terbit sebesar *Rp " . number_format($amount, 0, ',', '.') . "*.\nNomor Tagihan: *{$invNumber}*\nJatuh Tempo: *" . date('d F Y', strtotime($dueDate)) . "*\n\nSilakan lakukan pembayaran melalui portal pelanggan Anda."
                                        );
                                    } catch (\Throwable $e) {
                                        Log::warning("[CronService] Direct WA notification failed for customer #{$customer->id}: " . $e->getMessage());
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Customer #{$customer->id} ({$customer->name}): {$e->getMessage()}";
                            Log::error("[CronService] Failed to generate invoice for customer #{$customer->id}: {$e->getMessage()}");
                        }
                    });
                }
            });

        Log::info("[CronService] Invoices generated for tenant #{$tenantId}: {$generated} generated/accumulated, {$skipped} skipped.");

        return [
            'period' => $periodKey,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Check for overdue invoices and auto-isolate customers.
     * Also auto-unisolates customers who have paid.
     * Runs daily at 02:00.
     */
    protected function checkIsolation(): array
    {
        $today = now()->format('Y-m-d');

        Log::info("[CronService] Running isolation check for {$today}");

        $isolated = 0;
        $unisolated = 0;

        // --- ISOLATE: Customers with overdue unpaid invoices (Chunked to prevent memory spikes) ---
        Customer::query()
            ->with(['router', 'package', 'invoices' => function ($q) use ($today) {
                $q->where('paid', 0)
                  ->where('due_date', '<', $today)
                  ->whereRaw('due_date >= DATE(customers.created_at)');
            }])
            ->where('customers.status', 'active')
            ->whereHas('invoices', function ($q) use ($today) {
                $q->where('paid', 0)
                  ->where('due_date', '<', $today)
                  ->whereRaw('invoices.due_date >= DATE(customers.created_at)');
            })
            ->chunkById(200, function ($overdueCustomers) use ($today, &$isolated) {
                foreach ($overdueCustomers as $customer) {
                    // 1. Lewati jika pelanggan baru dibuat/sync di bulan berjalan pada atau setelah tanggal isolir
                    $isoDay = (int) ($customer->isolation_date ?: 20);
                    if ($customer->created_at) {
                        $createdAt = \Carbon\Carbon::parse($customer->created_at);
                        if ($createdAt->format('Y-m') === now()->format('Y-m') && $createdAt->day >= $isoDay) {
                            Log::info("[CronService] Skipping auto-isolation for customer #{$customer->id} ({$customer->name}): registered/synced on {$createdAt->format('Y-m-d')} on or after isolation day ({$isoDay}). Auto-isolation starts next month.");
                            continue;
                        }
                    }

                    // 2. Lewati jika paket mematikan isolir otomatis
                    if ($customer->package && !empty($customer->package->id) && empty($customer->package->auto_isolir)) {
                        Log::info("[CronService] Skipping auto-isolation for customer #{$customer->id} ({$customer->name}) because package '{$customer->package->name}' has auto_isolir disabled.");
                        continue;
                    }

                    // 3. Cek interval bulan isolir jika diatur > 1 bulan
                    $intervalMonths = (int) ($customer->package?->isolir_interval_months ?? 1);
                    if ($intervalMonths > 1) {
                        $overdueCount = $customer->invoices->count();
                        if ($overdueCount < $intervalMonths) {
                            Log::info("[CronService] Skipping auto-isolation for customer #{$customer->id} ({$customer->name}): overdue invoices count ({$overdueCount}) < package interval threshold ({$intervalMonths}).");
                            continue;
                        }
                    }

                    try {
                        $this->isolateCustomer($customer);
                        $isolated++;
                    } catch (\Exception $e) {
                        Log::error("[CronService] Failed to isolate customer #{$customer->id}: {$e->getMessage()}");
                    }
                }
            });

        // --- UNISOLATE: Customers who have paid all overdue / have no valid overdue invoices ---
        Customer::query()
            ->with('router', 'package')
            ->where('customers.status', 'isolated')
            ->whereDoesntHave('invoices', function ($q) use ($today) {
                $q->where('paid', 0)
                  ->where('due_date', '<', $today)
                  ->whereRaw('invoices.due_date >= DATE(customers.created_at)');
            })
            ->chunkById(200, function ($isolatedCustomers) use (&$unisolated) {
                foreach ($isolatedCustomers as $customer) {
                    try {
                        $this->unisolateCustomer($customer);
                        $unisolated++;
                    } catch (\Exception $e) {
                        Log::error("[CronService] Failed to unisolate customer #{$customer->id}: {$e->getMessage()}");
                    }
                }
            });

        $result = [
            'date' => $today,
            'isolated' => $isolated,
            'unisolated' => $unisolated,
        ];

        Log::info("[CronService] Isolation check complete: {$isolated} isolated, {$unisolated} unisolated.");

        return $result;
    }

    /**
     * Automated WhatsApp invoice reminders (H-3, H-1, Due Date).
     * Runs daily at 09:00.
     */
    public function sendInvoiceReminders(): array
    {
        Log::info("[CronService] Running automated WhatsApp invoice reminders...");

        $sentCount = 0;
        $failedCount = 0;
        $totalProcessed = 0;

        $tenants = \App\Models\Tenant::withoutGlobalScopes()->where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $res = $this->sendInvoiceRemindersForTenant($tenant->id, false);
            $sentCount += $res['sent'] ?? 0;
            $failedCount += $res['failed'] ?? 0;
            $totalProcessed += $res['total_processed'] ?? 0;
        }

        // Handle standalone invoices without tenant_id
        $resGlobal = $this->sendInvoiceRemindersForTenant(null, false);
        $sentCount += $resGlobal['sent'] ?? 0;
        $failedCount += $resGlobal['failed'] ?? 0;
        $totalProcessed += $resGlobal['total_processed'] ?? 0;

        Log::info("[CronService] WhatsApp reminders complete: {$sentCount} sent, {$failedCount} failed.");

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total_processed' => $totalProcessed,
        ];
    }

    /**
     * Send invoice reminders for a specific tenant.
     * If $forceAllUnpaid is true, sends to all unpaid & overdue invoices immediately.
     */
    public function sendInvoiceRemindersForTenant(?int $tenantId = null, bool $forceAllUnpaid = false): array
    {
        $sentCount = 0;
        $failedCount = 0;
        $totalProcessed = 0;

        $isAuto = \App\Models\Setting::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
            ->where('key', 'INVOICE_REMINDER_AUTO')->value('value')
            ?? \App\Models\Setting::withoutGlobalScopes()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
                ->where('key', 'NOTIF_REMINDER_ENABLED')->value('value')
            ?? '1';

        if (!$forceAllUnpaid && ($isAuto === '0' || $isAuto === 'false' || $isAuto === false)) {
            return [
                'sent' => 0,
                'failed' => 0,
                'total_processed' => 0,
                'message' => 'Otomatisasi pengingat dinonaktifkan.',
            ];
        }

        $reminderDaysSetting = \App\Models\Setting::withoutGlobalScopes()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
            ->where('key', 'INVOICE_REMINDER_DAYS')->value('value') ?? '3,1,0';
        $daysArray = array_filter(array_map('trim', explode(',', (string) $reminderDaysSetting)), fn($v) => is_numeric($v));
        if (empty($daysArray)) {
            $daysArray = [3, 1, 0];
        }

        $query = Invoice::withoutGlobalScopes()
            ->with(['customer', 'customer.package'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId), fn($q) => $q->whereNull('tenant_id'))
            ->where('paid', false);

        if (!$forceAllUnpaid) {
            $targetDates = [];
            foreach ($daysArray as $d) {
                $targetDates[] = now()->addDays((int) $d)->format('Y-m-d');
            }
            $targetDates = array_values(array_unique($targetDates));
            $query->whereIn('due_date', $targetDates);
        }

        $invoices = $query->get();
        $totalProcessed = count($invoices);

        $waService = new \App\Services\WhatsappService($tenantId);

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;
            if (!$customer || empty($customer->phone)) {
                continue;
            }

            try {
                // Direct WhatsApp Dispatch (Bypass queue worker dependency for reliable cron & manual triggers)
                $sent = $waService->sendInvoiceReminder($customer, $invoice);
                if ($sent) {
                    $sentCount++;
                } else {
                    $failedCount++;
                    Log::warning("[CronService] WhatsApp reminder rejected for invoice #{$invoice->invoice_number} (Customer {$customer->name}): " . ($waService->getLastError() ?: 'Unknown error'));
                }
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("[CronService] WhatsApp reminder exception for invoice #{$invoice->invoice_number}: {$e->getMessage()}");
            }
        }

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total_processed' => $totalProcessed,
        ];
    }

    /**
     * Trigger usage polling via UsageService.
     * Runs every 10 minutes.
     */
    protected function pollUsage(): array
    {
        Log::info('[CronService] Starting usage polling...');

        $usageService = app(UsageService::class);
        $stats = $usageService->pollAllRouters();

        Log::info('[CronService] Usage polling complete.', $stats);

        return $stats;
    }

    /**
     * Create a database backup.
     * Runs weekly.
     */
    protected function backupDatabase(): array
    {
        Log::info('[CronService] Starting database backup...');

        $dbPath = database_path('database.sqlite');
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup-'.now()->format('Y-m-d_His').'.sqlite';
        $backupPath = "{$backupDir}/{$filename}";

        if (file_exists($dbPath)) {
            copy($dbPath, $backupPath);
            $size = filesize($backupPath);
            Log::info("[CronService] Database backup created: {$filename} ({$size} bytes)");

            return [
                'file' => $filename,
                'path' => $backupPath,
                'size' => $size,
            ];
        }

        throw new \RuntimeException('Database file not found at: '.$dbPath);
    }

    /**
     * Generate subscription invoices for all active tenants.
     * Runs on the 1st of each month.
     */
    protected function generateSubscriptionInvoices(): array
    {
        $period = now()->format('Y-m');
        Log::info("[CronService] Generating subscription invoices for {$period}");

        $tenants = Tenant::where('is_active', true)->whereNotNull('expired_at')->get();
        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($tenants as $tenant) {
            $price = (int) ($tenant->settings['subscribed_price'] ?? 0);
            if ($price <= 0) {
                $skipped++;

                continue;
            }

            // Check if invoice already exists for this period
            $existing = Invoice::whereNull('tenant_id')
                ->where('customer_name', $tenant->name)
                ->where('period', $period)
                ->exists();

            if ($existing) {
                $skipped++;

                continue;
            }

            try {
                $invNumber = 'SUB-'.strtoupper($tenant->slug).'-'.now()->format('Ym').'-'.str_pad((Invoice::whereNull('tenant_id')->count() + 1), 4, '0', STR_PAD_LEFT);

                Invoice::create([
                    'tenant_id' => null,
                    'customer_id' => null,
                    'customer_name' => $tenant->name,
                    'invoice_number' => $invNumber,
                    'amount' => $price,
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'period' => $period,
                    'paid' => false,
                    'status' => 'pending',
                ]);

                $generated++;
            } catch (\Exception $e) {
                $errors[] = "Tenant #{$tenant->id}: {$e->getMessage()}";
                Log::error("[CronService] Subscription invoice failed for {$tenant->name}: {$e->getMessage()}");
            }
        }

        Log::info("[CronService] Subscription invoices: {$generated} generated, {$skipped} skipped, ".count($errors).' errors.');

        return compact('period', 'generated', 'skipped', 'errors');
    }

    // ------------------------------------------------------------------
    //  Jam Kalong (Night Speed) & FUP helpers
    // ------------------------------------------------------------------

    /**
     * Jam Kalong start — switch all customers (whose package has
     * use_night_speed) to the package's night profile.
     * Runs daily at 00:00.
     */
    protected function jamKalongStart(): array
    {
        Log::info('[CronService] Jam Kalong start — switching to night profiles...');

        $packages = Package::where('use_night_speed', true)
            ->whereNotNull('night_profile_name')
            ->pluck('night_profile_name', 'id');

        $customers = Customer::with('router')
            ->where('status', 'active')
            ->whereIn('package_id', $packages->keys())
            ->whereNotNull('pppoe_username')
            ->get();

        $switched = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $nightProfile = $packages[$customer->package_id] ?? null;
            if (! $nightProfile || ! $customer->router || ! $customer->router->is_active) {
                $failed++;

                continue;
            }

            try {
                $mikrotik = $this->mikrotikFor($customer->router);
                if (! $mikrotik->isConnected()) {
                    $failed++;

                    continue;
                }
                $mikrotik->setPppoeUserProfile($customer->pppoe_username, $nightProfile);
                $mikrotik->kickPppoeUser($customer->pppoe_username);
                $switched++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("[CronService] Jam Kalong start failed for {$customer->pppoe_username}: {$e->getMessage()}");
            }
        }

        Log::info("[CronService] Jam Kalong start complete: {$switched} switched, {$failed} failed.");

        return compact('switched', 'failed');
    }

    /**
     * Jam Kalong end — restore customers (whose package has use_night_speed)
     * to the package's normal profile.
     * Runs daily at 06:00.
     */
    protected function jamKalongEnd(): array
    {
        Log::info('[CronService] Jam Kalong end — restoring normal profiles...');

        $packages = Package::where('use_night_speed', true)
            ->pluck('profile_normal', 'id');

        $customers = Customer::with('router')
            ->where('status', 'active')
            ->whereIn('package_id', $packages->keys())
            ->whereNotNull('pppoe_username')
            ->get();

        $restored = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $normalProfile = $packages[$customer->package_id] ?? null;
            if (! $normalProfile || ! $customer->router || ! $customer->router->is_active) {
                $failed++;

                continue;
            }

            try {
                $mikrotik = $this->mikrotikFor($customer->router);
                if (! $mikrotik->isConnected()) {
                    $failed++;

                    continue;
                }
                $mikrotik->setPppoeUserProfile($customer->pppoe_username, $normalProfile);
                $mikrotik->kickPppoeUser($customer->pppoe_username);
                $restored++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("[CronService] Jam Kalong end failed for {$customer->pppoe_username}: {$e->getMessage()}");
            }
        }

        Log::info("[CronService] Jam Kalong end complete: {$restored} restored, {$failed} failed.");

        return compact('restored', 'failed');
    }

    /**
     * FUP check — for customers whose package has use_fup and a quota limit,
     * if the current month usage exceeds the limit, downgrade the PPPoE
     * profile to the package's FUP profile.
     * Runs hourly.
     */
    protected function checkFup(): array
    {
        Log::info('[CronService] FUP check running...');

        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        $packages = Package::where('use_fup', true)
            ->where('fup_limit_gb', '>', 0)
            ->whereNotNull('fup_profile_name')
            ->get()
            ->keyBy('id');

        $customers = Customer::with('router')
            ->where('status', 'active')
            ->whereIn('package_id', $packages->keys())
            ->whereNotNull('pppoe_username')
            ->get();

        $downgraded = 0;
        $failed = 0;
        $checked = 0;

        foreach ($customers as $customer) {
            $package = $packages[$customer->package_id] ?? null;
            if (! $package) {
                continue;
            }

            $usage = app(UsageService::class)->getUsage($customer->id, $month, $year);
            if (! $usage) {
                continue;
            }

            $checked++;
            $totalGb = ($usage->bytes_in + $usage->bytes_out) / (1024 * 1024 * 1024);

            if ($totalGb < (float) $package->fup_limit_gb) {
                continue;
            }

            if (! $customer->router || ! $customer->router->is_active) {
                $failed++;

                continue;
            }

            try {
                $fupService = app(FupService::class);
                if ($fupService->applyDynamicFup($customer, $package)) {
                    $downgraded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("[CronService] FUP downgrade failed for {$customer->pppoe_username}: {$e->getMessage()}");
            }
        }

        Log::info("[CronService] FUP check complete: {$downgraded} downgraded, {$checked} checked, {$failed} failed.");

        return compact('downgraded', 'checked', 'failed');
    }

    /**
     * Hapus log lama (>90 hari) supaya tabel client_logs, audit_logs & webhook_logs tidak
     * membesar tanpa kendali. Menggunakan chunked delete (1000 rows per batch) untuk
     * mencegah table lock pada database produksi.
     */
    protected function pruneLogs(): array
    {
        $days = max(1, (int) env('LOG_RETENTION_DAYS', 90));
        $cutoff = now()->subDays($days);

        $tables = ['client_logs', 'audit_logs', 'webhook_logs'];
        $deleted = [];

        foreach ($tables as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $totalDeleted = 0;
            do {
                $rows = \Illuminate\Support\Facades\DB::table($table)
                    ->where('created_at', '<', $cutoff)
                    ->limit(1000)
                    ->delete();
                $totalDeleted += $rows;
            } while ($rows > 0);

            $deleted[$table] = $totalDeleted;
        }

        Log::info("[CronService] Logs pruned (> {$days} days): ".json_encode($deleted));

        return $deleted;
    }

    /**
     * Build a MikrotikService for the given router.
     */
    private function mikrotikFor($router): MikrotikService
    {
        return new MikrotikService([
            'host' => $router->host,
            'port' => (int) ($router->port ?? 8728),
            'user' => $router->username,
            'pass' => $router->password ?? '',
        ]);
    }

    // ------------------------------------------------------------------
    //  Isolation helpers
    // ------------------------------------------------------------------

    /**
     * Isolate a customer by setting status and applying MikroTik profile.
     * Pakai router milik pelanggan (router_id) — bukan config global —
     * supaya isolasi di tenant multi-router nge-sasar router yang benar.
     * Fallback ke config global MIKROTIK_* kalau pelanggan tanpa router.
     */
    private function isolateCustomer(Customer $customer): void
    {
        try {
            app(\App\Services\IsolationService::class)->isolateCustomer($customer, 'Sistem Cron / Auto-Isolir');
        } catch (\Throwable $e) {
            Log::error("[CronService] MikroTik isolate error for customer #{$customer->id} ({$customer->name}): {$e->getMessage()}");
        }
    }

    /**
     * Un-isolate a customer by setting status active and restoring normal profile.
     */
    private function unisolateCustomer(Customer $customer): void
    {
        try {
            app(\App\Services\IsolationService::class)->unisolateCustomer($customer, 'Sistem Cron / Auto-Unisolir');
        } catch (\Throwable $e) {
            Log::error("[CronService] MikroTik unisolate error for customer #{$customer->id} ({$customer->name}): {$e->getMessage()}");
        }
    }

    /**
     * Bangun MikrotikService untuk satu customer.
     * Kalau customer punya router aktif → pakai router itu (password di-decrypt
     * otomatis oleh cast model). Kalau tidak ada → fallback ke config global.
     */
    private function mikrotikForCustomer(Customer $customer): MikrotikService
    {
        $router = $customer->router;
        if ($router && $router->is_active) {
            return $this->mikrotikFor($router);
        }

        return new MikrotikService;
    }

    /**
     * Dispatch NMS alert to Telegram and optionally WhatsApp if explicitly enabled.
     */
    protected function dispatchNmsAlert(?int $tenantId, string $msg, string $logMessage, string $level = 'warning'): void
    {
        if ($level === 'warning') {
            Log::warning($logMessage);
        } else {
            Log::info($logMessage);
        }

        $telegram = app(TelegramService::class);
        $tenantTelegram = app(TenantTelegramService::class);

        if ($tenantId) {
            $tenantTelegram->sendNmsNotification($tenantId, $msg, null, 'router');

            // WhatsApp NMS is strictly opt-in and disabled by default to prevent spamming
            $waNmsEnabled = (bool) \App\Models\Setting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', 'WHATSAPP_NMS_NOTIF')
                ->value('value');
            if ($waNmsEnabled) {
                $adminPhone = \App\Models\Setting::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'ADMIN_PHONE')
                    ->value('value');
                $wa = app(WhatsappService::class);
                if ($adminPhone && $wa->isConfigured()) {
                    $waMsg = strip_tags(str_replace(['<b>', '</b>', '<code>', '</code>'], ['*', '*', '`', '`'], $msg));
                    $wa->sendMessage($adminPhone, $waMsg);
                }
            }
        } else {
            // Global Superadmin
            $teleChatId = \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_SUPERADMIN_CHAT_ID')->whereNull('tenant_id')->value('value')
                ?: \App\Models\Setting::withoutGlobalScopes()->where('key', 'TELEGRAM_ADMIN_CHAT_ID')->whereNull('tenant_id')->value('value')
                ?: env('TELEGRAM_SUPERADMIN_CHAT_ID', '');
            if ($teleChatId && $telegram->isConfigured()) {
                $telegram->sendMessage($teleChatId, $msg, 'HTML');
            }

            // Superadmin WhatsApp NMS is also strictly opt-in
            $waNmsEnabled = (bool) \App\Models\Setting::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('key', 'WHATSAPP_NMS_NOTIF')
                ->value('value');
            if ($waNmsEnabled) {
                $adminPhone = \App\Models\Setting::withoutGlobalScopes()
                    ->whereNull('tenant_id')
                    ->where('key', 'ADMIN_PHONE')
                    ->value('value');
                $wa = app(WhatsappService::class);
                if ($adminPhone && $wa->isConfigured()) {
                    $waMsg = strip_tags(str_replace(['<b>', '</b>', '<code>', '</code>'], ['*', '*', '`', '`'], $msg));
                    $wa->sendMessage($adminPhone, $waMsg);
                }
            }
        }
    }

    /**
     * Monitor active MikroTik routers and OLTs.
     * Dispatches Telegram alerts on DOWN or RECOVERY (WhatsApp only if opted-in).
     */
    protected function monitorNetwork(): array
    {
        $routers = Mikrotik::withoutGlobalScopes()->where('is_active', true)->get();
        $olts = \App\Models\Olt::withoutGlobalScopes()->where('is_active', true)->get();

        $results = [
            'routers_checked' => 0,
            'routers_down' => 0,
            'olts_checked' => 0,
            'olts_down' => 0,
        ];

        // 1. Check MikroTik Routers
        foreach ($routers as $router) {
            $results['routers_checked']++;
            $host = trim((string) $router->host);
            $port = (int) ($router->port ?: 8728);
            if (str_contains($host, ':')) {
                [$h, $p] = explode(':', $host, 2);
                $host = $h;
                if (is_numeric($p)) {
                    $port = (int) $p;
                }
            }

            $isOnline = false;
            $connection = @fsockopen($host, $port, $errno, $errstr, 2.5);
            if (is_resource($connection)) {
                $isOnline = true;
                fclose($connection);
            }

            $cacheKey = "router_monitor_status_{$router->id}";
            $prevStatus = Cache::get($cacheKey, null);

            if (! $isOnline) {
                $results['routers_down']++;
                if ($prevStatus === 'online') {
                    // Router transitioned to DOWN -> Dispatch Alert!
                    Cache::put($cacheKey, 'offline', now()->addDays(7));

                    $tenant = $router->tenant_id ? Tenant::withoutGlobalScopes()->find($router->tenant_id) : null;
                    $tenantName = $tenant?->name ?? 'NODERA Cloud';
                    $nowStr = now()->translatedFormat('d F Y, H:i:s') . ' WIB';

                    $msg = "<b>🔴 PERINGATAN JARINGAN DOWN — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($router->name) . "\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Host/IP: <code>{$router->host}:{$port}</code>\n"
                        . "├ Tenant: {$tenantName}\n"
                        . "├ Dampak: Pelanggan berpotensi terputus / isolir tidak tersinkronisasi.\n"
                        . "└ Waktu: {$nowStr}";

                    $this->dispatchNmsAlert(
                        $router->tenant_id ? (int) $router->tenant_id : null,
                        $msg,
                        "[NetworkMonitor] Router #{$router->id} ({$router->name}) is DOWN.",
                        'warning'
                    );
                } elseif ($prevStatus === null) {
                    Cache::put($cacheKey, 'offline', now()->addDays(7));
                }
            } else {
                if ($prevStatus === 'offline') {
                    // Router RECOVERED -> Dispatch Recovery Alert!
                    Cache::put($cacheKey, 'online', now()->addDays(7));

                    $tenant = $router->tenant_id ? Tenant::withoutGlobalScopes()->find($router->tenant_id) : null;
                    $tenantName = $tenant?->name ?? 'NODERA Cloud';
                    $nowStr = now()->translatedFormat('d F Y, H:i:s') . ' WIB';

                    $msg = "<b>🟢 JARINGAN PULIH (RECOVERED) — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($router->name) . "\n"
                        . "├ Status: ONLINE\n"
                        . "├ Host/IP: <code>{$router->host}:{$port}</code>\n"
                        . "├ Tenant: {$tenantName}\n"
                        . "├ Keterangan: Koneksi API MikroTik telah normal kembali.\n"
                        . "└ Waktu: {$nowStr}";

                    $this->dispatchNmsAlert(
                        $router->tenant_id ? (int) $router->tenant_id : null,
                        $msg,
                        "[NetworkMonitor] Router #{$router->id} ({$router->name}) RECOVERED.",
                        'info'
                    );
                } elseif ($prevStatus === null) {
                    Cache::put($cacheKey, 'online', now()->addDays(7));
                }
            }
        }

        // 2. Check OLTs with multi-protocol support (SNMP, Telnet, HTTP)
        foreach ($olts as $olt) {
            $results['olts_checked']++;
            $host = trim((string) $olt->host);
            $customPort = null;
            if (str_contains($host, ':')) {
                [$h, $p] = explode(':', $host, 2);
                $host = $h;
                if (is_numeric($p)) {
                    $customPort = (int) $p;
                }
            }

            $connectionMode = strtolower($olt->connection_mode ?: 'snmp');
            $snmpPort = (int) ($olt->snmp_port ?: ($customPort ?: ($olt->port ?: 161)));
            $telnetPort = (int) ($olt->telnet_port ?: ($customPort ?: ($olt->port ?: 23)));
            $community = $olt->snmp_community ?: 'public';

            $isOnline = false;

            // 1. Check SNMP if connection mode is SNMP or Hybrid
            if ($connectionMode === 'snmp' || $connectionMode === 'hybrid') {
                foreach ([2, 1] as $ver) {
                    try {
                        $client = new \FreeDSx\Snmp\SnmpClient([
                            'host' => $host,
                            'port' => $snmpPort,
                            'version' => $ver,
                            'community' => $community,
                            'timeout_connect' => 3,
                            'timeout_read' => 3,
                        ]);
                        $testVal = $client->getValue('1.3.6.1.2.1.1.1.0') ?? $client->getValue('1.3.6.1.2.1.1.3.0');
                        if ($testVal !== null) {
                            $isOnline = true;
                            break;
                        }
                    } catch (\Throwable $e) {}
                }
            }

            // 2. Check Telnet if connection mode is Telnet or Hybrid (or if SNMP failed but Telnet port configured)
            if (! $isOnline && ($connectionMode === 'telnet' || $connectionMode === 'hybrid' || $olt->telnet_port)) {
                $connection = @fsockopen($host, $telnetPort, $errno, $errstr, 2.5);
                if (is_resource($connection)) {
                    $isOnline = true;
                    fclose($connection);
                }
            }

            // 3. Fallback: check HTTP Web API port if credentials exist or mode is HTTP
            if (! $isOnline && ($olt->username || $connectionMode === 'http')) {
                $httpPort = $customPort ?: 80;
                $connection = @fsockopen($host, $httpPort, $errno, $errstr, 2.0);
                if (is_resource($connection)) {
                    $isOnline = true;
                    fclose($connection);
                }
            }

            // 4. Retry once after 500ms if initial check failed (to prevent false alarms from transient network jitter)
            if (! $isOnline) {
                usleep(500000);

                if ($connectionMode === 'snmp' || $connectionMode === 'hybrid') {
                    foreach ([2, 1] as $ver) {
                        try {
                            $client = new \FreeDSx\Snmp\SnmpClient([
                                'host' => $host,
                                'port' => $snmpPort,
                                'version' => $ver,
                                'community' => $community,
                                'timeout_connect' => 3,
                                'timeout_read' => 3,
                            ]);
                            $testVal = $client->getValue('1.3.6.1.2.1.1.1.0') ?? $client->getValue('1.3.6.1.2.1.1.3.0');
                            if ($testVal !== null) {
                                $isOnline = true;
                                break;
                            }
                        } catch (\Throwable $e) {}
                    }
                }

                if (! $isOnline && ($connectionMode === 'telnet' || $connectionMode === 'hybrid' || $olt->telnet_port)) {
                    $connection = @fsockopen($host, $telnetPort, $errno, $errstr, 3.0);
                    if (is_resource($connection)) {
                        $isOnline = true;
                        fclose($connection);
                    }
                }
            }

            $cacheKey = "olt_monitor_status_{$olt->id}";
            $prevStatus = Cache::get($cacheKey, null);

            if (! $isOnline) {
                $results['olts_down']++;
                if ($prevStatus === 'online') {
                    Cache::put($cacheKey, 'offline', now()->addDays(7));

                    $tenant = $olt->tenant_id ? Tenant::withoutGlobalScopes()->find($olt->tenant_id) : null;
                    $tenantName = $tenant?->name ?? 'NODERA Cloud';
                    $nowStr = now()->translatedFormat('d F Y, H:i:s') . ' WIB';

                    $msg = "<b>🔴 PERINGATAN OLT DOWN — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($olt->name) . " ({$olt->model})\n"
                        . "├ Status: OFFLINE\n"
                        . "├ Host/IP: <code>{$olt->host}</code>\n"
                        . "├ Tenant: {$tenantName}\n"
                        . "├ Dampak: Seluruh ONU/ONT pada OLT ini terputus.\n"
                        . "└ Waktu: {$nowStr}";

                    $this->dispatchNmsAlert(
                        $olt->tenant_id ? (int) $olt->tenant_id : null,
                        $msg,
                        "[NetworkMonitor] OLT #{$olt->id} ({$olt->name}) is DOWN.",
                        'warning'
                    );
                } elseif ($prevStatus === null) {
                    Cache::put($cacheKey, 'offline', now()->addDays(7));
                }
            } else {
                if ($prevStatus === 'offline') {
                    Cache::put($cacheKey, 'online', now()->addDays(7));

                    $tenant = $olt->tenant_id ? Tenant::withoutGlobalScopes()->find($olt->tenant_id) : null;
                    $tenantName = $tenant?->name ?? 'NODERA Cloud';
                    $nowStr = now()->translatedFormat('d F Y, H:i:s') . ' WIB';

                    $msg = "<b>🟢 OLT PULIH (RECOVERED) — NODERA</b>\n\n"
                        . "┌ " . htmlspecialchars($olt->name) . " ({$olt->model})\n"
                        . "├ Status: ONLINE\n"
                        . "├ Host/IP: <code>{$olt->host}</code>\n"
                        . "├ Tenant: {$tenantName}\n"
                        . "└ Waktu: {$nowStr}";

                    $this->dispatchNmsAlert(
                        $olt->tenant_id ? (int) $olt->tenant_id : null,
                        $msg,
                        "[NetworkMonitor] OLT #{$olt->id} ({$olt->name}) RECOVERED.",
                        'info'
                    );
                } elseif ($prevStatus === null) {
                    Cache::put($cacheKey, 'online', now()->addDays(7));
                }
            }
        }

        return $results;
    }

    /**
     * Clean and Kick Expired Hotspot Active Sessions & Users across active routers
     */
    protected function cleanExpiredHotspot(): array
    {
        $routers = \App\Models\Mikrotik::withoutGlobalScopes()->where('is_active', true)->get();
        $totalKicked = 0;
        $details = [];

        foreach ($routers as $router) {
            try {
                $service = new MikrotikService($router);
                if ($service->isConnected()) {
                    $kicked = $service->cleanExpiredHotspotUsers();
                    $count = count($kicked);
                    $totalKicked += $count;
                    if ($count > 0) {
                        $usernames = array_column($kicked, 'user');
                        \App\Models\Voucher::withoutGlobalScopes()
                            ->whereIn('username', $usernames)
                            ->update([
                                'used' => true,
                                'used_at' => now(),
                            ]);
                        $details[] = "Router {$router->name}: {$count} expired sessions kicked";
                    }
                }
            } catch (\Exception $e) {
                Log::error("[CronService] Hotspot expired cleaner error on {$router->name}: " . $e->getMessage());
            }
        }

        return [
            'total_kicked' => $totalKicked,
            'details' => $details,
        ];
    }

    // ------------------------------------------------------------------
    //  Locking
    // ------------------------------------------------------------------

    private function acquireLock(string $jobName): void
    {
        Cache::put(self::JOB_LOCK_PREFIX.$jobName, true, self::LOCK_TTL);
    }

    private function releaseLock(string $jobName): void
    {
        Cache::forget(self::JOB_LOCK_PREFIX.$jobName);
    }
}
