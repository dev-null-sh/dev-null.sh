<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleCommits
{
    private const int PER_MINUTE = 10;

    /**
     * One budget of commits a minute, however they are posted. The commit is counted first and refused when that count
     * is over the budget: commits sent at the same moment would all read room left before any of them was counted. The
     * counting itself goes one commit at a time, as the database cache can lose a count when the first commits of a
     * minute arrive together. A commit that can't get its turn to be counted is told to slow down as well, rather than
     * going through uncounted.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $key = "commits:{$user->id}";

        try {
            $count = Cache::lock("{$key}:counting", 10)->block(5, fn (): int => RateLimiter::increment($key, 60));
        } catch (LockTimeoutException) {
            return $this->slowDown($request, max(1, RateLimiter::availableIn($key)));
        }

        if ($count > self::PER_MINUTE) {
            return $this->slowDown($request, RateLimiter::availableIn($key));
        }

        return $next($request);
    }

    private function slowDown(Request $request, int $retryAfter): Response
    {
        $message = 'Slow down: 10 commits a minute. Even CI takes breaks.';

        // The `devnull commit` script prints plain text...
        if ($request->routeIs('cli-commit.store')) {
            return response($message."\n", 429, ['Retry-After' => $retryAfter])
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // The mobile app reads JSON with a code...
        if ($request->routeIs('api-commit.store')) {
            return new ApiException(ApiErrorCode::RateLimited, $message, ['Retry-After' => $retryAfter])->render();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, ['Retry-After' => $retryAfter]);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return back();
    }
}
