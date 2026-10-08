<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->default(0)->after('profile');
            }
            if (!Schema::hasColumn('vouchers', 'time_limit')) {
                $table->string('time_limit', 30)->nullable()->after('price');
            }
            if (!Schema::hasColumn('vouchers', 'data_limit')) {
                $table->string('data_limit', 30)->nullable()->after('time_limit');
            }
            if (!Schema::hasColumn('vouchers', 'comment')) {
                $table->string('comment', 150)->nullable()->after('data_limit');
            }
            if (!Schema::hasColumn('vouchers', 'batch_id')) {
                $table->string('batch_id', 50)->nullable()->after('comment');
            }
            if (!Schema::hasColumn('vouchers', 'router_id')) {
                $table->unsignedBigInteger('router_id')->nullable()->after('batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'time_limit',
                'data_limit',
                'comment',
                'batch_id',
                'router_id',
            ]);
        });
    }
};
