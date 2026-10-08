<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FileStorageService
{
    const SIGNED_URL_TTL_MINUTES = 15;

    /**
     * Store a file under tenant-scoped private storage:
     * storage/app/tenants/{tenant_uuid}/{module}/{hash_name}
     */
    public function storeTenantFile(UploadedFile $file, string $module, ?string $tenantUuid = null): string
    {
        $uuid = $tenantUuid ?? (session('tenant_uuid') ?? 'default_tenant');
        $cleanModule = preg_replace('/[^a-zA-Z0-9_-]/', '', $module);
        $directory = "tenants/{$uuid}/{$cleanModule}";

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = Str::random(40) . '.' . $extension;

        return $file->storeAs($directory, $filename, 'local');
    }

    /**
     * Generate a temporary signed download URL (TTL 15 minutes) for a private tenant file.
     * Prevents Insecure Direct Object Reference (IDOR) attacks across tenants.
     */
    public function getTemporarySignedUrl(string $path, int $ttlMinutes = self::SIGNED_URL_TTL_MINUTES): string
    {
        return URL::temporarySignedRoute(
            'tenant.storage.download',
            now()->addMinutes($ttlMinutes),
            ['path' => base64_encode($path)]
        );
    }

    /**
     * Verify if the requested path belongs to the active tenant.
     */
    public function authorizeTenantAccess(string $path, ?string $tenantUuid = null): bool
    {
        $uuid = $tenantUuid ?? (session('tenant_uuid') ?? '');
        if (empty($uuid)) {
            return false;
        }

        return str_starts_with($path, "tenants/{$uuid}/");
    }
}
