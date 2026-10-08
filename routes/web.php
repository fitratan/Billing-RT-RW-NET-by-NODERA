<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NoderaPay\NoderaPayDashboardController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WaGateway\WaGatewayDashboardController;
use App\Http\Middleware\TenantStorageGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Master Entry Point)
|--------------------------------------------------------------------------
|
| Clean modular route registrations partitioned into domain files:
| - auth.php       : Authentication, Registration, Subdomain check, Setup wizard
| - portal.php     : Customer, Mobile, Cashier, Collector, Technician, Agent portals
| - admin.php      : Tenant admin dashboard, Billing, Network, OLT, Settings
| - superadmin.php : SuperAdmin platform administration, Tenants, VPN, ISP Licenses
| - services.php   : Member services, Arisan PWA, Mikhmon, GenieACS, Bookkeeping
| - shop.php       : Boutique, Public Shop, Hotspot Preview, Tenant Shop
| - tools.php      : MikroTik tools & Script generator studio
| - webhooks.php   : Payment webhooks, Deploy callbacks, Cron triggers, Client logs
|
*/

// ==================== MAIN LANDING & PWA MANIFEST ====================
Route::get('print-vouchers', [AdminController::class, 'printVouchers'])->middleware(['auth']);
Route::get('manifest.json', [LandingController::class, 'manifest']);

// ==================== WEB PUSH / VAPID ====================
Route::get('/api/push/public-key', [PushSubscriptionController::class, 'publicKey']);
Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'save']);

// ==================== LANDING & LEGAL ====================
Route::any('/', [LandingController::class, 'index']);
Route::get('/landing', fn () => redirect('/', 301));
Route::get('/sitemap.xml', [LandingController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [LandingController::class, 'robots'])->name('robots');
Route::get('/terms', [LandingController::class, 'terms'])->name('terms');
Route::get('/syarat-ketentuan', fn () => redirect()->route('terms', [], 301));
Route::get('/privacy', [LandingController::class, 'privacy'])->name('privacy');
Route::get('/kebijakan-privasi', fn () => redirect()->route('privacy', [], 301));

// Ecosystem landing pages
Route::get('/nodera-pay', fn () => view('noderapay-landing'));
Route::get('/noderapay', function (Request $request) {
    if (str_starts_with($request->getHost(), 'gateway.')) {
        return redirect('/', 301);
    }
    return view('noderapay-landing');
});
Route::get('/nodera-pay/docs', [NoderaPayDashboardController::class, 'docs']);
Route::get('/noderapay/docs', [NoderaPayDashboardController::class, 'docs']);
Route::get('/wa-gateway', fn () => view('wagateway-landing'));
Route::get('/wagateway', function (Request $request) {
    $host = $request->getHost();
    if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
        return redirect('/', 301);
    }
    return view('wagateway-landing');
});
Route::get('/wa-gateway/docs', [WaGatewayDashboardController::class, 'docs']);
Route::get('/wagateway/docs', [WaGatewayDashboardController::class, 'docs']);
Route::get('/docs', function (Request $request) {
    $host = $request->getHost();
    if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
        return app(WaGatewayDashboardController::class)->docs();
    }
    if (str_starts_with($host, 'gateway.') || str_starts_with($host, 'pay.')) {
        return app(NoderaPayDashboardController::class)->docs();
    }
    return redirect('/#ecosystem');
})->name('docs.index');
Route::get('/panduan', fn () => redirect()->route('docs.index', [], 301));
Route::get('/guide', fn () => redirect()->route('docs.index', [], 301));
Route::get('/dokumentasi', fn () => redirect()->route('docs.index', [], 301));
Route::get('/docs/pdf', [LandingController::class, 'guidePdf'])->name('docs.pdf');
Route::get('/panduan/pdf', [LandingController::class, 'guidePdf'])->name('panduan.pdf');
Route::get('/guide/pdf', [LandingController::class, 'guidePdf'])->name('guide.pdf');

// ==================== SEO REDIRECTS FOR LEGACY HOTSPOT & STATIC HTML ====================
Route::get('/login.html', fn () => redirect('/login', 301));
Route::get('/logout.html', fn () => redirect('/login', 301));
Route::get('/status.html', fn () => redirect('/login', 301));
Route::get('/alogin.html', fn () => redirect('/login', 301));
Route::get('/rlogin.html', fn () => redirect('/login', 301));
Route::get('/radvert.html', fn () => redirect('/', 301));
Route::get('/index.html', fn () => redirect('/', 301));

// ==================== SEO SOLUTIONS & BLOG ROUTES ====================
Route::get('/software-billing-isp', [SeoController::class, 'solution'])->name('seo.software-billing-isp');
Route::get('/billing-rt-rw-net', [SeoController::class, 'solution'])->name('seo.billing-rt-rw-net');
Route::get('/billing-mikrotik', [SeoController::class, 'solution'])->name('seo.billing-mikrotik');
Route::get('/monitoring-mikrotik', [SeoController::class, 'solution'])->name('seo.monitoring-mikrotik');
Route::get('/nms-olt', [SeoController::class, 'solution'])->name('seo.nms-olt');
Route::get('/gis-fiber-optic', [SeoController::class, 'solution'])->name('seo.gis-fiber-optic');
Route::get('/mikhmon-online', [SeoController::class, 'solution'])->name('seo.mikhmon-online');
Route::get('/harga', [SeoController::class, 'solution'])->name('seo.harga');
Route::get('/pricing', fn () => redirect('/harga', 301));
Route::get('/blog', [SeoController::class, 'blogIndex'])->name('seo.blog.index');
Route::get('/blog/{slug}', [SeoController::class, 'blogPost'])->name('seo.blog.post');

// Downloads
Route::get('/downloads', fn () => redirect('/#apps'))->name('downloads.index');
Route::get('/download', fn () => redirect('/#apps'));
Route::get('/download/{apk}', function (string $apk) {
    $cleanName = basename($apk);
    if (!str_ends_with(strtolower($cleanName), '.apk')) {
        $cleanName .= '.apk';
    }
    $path = public_path("downloads/{$cleanName}");
    if (file_exists($path)) {
        return response()->download($path, $cleanName, [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
    abort(404, 'File APK tidak ditemukan.');
})->name('apk.download');

// ==================== UNIVERSAL STORAGE FALLBACK ====================
Route::get('/storage/{path}', function ($path) {
    $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
    if (preg_match('/(\.(env|git|php|sh|sql|json|lock|yml|yaml|key|pem|conf|bak))$/i', $cleanPath) || str_starts_with($cleanPath, '.')) {
        abort(403, 'Access denied');
    }
    $allowedRoots = array_filter([
        realpath(storage_path('app/public')),
        realpath(public_path('storage')),
        realpath(public_path('uploads')),
    ]);
    $candidates = [
        storage_path('app/public/' . $cleanPath),
        public_path('storage/' . $cleanPath),
        public_path('uploads/' . $cleanPath),
    ];
    $filePath = null;
    foreach ($candidates as $cand) {
        $real = realpath($cand);
        if ($real && is_file($real)) {
            foreach ($allowedRoots as $root) {
                if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                    $filePath = $real;
                    break 2;
                }
            }
        }
    }
    if (!$filePath) {
        abort(404);
    }
    $mimeType = @mime_content_type($filePath) ?: 'application/octet-stream';
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('storage.fallback');

Route::get('/uploads/{path}', function ($path) {
    $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
    if (preg_match('/(\.(env|git|php|sh|sql|json|lock|yml|yaml|key|pem|conf|bak))$/i', $cleanPath) || str_starts_with($cleanPath, '.')) {
        abort(403, 'Access denied');
    }
    $allowedRoots = array_filter([
        realpath(public_path('uploads')),
        realpath(storage_path('app/public')),
        realpath(public_path('storage')),
    ]);
    $candidates = [
        public_path('uploads/' . $cleanPath),
        storage_path('app/public/' . $cleanPath),
        public_path('storage/' . $cleanPath),
    ];
    $filePath = null;
    foreach ($candidates as $cand) {
        $real = realpath($cand);
        if ($real && is_file($real)) {
            foreach ($allowedRoots as $root) {
                if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                    $filePath = $real;
                    break 2;
                }
            }
        }
    }
    if (!$filePath) {
        abort(404);
    }
    $mimeType = @mime_content_type($filePath) ?: 'application/octet-stream';
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('uploads.fallback');

Route::get('/secure-storage/download/{path}', function ($path) {
    $decodedPath = base64_decode($path);
    $fullPath = storage_path('app/' . $decodedPath);
    if (!file_exists($fullPath)) {
        abort(404, 'File tidak ditemukan.');
    }
    $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'private, no-cache',
    ]);
})->middleware([TenantStorageGuard::class])->name('tenant.storage.download');

// ==================== MODULAR ROUTE REGISTRATION ====================
require __DIR__ . '/auth.php';
require __DIR__ . '/portal.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/superadmin.php';
require __DIR__ . '/services.php';
require __DIR__ . '/shop.php';
require __DIR__ . '/tools.php';
require __DIR__ . '/webhooks.php';
