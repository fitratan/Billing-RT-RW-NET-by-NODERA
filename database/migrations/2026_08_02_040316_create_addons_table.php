<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Nama add-on
            $table->string('slug')->unique();    // Identifier
            $table->text('description')->nullable();
            $table->decimal('price', 12, 0)->default(0);  // Harga per tahun
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->text('config')->nullable();  // JSON config per tenant
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
        Schema::dropIfExists('addons');
    }
};
