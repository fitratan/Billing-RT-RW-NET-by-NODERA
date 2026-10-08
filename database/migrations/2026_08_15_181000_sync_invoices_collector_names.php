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
        if (Schema::hasTable('invoices') && Schema::hasTable('collectors')) {
            $collectors = DB::table('collectors')->select('id', 'name', 'username', 'tenant_id')->get();

            foreach ($collectors as $collector) {
                $canonicalName = $collector->name;
                $cleanName = strtolower(trim($collector->name));
                $cleanUsername = strtolower(trim($collector->username ?? ''));
                $noSpaceName = str_replace(' ', '', $cleanName);
                $noSpaceUser = str_replace(' ', '', $cleanUsername);

                // 1. Update by explicit collector_id
                DB::table('invoices')
                    ->where('collector_id', $collector->id)
                    ->update(['processed_by' => $canonicalName]);

                // 2. Update by alias / username / old name match
                $query = DB::table('invoices')
                    ->where(function ($q) use ($collector, $cleanName, $cleanUsername, $noSpaceName, $noSpaceUser) {
                        $q->where('collector_id', $collector->id)
                          ->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanName]);

                        if ($cleanUsername !== '') {
                            $q->orWhereRaw('LOWER(TRIM(processed_by)) = ?', [$cleanUsername]);
                        }
                        if ($noSpaceName !== '') {
                            $q->orWhereRaw("REPLACE(LOWER(TRIM(processed_by)), ' ', '') = ?", [$noSpaceName]);
                        }
                        if ($noSpaceUser !== '') {
                            $q->orWhereRaw("REPLACE(LOWER(TRIM(processed_by)), ' ', '') = ?", [$noSpaceUser]);
                        }
                        if (strlen($cleanUsername) >= 3) {
                            $q->orWhereRaw('LOWER(TRIM(processed_by)) LIKE ?', [$cleanUsername . '%']);
                        }
                    });

                if ($collector->tenant_id) {
                    $query->where(function ($tq) use ($collector) {
                        $tq->where('tenant_id', $collector->tenant_id)->orWhereNull('tenant_id');
                    });
                }

                $query->update([
                    'processed_by' => $canonicalName,
                    'collector_id' => $collector->id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse operation needed for retroactive data synchronization
    }
};
