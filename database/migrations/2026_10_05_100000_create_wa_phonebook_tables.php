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
        // 1. WhatsApp Groups (Synced from connected devices)
        if (!Schema::hasTable('wa_groups')) {
            Schema::create('wa_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('wa_merchants')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained('whatsapp_devices')->nullOnDelete();
                $table->string('group_jid')->index(); // e.g. 120363407267626173@g.us
                $table->string('name');
                $table->unsignedInteger('participants_count')->default(0);
                $table->string('owner_jid')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('creation_time')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['merchant_id', 'group_jid']);
                $table->index(['merchant_id', 'device_id']);
            });
        }

        // 2. Contact Groups (Merchant categories / labels)
        if (!Schema::hasTable('wa_contact_groups')) {
            Schema::create('wa_contact_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('wa_merchants')->cascadeOnDelete();
                $table->string('name');
                $table->string('color', 20)->default('#3B82F6');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['merchant_id']);
            });
        }

        // 3. Contacts (Phonebook entries)
        if (!Schema::hasTable('wa_contacts')) {
            Schema::create('wa_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('wa_merchants')->cascadeOnDelete();
                $table->foreignId('group_id')->nullable()->constrained('wa_contact_groups')->nullOnDelete();
                $table->string('name');
                $table->string('phone')->index(); // Normalized phone e.g. 628123456789
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();

                $table->index(['merchant_id', 'phone']);
                $table->index(['merchant_id', 'group_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_contacts');
        Schema::dropIfExists('wa_contact_groups');
        Schema::dropIfExists('wa_groups');
    }
};
