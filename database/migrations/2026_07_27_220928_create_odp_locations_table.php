<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odp_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->integer('capacity')->default(8);
            $table->integer('used_ports')->default(0);
            $table->foreignId('parent_odp_id')->nullable()->constrained('odp_locations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odp_locations');
    }
};
