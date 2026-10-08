<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PklExamSession extends Model
{
    use HasFactory;

    protected $table = 'pkl_exam_sessions';

    protected $fillable = [
        'title',
        'description',
        'duration_minutes',
        'passing_grade',
        'question_count',
        'categories',
        'status',
        'scheduled_at',
        'is_auto_broadcast',
        'started_at',
        'expires_at',
        'allow_retake',
        'max_retakes',
    ];

    protected $casts = [
        'categories'        => 'array',
        'allow_retake'      => 'boolean',
        'is_auto_broadcast' => 'boolean',
        'duration_minutes'  => 'integer',
        'passing_grade'     => 'integer',
        'question_count'    => 'integer',
        'max_retakes'       => 'integer',
        'scheduled_at'      => 'datetime',
        'started_at'        => 'datetime',
        'expires_at'        => 'datetime',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(PklExamAttempt::class, 'session_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
