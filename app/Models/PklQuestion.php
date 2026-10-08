<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PklQuestion extends Model
{
    use HasFactory;

    protected $table = 'pkl_questions';

    protected $fillable = [
        'category',
        'difficulty',
        'question_text',
        'image_url',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'explanation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function categoryLabels(): array
    {
        return [
            'fiber_optic' => 'Fiber Optic & FTTH',
            'linux'       => 'Linux Server & Sysadmin',
            'website'     => 'Website & Web Services',
            'osi_layer'   => 'Layer OSI & Jaringan Dasar',
        ];
    }
}
