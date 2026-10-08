<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PklExamAttempt extends Model
{
    use HasFactory;

    protected $table = 'pkl_exam_attempts';

    protected $fillable = [
        'session_id',
        'student_id',
        'telegram_chat_id',
        'attempt_number',
        'question_ids',
        'current_index',
        'answers',
        'score',
        'total_correct',
        'total_wrong',
        'total_questions',
        'status',
        'started_at',
        'completed_at',
        'expires_at',
        'last_message_id',
    ];

    protected $casts = [
        'question_ids'    => 'array',
        'answers'         => 'array',
        'score'           => 'decimal:2',
        'total_correct'   => 'integer',
        'total_wrong'     => 'integer',
        'total_questions' => 'integer',
        'current_index'   => 'integer',
        'attempt_number'  => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'expires_at'      => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PklExamSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PklStudent::class, 'student_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expires_at && now()->gt($this->expires_at));
    }
}
