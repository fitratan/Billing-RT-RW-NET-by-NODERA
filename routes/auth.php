<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\SuperAdmin\TwoFactorController;
use App\Services\SubdomainValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================== SUBDOMAIN VALIDATION API ====================
Route::get('/api/check-subdomain', function (Request $request) {
    $subdomain = $request->query('subdomain', '');
    $type = $request->query('type');
    $id = $request->query('id') ? (int) $request->query('id') : null;
    return response()->json(SubdomainValidationService::checkAvailability($subdomain, $type, $id));
});

// ==================== AUTHENTICATION & REGISTRATION ====================
Route::get('/daftar-isp', fn () => redirect()->route('register', [], 301));
Route::post('/daftar-isp', [LandingController::class, 'daftarIspStore']);
Route::get('/register', [LandingController::class, 'daftarIsp'])->name('register');
Route::post('/register', [LandingController::class, 'daftarIspStore']);
Route::get('/register/sukses', [LandingController::class, 'registerSukses'])->name('register.sukses');
Route::get('/register/status/{slug}', [LandingController::class, 'checkRegistrationStatus'])->name('register.status');
Route::match(['GET', 'POST'], '/register/cancel/{slug}', [LandingController::class, 'cancelRegistration'])->name('register.cancel');
Route::post('/register/regenerate/{slug}', [LandingController::class, 'regenerateRegistrationQris'])->name('register.regenerate');

// ==================== SETUP WIZARD (STANDALONE FIRST-RUN) ====================
Route::get('setup', [SetupWizardController::class, 'show'])->name('setup');
Route::post('setup/verify-license', [SetupWizardController::class, 'verifyLicense'])->middleware('throttle:20,1');
Route::post('setup/complete', [SetupWizardController::class, 'complete'])->middleware('throttle:10,1');
Route::post('activation', [SetupWizardController::class, 'complete'])->middleware('throttle:10,1');
