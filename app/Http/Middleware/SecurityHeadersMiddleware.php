<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and apply security response headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy allowing Turnstile and fonts
        $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https:; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com; " .
               "frame-src 'self' https://challenges.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' data: https://fonts.gstatic.com; " .
               "img-src 'self' data: blob: https:; " .
               "connect-src 'self' https://challenges.cloudflare.com;";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
