<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectBannedUsers
{
    /**
     * The routes a banned user still reaches: the page that tells them why, the terms and privacy policy it points to,
     * deleting their account, and signing out.
     */
    private const array ALLOWED_ROUTES = ['banned.show', 'terms.show', 'privacy.show', 'user.destroy', 'logout'];

    /**
     * A banned user is turned away from everything else, so all they can do is read why and delete their account.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $request->routeIs(...self::ALLOWED_ROUTES) || ! $user->isBanned()) {
            return $next($request);
        }

        // Requests from the page say it's a ban, so the page can go where the user is sent...
        if ($request->expectsJson()) {
            return response()->json(['message' => __('Your account is banned from /dev/null.'), 'banned' => true], 403);
        }

        return to_route('banned.show');
    }
}
