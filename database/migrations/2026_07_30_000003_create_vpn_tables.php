<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables if exist
        Schema::dropIfExists('vpn_accounts');
        Schema::dropIfExists('vpn_packages');
        Schema::dropIfExists('vpn_servers');

        Schema::create('vpn_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('host');
            $table->integer('api_port')->default(8728);
            $table->string('api_user');
            $table->string('api_pass');
            $table->string('server_ip')->nullable()->comment('IP lokal router untuk NAT dst-address');
            $table->string('server_domain')->nullable()->comment('Domain publik ditampilkan ke member');
            $table->string('profile_remot')->default('VPN_REMOT');
            $table->string('profile_dedicated')->default('VPN_DEDICATED');
            $table->string('ip_pool_remot_start')->nullable();
            $table->string('ip_pool_remot_end')->nullable();
            $table->string('ip_pool_dedicated_start')->nullable();
            $table->string('ip_pool_dedicated_end')->nullable();
            $table->integer('nat_port_start')->default(50000);
            $table->integer('nat_port_end')->default(60000);
            $table->boolean('require_approval')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vpn_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('vpn_servers')->cascadeOnDelete();
            $table->string('vpn_username')->unique();
            $table->string('vpn_password');
            $table->string('protocol')->default('pptp');
            $table->enum('type', ['REMOT', 'DEDICATED'])->default('REMOT');
            $table->string('package')->default('TRIAL');
            $table->string('ip_static')->nullable();
            $table->json('ports')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['PENDING_APPROVAL','ACTIVE','DISABLED','EXPIRED'])->default('PENDING_APPROVAL');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_accounts');
        Schema::dropIfExists('vpn_servers');
    }
};
