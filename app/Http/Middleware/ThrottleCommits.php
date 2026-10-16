<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\CreateCommitSeries;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleCommits
{
    private const int PER_MINUTE = 10;

    /**
     * One budget of commits a minute, however they are posted: each part of a series spends one, and a series that
     * no longer fits is refused as a whole.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $key = "commits:{$user->id}";
        $commits = $request->routeIs('commit-series.store')
            ? min(max(count(Arr::wrap($request->input('bodies'))), 1), CreateCommitSeries::MAX_PARTS)
            : 1;

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE - $commits + 1)) {
            return $this->slowDown($request, RateLimiter::availableIn($key));
        }

        RateLimiter::increment($key, 60, $commits);

        return $next($request);
    }

    private function slowDown(Request $request, int $retryAfter): Response
    {
        $message = __('Slow down: 10 commits a minute. Even CI takes breaks.');

        // The `devnull commit` script prints plain text...
        if ($request->routeIs('cli-commit.store')) {
            return response($message."\n", 429, ['Retry-After' => $retryAfter])
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return back();
    }
}
