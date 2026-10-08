<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentPortalController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\ClientLogController;
use App\Http\Controllers\CollectorAuthController;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\MikrotikToolsController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ArpController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PppoeController;
use App\Http\Controllers\QRISController;
use App\Http\Controllers\RadiusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TenantShopController;
use App\Http\Controllers\TechnicianAuthController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TopBandwidthController;
use App\Http\Controllers\WebhookController;
use App\Models\Addon;
use App\Models\TenantAddon;
use Inertia\Inertia;

// ==================== SUPER ADMIN ====================
Route::get('superadmin/login', [\App\Http\Controllers\Auth\LoginController::class, 'showSuperadminLogin'])->name('superadmin.login');
Route::post('superadmin/login', [\App\Http\Controllers\Auth\LoginController::class, 'authenticateSuperadmin'])->middleware('throttle:10,1');
Route::get('nodera/superadmin/login', [\App\Http\Controllers\Auth\LoginController::class, 'showSuperadminLogin']);
Route::post('nodera/superadmin/login', [\App\Http\Controllers\Auth\LoginController::class, 'authenticateSuperadmin'])->middleware('throttle:10,1');

// ==================== SUPERADMIN 2FA (di luar enforcement twofactor) ====================
Route::prefix('superadmin')->name('superadmin.')->middleware('superadmin')->group(function () {
});


// Public API Verification & Heartbeat for Source Code ISP Licenses

// Direct APK Download Routes for Mobile App
Route::get('download/apk', function () {
    $apkPath = public_path('downloads/noderaadmin.apk');
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/noderabilling.apk');
    }
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/nodera-billing.apk');
    }
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'noderaadmin.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});
Route::get('downloads/noderaadmin.apk', function () {
    $apkPath = public_path('downloads/noderaadmin.apk');
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/noderabilling.apk');
    }
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'noderaadmin.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});
Route::get('downloads/noderasuperadmin.apk', function () {
    $apkPath = public_path('downloads/noderasuperadmin.apk');
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'noderasuperadmin.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});
Route::get('downloads/noderabilling.apk', function () {
    $apkPath = public_path('downloads/noderabilling.apk');
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/noderaadmin.apk');
    }
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'noderabilling.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});
Route::get('downloads/nodera-billing.apk', function () {
    $apkPath = public_path('downloads/nodera-billing.apk');
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/noderaadmin.apk');
    }
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'nodera-billing.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});
Route::get('downloads/nodera.apk', function () {
    $apkPath = public_path('downloads/nodera.apk');
    if (!file_exists($apkPath)) {
        $apkPath = public_path('downloads/noderaadmin.apk');
    }
    if (!file_exists($apkPath)) {
        abort(404, 'File APK tidak ditemukan.');
    }
    return response()->download($apkPath, 'nodera.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});


// ── Tenant & User Login Routes ──
Route::get('{slug}', [\App\Http\Controllers\Auth\LoginController::class, 'showTenantLogin'])
    ->where('slug', '^(?!admin\b|portal\b|teknisi\b|kolektor\b|kasir\b|login\b|logout\b|storage\b|build\b|images\b|favicon\b).*');
Route::get('{slug}/login', [\App\Http\Controllers\Auth\LoginController::class, 'showTenantLogin'])
    ->where('slug', '^(?!admin\b|portal\b|teknisi\b|kolektor\b|kasir\b|login\b|logout\b|storage\b|build\b|images\b|favicon\b).*');
Route::post('{slug}/login', [\App\Http\Controllers\Auth\LoginController::class, 'authenticate'])
    ->middleware('throttle:10,1')
    ->where('slug', '^(?!admin\b|portal\b|teknisi\b|kolektor\b|kasir\b|login\b|logout\b|storage\b|build\b|images\b|favicon\b).*');

Route::fallback(function (\Illuminate\Http\Request $request) {
    return response()->view('errors.404', [], 404);
});