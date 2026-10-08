<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isp_license_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('STANDALONE_ISP'); // STANDALONE_ISP, FULL_SOURCE_CODE, ENTERPRISE_ISP
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('max_routers')->default(0); // 0 = unlimited
            $table->unsignedInteger('max_customers')->default(0); // 0 = unlimited
            $table->boolean('has_source_code')->default(false); // true jika client dapat download full source code
            $table->text('source_code_url')->nullable(); // URL download ZIP / Git repo
            $table->json('features')->nullable();
            $table->string('badge_text')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_license_packages');
    }
};
