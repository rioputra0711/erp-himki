<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan respons (checklist OWASP: security headers).
 *
 * CSP membatasi sumber skrip/gaya ke origin sendiri. Saat pengembangan lokal,
 * server Vite (localhost:5173) diizinkan agar hot reload tetap bekerja.
 */
final class LindungiRespons
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $viteDev = app()->environment('local')
            ? ' http://localhost:5173 ws://localhost:5173'
            : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'{$viteDev}",
            "style-src 'self' 'unsafe-inline'{$viteDev}",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'{$viteDev}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $nama => $nilai) {
            $response->headers->set($nama, $nilai);
        }

        return $response;
    }
}
