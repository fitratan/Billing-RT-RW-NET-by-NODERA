<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PklStudent extends Model
{
    use HasFactory;

    protected $table = 'pkl_students';

    protected $fillable = [
        'name',
        'school',
        'major',
        'telegram_chat_id',
        'telegram_username',
        'phone',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(PklExamAttempt::class, 'student_id');
    }
}
