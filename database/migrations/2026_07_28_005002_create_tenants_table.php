<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('max_customers')->default(0)->comment('0=unlimited');
            $table->integer('max_routers')->default(0)->comment('0=unlimited');
            $table->text('settings')->nullable();
            $table->string('db_host')->nullable();
            $table->string('db_name')->nullable();
            $table->timestamps();
        });

        // Add tenant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('mikrotiks', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('trouble_tickets', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('settings', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('payments', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('packages', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('invoices', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('customers', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('users', fn(Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenants');
    }
};
