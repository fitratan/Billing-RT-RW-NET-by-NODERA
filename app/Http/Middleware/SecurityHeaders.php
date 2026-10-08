<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and apply OWASP-recommended security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 2. Prevent Clickjacking by allowing iframes only from same origin
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 3. Enable standard browser XSS filtering protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Protect referrer information across domains
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Restrict device hardware permissions
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');

        return $response;
    }
}
