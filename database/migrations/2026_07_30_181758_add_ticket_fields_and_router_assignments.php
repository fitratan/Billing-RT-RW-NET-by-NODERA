<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom di trouble_tickets
        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('trouble_tickets', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->after('assigned_to');
            }
            if (!Schema::hasColumn('trouble_tickets', 'router_id')) {
                $table->foreignId('router_id')->nullable()->constrained('mikrotiks')->nullOnDelete()->after('assigned_to');
            }
            if (!Schema::hasColumn('trouble_tickets', 'title')) {
                $table->string('title')->nullable()->after('customer_id');
            }
        });

        // 2. Tambah router_id di collectors
        Schema::table('collectors', function (Blueprint $table) {
            if (!Schema::hasColumn('collectors', 'router_id')) {
                $table->foreignId('router_id')->nullable()->constrained('mikrotiks')->nullOnDelete()->after('tenant_id');
            }
        });

        // 3. Pivot table: teknisi ↔ router
        if (!Schema::hasTable('technician_routers')) {
            Schema::create('technician_routers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('router_id')->constrained('mikrotiks')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'router_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('router_id');
            $table->dropColumn('title');
        });
        Schema::table('collectors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('router_id');
        });
        Schema::dropIfExists('technician_routers');
    }
};
