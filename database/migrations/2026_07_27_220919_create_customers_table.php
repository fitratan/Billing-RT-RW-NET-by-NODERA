<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('pppoe_username', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 10, 8)->nullable();
            $table->integer('isolation_date')->default(20);
            $table->string('whatsapp_lid', 50)->nullable();
            $table->string('portal_password', 255)->nullable();
            $table->enum('status', ['active','inactive','isolated','paid','unpaid'])->default('inactive');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
