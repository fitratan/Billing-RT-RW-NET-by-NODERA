<?php

namespace App\Console\Commands;

use App\Models\MikhmonSubscription;
use App\Services\MikhmonProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncMikhmonDeployments extends Command
{
    protected $signature = 'mikhmon:sync';
    protected $description = 'Synchronize latest Mikhmon template files across all deployed public/mikhmon-* tenant subdomains';

    public function handle()
    {
        $provisioner = new MikhmonProvisioner();
        $count = 0;
        $syncedPaths = [];

        // 1. Sync all subscriptions from database
        $subscriptions = collect();
        try {
            $subscriptions = MikhmonSubscription::all();
            $this->info("Found " . $subscriptions->count() . " Mikhmon subscription(s) in database.");
        } catch (\Throwable $e) {
            $this->warn("Could not load subscriptions from DB: " . $e->getMessage());
        }

        foreach ($subscriptions as $sub) {
            $subdomain = $sub->subdomain;
            $rosVer = $sub->ros_version ?? '6';
            $possiblePaths = array_unique(array_filter([
                $provisioner->targetPath($subdomain),
                public_path($subdomain),
                base_path($subdomain),
                base_path('public/' . $subdomain),
                config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') . '/' . $subdomain : null,
            ]));

            $target = null;
            foreach ($possiblePaths as $p) {
                if (File::isDirectory($p)) {
                    $target = $p;
                    break;
                }
            }

            if ($target) {
                $this->info("Syncing template code (ROS {$rosVer}) to {$subdomain} at [{$target}]...");
                $provisioner->syncCode($target, $rosVer);
                $provisioner->writeLicense($target, $sub);
                $syncedPaths[] = realpath($target) ?: $target;
                $count++;
            } else {
                $this->info("Deploying fresh template (ROS {$rosVer}) for {$subdomain}...");
                $result = $provisioner->deploy($sub);
                if ($result['success'] ?? false) {
                    $count++;
                }
            }
        }

        // 2. Scan disk directories for any additional mikhmon-* folders
        $scanDirs = array_unique(array_filter([
            public_path(),
            base_path(),
            config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') : null,
        ]));

        foreach ($scanDirs as $baseDir) {
            if (!File::isDirectory($baseDir)) continue;
            $subDirs = File::directories($baseDir);
            foreach ($subDirs as $dir) {
                $folderName = basename($dir);
                $real = realpath($dir) ?: $dir;
                if ((str_starts_with($folderName, 'mikhmon-') || str_starts_with($folderName, 'hotspot-')) && !in_array($real, $syncedPaths)) {
                    $sub = null;
                    try {
                        $cleanSlug = preg_replace('/^(mikhmon|hotspot)-/', '', $folderName);
                        $sub = MikhmonSubscription::where('subdomain', $folderName)
                            ->orWhere('subdomain', $cleanSlug)
                            ->first();
                    } catch (\Throwable $e) {}
                    $rosVer = $sub ? ($sub->ros_version ?? '6') : '6';
                    $this->info("Syncing standalone folder {$folderName} (ROS {$rosVer})...");
                    $provisioner->syncCode($dir, $rosVer);
                    if ($sub) {
                        $provisioner->writeLicense($dir, $sub);
                    } else {
                        // Check if a Tenant exists
                        $cleanSlug = preg_replace('/^(mikhmon|hotspot)-/', '', $folderName);
                        $tenant = null;
                        try {
                            $tenant = \App\Models\Tenant::where('slug', $cleanSlug)->orWhere('slug', $folderName)->first();
                        } catch (\Throwable $e) {}

                        $expiryDate = null;
                        $status = 'ACTIVE';
                        if ($tenant) {
                            if (! $tenant->is_active) {
                                $status = 'SUSPENDED';
                            } elseif ($tenant->isExpired()) {
                                $status = 'EXPIRED';
                            }
                            if ($tenant->expired_at) {
                                $expiryDate = $tenant->expired_at->format('Y-m-d');
                            } elseif ($tenant->trial_ends_at) {
                                $expiryDate = $tenant->trial_ends_at->format('Y-m-d');
                            }
                        }

                        if (!File::exists($dir . '/config/license.php') || $tenant) {
                            $provisioner->writeDefaultLicense($dir, $folderName, $expiryDate, $status);
                        }
                    }
                    $syncedPaths[] = $real;
                    $count++;
                }
            }
        }

        $this->info("Successfully synchronized {$count} Mikhmon deployment(s)!");
        return 0;
    }
}
