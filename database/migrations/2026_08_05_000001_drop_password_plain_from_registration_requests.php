<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            if (Schema::hasColumn('registration_requests', 'password_plain')) {
                $table->dropColumn('password_plain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('password_plain')->nullable()->after('password_hash');
        });
    }
};