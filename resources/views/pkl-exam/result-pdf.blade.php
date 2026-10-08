<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Evaluasi Ujian PKL — {{ $student->name }}</title>
    <style>
        @page {
            margin: 25px 30px 30px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-logo {
            font-size: 18px;
            font-weight: bold;
            color: #0369a1;
            letter-spacing: 0.5px;
        }
        .header-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .header-badge {
            text-align: right;
        }
        .badge-official {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .info-table td {
            padding: 5px 8px;
            font-size: 10.5px;
        }
        .info-label {
            color: #64748b;
            width: 16%;
            font-weight: bold;
        }
        .info-val {
            color: #0f172a;
            font-weight: 500;
            width: 34%;
        }
        .score-summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .score-card {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .score-card.failed {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        .score-number {
            font-size: 26px;
            font-weight: bold;
            color: #15803d;
            margin: 2px 0;
        }
        .score-number.failed {
            color: #b91c1c;
        }
        .score-status {
            display: inline-block;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 4px;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-passed {
            background-color: #16a34a;
        }
        .status-failed {
            background-color: #dc2626;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .question-box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            margin-bottom: 10px;
            page-break-inside: avoid;
            background-color: #ffffff;
        }
        .question-header {
            background-color: #f1f5f9;
            padding: 5px 8px;
            font-size: 10.5px;
            font-weight: bold;
            border-bottom: 1px solid #e2e8f0;
        }
        .category-tag {
            float: right;
            font-size: 8.5px;
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .question-body {
            padding: 8px;
        }
        .question-text {
            font-size: 11px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .options-list {
            margin-left: 4px;
            margin-bottom: 6px;
        }
        .option-item {
            font-size: 10px;
            padding: 2px 0;
            color: #334155;
        }
        .option-item.selected-correct {
            color: #15803d;
            font-weight: bold;
        }
        .option-item.selected-wrong {
            color: #b91c1c;
            font-weight: bold;
        }
        .answer-evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 9.5px;
        }
        .answer-evaluation-table td {
            padding: 3px 6px;
        }
        .explanation-box {
            background-color: #f8fafc;
            border-left: 3px solid #0284c7;
            padding: 5px 8px;
            margin-top: 6px;
            font-size: 9.5px;
            color: #334155;
            line-height: 1.35;
        }
        .footer-note {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td>
                <div class="header-logo">NODERA PKL EXAMINATION CENTER</div>
                <div class="header-sub">Lembaga Evaluasi & Uji Kompetensi Siswa PKL SMK Teknik Komputer & Jaringan</div>
            </td>
            <td class="header-badge">
                <span class="badge-official">DOKUMEN RESMI HASIL UJIAN</span>
            </td>
        </tr>
    </table>

    <!-- Student & Exam Details -->
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Siswa</td>
            <td class="info-val">: {{ $student->name }}</td>
            <td class="info-label">Sesi Ujian</td>
            <td class="info-val">: {{ $session->title }}</td>
        </tr>
        <tr>
            <td class="info-label">Asal Sekolah</td>
            <td class="info-val">: {{ $student->school ?? 'SMK TKJ' }}</td>
            <td class="info-label">Waktu Pengerjaan</td>
            <td class="info-val">: {{ $attempt->started_at ? $attempt->started_at->format('d/m/Y H:i') : '-' }} s/d {{ $attempt->completed_at ? $attempt->completed_at->format('H:i') : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Jurusan</td>
            <td class="info-val">: {{ $student->major ?? 'Teknik Komputer & Jaringan' }}</td>
            <td class="info-label">Telegram Chat ID</td>
            <td class="info-val">: {{ $student->telegram_chat_id }}</td>
        </tr>
    </table>

    <!-- Score Summary Box -->
    <table class="score-summary">
        <tr>
            <td style="width: 40%; vertical-align: middle;">
                <div class="score-card {{ $attempt->status === 'passed' ? '' : 'failed' }}">
                    <div style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: bold;">Skor Akhir Evaluasi</div>
                    <div class="score-number {{ $attempt->status === 'passed' ? '' : 'failed' }}">{{ number_format($attempt->score, 0) }}<span style="font-size: 14px; font-weight: normal; color: #64748b;"> / 100</span></div>
                    <span class="score-status {{ $attempt->status === 'passed' ? 'status-passed' : 'status-failed' }}">
                        {{ $attempt->status === 'passed' ? '✓ LULUS KOMPETEN' : '✕ BELUM LULUS (REMEDIAL)' }}
                    </span>
                </div>
            </td>
            <td style="width: 60%; padding-left: 12px; vertical-align: middle;">
                <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 4px 0; color: #64748b;">Passing Grade / KKM Minimal</td>
                        <td style="padding: 4px 0; text-align: right; font-weight: bold; color: #0f172a;">{{ $session->passing_grade }} Poin</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 4px 0; color: #64748b;">Jawaban Benar</td>
                        <td style="padding: 4px 0; text-align: right; font-weight: bold; color: #16a34a;">{{ $attempt->total_correct }} Soal</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 4px 0; color: #64748b;">Jawaban Salah / Kurang Tepat</td>
                        <td style="padding: 4px 0; text-align: right; font-weight: bold; color: #dc2626;">{{ $attempt->total_wrong }} Soal</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Total Soal Dikerjakan</td>
                        <td style="padding: 4px 0; text-align: right; font-weight: bold; color: #0f172a;">{{ $attempt->total_questions }} Butir Soal</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Detailed Question Evaluation & Explanations -->
    <div class="section-title">Lembar Evaluasi & Pembahasan Kunci Jawaban</div>

    @foreach($reviewItems as $index => $item)
        @php
            $isCorrect = $item['is_correct'];
            $catLabel = match($item['category']) {
                'fiber_optic' => 'Fiber Optic & FTTH',
                'linux' => 'Linux Sysadmin',
                'website' => 'Web Services',
                'osi_layer' => '7 Layer OSI',
                default => strtoupper($item['category'])
            };
        @endphp
        <div class="question-box">
            <div class="question-header">
                <span>Soal No. {{ $index + 1 }}</span>
                @if($isCorrect)
                    <span style="color: #16a34a; margin-left: 6px;">[✓ Benar]</span>
                @else
                    <span style="color: #dc2626; margin-left: 6px;">[✕ Salah]</span>
                @endif
                <span class="category-tag">{{ $catLabel }}</span>
            </div>
            <div class="question-body">
                <div class="question-text">{{ $item['question'] }}</div>
                
                <div class="options-list">
                    @foreach($item['options'] as $optKey => $optVal)
                        @php
                            $isUserChoice = ($item['user_answer'] === $optKey);
                            $isRealKey = ($item['correct_answer'] === $optKey);
                            $cls = '';
                            if ($isUserChoice && $isCorrect) $cls = 'selected-correct';
                            elseif ($isUserChoice && !$isCorrect) $cls = 'selected-wrong';
                            elseif ($isRealKey && !$isCorrect) $cls = 'selected-correct';
                        @endphp
                        <div class="option-item {{ $cls }}">
                            <strong>{{ strtoupper($optKey) }}.</strong> {{ $optVal }}
                            @if($isUserChoice && $isCorrect)
                                <span style="color: #16a34a;">(Jawaban Kamu - Benar)</span>
                            @elseif($isUserChoice && !$isCorrect)
                                <span style="color: #dc2626;">(Jawaban Kamu - Salah)</span>
                            @elseif($isRealKey && !$isCorrect)
                                <span style="color: #16a34a;">(Kunci Jawaban yang Benar)</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Explanation Box -->
                @if(!empty($item['explanation']))
                    <div class="explanation-box">
                        <strong>💡 Pembahasan Materi:</strong><br>
                        {{ $item['explanation'] }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <!-- Footer Verification -->
    <div class="footer-note">
        Dokumen ini dibuat otomatis oleh NODERA Exam Engine pada {{ now()->translatedFormat('d F Y, H:i:s') }} WIB.<br>
        ID Referensi Attempt: #ATT-{{ $attempt->id }}-{{ strtoupper(substr(md5($attempt->id . $attempt->created_at), 0, 8)) }} | Digunakan sebagai arsip evaluasi dan modul pembelajaran siswa PKL.
    </div>

</body>
</html>
