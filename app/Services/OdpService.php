<?php

namespace App\Services;

use App\Models\OdpLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OdpService
{
    const RESERVATION_TTL_MINUTES = 30;

    /**
     * Reserve an available port on an ODP with Pessimistic Lock & TTL.
     * Prevents over-subscription race condition between multiple field technicians.
     */
    public function reservePort(int $odpId, int $customerId, ?int $requestedPort = null): array
    {
        return DB::transaction(function () use ($odpId, $customerId, $requestedPort) {
            // 1. Lock ODP row for update
            $odp = OdpLocation::lockForUpdate()->find($odpId);
            if (!$odp) {
                return ['success' => false, 'error' => 'ODP location not found.'];
            }

            $capacity = (int) ($odp->capacity ?? 8);
            $usedPorts = (int) ($odp->used_ports ?? 0);

            // Get active reservations from Cache
            $activeReservations = [];
            for ($p = 1; $p <= $capacity; $p++) {
                if (Cache::has("odp_{$odpId}_port_{$p}_reserved")) {
                    $activeReservations[$p] = Cache::get("odp_{$odpId}_port_{$p}_reserved");
                }
            }

            $occupiedCount = $usedPorts + count($activeReservations);
            if ($occupiedCount >= $capacity) {
                return ['success' => false, 'error' => "ODP {$odp->name} is at full capacity ({$usedPorts}/{$capacity} used, " . count($activeReservations) . " reserved)."];
            }

            // Determine target port
            $targetPort = null;
            if ($requestedPort) {
                if ($requestedPort > $capacity || isset($activeReservations[$requestedPort])) {
                    return ['success' => false, 'error' => "Port {$requestedPort} is not available on {$odp->name}."];
                }
                $targetPort = $requestedPort;
            } else {
                for ($p = 1; $p <= $capacity; $p++) {
                    if (!isset($activeReservations[$p])) {
                        $targetPort = $p;
                        break;
                    }
                }
            }

            if (!$targetPort) {
                return ['success' => false, 'error' => 'No free port available for reservation.'];
            }

            // Set reservation key with 30-min TTL
            $reservationData = [
                'odp_id' => $odpId,
                'port' => $targetPort,
                'customer_id' => $customerId,
                'reserved_at' => now()->toIso8601String(),
                'expires_at' => now()->addMinutes(self::RESERVATION_TTL_MINUTES)->toIso8601String(),
            ];

            Cache::put("odp_{$odpId}_port_{$targetPort}_reserved", $reservationData, now()->addMinutes(self::RESERVATION_TTL_MINUTES));

            Log::info("[OdpService] Port {$targetPort} reserved on ODP #{$odpId} for customer #{$customerId} (TTL 30m).");

            return [
                'success' => true,
                'odp_id' => $odpId,
                'odp_name' => $odp->name,
                'port' => $targetPort,
                'expires_at' => $reservationData['expires_at'],
            ];
        });
    }

    /**
     * Confirm port installation (permanently increments used_ports and clears reservation).
     */
    public function confirmPort(int $odpId, int $port): bool
    {
        return DB::transaction(function () use ($odpId, $port) {
            $odp = OdpLocation::lockForUpdate()->find($odpId);
            if (!$odp) {
                return false;
            }

            $odp->increment('used_ports');
            Cache::forget("odp_{$odpId}_port_{$port}_reserved");

            Log::info("[OdpService] Port {$port} confirmed on ODP #{$odpId}. New used count: {$odp->used_ports}");
            return true;
        });
    }

    /**
     * Release reserved or assigned port.
     */
    public function releasePort(int $odpId, int $port, bool $decrementUsed = false): bool
    {
        Cache::forget("odp_{$odpId}_port_{$port}_reserved");

        if ($decrementUsed) {
            $odp = OdpLocation::find($odpId);
            if ($odp && $odp->used_ports > 0) {
                $odp->decrement('used_ports');
            }
        }

        Log::info("[OdpService] Port {$port} on ODP #{$odpId} released.");
        return true;
    }
}
