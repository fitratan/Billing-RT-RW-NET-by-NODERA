<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * TotpService — RFC 6238 Time-based One-Time Password (pure PHP, no deps).
 *
 * Secret stored in Base32. Codes are 6 digits, 30-second window.
 *
 * Usage:
 *   $svc = app(TotpService::class);
 *   $secret = $svc->generate();
 *   $uri    = $svc->provisioningUri($secret, 'label');
 *   $ok     = $svc->verify($secret, $code);
 */
class TotpService
{
    private const WINDOW = 30;
    private const CODE_LENGTH = 6;

    public function generate(int $bytes = 16): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** Generate single-use recovery codes (returned in plaintext to the user). */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    /** Hash recovery codes for storage (bcrypt). */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn ($c) => bcrypt($c), $plainCodes);
    }

    /**
     * Verify a recovery code against a list of hashed codes.
     * Returns the refilled array (with the matched code removed) or null if none match.
     */
    public function consumeRecoveryCode(string $code, array $hashedCodes): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $remaining = [];
        $matched = false;
        foreach ($hashedCodes as $hash) {
            if (!$matched && is_string($hash) && password_verify($code, $hash)) {
                $matched = true;
                continue;
            }
            $remaining[] = $hash;
        }
        return $matched ? array_values($remaining) : null;
    }

    public function provisioningUri(string $secret, string $label): string
    {
        $label = rawurlencode($label);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode(config('app.name', 'NODERA'));
    }

    public function verify(string $secret, string $code, int $leeway = 1): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $timestamp = time();
        for ($i = -$leeway; $i <= $leeway; $i++) {
            $counter = (int) floor($timestamp / self::WINDOW) + $i;
            if (hash_equals($this->hotp($secret, $counter), $code)) {
                return true;
            }
        }
        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $this->base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $shifted = str_pad($binary, (int) ceil(strlen($binary) / 8) * 8, '0');
        $encoded = '';
        foreach (str_split($shifted, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $encoded;
    }

    private function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32) ?? '');
        foreach (str_split($base32) as $char) {
            $binary .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $bytes .= chr(bindec($chunk));
        }
        return $bytes;
    }
}