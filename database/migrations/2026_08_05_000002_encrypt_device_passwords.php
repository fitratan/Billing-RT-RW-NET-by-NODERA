<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enkripsi password MikroTik, OLT, dan VPN yang masih plaintext.
        // Nilai yang sudah terenkripsi (diawali `eyJ`) dilewati.
        foreach (DB::table('mikrotiks')->select('id', 'password')->get() as $row) {
            if (!empty($row->password) && !str_starts_with($row->password, 'eyJ')) {
                DB::table('mikrotiks')->where('id', $row->id)->update([
                    'password' => encrypt($row->password),
                ]);
            }
        }

        foreach (DB::table('olts')->select('id', 'password')->get() as $row) {
            if (!empty($row->password) && !str_starts_with($row->password, 'eyJ')) {
                DB::table('olts')->where('id', $row->id)->update([
                    'password' => encrypt($row->password),
                ]);
            }
        }

        foreach (DB::table('vpn_accounts')->select('id', 'vpn_password')->get() as $row) {
            if (!empty($row->vpn_password) && !str_starts_with($row->vpn_password, 'eyJ')) {
                DB::table('vpn_accounts')->where('id', $row->id)->update([
                    'vpn_password' => encrypt($row->vpn_password),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis (data sudah dienkripsi).
    }
};