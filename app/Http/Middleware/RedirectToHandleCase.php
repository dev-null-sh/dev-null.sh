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
     * each page has a single URL for links and search engines. Browsers keep the move for a day only, since the handle's
     * case may change on GitHub.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $user = $route->parameter('user');

        if ($user instanceof User && $route->originalParameter('user') !== $user->handle) {
            $query = $request->getQueryString();

            return redirect(route((string) $route->getName(), [...$route->parameters(), 'user' => $user->handle]).($query === null ? '' : "?{$query}"), 301, ['Cache-Control' => 'max-age=86400, private']);
        }

        return $next($request);
    }
}
