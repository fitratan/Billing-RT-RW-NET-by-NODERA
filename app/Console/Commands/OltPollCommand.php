<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Olt;
use App\Services\OltNmsService;

class OltPollCommand extends Command
{
    protected $signature = 'olt:poll {id? : ID OLT yang ingin di-poll (kosongkan untuk semua OLT aktif)}';
    protected $description = 'Jalankan polling dan sinkronisasi data OLT & ONU ke database';

    public function handle(OltNmsService $nms): int
    {
        $id = $this->argument('id');
        $olts = $id ? Olt::where('id', $id)->get() : Olt::where('is_active', true)->get();

        if ($olts->isEmpty()) {
            $this->warn("Tidak ada OLT yang ditemukan untuk di-poll.");
            return 0;
        }

        foreach ($olts as $olt) {
            $this->info("Memproses OLT #{$olt->id}: {$olt->name} ({$olt->host})...");
            $res = $nms->pollOlt($olt);
            if ($res['success']) {
                $this->info("  ✅ Sukses! Ditemukan {$res['count']} ONU.");
            } else {
                $this->error("  ❌ Gagal: " . ($res['message'] ?? 'Unknown error'));
            }
        }

        return 0;
    }
}
