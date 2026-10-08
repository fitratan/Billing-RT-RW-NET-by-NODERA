<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;

class FupService
{
    /**
     * Apply Dynamic FUP Rate Shaping without disconnecting the user.
     */
    public function applyDynamicFup(Customer $customer, Package $package): bool
    {
        if (!$customer->router || !$customer->router->is_active) {
            return false;
        }

        if (!RouterCircuitBreaker::isAvailable($customer->router->id)) {
            Log::warning("[FupService] Router #{$customer->router->id} circuit is OPEN. Skipping FUP update.");
            return false;
        }

        try {
            $mik = new MikrotikService([
                'host' => $customer->router->host,
                'user' => $customer->router->username,
                'pass' => $customer->router->password ?? '',
                'port' => (int) $customer->router->port,
            ]);

            if (!$mik->isConnected()) {
                RouterCircuitBreaker::recordFailure($customer->router->id);
                return false;
            }

            // 1. Update PPPoE User Profile for subsequent reconnects
            if (!empty($customer->pppoe_username) && !empty($package->fup_profile_name)) {
                $mik->setPppoeUserProfile($customer->pppoe_username, $package->fup_profile_name);
            }

            // 2. Zero-Downtime Dynamic Rate Shaping on Active Simple Queue
            $fupSpeed = $package->fup_speed_limit ?? '2M/2M';
            $targetQueueName = "<pppoe-{$customer->pppoe_username}>";
            $shaped = $mik->setSimpleQueueRate($targetQueueName, $fupSpeed);

            if (!$shaped && !empty($customer->pppoe_username)) {
                $mik->setSimpleQueueRate($customer->pppoe_username, $fupSpeed);
            }

            RouterCircuitBreaker::recordSuccess($customer->router->id);
            Log::info("[FupService] Dynamic FUP applied for customer #{$customer->id} ({$customer->name}) to {$fupSpeed} without disconnection.");
            return true;
        } catch (\Throwable $e) {
            RouterCircuitBreaker::recordFailure($customer->router->id);
            Log::error("[FupService] Dynamic FUP error for customer #{$customer->id}: " . $e->getMessage());
            return false;
        }
    }
}
