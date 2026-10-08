<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportCi4Data extends Command
{
    protected $signature = 'gembok:import {host?} {database?} {username?} {password?}';
    protected $description = 'Import data from existing CI4 database to Laravel';

    public function handle()
    {
        $host = $this->argument('host') ?? env('CI4_DB_HOST', 'localhost');
        $db = $this->argument('database') ?? env('CI4_DB_DATABASE', 'nodera_db');
        $user = $this->argument('username') ?? env('CI4_DB_USERNAME', 'root');
        $pass = $this->argument('password') ?? env('CI4_DB_PASSWORD', '');

        $this->info("Connecting to CI4 database: $host / $db");

        config(['database.connections.ci4' => [
            'driver' => 'mysql',
            'host' => $host,
            'database' => $db,
            'username' => $user,
            'password' => $pass,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        $tables = [
            'users' => ['id', 'username', 'password', 'name', 'email', 'phone', 'role', 'is_active', 'last_login', 'created_at', 'updated_at'],
            'customers' => ['id', 'name', 'pppoe_username', 'phone', 'email', 'address', 'package_id', 'lat', 'lng', 'isolation_date', 'status', 'created_at', 'updated_at'],
            'packages' => ['id', 'name', 'price', 'profile_normal', 'profile_isolir', 'description', 'created_at', 'updated_at'],
            'invoices' => ['id', 'customer_id', 'amount', 'description', 'due_date', 'paid', 'status', 'paid_at', 'payment_method', 'payment_ref', 'created_at', 'updated_at'],
            'payments' => ['id', 'invoice_id', 'amount', 'payment_method', 'payment_reference', 'notes', 'paid_at', 'created_at', 'updated_at'],
            'trouble_tickets' => ['id', 'customer_id', 'customer_name', 'customer_phone', 'description', 'status', 'priority', 'assigned_to', 'notes', 'resolution_notes', 'attachment', 'resolved_at', 'created_at', 'updated_at'],
            'onu_locations' => ['id', 'serial_number', 'name', 'lat', 'lng', 'odp_id', 'customer_id', 'created_at', 'updated_at'],
            'odp_locations' => ['id', 'name', 'lat', 'lng', 'capacity', 'used_ports', 'parent_odp_id', 'created_at', 'updated_at'],
            'settings' => ['key', 'value', 'created_at', 'updated_at'],
        ];

        foreach ($tables as $table => $columns) {
            $this->info("Importing $table...");
            try {
                $rows = DB::connection('ci4')->table($table)->get();
                if ($rows->isEmpty()) {
                    $this->warn("  No data in $table");
                    continue;
                }
                $chunks = $rows->chunk(100);
                foreach ($chunks as $chunk) {
                    DB::table($table)->insert($chunk->map(fn($r) => (array)$r)->toArray());
                }
                $this->info("  ✓ Imported {$rows->count()} rows");
            } catch (\Exception $e) {
                $this->error("  ✗ $table: " . $e->getMessage());
            }
        }

        $this->info('Import selesai!');
    }
}
