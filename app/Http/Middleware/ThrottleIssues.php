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
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleIssues
{
    private const int PER_DAY = 10;

    private const int DECAY_SECONDS = 86400;

    /**
     * One budget of issues a day, however they are opened. Only issues GitHub opened count, so a rejected draft or a
     * GitHub outage costs nothing.
     *
     * Asking GitHub takes a while, and issues sent at the same moment would all find room left before any of them was
     * counted. So each one takes its place first, one at a time, and gives it back unless GitHub opened it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $key = "issues:{$user->id}";

        try {
            $count = Cache::lock("{$key}:counting", 10)->block(5, fn (): int => RateLimiter::increment($key, self::DECAY_SECONDS));
        } catch (LockTimeoutException) {
            return $this->tooMany($request, max(1, RateLimiter::availableIn($key)));
        }

        if ($count > self::PER_DAY) {
            $this->giveBack($key);

            return $this->tooMany($request, RateLimiter::availableIn($key));
        }

        $response = $next($request);

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            $this->giveBack($key);
        }

        return $response;
    }

    /**
     * An empty budget is forgotten, so the day still starts with the first issue GitHub opened.
     */
    private function giveBack(string $key): void
    {
        if (RateLimiter::increment($key, self::DECAY_SECONDS, -1) <= 0) {
            RateLimiter::clear($key);
        }
    }

    private function tooMany(Request $request, int $retryAfter): Response
    {
        $message = 'Issue limit reached: '.self::PER_DAY.' a day. Try again tomorrow.';

        // The apps read JSON with a code...
        if ($request->routeIs('api-issue.store')) {
            return new ApiException(ApiErrorCode::RateLimited, $message, ['Retry-After' => $retryAfter])->render();
        }

        return response()->json(['message' => $message], 429);
    }
}
