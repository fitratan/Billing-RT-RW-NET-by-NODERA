<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * ConfigService - Wraps the Setting Eloquent model with env fallback.
 *
 * PRIORITY ORDER:
 * 1. Database (settings table) — editable via web admin
 * 2. Environment variables / .env file — fallback defaults
 *
 * This allows admins to override .env values via the web interface.
 */
class ConfigService
{
    /**
     * Get a configuration value.
     *
     * Database takes precedence, then env / .env file, then $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // PRIORITY 1: Try database first
        try {
            $val = Setting::getValue($key);
            if ($val !== '' && $val !== null) {
                return $val;
            }
        } catch (\Exception $e) {
            Log::debug("ConfigService: Database read failed for key \"{$key}\", falling back to env", [
                'error' => $e->getMessage(),
            ]);
        }

        // PRIORITY 2: Environment variable
        $env = env($key);
        if ($env !== null && $env !== '') {
            return $env;
        }

        return $default;
    }

    /**
     * Save or update a configuration value in the database.
     */
    public function set(string $key, mixed $value): bool
    {
        try {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );

            return true;
        } catch (\Exception $e) {
            Log::error("ConfigService::set() failed for key \"{$key}\": " . $e->getMessage());

            return false;
        }
    }

    /**
     * Return an associative array of requested keys.
     */
    public function getAll(array $keys): array
    {
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = $this->get($k);
        }

        return $result;
    }

    /**
     * Delete a configuration value from the database.
     */
    public function delete(string $key): bool
    {
        try {
            Setting::destroy($key);

            return true;
        } catch (\Exception $e) {
            Log::error("ConfigService::delete() failed: " . $e->getMessage());

            return false;
        }
    }
}
