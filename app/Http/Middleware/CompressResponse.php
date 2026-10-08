<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    /**
     * Handle an incoming request and gzip compress the response if supported by client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only compress if client supports gzip and response is not already encoded / binary streamed
        if (! function_exists('gzencode') || $response->headers->has('Content-Encoding')) {
            return $response;
        }

        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (! str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < 1024) { // Don't compress tiny payloads < 1KB
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (! (str_contains($contentType, 'text/') || str_contains($contentType, 'application/json') || str_contains($contentType, 'application/javascript'))) {
            return $response;
        }

        $compressed = gzencode($content, 5);
        if ($compressed !== false && strlen($compressed) < strlen($content)) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', (string) strlen($compressed));
            $response->headers->set('Vary', 'Accept-Encoding', false);
        }

        return $response;
    }
}
