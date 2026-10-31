<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Attach a per-request CSP nonce and the baseline security headers.
     *
     * Script execution requires either the per-request nonce or one of the
     * trusted CDN hosts — no 'unsafe-inline'/'unsafe-eval', which would
     * otherwise let injected markup execute arbitrary JS.
     *
     * The nonce is a fresh CSPRNG value per request and is never derived from
     * request data, so nothing about the request (including exception detail,
     * which Laravel renders through the handler in bootstrap/app.php after this
     * middleware has already unwound) can leak into the header value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(Str::random(16));
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        // HTTP cache hints: keep dynamic/personalized responses uncacheable,
        // allow short-lived public caching for anonymous storefront GETs.
        if (! $response->headers->has('Cache-Control')) {
            $path = $request->path();
            $noStore = $request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')
                || str_starts_with($path, 'admin') || str_starts_with($path, 'cart') || str_starts_with($path, 'checkout')
                || str_starts_with($path, 'account') || str_starts_with($path, 'login') || str_starts_with($path, 'register')
                || str_starts_with($path, 'api');
            if ($noStore) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            } elseif ($request->isMethod('GET')) {
                $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
            }
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=(self "https://js.stripe.com")',
            'usb=()',
        ]));

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://js.stripe.com https://code.jquery.com https://cdn.datatables.net",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdn.datatables.net",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https://api.stripe.com",
            "frame-src 'self' https://js.stripe.com https://api.stripe.com https://hooks.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        if ($this->shouldSendStrictTransportSecurity($request)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Send HSTS only when the request actually arrived over TLS.
     *
     * Sending it on a plain-HTTP environment pins that host to HTTPS and locks
     * every operator and developer out of it. Trusting X-Forwarded-Proto keeps
     * HSTS working behind a TLS-terminating proxy, where
     * Request::isSecure() is only true if TrustProxies is enabled.
     */
    protected function shouldSendStrictTransportSecurity(Request $request): bool
    {
        if (app()->isLocal()) {
            return false;
        }

        if ($request->isSecure()) {
            return true;
        }

        return in_array(strtolower((string) $request->server('HTTP_X_FORWARDED_PROTO', '')), ['https', 'on'], true)
            || strtolower((string) $request->server('HTTP_X_FORWARDED_SCHEME', '')) === 'https';
    }
}
