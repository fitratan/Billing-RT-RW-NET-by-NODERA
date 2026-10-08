<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotiks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(8728);
            $table->string('username');
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('api_mode')->default('api');
            $table->string('default_profile')->nullable();
            $table->string('default_isolir_profile')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotiks');
    }
};
