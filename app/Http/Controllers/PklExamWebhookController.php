<?php

namespace App\Http\Controllers;

use App\Models\PklExamSetting;
use App\Services\PklTelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PklExamWebhookController extends Controller
{
    /**
     * Handle Telegram Webhook update for PKL Exam Bot
     */
    public function handle(Request $request, ?string $token = null): JsonResponse
    {
        $setting = PklExamSetting::first();

        // If a token parameter is present in URL, verify it matches
        if ($token && $setting && $setting->bot_token && $token !== $setting->bot_token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 403);
        }

        // Check secret header if configured
        if ($setting && !empty($setting->webhook_secret)) {
            $incomingSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($incomingSecret !== $setting->webhook_secret) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized secret'], 403);
            }
        }

        $update = $request->all();

        if (empty($update)) {
            return response()->json(['status' => 'ok', 'message' => 'Empty payload']);
        }

        try {
            $botService = new PklTelegramBotService($setting?->bot_token);
            $botService->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error("[PklExamWebhook] Error processing update: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
