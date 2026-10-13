<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('nodera_pay_merchants')) {
            try {
                $merchants = DB::table('nodera_pay_merchants')
                    ->where('email', 'merchant@nodera.id')
                    ->orWhere('email', 'like', 'merchant@nodera.id%')
                    ->get();

                foreach ($merchants as $m) {
                    $cleanCode = strtolower(preg_replace('/[^a-z0-9]/i', '', $m->merchant_code ?: ('np' . $m->id)));
                    $newEmail = "merchant_{$cleanCode}@gateway.nodera.id";
                    DB::table('nodera_pay_merchants')
                        ->where('id', $m->id)
                        ->update(['email' => $newEmail]);
                }
            } catch (\Throwable $e) {
                // Non-blocking fallback
            }
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
