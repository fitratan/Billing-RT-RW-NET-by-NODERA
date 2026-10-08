<?php

namespace App\Console\Commands;

use App\Services\RouterBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupRouters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodera:backup-routers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform staggered, encrypted backup (/export hide-sensitive) on all active MikroTik routers';

    /**
     * Execute the console command.
     */
    public function handle(RouterBackupService $backupService): int
    {
        $this->info("Starting staggered MikroTik configuration backup routine (02:00 - 04:00 window)...");
        Log::info("[BackupRouters] Starting scheduled router backup routine.");

        $results = $backupService->backupAllRouters();

        $successCount = 0;
        $failedCount = 0;

        foreach ($results as $res) {
            if ($res['success'] ?? false) {
                $successCount++;
                $this->info("✔ Backup success: {$res['router_name']} -> {$res['backup_name']}");
            } else {
                $failedCount++;
                $this->error("✘ Backup failed: " . ($res['error'] ?? 'Unknown error'));
            }
        }

        $this->info("Backup routine complete: {$successCount} succeeded, {$failedCount} failed.");
        Log::info("[BackupRouters] Completed backup routine: {$successCount} succeeded, {$failedCount} failed.");

        return Command::SUCCESS;
    }
}
