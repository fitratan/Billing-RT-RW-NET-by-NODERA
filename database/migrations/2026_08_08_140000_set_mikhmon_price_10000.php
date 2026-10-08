<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Harga Mikhmon berubah dari 15.000 menjadi 10.000 per bulan.
        // Samakan harga langganan yang sudah tercatat agar tagihan konsisten.
        DB::table('mikhmon_subscriptions')->update(['price' => 10000]);
    }

    public function down(): void
    {
        DB::table('mikhmon_subscriptions')->update(['price' => 15000]);
    }
};
