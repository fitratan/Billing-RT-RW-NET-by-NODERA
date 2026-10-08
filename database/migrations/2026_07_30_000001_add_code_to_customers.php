<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        // Generate random 8-digit codes for existing customers
        $customers = DB::table('customers')->whereNull('code')->get();
        foreach ($customers as $c) {
            do {
                $code = (string) random_int(10000000, 99999999);
            } while (DB::table('customers')->where('code', $code)->where('id', '!=', $c->id)->exists());
            DB::table('customers')->where('id', $c->id)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
