<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update wa_merchants credentials prefix
        if (DB::getSchemaBuilder()->hasTable('wa_merchants')) {
            DB::statement("UPDATE wa_merchants SET api_key = CONCAT('dgtl_', SUBSTRING(api_key, 9)) WHERE api_key LIKE 'wa_live_%'");
            DB::statement("UPDATE wa_merchants SET api_key = CONCAT('dgtl_', SUBSTRING(api_key, 4)) WHERE api_key LIKE 'wa_%' AND api_key NOT LIKE 'wa_live_%'");
            DB::statement("UPDATE wa_merchants SET secret_key = CONCAT('dgtl_sec_', SUBSTRING(secret_key, 8)) WHERE secret_key LIKE 'wa_sec_%'");
        }

        // Update whatsapp_devices session_id & api_key prefix
        if (DB::getSchemaBuilder()->hasTable('whatsapp_devices')) {
            DB::statement("UPDATE whatsapp_devices SET session_id = CONCAT('dgtl_', SUBSTRING(session_id, 4)) WHERE session_id LIKE 'wa_%'");
            DB::statement("UPDATE whatsapp_devices SET api_key = CONCAT('dgtl_', SUBSTRING(api_key, 8)) WHERE api_key LIKE 'wa_key_%'");
            DB::statement("UPDATE whatsapp_devices SET api_key = CONCAT('dgtl_', SUBSTRING(api_key, 4)) WHERE api_key LIKE 'wa_%' AND api_key NOT LIKE 'wa_key_%'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
