<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkl_exam_settings', function (Blueprint $table) {
            $table->id();
            $table->string('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('welcome_message')->nullable();
            $table->string('announcement_chat_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pkl_students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('school')->default('SMK');
            $table->string('major')->default('Teknik Komputer & Jaringan (TKJ)');
            $table->string('telegram_chat_id')->unique();
            $table->string('telegram_username')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['telegram_chat_id', 'is_active']);
        });

        Schema::create('pkl_questions', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index(); // fiber_optic, linux, website, osi_layer
            $table->string('difficulty')->default('medium'); // easy, medium, hard
            $table->text('question_text');
            $table->string('image_url')->nullable();
            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');
            $table->char('correct_answer', 1); // 'a', 'b', 'c', 'd'
            $table->text('explanation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('pkl_exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->integer('passing_grade')->default(75);
            $table->integer('question_count')->default(20);
            $table->json('categories')->nullable();
            $table->string('status')->default('active'); // draft, active, closed
            $table->boolean('allow_retake')->default(false);
            $table->integer('max_retakes')->default(1);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('pkl_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('telegram_chat_id')->index();
            $table->integer('attempt_number')->default(1);
            $table->json('question_ids');
            $table->integer('current_index')->default(0);
            $table->json('answers')->nullable();
            $table->decimal('score', 5, 2)->default(0);
            $table->integer('total_correct')->default(0);
            $table->integer('total_wrong')->default(0);
            $table->integer('total_questions')->default(0);
            $table->string('status')->default('in_progress'); // in_progress, completed, expired, abandoned
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('last_message_id')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('pkl_exam_sessions')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('pkl_students')->onDelete('cascade');
            $table->index(['student_id', 'session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkl_exam_attempts');
        Schema::dropIfExists('pkl_exam_sessions');
        Schema::dropIfExists('pkl_questions');
        Schema::dropIfExists('pkl_students');
        Schema::dropIfExists('pkl_exam_settings');
    }
};
