<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductionSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), payment=(), usb=()');
        $response->headers->set(
            'Content-Security-Policy',
            "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'"
        );

        if (
            $request->isSecure()
            || (
                app()->environment('production')
                && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'
            )
        ) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'max-age=0, no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
