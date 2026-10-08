<?php

namespace App\Services;

use App\Models\PklExamAttempt;
use App\Models\PklExamSession;
use App\Models\PklExamSetting;
use App\Models\PklQuestion;
use App\Models\PklStudent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PklTelegramBotService
{
    protected ?string $token = null;
    protected string $apiUrl = 'https://api.telegram.org/bot';

    public function __construct(?string $token = null)
    {
        if ($token) {
            $this->token = $token;
        } else {
            $setting = PklExamSetting::first();
            $this->token = $setting?->bot_token;
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get Bot Info from Telegram API
     */
    public function getMe(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}{$this->token}/getMe");
            return $response->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Set Webhook for PKL Exam Bot
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }
        try {
            $params = ['url' => $url];
            if ($secretToken) {
                $params['secret_token'] = $secretToken;
            }
            $response = Http::timeout(15)->post("{$this->apiUrl}{$this->token}/setWebhook", $params);
            return $response->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Delete Webhook
     */
    public function deleteWebhook(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }
        try {
            $response = Http::timeout(10)->post("{$this->apiUrl}{$this->token}/deleteWebhook");
            return $response->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Send Message
     */
    public function sendMessage(string|int $chatId, string $text, string $parseMode = 'HTML', ?array $replyMarkup = null, ?int $messageThreadId = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }
        try {
            $params = [
                'chat_id' => (string) $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ];
            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }
            if ($messageThreadId) {
                $params['message_thread_id'] = $messageThreadId;
            }
            $res = Http::timeout(10)->post("{$this->apiUrl}{$this->token}/sendMessage", $params);
            return $res->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            Log::error("[PklExamBot] sendMessage error: " . $e->getMessage());
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Edit Message Text
     */
    public function editMessageText(string|int $chatId, int $messageId, string $text, string $parseMode = 'HTML', ?array $replyMarkup = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }
        try {
            $params = [
                'chat_id' => (string) $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ];
            if ($replyMarkup !== null) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }
            $res = Http::timeout(10)->post("{$this->apiUrl}{$this->token}/editMessageText", $params);
            return $res->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            Log::error("[PklExamBot] editMessageText error: " . $e->getMessage());
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Answer Callback Query
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false];
        }
        try {
            $params = ['callback_query_id' => $callbackQueryId];
            if ($text !== null) {
                $params['text'] = $text;
                $params['show_alert'] = $showAlert;
            }
            $res = Http::timeout(5)->post("{$this->apiUrl}{$this->token}/answerCallbackQuery", $params);
            return $res->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            return ['ok' => false];
        }
    }

    /**
     * Handle Incoming Telegram Update
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    /**
     * Handle Incoming Text Message
     */
    protected function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $fromUsername = $message['from']['username'] ?? null;

        if (!$chatId) return;

        // Check Whitelist Student
        $student = PklStudent::where('telegram_chat_id', (string) $chatId)->first();

        if (!$student || !$student->is_active) {
            $this->sendUnauthorizedMessage($chatId);
            return;
        }

        // Update username if changed
        if ($fromUsername && $student->telegram_username !== $fromUsername) {
            $student->update(['telegram_username' => $fromUsername]);
        }

        $command = strtolower(explode(' ', $text)[0]);

        switch ($command) {
            case '/start':
            case '/ujian':
            case '/menu':
                $this->sendStudentHomeMenu($student);
                break;
            case '/nilai':
            case '/hasil':
                $this->sendStudentResults($student);
                break;
            case '/help':
            case '/bantuan':
                $this->sendHelpMessage($student);
                break;
            default:
                $this->sendStudentHomeMenu($student);
                break;
        }
    }

    /**
     * Send Unauthorized Message for non-whitelisted Chat ID
     */
    protected function sendUnauthorizedMessage(string|int $chatId): void
    {
        $text = "⛔ <b>AKSES UJIAN DITOLAK — NODERA PKL</b>\n\n"
            . "Akun Telegram Anda belum terdaftar dalam sistem ujian PKL.\n\n"
            . "📋 <b>Data Verifikasi Anda:</b>\n"
            . "├ Telegram Chat ID: <code>{$chatId}</code>\n"
            . "└ Status: <b>Belum Terdaftar / Dinonaktifkan</b>\n\n"
            . "💡 <i>Silakan salin Chat ID di atas dan berikan kepada Pembimbing / Superadmin untuk didaftarkan ke sistem ujian.</i>";

        $this->sendMessage($chatId, $text);
    }

    /**
     * Send Student Home Menu
     */
    public function sendStudentHomeMenu(PklStudent $student): void
    {
        $chatId = $student->telegram_chat_id;
        $activeSession = PklExamSession::where('status', 'active')->latest()->first();

        if (!$activeSession) {
            $text = "👋 <b>Halo, {$student->name}!</b>\n\n"
                . "┌ <b>Profil Peserta PKL</b>\n"
                . "├ Asal Sekolah: <b>{$student->school}</b>\n"
                . "├ Jurusan: <b>{$student->major}</b>\n"
                . "└ Status: <tg-spoiler>Terdaftar & Aktif</tg-spoiler>\n\n"
                . "ℹ️ <i>Saat ini belum ada sesi ujian PKL yang sedang dibuka. Silakan pantau informasi dari pembimbing.</i>";

            $this->sendMessage($chatId, $text);
            return;
        }

        // Check if student has in-progress attempt
        $inProgressAttempt = PklExamAttempt::where('session_id', $activeSession->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($inProgressAttempt) {
            if ($inProgressAttempt->isExpired()) {
                $this->finalizeAttempt($inProgressAttempt, 'expired');
            } else {
                $this->sendQuestion($inProgressAttempt);
                return;
            }
        }

        // Check completed attempts
        $completedAttempts = PklExamAttempt::where('session_id', $activeSession->id)
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->get();

        $attemptCount = $completedAttempts->count();
        $bestScore = $completedAttempts->max('score') ?? 0;

        $canAttempt = true;
        if ($attemptCount > 0 && !$activeSession->allow_retake) {
            $canAttempt = false;
        } elseif ($attemptCount >= $activeSession->max_retakes) {
            $canAttempt = false;
        }

        $text = "🎓 <b>PORTAL UJIAN PKL — NODERA</b>\n\n"
            . "Selamat datang, <b>{$student->name}</b>!\n"
            . "🏫 <b>Sekolah:</b> {$student->school} | 📚 {$student->major}\n\n"
            . "════════════════════════\n"
            . "📌 <b>SESI UJIAN AKTIF</b>\n"
            . "📝 <b>Judul:</b> {$activeSession->title}\n"
            . "⏱️ <b>Durasi:</b> {$activeSession->duration_minutes} Menit\n"
            . "🎯 <b>Standar Kelulusan (KKM):</b> {$activeSession->passing_grade} Poin\n"
            . "📊 <b>Jumlah Soal:</b> {$activeSession->question_count} Pilihan Ganda\n"
            . "════════════════════════\n\n";

        if ($attemptCount > 0) {
            $text .= "📊 <b>Riwayat Pengerjaan Anda:</b>\n"
                . "├ Sudah Dikerjakan: <b>{$attemptCount}x</b>\n"
                . "├ Nilai Terbaik: <b>{$bestScore}/100</b>\n"
                . "└ Status: " . ($bestScore >= $activeSession->passing_grade ? "✅ <b>LULUS</b>" : "❌ <b>BELUM LULUS</b>") . "\n\n";
        }

        if ($canAttempt) {
            $text .= "🚀 <i>Tekan tombol di bawah untuk memulai pengerjaan ujian. Waktu akan langsung berjalan saat Anda menekan tombol mulai.</i>";
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => '🚀 Mulai Kerjakan Ujian', 'callback_data' => "exam_start:{$activeSession->id}"],
                    ],
                    [
                        ['text' => '📋 Lihat Riwayat Nilai', 'callback_data' => 'exam_history'],
                    ]
                ]
            ];
        } else {
            $text .= "🔒 <i>Anda telah menyelesaikan kesempatan ujian untuk sesi ini.</i>";
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => '📋 Lihat Rincian Nilai', 'callback_data' => 'exam_history'],
                    ]
                ]
            ];
        }

        $this->sendMessage($chatId, $text, 'HTML', $buttons);
    }

    /**
     * Handle Callback Query
     */
    protected function handleCallbackQuery(array $callback): void
    {
        $callbackId = $callback['id'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = $callback['data'] ?? '';

        if (!$chatId) return;

        $student = PklStudent::where('telegram_chat_id', (string) $chatId)->first();
        if (!$student || !$student->is_active) {
            $this->answerCallbackQuery($callbackId, "Akun tidak terdaftar", true);
            $this->sendUnauthorizedMessage($chatId);
            return;
        }

        $parts = explode(':', $data);
        $action = $parts[0] ?? '';

        switch ($action) {
            case 'exam_start':
                $sessionId = (int) ($parts[1] ?? 0);
                $this->answerCallbackQuery($callbackId, "Memulai sesi ujian...");
                $this->startExamSession($student, $sessionId, $messageId);
                break;

            case 'exam_ans':
                $attemptId = (int) ($parts[1] ?? 0);
                $questionId = (int) ($parts[2] ?? 0);
                $chosenOption = strtolower($parts[3] ?? '');
                $this->handleAnswerSubmission($student, $attemptId, $questionId, $chosenOption, $messageId, $callbackId);
                break;

            case 'exam_history':
                $this->answerCallbackQuery($callbackId);
                $this->sendStudentResults($student);
                break;

            case 'exam_menu':
                $this->answerCallbackQuery($callbackId);
                $this->sendStudentHomeMenu($student);
                break;

            default:
                $this->answerCallbackQuery($callbackId);
                break;
        }
    }

    /**
     * Start a new exam attempt
     */
    public function startExamSession(PklStudent $student, int $sessionId, ?int $messageId = null): void
    {
        $session = PklExamSession::find($sessionId);
        if (!$session || $session->status !== 'active') {
            $this->sendMessage($student->telegram_chat_id, "⚠️ Sesi ujian sudah ditutup atau tidak aktif.");
            return;
        }

        // Check if active attempt exists
        $existing = PklExamAttempt::where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            if ($existing->isExpired()) {
                $this->finalizeAttempt($existing, 'expired');
            } else {
                $this->sendQuestion($existing, $messageId);
                return;
            }
        }

        // Check attempt count
        $attemptCount = PklExamAttempt::where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->count();

        if ($attemptCount > 0 && !$session->allow_retake) {
            $this->sendMessage($student->telegram_chat_id, "⚠️ Anda sudah mengikuti ujian ini dan ujian ulang tidak diizinkan.");
            return;
        }

        // Select questions with smart randomization (exclude mastered, allow remedial, prioritize fresh)
        $questionIds = $this->selectExamQuestionsForStudent($student, $session);

        if (empty($questionIds)) {
            $this->sendMessage($student->telegram_chat_id, "⚠️ Bank soal untuk sesi ujian ini belum tersedia. Hubungi pembimbing.");
            return;
        }

        $attempt = PklExamAttempt::create([
            'session_id'       => $session->id,
            'student_id'       => $student->id,
            'telegram_chat_id' => $student->telegram_chat_id,
            'attempt_number'   => $attemptCount + 1,
            'question_ids'     => $questionIds,
            'current_index'    => 0,
            'answers'          => [],
            'score'            => 0,
            'total_correct'    => 0,
            'total_wrong'      => 0,
            'total_questions'  => count($questionIds),
            'status'           => 'in_progress',
            'started_at'       => now(),
            'expires_at'       => now()->addMinutes($session->duration_minutes),
        ]);

        $this->sendQuestion($attempt, $messageId);
    }

    /**
     * Select questions for student with intelligent randomization:
     * 1. Questions are strictly randomized (acak).
     * 2. Questions used in past exam sessions are excluded from subsequent exams,
     *    EXCEPT if the average result on that question was remedial (high error/failure rate >= 40%)
     *    or if this specific student answered it wrong previously (personal remedial).
     * 3. Fresh unseen questions have top priority.
     */
    public function selectExamQuestionsForStudent(PklStudent $student, PklExamSession $session): array
    {
        $targetCount = max(1, (int) $session->question_count);

        // Base query for active questions matching session categories
        $baseQuery = PklQuestion::where('is_active', true);
        if (!empty($session->categories) && is_array($session->categories)) {
            $baseQuery->whereIn('category', $session->categories);
        }

        $allMatchingQuestions = $baseQuery->get(['id', 'correct_answer']);
        $allMatchingIds = $allMatchingQuestions->pluck('id')->all();
        $correctAnswerMap = $allMatchingQuestions->pluck('correct_answer', 'id')->all();

        if (empty($allMatchingIds)) {
            return [];
        }

        // 1. Analyze global question statistics across ALL completed attempts
        $allCompletedAttempts = PklExamAttempt::where('status', 'completed')
            ->get(['question_ids', 'answers', 'student_id']);

        $globalQuestionStats = []; // [qId => ['total' => int, 'wrong' => int, 'correct' => int]]
        $studentMasteredQuestionIds = []; // Questions this student answered correctly in past attempts
        $studentRemedialQuestionIds = []; // Questions this student answered incorrectly in past attempts

        foreach ($allCompletedAttempts as $att) {
            $isThisStudent = ((int) $att->student_id === (int) $student->id);
            $qIds = $att->question_ids ?? [];
            $ans = $att->answers ?? [];

            foreach ($qIds as $qId) {
                if (!isset($correctAnswerMap[$qId])) {
                    continue;
                }

                if (!isset($globalQuestionStats[$qId])) {
                    $globalQuestionStats[$qId] = ['total' => 0, 'wrong' => 0, 'correct' => 0];
                }

                $globalQuestionStats[$qId]['total']++;

                $userAns = strtolower(trim($ans[$qId] ?? ''));
                $correctAns = strtolower(trim($correctAnswerMap[$qId] ?? ''));

                if ($userAns !== '' && $userAns === $correctAns) {
                    $globalQuestionStats[$qId]['correct']++;
                    if ($isThisStudent) {
                        $studentMasteredQuestionIds[$qId] = true;
                    }
                } else {
                    $globalQuestionStats[$qId]['wrong']++;
                    if ($isThisStudent) {
                        $studentRemedialQuestionIds[$qId] = true;
                    }
                }
            }
        }

        // Questions this student answered wrong and has not subsequently mastered
        $studentRemedialIds = array_values(array_diff(
            array_keys($studentRemedialQuestionIds),
            array_keys($studentMasteredQuestionIds)
        ));

        // Questions used globally that have a high remedial/failure rate (>= 40% error rate)
        $globalRemedialIds = [];
        // Questions used globally that were mostly mastered/passed (< 40% error rate)
        $globallyUsedMasteredIds = [];
        // Fresh questions never used in any completed exam session
        $freshUnusedIds = [];

        foreach ($allMatchingIds as $qId) {
            // If this student already mastered this question, skip it from regular candidate pools
            if (isset($studentMasteredQuestionIds[$qId])) {
                continue;
            }

            if (!isset($globalQuestionStats[$qId]) || $globalQuestionStats[$qId]['total'] === 0) {
                $freshUnusedIds[] = $qId;
            } else {
                $total = $globalQuestionStats[$qId]['total'];
                $wrong = $globalQuestionStats[$qId]['wrong'];
                $wrongRate = $total > 0 ? ($wrong / $total) : 0;

                if ($wrongRate >= 0.40) {
                    // Average remedial question: students struggled with this question -> allowed to be reused
                    $globalRemedialIds[] = $qId;
                } else {
                    // Question was mostly passed / mastered in previous exams -> do NOT reuse unless bank is exhausted
                    $globallyUsedMasteredIds[] = $qId;
                }
            }
        }

        // Randomize each pool
        shuffle($freshUnusedIds);
        shuffle($globalRemedialIds);
        shuffle($studentRemedialIds);
        shuffle($globallyUsedMasteredIds);

        // Priority 1: Fresh unused questions (never used in previous exams)
        // Priority 2: Questions with high remedial rate (rata-rata remedial) + Student's personal remedial questions
        // Priority 3: Other unused-by-this-student questions
        // Priority 4: Fallback from remaining pool if total questions in bank is smaller than target count
        $candidateIds = [];

        // Add fresh questions first
        foreach ($freshUnusedIds as $id) {
            if (!in_array($id, $candidateIds, true)) {
                $candidateIds[] = $id;
            }
        }

        // Add remedial questions (global remedial & student remedial)
        $combinedRemedials = array_unique(array_merge($studentRemedialIds, $globalRemedialIds));
        shuffle($combinedRemedials);
        foreach ($combinedRemedials as $id) {
            if (!in_array($id, $candidateIds, true)) {
                $candidateIds[] = $id;
            }
        }

        // If candidate count is still less than target, add from globally used mastered questions that this student hasn't seen
        if (count($candidateIds) < $targetCount) {
            foreach ($globallyUsedMasteredIds as $id) {
                if (!in_array($id, $candidateIds, true)) {
                    $candidateIds[] = $id;
                }
            }
        }

        // If still not enough (e.g. user requested 100 questions but bank has fewer unmastered questions),
        // fallback to mastered questions
        if (count($candidateIds) < $targetCount) {
            $remaining = array_values(array_diff($allMatchingIds, $candidateIds));
            shuffle($remaining);
            $candidateIds = array_merge($candidateIds, $remaining);
        }

        // Select up to target count
        $selectedIds = array_slice($candidateIds, 0, $targetCount);

        // Always randomize / shuffle the final question order
        shuffle($selectedIds);

        return $selectedIds;
    }

    /**
     * Send current question to student
     */
    public function sendQuestion(PklExamAttempt $attempt, ?int $messageId = null): void
    {
        $questionIds = $attempt->question_ids ?? [];
        $currentIndex = $attempt->current_index;
        $totalQuestions = count($questionIds);

        if ($currentIndex >= $totalQuestions) {
            $this->finalizeAttempt($attempt, 'completed', $messageId);
            return;
        }

        if ($attempt->isExpired()) {
            $this->finalizeAttempt($attempt, 'expired', $messageId);
            return;
        }

        $questionId = $questionIds[$currentIndex] ?? null;
        $question = PklQuestion::find($questionId);

        if (!$question) {
            $attempt->increment('current_index');
            $this->sendQuestion($attempt->fresh(), $messageId);
            return;
        }

        $categoryLabels = PklQuestion::categoryLabels();
        $catName = $categoryLabels[$question->category] ?? ucfirst($question->category);

        $minsLeft = max(1, (int) now()->diffInMinutes($attempt->expires_at, false));

        $qNumber = $currentIndex + 1;

        $text = "📝 <b>SOAL UJIAN NO. {$qNumber} / {$totalQuestions}</b>\n"
            . "📂 <i>Materi: {$catName}</i> | ⏱️ <i>Sisa: ~{$minsLeft} Menit</i>\n"
            . "════════════════════════\n\n"
            . "<b>Pertanyaan:</b>\n"
            . "{$question->question_text}\n\n"
            . "<b>A.</b> " . htmlspecialchars($question->option_a) . "\n"
            . "<b>B.</b> " . htmlspecialchars($question->option_b) . "\n"
            . "<b>C.</b> " . htmlspecialchars($question->option_c) . "\n"
            . "<b>D.</b> " . htmlspecialchars($question->option_d) . "\n\n"
            . "👉 <i>Pilih jawaban Anda di tombol bawah:</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🅰️ Pilihan A', 'callback_data' => "exam_ans:{$attempt->id}:{$question->id}:a"],
                    ['text' => '🅱️ Pilihan B', 'callback_data' => "exam_ans:{$attempt->id}:{$question->id}:b"],
                ],
                [
                    ['text' => '🅲 Pilihan C', 'callback_data' => "exam_ans:{$attempt->id}:{$question->id}:c"],
                    ['text' => '🅳 Pilihan D', 'callback_data' => "exam_ans:{$attempt->id}:{$question->id}:d"],
                ]
            ]
        ];

        if ($messageId) {
            $res = $this->editMessageText($attempt->telegram_chat_id, $messageId, $text, 'HTML', $keyboard);
            if (!($res['ok'] ?? false)) {
                $newRes = $this->sendMessage($attempt->telegram_chat_id, $text, 'HTML', $keyboard);
                if (!empty($newRes['result']['message_id'])) {
                    $attempt->update(['last_message_id' => (string) $newRes['result']['message_id']]);
                }
            }
        } else {
            $res = $this->sendMessage($attempt->telegram_chat_id, $text, 'HTML', $keyboard);
            if (!empty($res['result']['message_id'])) {
                $attempt->update(['last_message_id' => (string) $res['result']['message_id']]);
            }
        }
    }

    /**
     * Handle Answer selection from student
     */
    protected function handleAnswerSubmission(PklStudent $student, int $attemptId, int $questionId, string $option, ?int $messageId = null, ?string $callbackId = null): void
    {
        $attempt = PklExamAttempt::where('id', $attemptId)->where('student_id', $student->id)->first();
        if (!$attempt || $attempt->status !== 'in_progress') {
            if ($callbackId) $this->answerCallbackQuery($callbackId, "Sesi ujian telah selesai", true);
            return;
        }

        if ($attempt->isExpired()) {
            if ($callbackId) $this->answerCallbackQuery($callbackId, "Waktu ujian habis!", true);
            $this->finalizeAttempt($attempt, 'expired', $messageId);
            return;
        }

        if ($callbackId) {
            $this->answerCallbackQuery($callbackId, "Jawaban {$option} tersimpan!");
        }

        $answers = $attempt->answers ?? [];
        $answers[$questionId] = $option;

        $attempt->answers = $answers;
        $attempt->current_index = $attempt->current_index + 1;
        $attempt->save();

        $this->sendQuestion($attempt->fresh(), $messageId);
    }

    /**
     * Finalize and score an attempt
     */
    public function finalizeAttempt(PklExamAttempt $attempt, string $finalStatus = 'completed', ?int $messageId = null): void
    {
        $questionIds = $attempt->question_ids ?? [];
        $answers = $attempt->answers ?? [];

        $questions = PklQuestion::whereIn('id', $questionIds)->get()->keyBy('id');

        $totalQuestions = count($questionIds);
        $totalCorrect = 0;
        $totalWrong = 0;

        foreach ($questionIds as $qId) {
            $q = $questions->get($qId);
            $userAns = strtolower($answers[$qId] ?? '');
            if ($q && $userAns === strtolower($q->correct_answer)) {
                $totalCorrect++;
            } else {
                $totalWrong++;
            }
        }

        $score = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;

        $attempt->update([
            'status'          => $finalStatus,
            'completed_at'    => now(),
            'score'           => $score,
            'total_correct'   => $totalCorrect,
            'total_wrong'     => $totalWrong,
            'total_questions' => $totalQuestions,
        ]);

        $student = $attempt->student;
        $session = $attempt->session;

        $passingGrade = $session->passing_grade ?? 75;
        $isPassed = $score >= $passingGrade;

        $statusBadge = $isPassed ? "✅ LULUS" : "❌ TIDAK LULUS (REMEDIAL)";

        $resultCard = "🎓 <b>HASIL UJIAN PKL — NODERA</b>\n"
            . "════════════════════════\n\n"
            . "👤 <b>Nama Peserta:</b> {$student->name}\n"
            . "🏫 <b>Asal Sekolah:</b> {$student->school}\n"
            . "📚 <b>Jurusan:</b> {$student->major}\n"
            . "📝 <b>Sesi Ujian:</b> {$session->title}\n\n"
            . "┌ <b>Rekapitulasi Nilai</b>\n"
            . "├ Total Soal: <b>{$totalQuestions} Soal</b>\n"
            . "├ Jawaban Benar: <b>{$totalCorrect}</b>\n"
            . "├ Jawaban Salah / Kosong: <b>{$totalWrong}</b>\n"
            . "├ Standar Kelulusan (KKM): <b>{$passingGrade} Poin</b>\n"
            . "├ <b>Nilai Akhir:</b> <code>{$score} / 100</code>\n"
            . "└ <b>Status:</b> <b>{$statusBadge}</b>\n\n"
            . "════════════════════════\n"
            . "⏱️ <i>Waktu Selesai: " . now()->format('d/m/Y H:i:s') . " WIB</i>\n\n"
            . "Terima kasih telah mengikuti ujian PKL. Hasil ini telah tersimpan di sistem Superadmin.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 Lihat Riwayat Ujian', 'callback_data' => 'exam_history'],
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'exam_menu'],
                ]
            ]
        ];

        if ($messageId) {
            $this->editMessageText($attempt->telegram_chat_id, $messageId, $resultCard, 'HTML', $keyboard);
        } else {
            $this->sendMessage($attempt->telegram_chat_id, $resultCard, 'HTML', $keyboard);
        }

        $setting = PklExamSetting::getActive();
        $groupChatId = $setting->group_chat_id ?: $setting->announcement_chat_id;
        $groupThreadId = $setting->group_thread_id ? (int) $setting->group_thread_id : null;

        // Generate and send evaluation PDF directly to the student via Telegram Document
        $pdfPath = null;
        try {
            $pdfPath = $this->generateResultPdf($attempt);
            if (file_exists($pdfPath)) {
                $caption = "📄 <b>DOKUMEN RESMI HASIL EVALUASI & PEMBAHASAN UJIAN PKL</b>\n\n"
                    . "Halo <b>{$student->name}</b>, berikut adalah berkas PDF resmi lembar jawaban dan pembahasan soal ujian <b>{$session->title}</b> untuk bahan evaluasi dan pembelajaran Anda.";
                $this->sendDocument($attempt->telegram_chat_id, $pdfPath, $caption);
            }
        } catch (\Throwable $e) {
            Log::error("[PklExamBot] Failed generating/sending PDF to student: " . $e->getMessage());
        }

        // Send Report and PDF to PKL Telegram Group & Topic (Sent directly by PKL Exam Bot)
        if (!empty($groupChatId)) {
            try {
                $groupReport = "🎓 <b>LAPORAN HASIL UJIAN PKL — NODERA</b>\n\n"
                    . "┌ <b>Data Peserta</b>\n"
                    . "├ Nama: <b>{$student->name}</b>\n"
                    . "├ Sekolah: {$student->school} ({$student->major})\n"
                    . "├ Chat ID: <code>{$student->telegram_chat_id}</code>\n"
                    . "├ Sesi Ujian: <b>{$session->title}</b>\n"
                    . "├ Nilai Akhir: <b>{$score}/100</b> ({$totalCorrect} Benar, {$totalWrong} Salah)\n"
                    . "├ Standar Kelulusan (KKM): <b>{$passingGrade} Poin</b>\n"
                    . "├ Status: <b>{$statusBadge}</b>\n"
                    . "└ Waktu: " . now()->format('d/m/Y H:i:s') . " WIB\n\n"
                    . "📄 <i>Berkas PDF evaluasi dan pembahasan soal lengkap siswa terlampir.</i>";

                $this->sendMessage($groupChatId, $groupReport, 'HTML', null, $groupThreadId);

                if ($pdfPath && file_exists($pdfPath)) {
                    $docCaption = "📄 Berkas Evaluasi & Pembahasan Ujian PKL — {$student->name} ({$score}/100 - {$statusBadge})";
                    $this->sendDocument($groupChatId, $pdfPath, $docCaption, $groupThreadId);
                }
            } catch (\Throwable $e) {
                Log::warning("[PklExamBot] Group notification failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Send Student History & Results
     */
    public function sendStudentResults(PklStudent $student): void
    {
        $attempts = PklExamAttempt::with('session')
            ->where('student_id', $student->id)
            ->whereIn('status', ['completed', 'expired'])
            ->latest('id')
            ->take(5)
            ->get();

        if ($attempts->isEmpty()) {
            $text = "📋 <b>Riwayat Ujian — {$student->name}</b>\n\n"
                . "<i>Anda belum memiliki riwayat ujian yang telah selesai.</i>";
        } else {
            $text = "📋 <b>RIWAYAT NILAI UJIAN PKL</b>\n"
                . "👤 <b>{$student->name}</b> ({$student->school})\n"
                . "════════════════════════\n\n";

            foreach ($attempts as $idx => $att) {
                $sessionTitle = $att->session?->title ?? 'Sesi Ujian';
                $passingGrade = $att->session?->passing_grade ?? 75;
                $passed = $att->score >= $passingGrade;
                $status = $passed ? "LULUS" : "REMEDIAL";
                $date = $att->completed_at ? $att->completed_at->format('d/m/Y H:i') : '-';

                $text .= "<b>" . ($idx + 1) . ". {$sessionTitle}</b>\n"
                    . "├ Nilai: <b>{$att->score} / 100</b>\n"
                    . "├ Benar: {$att->total_correct} | Salah: {$att->total_wrong}\n"
                    . "├ Status: <b>{$status}</b> (KKM: {$passingGrade})\n"
                    . "└ Tanggal: {$date}\n\n";
            }
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 Kembali ke Menu Utama', 'callback_data' => 'exam_menu'],
                ]
            ]
        ];

        $this->sendMessage($student->telegram_chat_id, $text, 'HTML', $keyboard);
    }

    /**
     * Send Help message
     */
    public function sendHelpMessage(PklStudent $student): void
    {
        $text = "ℹ️ <b>BANTUAN UJIAN PKL — NODERA</b>\n\n"
            . "Berikut adalah panduan mengerjakan ujian:\n"
            . "1. Pastikan koneksi internet Anda stabil.\n"
            . "2. Tekan tombol /start atau /ujian untuk membuka portal.\n"
            . "3. Klik 'Mulai Kerjakan Ujian' ketika Anda sudah siap.\n"
            . "4. Pilih jawaban A, B, C, atau D pada setiap pertanyaan.\n"
            . "5. Perhatikan sisa waktu yang tertera pada bagian atas soal.\n"
            . "6. Setelah selesai, nilai akhir akan langsung keluar otomatis.\n\n"
            . "Jika ada pertanyaan atau kendala teknis, silakan hubungi Pembimbing PKL Anda.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'exam_menu'],
                ]
            ]
        ];

        $this->sendMessage($student->telegram_chat_id, $text, 'HTML', $keyboard);
    }

    /**
     * Generate Result & Review PDF Document using Dompdf
     */
    public function generateResultPdf(PklExamAttempt $attempt): string
    {
        $attempt->loadMissing(['student', 'session']);
        $student = $attempt->student;
        $session = $attempt->session;

        $questionIds = $attempt->question_ids ?? [];
        $answers = $attempt->answers ?? [];

        $questions = PklQuestion::whereIn('id', $questionIds)->get()->keyBy('id');

        $reviewItems = [];
        foreach ($questionIds as $qId) {
            $q = $questions->get($qId);
            if (!$q) continue;

            $userAns = strtolower($answers[$qId] ?? '');
            $correctAns = strtolower($q->correct_answer ?? '');
            $isCorrect = ($userAns !== '' && $userAns === $correctAns);

            $reviewItems[] = [
                'id'             => $q->id,
                'category'       => $q->category,
                'question'       => $q->question_text,
                'options'        => [
                    'a' => $q->option_a,
                    'b' => $q->option_b,
                    'c' => $q->option_c,
                    'd' => $q->option_d,
                ],
                'user_answer'    => $userAns,
                'correct_answer' => $correctAns,
                'is_correct'     => $isCorrect,
                'explanation'    => $q->explanation ?? '',
            ];
        }

        $html = view('pkl-exam.result-pdf', [
            'attempt'     => $attempt,
            'student'     => $student,
            'session'     => $session,
            'reviewItems' => $reviewItems,
        ])->render();

        $dompdf = new \Dompdf\Dompdf([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dir = storage_path('app/public/pkl-exam-results');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $student->name ?? 'student');
        $fileName = "Hasil_Evaluasi_PKL_{$safeName}_#{$attempt->id}.pdf";
        $filePath = "{$dir}/{$fileName}";
        file_put_contents($filePath, $dompdf->output());

        return $filePath;
    }

    /**
     * Send Document attachment to Telegram Chat
     */
    public function sendDocument(string|int $chatId, string $filePath, ?string $caption = null, ?int $messageThreadId = null): array
    {
        if (!$this->isConfigured() || !file_exists($filePath)) {
            return ['ok' => false, 'description' => 'Bot not configured or file not found'];
        }

        try {
            $token = $this->token;
            $fileName = basename($filePath);

            $data = [
                'chat_id'    => (string) $chatId,
                'caption'    => $caption ?? '',
                'parse_mode' => 'HTML',
            ];
            if ($messageThreadId) {
                $data['message_thread_id'] = $messageThreadId;
            }

            $response = Http::attach(
                'document',
                file_get_contents($filePath),
                $fileName
            )->post("https://api.telegram.org/bot{$token}/sendDocument", $data);

            return $response->json() ?? ['ok' => false];
        } catch (\Throwable $e) {
            Log::error("[PklExamBot] sendDocument failed: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Broadcast Session Activation to all active PKL Students
     */
    public function broadcastSessionOpen(PklExamSession $session): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bot Token belum dikonfigurasi.'];
        }

        $students = PklStudent::where('is_active', true)->get();
        $sentCount = 0;
        $failCount = 0;

        $msg = "📢 <b>PENGUMUMAN: SESI UJIAN PKL RESMI DIBUKA!</b>\n\n"
            . "Halo Rekan Siswa PKL SMK TKJ,\n"
            . "Superadmin telah membuka sesi ujian kompetensi berikut:\n\n"
            . "════════════════════════\n"
            . "📝 <b>Judul Ujian:</b> {$session->title}\n"
            . "⏱️ <b>Durasi Waktu:</b> {$session->duration_minutes} Menit\n"
            . "📊 <b>Jumlah Soal:</b> {$session->question_count} Pilihan Ganda\n"
            . "🎯 <b>Passing Grade (KKM):</b> {$session->passing_grade} Poin\n"
            . "════════════════════════\n\n"
            . "🚀 <i>Silakan klik tombol di bawah untuk langsung membuka portal ujian dan memulai pengerjaan.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🚀 Mulai Kerjakan Ujian Sekarang', 'callback_data' => "exam_start:{$session->id}"],
                ]
            ]
        ];

        foreach ($students as $st) {
            $res = $this->sendMessage($st->telegram_chat_id, $msg, 'HTML', $keyboard);
            if ($res['ok'] ?? false) {
                $sentCount++;
            } else {
                $failCount++;
            }
        }

        return [
            'ok'     => true,
            'sent'   => $sentCount,
            'failed' => $failCount,
            'total'  => $students->count(),
        ];
    }
}
