<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            if (!Schema::hasColumn('mikrotiks', 'lat')) {
                $table->decimal('lat', 10, 6)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('mikrotiks', 'lng')) {
                $table->decimal('lng', 10, 6)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('mikrotiks', 'location')) {
                $table->string('location')->nullable()->after('lng');
            }
        });

        Schema::table('odp_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('odp_locations', 'router_id')) {
                $table->foreignId('router_id')->nullable()->after('parent_odp_id')->constrained('mikrotiks')->nullOnDelete();
            }
            if (!Schema::hasColumn('odp_locations', 'cable_path')) {
                $table->json('cable_path')->nullable()->after('router_id');
            }
            if (!Schema::hasColumn('odp_locations', 'network_mode')) {
                $table->string('network_mode', 20)->default('pon')->after('type'); // 'pon' (ODC/ODP) or 'lan' (HTB/Switch)
            }
        });

        if (Schema::hasTable('olts')) {
            Schema::table('olts', function (Blueprint $table) {
                if (!Schema::hasColumn('olts', 'lat')) {
                    $table->decimal('lat', 10, 6)->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('olts', 'lng')) {
                    $table->decimal('lng', 10, 6)->nullable()->after('lat');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'location']);
        });

        Schema::table('odp_locations', function (Blueprint $table) {
            $table->dropForeign(['router_id']);
            $table->dropColumn(['router_id', 'cable_path', 'network_mode']);
        });

        if (Schema::hasTable('olts')) {
            Schema::table('olts', function (Blueprint $table) {
                $table->dropColumn(['lat', 'lng']);
            });
        }
    }
};
