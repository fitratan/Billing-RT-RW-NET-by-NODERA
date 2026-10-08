<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columns already exist from partial run. No-op.
    }

    public function down(): void
    {
        // Columns kept for data safety
    }
};
