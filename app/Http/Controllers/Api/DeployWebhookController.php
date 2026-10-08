<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    /**
     * Handle automated deployment webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = env('DEPLOY_WEBHOOK_SECRET');
        $token = $request->header('X-Deploy-Token') ?: $request->query('token');

        if (empty($secret)) {
            Log::warning("[DeployWebhook] Deployment webhook requested but DEPLOY_WEBHOOK_SECRET is not configured.");
            return response()->json([
                'success' => false,
                'message' => 'Deploy webhook is not configured on this server.',
            ], 403);
        }

        if (empty($token) || !hash_equals((string) $secret, (string) $token)) {
            Log::warning("[DeployWebhook] Unauthorized deploy attempt from IP: " . $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized deploy token.',
            ], 401);
        }

        Log::info("[DeployWebhook] Valid deployment webhook triggered from IP: " . $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Deployment triggered successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
