<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix: kolom lat/lng di semua tabel lokasi diubah ke decimal(12,8)
 *
 * Root cause: decimal(10,8) hanya bisa menyimpan hingga 99.99999999
 * Longitude Indonesia berkisar 95°–141°E, sehingga nilai >= 100 di-clamp
 * oleh MySQL menjadi 99.99999999 tanpa error (non-strict mode).
 *
 * decimal(12,8) → 4 digit sebelum koma → cover ±9999.99999999
 * Cukup untuk semua koordinat valid di Bumi (-180 s/d +180 lon, -90 s/d +90 lat).
 */
return new class extends Migration
{
    public function up(): void
    {
        // customers
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('lat', 12, 8)->nullable()->change();
            $table->decimal('lng', 12, 8)->nullable()->change();
        });

        // odp_locations
        if (Schema::hasTable('odp_locations')) {
            Schema::table('odp_locations', function (Blueprint $table) {
                if (Schema::hasColumn('odp_locations', 'lat')) {
                    $table->decimal('lat', 12, 8)->nullable()->change();
                }
                if (Schema::hasColumn('odp_locations', 'lng')) {
                    $table->decimal('lng', 12, 8)->nullable()->change();
                }
            });
        }

        // onu_locations
        if (Schema::hasTable('onu_locations')) {
            Schema::table('onu_locations', function (Blueprint $table) {
                if (Schema::hasColumn('onu_locations', 'lat')) {
                    $table->decimal('lat', 12, 8)->nullable()->change();
                }
                if (Schema::hasColumn('onu_locations', 'lng')) {
                    $table->decimal('lng', 12, 8)->nullable()->change();
                }
            });
        }

        // mikrotiks (router lat/lng)
        if (Schema::hasTable('mikrotiks')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                if (Schema::hasColumn('mikrotiks', 'lat')) {
                    $table->decimal('lat', 12, 8)->nullable()->change();
                }
                if (Schema::hasColumn('mikrotiks', 'lng')) {
                    $table->decimal('lng', 12, 8)->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Rollback ke decimal(11,8) — sudah cukup untuk koordinat valid
        // tapi kita tidak rollback ke 10,8 karena itu yang menyebabkan bug
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('lat', 11, 8)->nullable()->change();
            $table->decimal('lng', 11, 8)->nullable()->change();
        });

        if (Schema::hasTable('odp_locations')) {
            Schema::table('odp_locations', function (Blueprint $table) {
                if (Schema::hasColumn('odp_locations', 'lat')) {
                    $table->decimal('lat', 11, 8)->nullable()->change();
                }
                if (Schema::hasColumn('odp_locations', 'lng')) {
                    $table->decimal('lng', 11, 8)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('onu_locations')) {
            Schema::table('onu_locations', function (Blueprint $table) {
                if (Schema::hasColumn('onu_locations', 'lat')) {
                    $table->decimal('lat', 11, 8)->nullable()->change();
                }
                if (Schema::hasColumn('onu_locations', 'lng')) {
                    $table->decimal('lng', 11, 8)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('mikrotiks')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                if (Schema::hasColumn('mikrotiks', 'lat')) {
                    $table->decimal('lat', 11, 8)->nullable()->change();
                }
                if (Schema::hasColumn('mikrotiks', 'lng')) {
                    $table->decimal('lng', 11, 8)->nullable()->change();
                }
            });
        }
    }
};
