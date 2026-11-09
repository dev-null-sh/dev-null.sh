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

        // Commit embeds are made to be framed by other sites, and send their own policy...
        if ($request->routeIs('commit-embed.show')) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', implode('; ', array_filter([
            $response->headers->get('Content-Security-Policy'),
            "frame-ancestors 'none'",
        ])));

        return $response;
    }
}
