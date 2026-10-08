<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_requests', 'username')) {
                $table->string('username')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('registration_requests', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn(['username', 'password_hash']);
        });
    }
};
