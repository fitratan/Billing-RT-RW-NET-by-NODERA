<?php

use App\Http\Middleware\AuthOrTechnicianCollector;
use App\Http\Middleware\CheckIspLicense;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CollectorAuth;
use App\Http\Middleware\DetectDevice;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\TechnicianAuth;
use App\Http\Middleware\TenantDetectionMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Models\Setting;
use App\Providers\EventServiceProvider;
use App\Services\CronService;
use App\Services\HermesAutoFixService;
use App\Services\TelegramService;
use App\Services\UsageService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as AppRouteServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// 🛡️ Zero-Downtime Safe Route Cache Loading: Gracefully fallback to dynamic route loading if cache file is missing/corrupted during deployment race condition
AppRouteServiceProvider::loadCachedRoutesUsing(function (\Illuminate\Foundation\Application $app) {
    $cached = $app->getCachedRoutesPath();
    if (file_exists($cached) && is_readable($cached)) {
        try {
            require $cached;
            return;
        } catch (\Throwable) {
            // Fall through to dynamic route loading fallback
        }
    }

    try {
        $reflection = new \ReflectionClass(AppRouteServiceProvider::class);
        $prop = $reflection->getProperty('alwaysLoadRoutesUsing');
        $prop->setAccessible(true);
        $alwaysLoad = $prop->getValue();
        if ($alwaysLoad) {
            $app->call($alwaysLoad);
        }
        if ($app->bound('router')) {
            $app['router']->getRoutes()->refreshNameLookups();
            $app['router']->getRoutes()->refreshActionLookups();
        }
    } catch (\Throwable) {
        // Safe fallback
    }
});

// Auto-load embedded QR Code engine if vendor package is absent on shared hosting
if (file_exists(__DIR__ . '/../app/Support/QRCode/autoload.php')) {
    require_once __DIR__ . '/../app/Support/QRCode/autoload.php';
}

$app = new Application(dirname(__DIR__));

// 🛡️ Zero-Downtime Safe Event & Route Cache Guard: Register booting hook to verify events & routes cache validity before providers boot
$app->booting(function (\Illuminate\Foundation\Application $app) {
    // 1. Events cache verification: prevent fatal require() if events.php is missing/cleared/corrupted
    try {
        $eventsCachePath = $app->getCachedEventsPath();
        if (!file_exists($eventsCachePath) || !is_readable($eventsCachePath) || (int) @filesize($eventsCachePath) === 0) {
            $app->instance('events.cached', false);
        } else {
            $testEvents = @include $eventsCachePath;
            if (!is_array($testEvents)) {
                $app->instance('events.cached', false);
            }
        }
    } catch (\Throwable) {
        $app->instance('events.cached', false);
    }

    // 2. Routes cache verification: prevent fatal require() if routes-v7.php is missing/cleared/corrupted or during testing
    try {
        if ($app->environment('testing') || env('APP_ENV') === 'testing' || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING)) {
            $app->instance('routes.cached', false);
            $app->instance('events.cached', false);
        } else {
            $routesCachePath = $app->getCachedRoutesPath();
            if (!file_exists($routesCachePath) || !is_readable($routesCachePath) || (int) @filesize($routesCachePath) === 0) {
                $app->instance('routes.cached', false);
            }
        }
    } catch (\Throwable) {
        $app->instance('routes.cached', false);
    }
});

return (new \Illuminate\Foundation\Configuration\ApplicationBuilder($app))
    ->withKernels()
    ->withEvents()
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $tz = config('app.timezone', 'Asia/Jakarta');

        // Baca setting isolir dari database (safe-guard saat migrate)
        try {
            $isolirHour = (int) (Setting::getValue('ISOLIR_HOUR', '2'));
            $isolirMinute = (int) (Setting::getValue('ISOLIR_MINUTE', '0'));
        } catch (Exception $e) {
            $isolirHour = 2;
            $isolirMinute = 0;
        }
        $isolirTime = sprintf('%02d:%02d', $isolirHour, $isolirMinute);

        // Check and generate invoices based on tenant custom configured day daily at 00:01 WIB
        $schedule->call(function () {
            app(CronService::class)->runJob('invoice:generate');
        })->name('cron.invoice.generate')->dailyAt('00:01')->timezone($tz);

        // Check isolation daily at configured time (default 02:00 WIB)
        $schedule->call(function () {
            app(CronService::class)->runJob('isolation:check');
        })->name('cron.isolation.check')->dailyAt($isolirTime)->timezone($tz);

        // WhatsApp automated invoice reminders (H-3, H-1, Due Date) daily at 09:00 WIB
        $schedule->call(function () {
            app(CronService::class)->runJob('invoice:remind');
        })->name('cron.invoice.remind')->dailyAt('09:00')->timezone($tz);

        // Monitor Router & OLT Connectivity (Down/Recovery Alert) every 5 minutes
        $schedule->call(function () {
            app(CronService::class)->runJob('network:monitor');
        })->name('cron.network.monitor')->everyFiveMinutes();

        // Poll usage every 10 minutes
        $schedule->call(function () {
            app(UsageService::class)->pollAllRouters();
        })->name('cron.usage.poll')->everyTenMinutes();

        // Jam Kalong (Night Speed): start 00:00, end 06:00
        $schedule->call(function () {
            app(CronService::class)->runJob('jamkalong:start');
        })->name('cron.jamkalong.start')->dailyAt('00:00')->timezone($tz);

        $schedule->call(function () {
            app(CronService::class)->runJob('jamkalong:end');
        })->name('cron.jamkalong.end')->dailyAt('06:00')->timezone($tz);

        // FUP check hourly
        $schedule->call(function () {
            app(CronService::class)->runJob('fup:check');
        })->name('cron.fup.check')->hourly();

        // Backup database weekly on Sunday at 03:00 WIB
        $schedule->call(function () {
            app(CronService::class)->runJob('backup:database');
        })->name('cron.backup.database')->weeklyOn(0, '03:00')->timezone($tz);

        // Prune log lama (client_logs & audit_logs) tiap hari 04:00 WIB
        $schedule->call(function () {
            app(CronService::class)->runJob('logs:prune');
        })->name('cron.logs.prune')->dailyAt('04:00')->timezone($tz);

        // Hotspot Expired Cleaner (Auto-Kick session & users) every 5 minutes
        $schedule->call(function () {
            app(CronService::class)->runJob('hotspot:clean-expired');
        })->name('cron.hotspot.clean-expired')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend(TenantDetectionMiddleware::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureSetupCompleted::class);
        $middleware->appendToGroup('web', CheckIspLicense::class);
        $middleware->appendToGroup('web', HandleInertiaRequests::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);


        // Trust all proxies (behind nginx/Cloudflare/Docker/SSL termination)
        $middleware->trustProxies('*', Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PREFIX | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->encryptCookies(except: [
            'PHPSESSID',
        ]);

        $middleware->validateCsrfTokens(except: [
            'client-error',
            'client-logs',
            'api/client-error',
            'api/client-logs',
            '*/client-error',
            '*/client-logs',
            'webhook/*',
            'webhook',
            'api/webhook/*',
            'api/webhook',
            'api/*',
            'send-message',
            'api/send-message',
            'message/send',
            'api/message/send',
            'v1/wa/*',
            'api/v1/wa/*',
            'api/v1/license/*',
            'api/license/*',
            'mikhmon-*',
            'hotspot-*',
            'kas-*',
            'admin.php*',
            'login.php*',
            'index.php*',
            'setup_admin_whatsapp.php*',
            'admin_payment_methods.php*',
            'check_admin.php*',
            'install.php*',
            'update.php*',
            'telegram_webhook.php*',
            '*/telegram_webhook.php*',
            '*telegram_webhook.php*',
            'api_webhook.php*',
            '*/api_webhook.php*',
            '*api_webhook.php*',
            'buy_process.php*',
            '*/buy_process.php*',
            '*buy_process.php*',
            'buy.php*',
            '*/buy.php*',
            '*buy.php*',
            'portal/speedtest/*',
            'speedtest/*',
            '*/speedtest/*',
        ]);
        $middleware->alias([
            'tenant'          => TenantMiddleware::class,
            'tenant.detect'   => TenantDetectionMiddleware::class,
            'license.check'   => CheckIspLicense::class,
            'superadmin'      => SuperAdminMiddleware::class,
            'auth.or.tech'    => AuthOrTechnicianCollector::class,
            'auth.collector'  => CollectorAuth::class,
            'auth.technician' => TechnicianAuth::class,
            'role'            => CheckRole::class,
            'locale'          => SetLocale::class,
            'twofactor'       => RequireTwoFactor::class,
            'arisan.tenant'   => \App\Http\Middleware\ArisanTenantMiddleware::class,
            'arisan.admin'    => \App\Http\Middleware\ArisanAdminAuthMiddleware::class,
            'arisan.member'   => \App\Http\Middleware\ArisanMemberAuthMiddleware::class,
        ]);
    })

    ->withProviders()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (Throwable $e): void {
            try {
                if (app()->bound('log')) {
                    Log::error($e);
                }
            } catch (Throwable $ignored) {
            }
        });

        // 🚨 Kirim notifikasi error sistem (500+, 405, ParseError, Crash & Critical Route 404) langsung ke Telegram Superadmin & Forum Error Topic
        $exceptions->report(function (Throwable $e): void {
            $isHttp = $e instanceof HttpExceptionInterface;
            $status = $isHttp ? $e->getStatusCode() : 500;

            $req = app()->bound('request') ? app('request') : null;
            $host = $req ? $req->getHost() : '';
            $isGatewayDomain = str_starts_with($host, 'gateway.') || str_contains($host, 'gateway');

            $isCriticalRoute = $req && (
                $isGatewayDomain ||
                $req->is('superadmin/*') || 
                $req->is('admin/*') || 
                $req->is('vpn/*') ||
                $req->is('portal/*') ||
                $req->is('kolektor/*') ||
                $req->is('teknisi/*') ||
                $req->is('cashier/*') ||
                $req->is('noderapay/*') ||
                $req->is('pay/*') ||
                $req->is('checkout/*') ||
                $req->is('api/*') ||
                $req->is('webhook/*') ||
                $req->is('cron/*') ||
                $req->is('nodera/superadmin/*')
            );

            // Kirim notifikasi Telegram jika:
            // 1. Status >= 500 (Server Error / Fatal / Uncaught / Database Error di SEMUA domain & rute)
            // 2. Route panel admin/superadmin/vpn/gateway/api/webhook/kolektor/teknisi mengalami 404, 405, 500
            $isCriticalError = ($status >= 500) || ($isCriticalRoute && in_array($status, [404, 405, 500]));
            if (!$isCriticalError) {
                return;
            }

            // Abaikan 404 aset statis / favicon / robots / map
            if ($status === 404 && $req) {
                $path = $req->path();
                if (preg_match('/\.(ico|png|jpg|jpeg|svg|css|js|map|woff2?|ttf|webp)$/i', $path)) {
                    return;
                }
            }

            try {
                $telegram = app(TelegramService::class);
                if (! $telegram->isConfigured()) {
                    return;
                }

                $req = app()->bound('request') ? app('request') : null;
                $reqUrl = $req ? ($req->method() . ' ' . $req->fullUrl()) : 'CLI / Background / Cron';
                $route = app()->bound('router') && app('router')->currentRouteName() ? app('router')->currentRouteName() : '-';
                $ip = $req ? $req->ip() : '-';
                $ua = $req ? mb_substr($req->userAgent() ?? '-', 0, 150) : '-';

                $userStr = 'Guest / Unauthenticated';
                if (auth()->check()) {
                    $u = auth()->user();
                    $userStr = "{$u->name} (ID: {$u->id}, Role: " . ($u->role ?? 'user') . ", Tenant: " . ($u->tenant_id ?? 'root') . ")";
                }

                $file = str_replace(base_path() . '/', '', $e->getFile());
                $line = $e->getLine();
                $errClass = get_class($e);
                $msg = $e->getMessage() ?: 'No exception message';
                if (mb_strlen($msg) > 800) {
                    $msg = mb_substr($msg, 0, 800) . '...';
                }

                // Sanitized payload preview (excluding secrets)
                $payloadPreview = '-';
                if ($req && in_array($req->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    $inputs = $req->except(['password', 'password_confirmation', 'token', 'secret', 'key', 'pin', 'cvv', 'credit_card', '_token']);
                    if (!empty($inputs)) {
                        $json = json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if (mb_strlen($json) > 400) {
                            $json = mb_substr($json, 0, 400) . '...';
                        }
                        $payloadPreview = $json;
                    }
                }

                // Relevant stack trace preview (top 3 app frames)
                $traceFrames = [];
                foreach ($e->getTrace() as $frame) {
                    if (isset($frame['file']) && ! str_contains($frame['file'], '/vendor/')) {
                        $shortFile = str_replace(base_path() . '/', '', $frame['file']);
                        $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
                        $traceFrames[] = "• <code>{$shortFile}:" . ($frame['line'] ?? '?') . "</code> → <code>" . htmlspecialchars($func) . "()</code>";
                        if (count($traceFrames) >= 3) {
                            break;
                        }
                    }
                }
                $traceLines = [];
                $cnt = count($traceFrames);
                foreach ($traceFrames as $idx => $tf) {
                    $prefix = ($idx === $cnt - 1) ? '└' : '├';
                    $traceLines[] = "{$prefix} " . ltrim($tf, "• ");
                }
                $traceText = !empty($traceLines) ? implode("\n", $traceLines) : '└ <code>' . htmlspecialchars($file . ':' . $line) . '</code>';

                $appName = htmlspecialchars(config('app.name', 'NODERA'));
                $appEnv = strtoupper(config('app.env', 'production'));

                $message = "<b>🚨 SYSTEM ERROR ALERT — {$appName} [{$appEnv}]</b>\n\n"
                    . "┌ Detail Error\n"
                    . "├ HTTP Status: <code>{$status}</code>\n"
                    . "├ Exception: <code>" . htmlspecialchars($errClass) . "</code>\n"
                    . "├ Message: " . htmlspecialchars($msg) . "\n"
                    . "├ Location: <code>" . htmlspecialchars("{$file}:{$line}") . "</code>\n"
                    . "├ Request: <code>" . htmlspecialchars($reqUrl) . "</code>\n"
                    . "├ Route: <code>" . htmlspecialchars($route) . "</code>\n"
                    . "├ User: " . htmlspecialchars($userStr) . "\n"
                    . "├ Client IP: <code>" . htmlspecialchars($ip) . "</code>\n"
                    . "├ Agent: <code>" . htmlspecialchars($ua) . "</code>\n"
                    . "├ Payload: <code>" . htmlspecialchars($payloadPreview) . "</code>\n"
                    . "└ Waktu: " . date('Y-m-d H:i:s T') . "\n\n"
                    . "┌ Trace Snippet\n"
                    . $traceText;

                $telegram->sendAdminNotification($message, 'error', 'HTML');
                HermesAutoFixService::dispatch($e, $reqUrl, $payloadPreview);
            } catch (Throwable $ignored) {
                // Alert dispatch must never break app flow
            }
        });

        // 🛡️ Jangan pernah tampilkan raw framework stack trace ke pengguna akhir
        $exceptions->render(function (Throwable $e, Request $request) {
            $isHttp = $e instanceof HttpExceptionInterface;
            $status = $isHttp ? $e->getStatusCode() : 500;

            // Tangani PostTooLargeException (Upload Foto Melebihi Batas PHP) secara aman
            if ($e instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
                if ($request->header('X-Inertia')) {
                    return back()->with('error', 'Ukuran file foto terlalu besar. Sistem otomatis mengoptimasi ukuran gambar, silakan coba unggah kembali.');
                }
                return redirect()->back()->with('error', 'Ukuran total upload terlalu besar. Silakan pilih foto dengan resolusi lebih wajar.');
            }

            // Tangani 419 CSRF Token Mismatch / Sesi Kedaluwarsa secara otomatis
            if ($e instanceof \Illuminate\Session\TokenMismatchException || $status === 419) {
                if ($request->header('X-Inertia')) {
                    return back()->with('warning', 'Sesi login telah diperbarui. Silakan coba klik login kembali.');
                }
                return redirect()->back()->with('warning', 'Sesi telah kedaluwarsa. Silakan refresh halaman dan coba kembali.');
            }

            // Biarkan ValidationException ditangani secara normal oleh Laravel & Inertia
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            // Biarkan Authentication Exception ditangani secara normal (redirect ke login)
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            // 1. Jika request dari Inertia.js SPA -> Selalu kembalikan flash error bersih tanpa memicu modal Whoops atau iframe debug!
            if ($request->header('X-Inertia')) {
                if ($status === 404) {
                    return back()->with('error', 'Halaman atau endpoint yang Anda tuju tidak ditemukan.');
                }
                if ($status === 405) {
                    return back()->with('error', 'Metode request tidak didukung untuk aksi ini.');
                }
                if ($status === 403) {
                    return back()->with('error', 'Akses ditolak. Anda tidak memiliki izin untuk aksi ini.');
                }

                // JANGAN PERNAH bocorkan raw PHP/Framework exception message ke pengguna akhir!
                return back()->with('error', 'Terjadi kendala pada sistem. Laporan telah otomatis dikirimkan ke tim teknis.');
            }

            // Biarkan 404, 403, 401, 422 untuk request non-Inertia ditangani oleh view/redirect bawaan
            if ($status < 500) {
                return null;
            }

            // 2. Jika request API / JSON murni
            if ($request->is('api/*') || $request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kendala sistem internal. Laporan otomatis telah diteruskan ke tim teknis.',
                ], 500);
            }

            // 3. Jika request web biasa, selalu tampilkan halaman branded error 500 tanpa stack trace
            if (view()->exists('errors.500')) {
                return response()->view('errors.500', [], 500);
            }

            return response('<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Kendala Sistem</title><style>body{background:#090B0E;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center"><h2>Terjadi kendala sistem internal</h2><p style="color:#94A3B8">Laporan otomatis telah diteruskan ke tim teknis.</p><a href="/dashboard" style="color:#00C2FF;text-decoration:none">Kembali ke Beranda</a></div></body></html>', 500);
        });
    })->create();

// 🛡️ Zero-Downtime Safe Event Cache Verification: Gracefully prevent race condition if events cache file is unlinked or corrupted during deployment
try {
    $eventsCachePath = $app->getCachedEventsPath();
    if (!file_exists($eventsCachePath) || !is_readable($eventsCachePath) || (int) @filesize($eventsCachePath) === 0) {
        $app->instance('events.cached', false);
    } else {
        $testEvents = @include $eventsCachePath;
        if (!is_array($testEvents)) {
            $app->instance('events.cached', false);
        }
    }
} catch (\Throwable) {
    $app->instance('events.cached', false);
}

return $app;
