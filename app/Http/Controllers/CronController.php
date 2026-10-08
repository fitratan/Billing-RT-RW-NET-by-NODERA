<?php

namespace App\Http\Controllers;

use App\Services\CronService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CronController — HTTP fallback trigger for scheduled jobs.
 *
 * Allows external cron services (e.g., operating system cron, UptimeRobot)
 * to trigger Laravel scheduled jobs via HTTP GET.
 *
 * Protected by an API key query parameter (?key=...).
 * The key is checked against the CRON_SECRET env variable.
 *
 * Routes (web.php):
 *   GET /cron/run/{job}       -> CronController@run
 *   GET /cron/status          -> CronController@status
 */
class CronController extends Controller
{
    private CronService $cronService;

    /** Default secret for local/dev environments. */
    private const DEFAULT_SECRET = '';

    public function __construct(CronService $cronService)
    {
        $this->cronService = $cronService;
    }

    /**
     * Run a specific cron job via HTTP trigger.
     *
     * @param string $job Job name (invoice:generate, isolation:check, usage:poll, backup:database)
     */
    public function run(Request $request, string $job): \Illuminate\Http\JsonResponse
    {
        // --- API key validation ---
        $envSecret = env('CRON_SECRET', self::DEFAULT_SECRET);

        if (empty($envSecret)) {
            Log::warning('[CronController] CRON_SECRET not configured.');
            return response()->json([
                'success' => false,
                'message' => 'Cron not configured',
            ], 500);
        }

        if (!$this->hasValidKey($request)) {
            Log::warning('[CronController] Invalid cron key attempt.', [
                'ip' => $request->ip(),
                'job' => $job,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Invalid Cron Key',
            ], 403);
        }

        // --- Validate job exists ---
        if (!isset(CronService::JOBS[$job])) {
            return response()->json([
                'success' => false,
                'message' => "Unknown job: {$job}. Available jobs: " . implode(', ', array_keys(CronService::JOBS)),
            ], 404);
        }

        // --- Execute job ---
        $result = $this->cronService->runJob($job);

        $statusCode = $result['success'] ? 200 : 500;

        return response()->json($result, $statusCode);
    }

    /**
     * Get the status of all cron jobs.
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$this->hasValidKey($request)) {
            Log::warning('[CronController] Invalid cron key attempt (status).', ['ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Invalid Cron Key',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->cronService->getJobStatus(),
        ]);
    }

    /**
     * Validasi CRON_SECRET dari query param `key`.
     */
    private function hasValidKey(Request $request): bool
    {
        $envSecret = env('CRON_SECRET', self::DEFAULT_SECRET);
        if (empty($envSecret)) {
            return false;
        }

        return hash_equals((string) $envSecret, (string) $request->query('key', ''));
    }
}
