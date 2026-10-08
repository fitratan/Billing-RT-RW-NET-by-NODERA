<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->integer('duration')->default(1)->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
