<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Jobs\ProcessMonthlyInvoicesJob;
use App\Models\Tenant;
use App\Models\Customer;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cron:run-invoices {--tenant= : ID Tenant opsional} {--queue : Jalankan via antrean background queue}', function () {
    $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
    $useQueue = $this->option('queue');

    if ($useQueue) {
        $this->info('Mengirim job generasi tagihan bulanan ke antrean queue...');
        ProcessMonthlyInvoicesJob::dispatch($tenantId);
        $this->info('✓ Job generasi tagihan berhasil dikirim ke antrean queue!');
        return 0;
    }

    $this->info('Memulai generasi tagihan bulanan langsung (synchronous)...');
    $cron = app(\App\Services\CronService::class);
    if ($tenantId) {
        $res = $cron->generateInvoicesForTenant($tenantId);
    } else {
        $res = $cron->generateInvoices();
    }

    $this->info("✓ Generasi tagihan selesai!");
    $this->line("  • Tagihan Baru Dibuat: <fg=green>{$res['generated']}</>");
    $this->line("  • Dilewati (Sudah Ada / Non-Aktif): <fg=yellow>{$res['skipped']}</>");
    if (!empty($res['errors'])) {
        $this->error("  • Terjadi " . count($res['errors']) . " error:");
        foreach ($res['errors'] as $err) {
            $this->line("    - {$err}");
        }
    }
})->purpose('Generate monthly invoices for active customers');

Artisan::command('billing:generate-invoices {--tenant= : ID Tenant opsional}', function () {
    $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
    $this->info('Memulai pembuatan tagihan bulanan...');
    $cron = app(\App\Services\CronService::class);
    if ($tenantId) {
        $res = $cron->generateInvoicesForTenant($tenantId);
    } else {
        $res = $cron->generateInvoices();
    }
    $this->info("✓ Selesai: {$res['generated']} tagihan baru dibuat, {$res['skipped']} sudah ada/dilewati.");
})->purpose('Generate monthly invoices for customers immediately');

Artisan::command('tenant:check-limits', function () {
    $this->info('--- REKAPITULASI KUOTA PELANGGAN TENANT SAAS ---');
    $tenants = Tenant::where('is_active', true)->get();
    foreach ($tenants as $tenant) {
        $count = Customer::where('tenant_id', $tenant->id)->count();
        $limit = $tenant->max_customers;
        $status = ($limit > 0 && $count >= $limit) ? '<fg=red>MEMENUHI LIMIT</>' : '<fg=green>OK</>';
        $this->line("Tenant: {$tenant->name} ({$tenant->slug}) | Pelanggan: {$count} / " . ($limit > 0 ? $limit : 'Unli') . " | Status: {$status}");
    }
})->purpose('Check customer quota usage per active SaaS tenant');

// VPN Auto Renew Scheduler
\Illuminate\Support\Facades\Schedule::command('vpn:auto-renew')->dailyAt('02:00');
// Mikhmon Auto Renew Scheduler (debit saldo bulanan + suspend setelah 7 hari tenggang)
\Illuminate\Support\Facades\Schedule::command('mikhmon:auto-renew')->dailyAt('02:15');

// Add-on Auto Renew Scheduler (potong saldo bulanan untuk add-on tenant)
\Illuminate\Support\Facades\Schedule::command('addons:auto-renew')->dailyAt('02:20');

// Bookkeeping Auto Renew Scheduler (debit saldo bulanan + suspend setelah 7 hari tenggang)
\Illuminate\Support\Facades\Schedule::command('bookkeeping:auto-renew')->dailyAt('02:30');

// Cloud SaaS ISP Billing Auto Renew Scheduler (debit saldo bulanan + suspend)
\Illuminate\Support\Facades\Schedule::command('isp-billing:auto-renew')->dailyAt('02:45');

// System Maintenance Scheduler: Bersihkan voucher expired di DB & MikroTik, transaksi unpaid/expired, dan log lama
\Illuminate\Support\Facades\Schedule::command('system:maintenance')->dailyAt('03:30');

// Activity Logs Cleanup: Hapus log aktivitas lama sebelum awal bulan berjalan setiap tanggal 1 jam 00:05
Artisan::command('activity-logs:cleanup', function () {
    $this->info('Memulai pembersihan log aktivitas lama...');
    $startOfMonth = now()->startOfMonth();
    $deleted = \App\Models\AuditLog::where('created_at', '<', $startOfMonth)->delete();
    $this->info("Pembersihan selesai. Berhasil menghapus {$deleted} baris log aktivitas lama sebelum " . $startOfMonth->format('Y-m-d'));
})->purpose('Bersihkan log aktivitas lama setiap awal bulan');

\Illuminate\Support\Facades\Schedule::command('activity-logs:cleanup')->monthlyOn(1, '00:05');

// Test pengiriman pesan & alert notifikasi Telegram
Artisan::command('telegram:test {--topic=general : Target topik (error, topup, registration, general)} {--chat_id= : Custom Chat ID tujuan}', function () {
    $telegram = app(\App\Services\TelegramService::class);
    $this->info('Memeriksa konfigurasi Telegram Bot...');

    if (! $telegram->isConfigured()) {
        $this->error('TELEGRAM_BOT_TOKEN belum dikonfigurasi di file .env atau tabel settings!');
        return 1;
    }

    $topic = $this->option('topic') ?: 'general';
    $this->info("Mengirim pesan uji coba (Topik: {$topic})...");
    $testMsg = "<b>🔔 UJI COBA NOTIFIKASI TELEGRAM — NODERA</b>\n\n"
        . "┌ Sistem Notifikasi\n"
        . "├ Status: TERHUBUNG\n"
        . "├ Target Topik: {$topic}\n"
        . "└ Waktu: " . date('d/m/Y H:i:s');
    
    $customChatId = $this->option('chat_id');
    if ($customChatId) {
        $res = $telegram->sendMessage($customChatId, $testMsg, 'HTML');
    } else {
        $res = $telegram->sendAdminNotification($testMsg, $topic, 'HTML');
    }

    if (! empty($res['ok'])) {
        $this->info("Berhasil terkirim! Detail: " . json_encode($res));
        return 0;
    } else {
        $this->error("Gagal mengirim pesan: " . json_encode($res));
        return 1;
    }
})->purpose('Uji konektivitas dan kirim pesan test ke Telegram Bot Admin');

// Background Poller: Caching status & resource semua MikroTik aktif agar loading dashboard dan halaman router instan (0ms)
Artisan::command('mikrotik:poll-status', function () {
    $routers = \App\Models\Mikrotik::withoutGlobalScopes()->where('is_active', true)->get();
    $this->info("Memulai background polling untuk {$routers->count()} router MikroTik...");

    foreach ($routers as $router) {
        $host = $router->host;
        $port = (int) ($router->port ?? 8728);
        $resKey = "mik_res_" . md5("{$host}:{$port}");
        $activePppKey = "mik_active_cnt_{$router->id}";
        $activeHsKey = "mik_hotspot_cnt_{$router->id}";

        try {
            $mik = new \App\Services\MikrotikService($router);
            if ($mik->isConnected()) {
                $res = $mik->getResource(false);
                if (!empty($res)) {
                    \Illuminate\Support\Facades\Cache::put($resKey, $res, 180);
                }

                $activePpp = $mik->getPppoeActive();
                if (is_array($activePpp)) {
                    \Illuminate\Support\Facades\Cache::put($activePppKey, count($activePpp), 180);
                }

                $activeHs = $mik->getHotspotActive();
                if (is_array($activeHs)) {
                    \Illuminate\Support\Facades\Cache::put($activeHsKey, count($activeHs), 180);
                }

                // Sync delta bandwidth PPPoE ke database CustomerUsage secara real-time
                try {
                    app(\App\Services\UsageService::class)->pollRouter($router);
                } catch (\Throwable $e) {
                    // Ignore usage poll error
                }

                $this->line("<fg=green>✓ [ONLINE]</> {$router->name} ({$host}:{$port}) — CPU: " . ($res['cpu_load'] ?? '-') . "%, PPP: " . (is_array($activePpp) ? count($activePpp) : 0));
            } else {
                $this->line("<fg=yellow>✗ [OFFLINE]</> {$router->name} ({$host}:{$port}) — " . ($mik->getLastError() ?: 'Timeout'));
            }
        } catch (\Throwable $e) {
            $this->line("<fg=red>✗ [ERROR]</> {$router->name} ({$host}:{$port}) — " . $e->getMessage());
        }
    }

    $this->info('Background polling MikroTik selesai.');
})->purpose('Background Poller untuk caching resource & online session router MikroTik secara berkala');

\Illuminate\Support\Facades\Schedule::command('mikrotik:poll-status')->everyMinute()->withoutOverlapping();

// NMS Telegram Monitor: Deteksi status offline/online Router, PPPoE, Hotspot, & ARP setiap menit
Artisan::command('nms:telegram-monitor', function () {
    $this->info('Memulai pemantauan NMS Telegram...');
    $monitor = app(\App\Services\NmsTelegramMonitorService::class);
    $monitor->runChecks();
    $this->info('Pemantauan NMS selesai.');
})->purpose('Monitor Router, PPPoE, Hotspot, dan ARP status untuk mengirimkan alert Telegram');

\Illuminate\Support\Facades\Schedule::command('nms:telegram-monitor')->everyMinute()->withoutOverlapping();

// Set Telegram Webhook Command
Artisan::command('telegram:set-webhook {--url= : URL webhook kustom (default: APP_URL/webhook/telegram)}', function () {
    $telegram = app(\App\Services\TelegramService::class);
    if (! $telegram->isConfigured()) {
        $this->error('TELEGRAM_BOT_TOKEN belum dikonfigurasi!');
        return 1;
    }

    $token = $telegram->getBotToken();
    $url = $this->option('url') ?: (rtrim(config('app.url'), '/') . '/webhook/telegram' . ($token ? '?token=' . urlencode($token) : ''));
    $secret = \App\Models\Setting::getValue('TELEGRAM_WEBHOOK_SECRET');
    if (empty($secret)) {
        $secret = bin2hex(random_bytes(32));
        \App\Models\Setting::setValue('TELEGRAM_WEBHOOK_SECRET', $secret);
    }

    $this->info("Menghubungkan Telegram Webhook ke: {$url} ...");
    $result = $telegram->setWebhook($url, $secret);

    if (!empty($result['ok'])) {
        $this->info("✓ Berhasil! Webhook aktif: " . ($result['description'] ?? 'OK'));
    } else {
        $this->error("✗ Gagal: " . json_encode($result));
    }
})->purpose('Set the Telegram bot webhook to this application');

// Get Telegram Webhook Info Command
Artisan::command('telegram:get-webhook', function () {
    $telegram = app(\App\Services\TelegramService::class);
    if (! $telegram->isConfigured()) {
        $this->error('TELEGRAM_BOT_TOKEN belum dikonfigurasi!');
        return 1;
    }

    $info = $telegram->getWebhookInfo();
    $this->line("Info Webhook Telegram:");
    $this->line(json_encode($info, JSON_PRETTY_PRINT));
})->purpose('Inspect the current Telegram bot webhook info');

// Auto-Clean Voucher Expired/Used Command
Artisan::command('voucher:auto-clean {--days=7 : Hapus voucher expired/used yang lebih lama dari N hari}', function () {
    $days = (int) ($this->option('days') ?: 7);
    $cutoff = now()->subDays($days);

    $this->info("Memulai auto-clean voucher kedaluwarsa/terpakai sebelum " . $cutoff->format('Y-m-d H:i:s') . " ({$days} hari lalu)...");

    // 1. Ambil voucher used / expired yang melewati cutoff
    $expiredVouchers = \App\Models\Voucher::withoutGlobalScopes()
        ->where(function($q) use ($cutoff) {
            $q->where('used', true)->where('used_at', '<', $cutoff);
        })
        ->orWhere(function($q) use ($cutoff) {
            $q->where('used', true)->whereNull('used_at')->where('updated_at', '<', $cutoff);
        })
        ->get();

    $total = $expiredVouchers->count();
    if ($total === 0) {
        $this->info("✓ Tidak ada voucher kedaluwarsa yang perlu dibersihkan.");
        return 0;
    }

    $this->line("Ditemukan {$total} voucher kedaluwarsa yang akan dibersihkan.");

    // Group per router untuk batch delete dari MikroTik
    $byRouter = $expiredVouchers->groupBy('router_id');
    foreach ($byRouter as $routerId => $vouchers) {
        if (!$routerId) continue;
        $router = \App\Models\Mikrotik::withoutGlobalScopes()->find($routerId);
        if ($router && $router->is_active) {
            try {
                $service = new \App\Services\MikrotikService($router);
                if ($service->isConnected()) {
                    $usernames = $vouchers->pluck('username')->toArray();
                    $deletedFromRouter = $service->deleteHotspotUsersBatch($usernames);
                    $this->line("<fg=green>✓ [ROUTER CLEAN]</> Berhasil menghapus {$deletedFromRouter} user dari router {$router->name}");
                }
            } catch (\Throwable $e) {
                $this->line("<fg=yellow>✗ [ROUTER ERROR]</> Gagal menghapus dari router {$router->name}: " . $e->getMessage());
            }
        }
    }

    // Hapus dari database
    $deleted = \App\Models\Voucher::withoutGlobalScopes()
        ->whereIn('id', $expiredVouchers->pluck('id'))
        ->delete();

    $this->info("✓ Pembersihan selesai! {$deleted} voucher berhasil dihapus dari database & router.");
    return 0;
})->purpose('Bersihkan voucher hotspot yang sudah terpakai/kedaluwarsa lebih dari N hari');

// Auto-Isolir Tagihan Jatuh Tempo (Dispatched paralel via Horizon queue 'isolation')
\Illuminate\Support\Facades\Schedule::command('billing:isolate-overdue')->dailyAt('06:00');

// Background Poller: Caching status & optical power (redaman) semua OLT & ONU aktif secara otomatis
Artisan::command('olt:poll-status', function () {
    $olts = \App\Models\Olt::withoutGlobalScopes()->where('is_active', true)->get();
    $this->info("Memulai background polling untuk {$olts->count()} OLT...");
    $nms = app(\App\Services\OltNmsService::class);

    foreach ($olts as $olt) {
        try {
            $result = $nms->pollOlt($olt);
            $count = $result['count'] ?? 0;
            $this->line("<fg=green>✓ [SYNC]</> OLT {$olt->name} ({$olt->host}) — {$count} ONU tersinkronisasi");
        } catch (\Throwable $e) {
            $this->line("<fg=red>✗ [ERROR]</> OLT {$olt->name} ({$olt->host}) — " . $e->getMessage());
        }
    }
    $this->info('Background polling OLT selesai.');
})->purpose('Background Poller untuk update status ONU & redaman OLT berkala');

\Illuminate\Support\Facades\Schedule::command('olt:poll-status')->everyMinute()->withoutOverlapping();

// Auto-Prune System & Audit Logs Command
Artisan::command('logs:prune {--days=30 : Hapus log yang lebih lama dari N hari}', function () {
    $days = (int) ($this->option('days') ?: 30);
    $cutoff = now()->subDays($days);

    $this->info("Memulai pembersihan log sebelum " . $cutoff->format('Y-m-d H:i:s') . " ({$days} hari lalu)...");

    if (\Illuminate\Support\Facades\Schema::hasTable('client_logs')) {
        $clientLogs = \Illuminate\Support\Facades\DB::table('client_logs')->where('created_at', '<', $cutoff)->delete();
        $this->line("<fg=green>✓ [CLIENT LOGS]</> {$clientLogs} record berhasil dihapus.");
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
        $auditLogs = \Illuminate\Support\Facades\DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
        $this->line("<fg=green>✓ [AUDIT LOGS]</> {$auditLogs} record berhasil dihapus.");
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('webhook_logs')) {
        $webhookLogs = \Illuminate\Support\Facades\DB::table('webhook_logs')->where('created_at', '<', $cutoff)->delete();
        $this->line("<fg=green>✓ [WEBHOOK LOGS]</> {$webhookLogs} record berhasil dihapus.");
    }

    $this->info("✓ Pembersihan log selesai.");
    return 0;
})->purpose('Bersihkan client_logs, audit_logs, dan webhook_logs yang lebih lama dari N hari');

\Illuminate\Support\Facades\Schedule::command('logs:prune --days=30')->dailyAt('03:00');

// NODERA PAY Auto-Kliring T+1 Settlement Scheduler (setiap jam memproses transaksi yang sudah 24 jam)
\Illuminate\Support\Facades\Schedule::command('noderapay:process-clearing')->hourly()->withoutOverlapping();

// PKL Exam Scheduled Sessions & Auto-Broadcast Checker (setiap menit)
\Illuminate\Support\Facades\Schedule::command('pkl:check-scheduled-exams')->everyMinute()->withoutOverlapping();


