<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vpn_packages')) {
            Schema::create('vpn_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->integer('quota_gb')->default(0);
                $table->integer('duration_days')->default(30);
                $table->decimal('price', 15, 2)->default(0);
                $table->foreignId('server_id')->nullable()->constrained('vpn_servers')->nullOnDelete();
                $table->string('type', 20)->default('REMOT');
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });

            DB::table('vpn_packages')->insert([
                ['name' => 'Remot 1 Bulan', 'duration_days' => 30, 'price' => 2000, 'type' => 'REMOT', 'quota_gb' => 0, 'description' => 'VPN Remote 30 hari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Remot 3 Bulan', 'duration_days' => 90, 'price' => 5000, 'type' => 'REMOT', 'quota_gb' => 0, 'description' => 'VPN Remote 90 hari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Dedicated 1 Bulan', 'duration_days' => 30, 'price' => 5000, 'type' => 'DEDICATED', 'quota_gb' => 0, 'description' => 'VPN Dedicated 30 hari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Dedicated 3 Bulan', 'duration_days' => 90, 'price' => 12000, 'type' => 'DEDICATED', 'quota_gb' => 0, 'description' => 'VPN Dedicated 90 hari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_packages');
    }
};
