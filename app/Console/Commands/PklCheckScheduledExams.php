<?php

namespace App\Console\Commands;

use App\Models\PklExamAttempt;
use App\Models\PklExamSession;
use App\Models\PklQuestion;
use App\Services\PklTelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PklCheckScheduledExams extends Command
{
    protected $signature = 'pkl:check-scheduled-exams';
    protected $description = 'Check and automatically activate scheduled PKL exam sessions and close expired ones';

    public function handle(PklTelegramBotService $botService): int
    {
        $now = now();

        // 1. Process Scheduled Sessions that are ready to start
        $dueSessions = PklExamSession::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($dueSessions as $session) {
            $duration = (int) ($session->duration_minutes ?: 30);
            $startedAt = $now->copy();
            $expiresAt = $now->copy()->addMinutes($duration);

            $session->update([
                'status'     => 'active',
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
            ]);

            $this->info("Activated scheduled PKL session: #{$session->id} '{$session->title}'");
            Log::info("[PklExam] Auto-activated scheduled session #{$session->id} '{$session->title}' at {$now}");

            if ($session->is_auto_broadcast) {
                try {
                    $res = $botService->broadcastSessionOpen($session);
                    $this->info("Auto-broadcasted session to {$res['sent']} student(s).");
                } catch (\Throwable $e) {
                    Log::error("[PklExam] Auto-broadcast failed for session #{$session->id}: " . $e->getMessage());
                }
            }
        }

        // 2. Process Expired Sessions that are past expires_at
        $expiredSessions = PklExamSession::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredSessions as $session) {
            $session->update([
                'status' => 'closed',
            ]);
            $this->info("Closed expired PKL session: #{$session->id} '{$session->title}'");

            // Finalize any lingering in-progress attempts
            $inProgress = PklExamAttempt::where('session_id', $session->id)
                ->where('status', 'in_progress')
                ->get();

            foreach ($inProgress as $att) {
                try {
                    $botService->finalizeAttempt($att);
                } catch (\Throwable $e) {
                    Log::error("[PklExam] Auto-finalize attempt #{$att->id} failed: " . $e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }
}
