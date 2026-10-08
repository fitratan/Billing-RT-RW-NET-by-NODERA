<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('registration_requests', fn($t) => $t->string('password_plain')->nullable()->after('password_hash')); }
    public function down(): void { Schema::table('registration_requests', fn($t) => $t->dropColumn('password_plain')); }
};
