<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah status SUSPENDED ke enum akun VPN & langganan Mikhmon.
        // `MODIFY COLUMN ... ENUM(...)` hanya sintaks MySQL/MariaDB —
        // SQLite (dan driver lain) tidak memakainya, kolom status cukup varchar.
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE vpn_accounts MODIFY COLUMN status ENUM('PENDING_APPROVAL','ACTIVE','DISABLED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'PENDING_APPROVAL'");
            DB::statement("ALTER TABLE mikhmon_subscriptions MODIFY COLUMN status ENUM('PENDING_APPROVAL','ACTIVE','EXPIRED','DISABLED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE'");
        }

        // Kolom masa tenggang & suspend untuk Mikhmon
        Schema::table('mikhmon_subscriptions', function (Blueprint $table) {
            $table->timestamp('expired_grace_at')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('expired_grace_at');
        });
    }

    public function down(): void
    {
        Schema::table('mikhmon_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['expired_grace_at', 'suspended_at']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE vpn_accounts MODIFY COLUMN status ENUM('PENDING_APPROVAL','ACTIVE','DISABLED','EXPIRED') NOT NULL DEFAULT 'PENDING_APPROVAL'");
            DB::statement("ALTER TABLE mikhmon_subscriptions MODIFY COLUMN status ENUM('PENDING_APPROVAL','ACTIVE','EXPIRED','DISABLED') NOT NULL DEFAULT 'ACTIVE'");
        }
    }
};
