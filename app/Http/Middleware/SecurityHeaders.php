<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers a browser reads before it trusts anything on the page. "PreventFraming" keeps its own concern,
 * so this only adds what it does not already cover.
 */
final readonly class SecurityHeaders
{
    /**
     * Only over HTTPS: browsers ignore the header on plain HTTP, and sending it there would pin local
     * development to a scheme it does not serve. "includeSubDomains" and "preload" are left off on purpose,
     * since either one breaks any subdomain that is not HTTPS-only yet.
     */
    private const string HSTS = 'max-age=31536000';

    /**
     * Recording a voice note is the one capability the app asks for, so the microphone stays available to the
     * page itself and to nobody else. Everything it never asks for is denied outright.
     */
    private const string PERMISSIONS = 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=()';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', self::HSTS);
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', self::PERMISSIONS);

        return $response;
    }
}
