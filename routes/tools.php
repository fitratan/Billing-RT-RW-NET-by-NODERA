<?php

use App\Http\Controllers\MikrotikToolsController;
use Illuminate\Support\Facades\Route;

// ==================== MIKROTIK TOOLS & SCRIPT GENERATOR STUDIO ====================
Route::prefix('tools')->middleware(['auth', 'role:admin,superadmin'])->group(function () {
    Route::get('/', [MikrotikToolsController::class, 'index'])->name('tools.index');
    Route::get('/loadbalance', [MikrotikToolsController::class, 'loadbalance'])->name('tools.loadbalance');
    Route::get('/game', [MikrotikToolsController::class, 'game'])->name('tools.game');
    Route::get('/stream', [MikrotikToolsController::class, 'stream'])->name('tools.stream');
    Route::get('/speedtest', [MikrotikToolsController::class, 'speedtest'])->name('tools.speedtest');
    Route::get('/security', [MikrotikToolsController::class, 'security'])->name('tools.security');
    Route::get('/hotspot-pppoe', [MikrotikToolsController::class, 'hotspotPppoe'])->name('tools.hotspot-pppoe');
    Route::get('/port-forward', [MikrotikToolsController::class, 'portForward'])->name('tools.port-forward');
    Route::get('/burst-qos', [MikrotikToolsController::class, 'burstQos'])->name('tools.burst-qos');
    Route::get('/mikrotik', [MikrotikToolsController::class, 'loadbalance'])->name('tools.mikrotik');

    Route::post('/api/mikrotik/test-connection', [MikrotikToolsController::class, 'testConnection']);
    Route::post('/api/mikrotik/execute-command', [MikrotikToolsController::class, 'executeCommand']);
    Route::post('/api/mikrotik/execute-batch', [MikrotikToolsController::class, 'executeBatch']);
    Route::post('/api/mikrotik/upload-isolir-page', [MikrotikToolsController::class, 'uploadIsolirPage']);
});
