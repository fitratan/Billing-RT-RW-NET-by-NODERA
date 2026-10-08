<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    protected string $backupDisk = 'local';
    protected string $backupDir = 'backups';

    public function index()
    {
        $backups = $this->listBackups();
        $totalSize = collect($backups)->sum('size');
        $dbCount = collect($backups)->where('type', 'database')->count();
        $fullCount = collect($backups)->where('type', 'full')->count();

        return Inertia::render('Admin/Backup', ['backups' => $backups, 'totalSize' => (float) $totalSize, 'dbCount' => (int) $dbCount, 'fullCount' => (int) $fullCount]);
    }

    public function superIndex()
    {
        $backups = $this->listBackups();
        $totalSize = collect($backups)->sum('size');
        $dbCount = collect($backups)->where('type', 'database')->count();
        $fullCount = collect($backups)->where('type', 'full')->count();

        return Inertia::render('Superadmin/Backup', ['backups' => $backups, 'totalSize' => (float) $totalSize, 'dbCount' => (int) $dbCount, 'fullCount' => (int) $fullCount]);
    }

    public function runBackup(Request $request)
    {
        return $this->runBackupCommon($request, '/admin/backup');
    }

    public function superRunBackup(Request $request)
    {
        return $this->runBackupCommon($request, '/superadmin/backup');
    }

    protected function runBackupCommon(Request $request, string $redirectPath)
    {
        $type = $request->input('type', 'database');

        try {
            if ($type === 'database' || $type === 'all') {
                $this->backupDatabase();
            }
            if ($type === 'full' || $type === 'all') {
                $this->backupDatabase();
                $this->backupEnvironment();
            }

            return redirect($redirectPath)->with('success', 'Backup berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            return redirect($redirectPath)->with('error', 'Gagal membuat backup: ' . $e->getMessage());
        }
    }

    private function sanitizeFileName(string $fileName): string
    {
        // Strip path traversal sequences
        $fileName = str_replace(['../', '..\\', './', '.\\'], '', $fileName);
        return basename($fileName);
    }

    public function downloadBackup($fileName)
    {
        $fileName = $this->sanitizeFileName($fileName);
        $path = $this->backupDir . '/' . $fileName;

        if (!Storage::disk($this->backupDisk)->exists($path)) {
            return redirect('/admin/backup')->with('error', 'File backup tidak ditemukan');
        }

        return Storage::disk($this->backupDisk)->download($path);
    }

    public function superDownloadBackup($fileName)
    {
        $fileName = $this->sanitizeFileName($fileName);
        $path = $this->backupDir . '/' . $fileName;

        if (!Storage::disk($this->backupDisk)->exists($path)) {
            return redirect('/superadmin/backup')->with('error', 'File backup tidak ditemukan');
        }

        return Storage::disk($this->backupDisk)->download($path);
    }

    public function deleteBackup($fileName)
    {
        $fileName = $this->sanitizeFileName($fileName);
        $path = $this->backupDir . '/' . $fileName;

        if (!Storage::disk($this->backupDisk)->exists($path)) {
            return redirect('/admin/backup')->with('error', 'File backup tidak ditemukan');
        }

        Storage::disk($this->backupDisk)->delete($path);

        return redirect('/admin/backup')->with('success', 'Backup berhasil dihapus');
    }

    public function superDeleteBackup($fileName)
    {
        $fileName = $this->sanitizeFileName($fileName);
        $path = $this->backupDir . '/' . $fileName;

        if (!Storage::disk($this->backupDisk)->exists($path)) {
            return redirect('/superadmin/backup')->with('error', 'File backup tidak ditemukan');
        }

        Storage::disk($this->backupDisk)->delete($path);

        return redirect('/superadmin/backup')->with('success', 'Backup berhasil dihapus');
    }

    protected function backupDatabase(): string
    {
        $dbPath = database_path('database.sqlite');
        $timestamp = now()->format('Ymd_His');
        $fileName = "backup_db_{$timestamp}.sqlite";
        $destPath = storage_path("app/{$this->backupDir}/{$fileName}");

        if (!is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }

        if (!file_exists($dbPath)) {
            throw new \RuntimeException('Database file not found: ' . $dbPath);
        }

        copy($dbPath, $destPath);

        return $fileName;
    }

    protected function backupEnvironment(): string
    {
        $envPath = base_path('.env');
        $timestamp = now()->format('Ymd_His');
        $fileName = "backup_env_{$timestamp}.env";
        $destPath = storage_path("app/{$this->backupDir}/{$fileName}");

        if (!is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }

        if (file_exists($envPath)) {
            copy($envPath, $destPath);
        }

        return $fileName;
    }

    protected function listBackups(): array
    {
        $path = storage_path("app/{$this->backupDir}");

        if (!is_dir($path)) {
            return [];
        }

        $files = scandir($path, SCANDIR_SORT_DESCENDING);
        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . '/' . $file;

            if (is_file($filePath)) {
                $type = 'unknown';
                if (str_starts_with($file, 'backup_db_')) {
                    $type = 'database';
                } elseif (str_starts_with($file, 'backup_env_')) {
                    $type = 'full';
                }

                $backups[] = [
                    'fileName' => $file,
                    'type' => $type,
                    'size' => filesize($filePath),
                    'sizeFormatted' => $this->formatBytes(filesize($filePath)),
                    'created' => filectime($filePath),
                    'createdFormatted' => date('Y-m-d H:i:s', filectime($filePath)),
                ];
            }
        }

        return $backups;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[(int)$i];
    }
}
