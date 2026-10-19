<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\UpdateUserLastSeenAt;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class UpdateLastSeenAt
{
    public function __construct(private UpdateUserLastSeenAt $updateUserLastSeenAt)
    {
        //
    }

    /**
     * Someone is seen when they open a page or do something on the web, like starring or replying, or use the API from
     * the mobile app or the terminal. What a page reads on its own, like polling for notifications, partial reloads
     * and prefetches, doesn't count, or a tab left open would keep its owner active forever. The user is read once the
     * request is handled, since the API only signs it in along the way. A failure is reported, never thrown: counting
     * a visit must not break the page it counts.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        $isPageVisit = $request->isMethod('GET')
            && ! $request->prefetch()
            && ! $request->hasHeader('X-Inertia-Partial-Data')
            && ($request->hasHeader('X-Inertia') || ! $request->expectsJson());

        if ($user instanceof User && $response->isSuccessful() && ($request->is('api/*') || ! $request->isMethod('GET') || $isPageVisit)) {
            rescue(fn () => $this->updateUserLastSeenAt->handle($user));
        }

        return $response;
    }
}
