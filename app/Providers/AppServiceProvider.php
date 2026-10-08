<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (file_exists(app_path('Support/QRCode/autoload.php'))) {
            require_once app_path('Support/QRCode/autoload.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS hanya jika APP_URL disetting dengan domain HTTPS (Cloud/Production SSL)
        if (
            $this->app->environment('production')
            && str_starts_with(config('app.url', ''), 'https://')
            && !str_contains(config('app.url', ''), 'localhost')
            && !str_contains(config('app.url', ''), '127.0.0.1')
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }



        // User yang sudah login lalu membuka /login → arahkan sesuai subdomain dan role
        RedirectIfAuthenticated::redirectUsing(function ($request) {
            $host = $request->getHost();

            if (str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.')) {
                return '/dashboard';
            }
            if (str_starts_with($host, 'gateway.')) {
                return '/dashboard';
            }
            if (str_starts_with($host, 'panel.')) {
                return '/dashboard';
            }

            $user = $request->user();

            if ($user && $user->role === 'superadmin') {
                return '/superadmin';
            }
            if ($user && $user->role === 'technician') {
                return '/teknisi/dashboard';
            }

            return '/dashboard';
        });

        \Illuminate\Support\Facades\Blade::directive('rp', function ($expression) {
            return "<?php echo 'Rp ' . number_format((float) {$expression}, 0, ',', '.'); ?>";
        });

        // Default untuk cangkang master (layouts.app). Tiap panel boleh override
        // lewat @php sebelum @extends, atau composer di layout panel.
        View::composer('layouts.app', function ($view) {
            $view->with([
                'pageTitle'   => 'NODERA',
                'panelName'   => 'Panel',
                'home'        => '/',
                'logoutUrl'   => '/logout',
                'userName'    => null,
                'userRole'    => null,
                'userInitial' => 'U',
                'hasNav'      => false,
                'back'        => null,
                'bottomnav'   => null,
            ]);
        });
    }
}
