<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PreventFraming
{
    /**
     * A policy the response already carries is kept, with framing forbidden on top of it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', implode('; ', array_filter([
            $response->headers->get('Content-Security-Policy'),
            "frame-ancestors 'none'",
        ])));

        return $response;
    }
}
