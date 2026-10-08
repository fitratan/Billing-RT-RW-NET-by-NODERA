<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RouterCircuitBreaker
{
    public const STATE_CLOSED = 'CLOSED';       // Normal operation, requests allowed
    public const STATE_OPEN = 'OPEN';           // Router failing, requests blocked (fail-fast)
    public const STATE_HALF_OPEN = 'HALF_OPEN'; // Cooldown expired, probing single request

    protected const MAX_FAILURES = 3;
    protected const COOLDOWN_SECONDS = 60; // 60 seconds cooldown when tripped

    /**
     * Check if router is available to receive commands.
     * Returns false if circuit is OPEN (preventing socket storm & timeouts).
     */
    public static function isAvailable(int $routerId): bool
    {
        $state = self::getState($routerId);

        if ($state === self::STATE_OPEN) {
            Log::warning("[CircuitBreaker] Router #{$routerId} is in OPEN state. Skipping execution to prevent socket storm.");
            return false;
        }

        return true;
    }

    /**
     * Get current state of the circuit breaker for given router.
     */
    public static function getState(int $routerId): string
    {
        try {
            $state = Redis::get("circuit:router:{$routerId}:state");
            if ($state) {
                return (string) $state;
            }
        } catch (\Throwable $e) {
            // Fallback to standard Cache if Redis facade is unavailable
            $state = Cache::get("circuit:router:{$routerId}:state");
            if ($state) {
                return (string) $state;
            }
        }

        return self::STATE_CLOSED;
    }

    /**
     * Record a successful connection and operation on the router.
     * Closes the circuit and resets consecutive failure counter.
     */
    public static function recordSuccess(int $routerId): void
    {
        try {
            Redis::del("circuit:router:{$routerId}:failures");
            Redis::set("circuit:router:{$routerId}:state", self::STATE_CLOSED);
        } catch (\Throwable $e) {
            Cache::forget("circuit:router:{$routerId}:failures");
            Cache::put("circuit:router:{$routerId}:state", self::STATE_CLOSED, now()->addHours(24));
        }
    }

    /**
     * Record a connection/command failure on the router.
     * Trips circuit to OPEN after MAX_FAILURES (3) consecutive failures.
     */
    public static function recordFailure(int $routerId): void
    {
        $key = "circuit:router:{$routerId}:failures";
        $failures = 1;

        try {
            $failures = Redis::incr($key);
            Redis::expire($key, 120);

            if ($failures >= self::MAX_FAILURES) {
                Redis::setex("circuit:router:{$routerId}:state", self::COOLDOWN_SECONDS, self::STATE_OPEN);
                Log::error("[CircuitBreaker] Router #{$routerId} TRIPPED (OPEN) after {$failures} consecutive failures. Cooldown for " . self::COOLDOWN_SECONDS . "s.");
            }
        } catch (\Throwable $e) {
            $cached = (int) Cache::get($key, 0) + 1;
            $failures = $cached;
            Cache::put($key, $failures, now()->addSeconds(120));

            if ($failures >= self::MAX_FAILURES) {
                Cache::put("circuit:router:{$routerId}:state", self::STATE_OPEN, now()->addSeconds(self::COOLDOWN_SECONDS));
                Log::error("[CircuitBreaker] Router #{$routerId} TRIPPED (OPEN) via Cache fallback after {$failures} failures.");
            }
        }
    }

    /**
     * Manually reset the circuit breaker for a router.
     */
    public static function reset(int $routerId): void
    {
        try {
            Redis::del("circuit:router:{$routerId}:failures");
            Redis::del("circuit:router:{$routerId}:state");
        } catch (\Throwable $e) {
            Cache::forget("circuit:router:{$routerId}:failures");
            Cache::forget("circuit:router:{$routerId}:state");
        }
    }

    /**
     * Get consecutive failure count for router.
     */
    public static function getFailures(int $routerId): int
    {
        try {
            return (int) Redis::get("circuit:router:{$routerId}:failures");
        } catch (\Throwable $e) {
            return (int) Cache::get("circuit:router:{$routerId}:failures", 0);
        }
    }
}
