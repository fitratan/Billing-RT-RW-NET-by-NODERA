<?php

namespace Database\Seeders;

use App\Models\PklExamSession;
use App\Models\PklQuestion;
use Illuminate\Database\Seeder;

class PklQuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        // Load extra 300 questions from JSON if count < 600
        $jsonPath = __DIR__ . '/extra_300_questions.json';
        if (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            if (is_array($data)) {
                foreach ($data as $item) {
                    $exists = PklQuestion::where('question_text', $item['question_text'])->exists();
                    if (!$exists) {
                        PklQuestion::create($item);
                    }
                }
            }
        }

        // Ensure even distribution of correct answers across A, B, C, D
        $questions = PklQuestion::all();
        foreach ($questions as $q) {
            $currentCorrectKey = strtolower(trim($q->correct_answer));
            $optionsMap = [
                "a" => $q->option_a,
                "b" => $q->option_b,
                "c" => $q->option_c,
                "d" => $q->option_d,
            ];

            $correctText = $optionsMap[$currentCorrectKey] ?? $optionsMap["a"];

            $optValues = array_values($optionsMap);
            shuffle($optValues);

            $newCorrectKey = "a";
            $keys = ["a", "b", "c", "d"];
            foreach ($keys as $idx => $k) {
                if ($optValues[$idx] === $correctText) {
                    $newCorrectKey = $k;
                    break;
                }
            }

            $q->option_a = $optValues[0];
            $q->option_b = $optValues[1];
            $q->option_c = $optValues[2];
            $q->option_d = $optValues[3];
            $q->correct_answer = $newCorrectKey;
            $q->save();
        }

        // Create default active Exam Session
        PklExamSession::updateOrCreate(
            ['title' => 'Ujian Kompetensi PKL — SMK TKJ Kelas 3'],
            [
                'description' => 'Evaluasi kompetensi praktik kerja lapangan kelas XII SMK TKJ materi Fiber Optic (FTTH), Linux Server Administration, Web Services, dan 7 Layer OSI Networking.',
                'duration_minutes' => 30,
                'passing_grade' => 75,
                'question_count' => 25,
                'categories' => ['fiber_optic', 'linux', 'website', 'osi_layer'],
                'status' => 'active',
                'allow_retake' => false,
                'max_retakes' => 1,
            ]
        );
    }
}
