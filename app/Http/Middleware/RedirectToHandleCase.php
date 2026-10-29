<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectToHandleCase
{
    /**
     * "@handle" pages open whatever the handle's case, like GitHub's, then move for good to the handle's own case, so
     * each page has a single URL for links and search engines.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $user = $route->parameter('user');

        if ($user instanceof User && $route->originalParameter('user') !== $user->handle) {
            return to_route((string) $route->getName(), [...$route->parameters(), 'user' => $user->handle, ...$request->query()], 301);
        }

        return $next($request);
    }
}
