<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SetupWizardController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $isStandalone = (bool) (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false));

        // In SaaS Cloud mode: Setup Wizard is disabled completely
        if (!$isStandalone) {
            if ($request->is('setup', 'setup/*')) {
                return redirect('/login');
            }
            return $next($request);
        }

        // Whitelisted routes: setup routes, static assets, health, license apis, webhooks
        $isWhitelisted = $request->is(
            'setup', 'setup/*', 'api/setup/*', 'activation',
            'up', 'manifest.json', 'assets/*', 'build/*', 'favicon.ico',
            'api/v1/license/*', 'api/license/*',
            'webhook/*', 'webhook', 'api/webhook/*', 'api/webhook'
        );

        $isCompleted = SetupWizardController::isSetupCompleted();

        // 1. If Setup is NOT completed in Standalone mode
        if (!$isCompleted) {
            if (!$isWhitelisted) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Setup required',
                        'redirect' => '/setup',
                    ], 403);
                }
                return redirect('/setup');
            }
            return $next($request);
        }

        // 2. If Setup IS completed, prevent access to /setup
        if ($request->is('setup', 'setup/*')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
