<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PklExamSetting extends Model
{
    use HasFactory;

    protected $table = 'pkl_exam_settings';

    protected $fillable = [
        'bot_token',
        'bot_username',
        'webhook_secret',
        'is_active',
        'welcome_message',
        'announcement_chat_id',
        'group_chat_id',
        'group_thread_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getActive(): self
    {
        return static::firstOrCreate([], [
            'is_active' => true,
            'welcome_message' => 'Selamat datang di Bot Ujian Praktik Kerja Lapangan (PKL) NODERA.',
        ]);
    }
}
