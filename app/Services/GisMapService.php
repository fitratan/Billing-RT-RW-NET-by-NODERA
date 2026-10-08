<?php

namespace App\Services;

class GisMapService
{
    /**
     * Build standard GIS map payload for Admin, Technician, and Collector maps.
     * Guaranteed single source of truth across Admin, Collector, and Technician roles.
     */
    public static function getMapData(): array
    {
        try {
            return app(\App\Http\Controllers\AdminController::class)->buildMapPayload();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("GisMapService::getMapData exception: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'routers' => [],
                'odps' => [],
                'onusMarkers' => [],
                'odpMarkers' => [],
                'customerMarkers' => [],
                'allCustomers' => [],
                'connections' => [],
                'create' => false,
            ];
        }
    }
}
