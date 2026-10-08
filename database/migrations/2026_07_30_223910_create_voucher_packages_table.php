<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('voucher_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('duration_days')->default(30);
            $table->integer('bandwidth_up')->nullable();
            $table->integer('bandwidth_down')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('voucher_packages'); }
};
