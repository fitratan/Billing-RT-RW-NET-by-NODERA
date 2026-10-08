<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Standard FreeRADIUS v3 MySQL / MariaDB schema with multi-tenant support.
     */
    public function up(): void
    {
        // 1. radcheck — User authentication check attributes (Cleartext-Password, etc.)
        if (!Schema::hasTable('radcheck')) {
            Schema::create('radcheck', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('username');
            });
        }

        // 2. radreply — User reply attributes (Framed-IP-Address, etc.)
        if (!Schema::hasTable('radreply')) {
            Schema::create('radreply', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('username');
            });
        }

        // 3. radgroupcheck — Group check attributes
        if (!Schema::hasTable('radgroupcheck')) {
            Schema::create('radgroupcheck', function (Blueprint $table) {
                $table->increments('id');
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('groupname');
            });
        }

        // 4. radgroupreply — Group reply attributes (Mikrotik-Rate-Limit, Session-Timeout, etc.)
        if (!Schema::hasTable('radgroupreply')) {
            Schema::create('radgroupreply', function (Blueprint $table) {
                $table->increments('id');
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('groupname');
            });
        }

        // 5. radusergroup — User to group mappings
        if (!Schema::hasTable('radusergroup')) {
            Schema::create('radusergroup', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('groupname', 64)->default('');
                $table->integer('priority')->default(1);
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('username');
            });
        }

        // 6. radacct — RADIUS accounting records
        if (!Schema::hasTable('radacct')) {
            Schema::create('radacct', function (Blueprint $table) {
                $table->bigIncrements('radacctid');
                $table->string('acctsessionid', 64)->default('');
                $table->string('acctuniqueid', 32)->default('');
                $table->string('username', 64)->default('');
                $table->string('groupname', 64)->default('');
                $table->string('realm', 64)->nullable()->default('');
                $table->string('nasipaddress', 45)->default('');
                $table->string('nasportid', 32)->nullable();
                $table->string('nasporttype', 32)->nullable();
                $table->dateTime('acctstarttime')->nullable()->index();
                $table->dateTime('acctupdatetime')->nullable();
                $table->dateTime('acctstoptime')->nullable()->index();
                $table->integer('acctinterval')->nullable();
                $table->unsignedInteger('acctsessiontime')->nullable();
                $table->string('acctauthentic', 32)->nullable();
                $table->string('connectinfo_start', 50)->nullable();
                $table->string('connectinfo_stop', 50)->nullable();
                $table->unsignedBigInteger('acctinputoctets')->nullable()->default(0);
                $table->unsignedBigInteger('acctoutputoctets')->nullable()->default(0);
                $table->string('calledstationid', 50)->nullable();
                $table->string('callingstationid', 50)->nullable();
                $table->string('acctterminatecause', 32)->nullable();
                $table->string('servicetype', 32)->nullable();
                $table->string('framedprotocol', 32)->nullable();
                $table->string('framedipaddress', 45)->nullable()->index();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('username');
            });
        }

        // 7. nas — Network Access Server (MikroTik / OLT / Cisco routers)
        if (!Schema::hasTable('nas')) {
            Schema::create('nas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nasname', 128)->index();
                $table->string('shortname', 32)->nullable();
                $table->string('type', 30)->default('other');
                $table->integer('ports')->nullable();
                $table->string('secret', 60)->default('secret');
                $table->string('server', 64)->nullable();
                $table->string('community', 50)->nullable();
                $table->string('description', 200)->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }

        // 8. radpostauth — Authentication logs
        if (!Schema::hasTable('radpostauth')) {
            Schema::create('radpostauth', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('pass', 64)->default('');
                $table->string('reply', 32)->default('');
                $table->timestamp('authdate')->useCurrent();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->index('username');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radpostauth');
        Schema::dropIfExists('nas');
        Schema::dropIfExists('radacct');
        Schema::dropIfExists('radusergroup');
        Schema::dropIfExists('radgroupreply');
        Schema::dropIfExists('radgroupcheck');
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radcheck');
    }
};
