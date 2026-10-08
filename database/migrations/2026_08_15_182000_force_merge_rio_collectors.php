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
        if (! Schema::hasTable('invoices')) {
            return;
        }

        // 1. BERSIHKAN: Invoice yang BELUM LUNAS / PENDING tidak boleh memiliki processed_by!
        DB::table('invoices')
            ->where(function ($q) {
                $q->where('paid', 0)
                  ->orWhere('paid', false)
                  ->orWhere('status', '!=', 'paid')
                  ->orWhereNull('status');
            })
            ->update(['processed_by' => null]);

        if (! Schema::hasTable('collectors')) {
            return;
        }

        // 2. Untuk invoice yang SUDAH LUNAS (paid = 1):
        // Satukan variasi nama "rio", "rioceleng", "Rio Celeng" ke akun kolektor aktif
        $rioCeleng = DB::table('collectors')
            ->where(function ($q) {
                $q->whereRaw("LOWER(name) LIKE '%celeng%'")
                  ->orWhereRaw("LOWER(username) LIKE '%celeng%'");
            })
            ->orderBy('id', 'desc')
            ->first();

        if ($rioCeleng) {
            DB::table('invoices')
                ->where('paid', 1)
                ->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(processed_by)) = 'rio'")
                      ->orWhereRaw("LOWER(TRIM(processed_by)) LIKE 'rio %'")
                      ->orWhereRaw("LOWER(TRIM(processed_by)) LIKE '%rioceleng%'");
                })
                ->where(function ($tq) use ($rioCeleng) {
                    if ($rioCeleng->tenant_id) {
                        $tq->where('tenant_id', $rioCeleng->tenant_id)->orWhereNull('tenant_id');
                    }
                })
                ->update([
                    'processed_by' => $rioCeleng->name,
                    'collector_id' => $rioCeleng->id,
                ]);

            // Hapus duplikat kolektor lama bernama 'rio' jika ada
            DB::table('collectors')
                ->where('id', '!=', $rioCeleng->id)
                ->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(name)) = 'rio'")
                      ->orWhereRaw("LOWER(TRIM(username)) = 'rio'");
                })
                ->where('tenant_id', $rioCeleng->tenant_id)
                ->delete();
        }

        // 3. Sinkronkan semua invoice lunas lainnya sesuai kolektor aktif
        $collectors = DB::table('collectors')->get();
        foreach ($collectors as $collector) {
            DB::table('invoices')
                ->where('paid', 1)
                ->where('collector_id', $collector->id)
                ->update(['processed_by' => $collector->name]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
