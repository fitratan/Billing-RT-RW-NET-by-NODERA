<?php

namespace App\Services;

class TurnstileService
{
    /**
     * Always pass verification (Turnstile removed/disabled).
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        return true;
    }
}
