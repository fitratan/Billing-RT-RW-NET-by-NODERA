<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;

class WhatsappGatewayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wa:gateway {action=status : Action to perform: start, stop, restart, status, logs} {--port=3000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the embedded NODERA Universal WhatsApp Gateway microservice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = strtolower($this->argument('action'));
        $serviceDir = base_path('services/wa-gateway');
        $ecosystemFile = $serviceDir . '/ecosystem.config.js';

        switch ($action) {
            case 'start':
                $this->info('Starting NODERA WhatsApp Gateway via PM2...');
                $process = new Process(['pm2', 'start', $ecosystemFile]);
                $process->run();

                if ($process->isSuccessful()) {
                    $this->info('WhatsApp Gateway started successfully!');
                    $this->line($process->getOutput());
                } else {
                    $this->warn('PM2 command failed or PM2 not in global path. Running fallback node process...');
                    $cmd = "cd {$serviceDir} && node server.js > /dev/null 2>&1 &";
                    exec($cmd);
                    $this->info('Node background process launched.');
                }
                break;

            case 'stop':
                $this->info('Stopping WhatsApp Gateway...');
                $process = new Process(['pm2', 'stop', 'nodera-wa-gateway']);
                $process->run();
                if (!$process->isSuccessful()) {
                    exec("pkill -f 'services/wa-gateway/server.js'");
                }
                $this->info('WhatsApp Gateway stopped.');
                break;

            case 'restart':
                $this->info('Restarting WhatsApp Gateway...');
                $process = new Process(['pm2', 'restart', 'nodera-wa-gateway']);
                $process->run();
                if (!$process->isSuccessful()) {
                    exec("pkill -f 'services/wa-gateway/server.js'");
                    exec("cd {$serviceDir} && node server.js > /dev/null 2>&1 &");
                }
                $this->info('WhatsApp Gateway restarted.');
                break;

            case 'logs':
                $this->info('Showing WhatsApp Gateway live logs...');
                passthru('pm2 logs nodera-wa-gateway --lines 50');
                break;

            case 'status':
            default:
                $this->info('Checking WhatsApp Gateway microservice status...');
                try {
                    $response = Http::timeout(3)->get('http://127.0.0.1:3000/api/status');
                    if ($response->successful()) {
                        $json = $response->json();
                        $this->table(
                            ['Metric', 'Value'],
                            [
                                ['Status', '🟢 RUNNING (Online)'],
                                ['Engine', $json['engine'] ?? 'Baileys Multi-Session'],
                                ['Total Sessions', $json['total_sessions'] ?? 0],
                                ['Connected Sessions', $json['connected_sessions'] ?? 0],
                                ['Uptime (s)', $json['uptime_seconds'] ?? 0],
                            ]
                        );
                    } else {
                        $this->error('WhatsApp Gateway responded with error HTTP ' . $response->status());
                    }
                } catch (\Throwable $e) {
                    $this->warn('🔴 WhatsApp Gateway is OFFLINE or not responding on http://127.0.0.1:3000');
                    $this->line('Run: php artisan wa:gateway start');
                }
                break;
        }

        return 0;
    }
}
