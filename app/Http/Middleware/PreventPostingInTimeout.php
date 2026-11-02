<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserTimeout;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final readonly class PreventPostingInTimeout
{
    /**
     * A user in timeout can still read, but nothing they post goes through until it's over, however they post it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $timeout = $user->activeTimeout();

        if (! $timeout instanceof UserTimeout) {
            return $next($request);
        }

        $message = $timeout->message();

        // The `devnull commit` script prints plain text...
        if ($request->routeIs('cli-commit.store')) {
            return response($message."\n", 403)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // The mobile app reads JSON with a code...
        if ($request->is('api/v1/*')) {
            return new ApiException(ApiErrorCode::TimedOut, $message)->render();
        }

        // The web shows a dialog instead, from the details...
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'timeout' => $timeout->details()], 403);
        }

        Inertia::flash('timeout', $timeout->details());

        return back();
    }
}
