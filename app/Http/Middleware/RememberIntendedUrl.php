<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RememberIntendedUrl
{
    /**
     * Guests return to the public page they were reading once they sign in, like after starring or replying to a
     * commit. Prefetches and partial reloads are not pages the guest is reading, so they are left out.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null || ! $response->isSuccessful() || $request->prefetch() || $request->hasHeader('X-Inertia-Partial-Data')) {
            return $response;
        }

        redirect()->setIntendedUrl($request->fullUrl());

        if (app()->isLocal() && config('app.auto_login')) {
            return to_route('home');
        }

        return $response;
    }
}
