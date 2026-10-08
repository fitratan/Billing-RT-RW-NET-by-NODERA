<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dashboard_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('menu_key');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'menu_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('dashboard_menu_settings'); }
};
