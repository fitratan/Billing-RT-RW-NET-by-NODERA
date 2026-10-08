<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('attendance', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('attendance', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable(false)->change();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
