<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('lat', 11, 8)->nullable()->change();
            $table->decimal('lng', 11, 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->change();
            $table->decimal('lng', 10, 8)->nullable()->change();
        });
    }
};
