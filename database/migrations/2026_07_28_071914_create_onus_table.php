<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number');
            $table->foreignId('olt_id')->constrained()->onDelete('cascade');
            $table->string('pon_port')->nullable();
            $table->integer('onu_index')->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status')->default('offline')->comment('online, offline');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
