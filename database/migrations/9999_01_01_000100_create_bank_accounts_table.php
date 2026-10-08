<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('account_name', 200);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('bank_accounts');
    }
};
