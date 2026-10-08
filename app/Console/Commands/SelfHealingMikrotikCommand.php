<?php

namespace App\Console\Commands;

use App\Models\Mikrotik;
use App\Models\ShopOrder;
use App\Services\MikrotikService;
use App\Services\TenantTelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SelfHealingMikrotikCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodera:self-heal 
                            {--router= : ID router tertentu} 
                            {--tenant= : ID tenant tertentu} 
                            {--dry-run : Audit tanpa inject ulang}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis mendeteksi dan meng-inject ulang (self-heal) akun voucher aktif yang hilang di router MikroTik';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("🛡️ Memulai NODERA MikroTik Self-Healing Engine...");
        $dryRun = (bool) $this->option('dry-run');

        $routerQuery = Mikrotik::withoutGlobalScopes()->where('is_active', true);
        if ($routerId = $this->option('router')) {
            $routerQuery->where('id', $routerId);
        }
        if ($tenantId = $this->option('tenant')) {
            $routerQuery->where('tenant_id', $tenantId);
        }

        $routers = $routerQuery->get();
        if ($routers->isEmpty()) {
            $this->warn("⚠️ Tidak ada router aktif ditemukan.");
            return 0;
        }

        $totalAudited = 0;
        $totalMissing = 0;
        $totalHealed = 0;

        foreach ($routers as $router) {
            $this->line("📡 Memeriksa Router: {$router->name} ({$router->host}) [Tenant ID: {$router->tenant_id}]");

            try {
                $mikrotikService = new MikrotikService($router);
                $existingUsers = $mikrotikService->getHotspotUsers();
                $existingUsernames = collect($existingUsers)->pluck('name')->filter()->toArray();
            } catch (\Throwable $e) {
                $this->error("❌ Gagal terhubung ke router {$router->name}: " . $e->getMessage());
                Log::warning("SelfHealingMikrotikCommand: Router {$router->id} connection failed: " . $e->getMessage());
                continue;
            }

            // Ambil pesanan voucher yang statusnya paid / approved dalam 30 hari terakhir
            $orders = ShopOrder::withoutGlobalScopes()
                ->where('tenant_id', $router->tenant_id)
                ->where('payment_status', 'paid')
                ->where('order_status', '!=', 'cancelled')
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            foreach ($orders as $order) {
                $vouchers = TenantTelegramService::parseVoucherList($order);
                foreach ($vouchers as $v) {
                    $uName = $v['username'] ?? '';
                    if (empty($uName)) continue;

                    $totalAudited++;

                    if (!in_array($uName, $existingUsernames)) {
                        $totalMissing++;
                        $this->warn("   ⚠️ Voucher hilang di router: {$uName} (Order: #{$order->order_number})");

                        if (!$dryRun) {
                            try {
                                $pkgName = $v['package_name'] ?? 'Voucher';
                                $profile = !empty($v['profile']) ? $v['profile'] : 'default';
                                $timeLimit = $v['time_limit'] ?? '';
                                $comment = "SelfHealed-{$order->order_number}";

                                $ok = $mikrotikService->addHotspotUser(
                                    $uName,
                                    $v['password'] ?? $uName,
                                    $profile,
                                    $timeLimit,
                                    0,
                                    'all',
                                    $comment
                                );

                                if ($ok) {
                                    $totalHealed++;
                                    $this->info("   ✅ Berhasil di-inject ulang (Self-Healed): {$uName}");
                                    Log::info("SelfHealing: Successfully restored missing voucher {$uName} on router {$router->name}");
                                } else {
                                    $this->error("   ❌ Gagal inject: {$uName}");
                                }
                            } catch (\Throwable $err) {
                                $this->error("   ❌ Error inject {$uName}: " . $err->getMessage());
                            }
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Voucher Di-audit', $totalAudited],
                ['Voucher Hilang / Terhapus di Router', $totalMissing],
                ['Berhasil Di-pulihkan (Self-Healed)', $dryRun ? 'Dry Run (0)' : $totalHealed],
            ]
        );

        $this->info("✨ Selesai! Self-Healing Engine NODERA telah bekerja optimal.");
        return 0;
    }
}
