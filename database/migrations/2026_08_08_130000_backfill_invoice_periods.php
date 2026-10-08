<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill periode invoice lama yang period-nya NULL (dibuat sebelum
     * kolom period dipakai) — ambil dari created_at. Biar periode tampil
     * (bukan "-") di invoice & kolektor.
     */
    public function up(): void
    {
        $rows = DB::table('invoices')->whereNull('period')->whereNotNull('created_at')->get(['id', 'created_at']);
        foreach ($rows as $r) {
            DB::table('invoices')->where('id', $r->id)->update([
                'period' => date('Y-m', strtotime($r->created_at)),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak ada rollback — data backfill tidak perlu dibatalkan.
    }
};
