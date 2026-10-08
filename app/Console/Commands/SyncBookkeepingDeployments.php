<?php

namespace App\Console\Commands;

use App\Services\BookkeepingProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncBookkeepingDeployments extends Command
{
    protected $signature = 'bookkeeping:sync';
    protected $description = 'Synchronize latest bookkeeping template code across all deployed public/kas-* tenant subdomains';

    public function handle()
    {
        $provisioner = new BookkeepingProvisioner();
        $publicDirs = File::directories(public_path());
        $count = 0;

        foreach ($publicDirs as $dir) {
            $folderName = basename($dir);
            if (str_starts_with($folderName, 'kas-')) {
                $this->info("Syncing template code to {$folderName}...");
                $provisioner->syncCode($dir);
                $sub = \App\Models\BookkeepingSubscription::where('subdomain', $folderName)->first();
                if ($sub) {
                    $provisioner->writeLicense($dir, $sub);
                }
                $count++;
            }
        }

        $this->info("Successfully synchronized {$count} bookkeeping deployments!");
        return 0;
    }
}
