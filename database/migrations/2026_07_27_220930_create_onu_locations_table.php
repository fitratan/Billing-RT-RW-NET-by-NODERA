<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onu_locations', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 100);
            $table->string('name', 150);
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->foreignId('odp_id')->nullable()->constrained('odp_locations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onu_locations');
    }
};
