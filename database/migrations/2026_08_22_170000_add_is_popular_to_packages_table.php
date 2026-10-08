<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'is_popular')) {
                $table->boolean('is_popular')->default(false)->after('description');
            }
        });

        // Set default popular package (Paket Business / ID 2)
        DB::table('packages')->where('id', 2)->update(['is_popular' => true]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'is_popular')) {
                $table->dropColumn('is_popular');
            }
        });
    }
};
