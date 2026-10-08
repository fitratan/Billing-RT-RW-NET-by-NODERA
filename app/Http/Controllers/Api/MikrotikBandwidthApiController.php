<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MikrotikBandwidthApiController extends Controller
{
    /**
     * Handle PPPoE session disconnect event sent from MikroTik on-down script.
     * Records the final byte count into CustomerUsage before interface is deleted.
     */
    public function recordDisconnect(Request $request)
    {
        $username = trim($request->input('username') ?? $request->input('user') ?? '');
        $rxBytes = (int) ($request->input('rx') ?? $request->input('bytes_in') ?? $request->input('rx_byte') ?? 0);
        $txBytes = (int) ($request->input('tx') ?? $request->input('bytes_out') ?? $request->input('tx_byte') ?? 0);
        $routerId = $request->filled('router_id') ? (int) $request->input('router_id') : null;

        if (!$username) {
            return response()->json([
                'success' => false,
                'message' => 'Username parameter is required',
            ], 400);
        }

        try {
            $success = app(UsageService::class)->recordSessionDisconnect($username, $rxBytes, $txBytes, $routerId);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Disconnect session recorded successfully' : 'Customer not found, skipped',
                'username' => $username,
                'rx' => $rxBytes,
                'tx' => $txBytes,
            ]);
        } catch (\Throwable $e) {
            Log::error("[MikrotikBandwidthApi] Error recording disconnect for {$username}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
