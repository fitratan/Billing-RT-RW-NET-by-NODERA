<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->text('duration_options')->nullable()->after('annual_price');
        });
    }
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('duration_options');
        });
    }
};
