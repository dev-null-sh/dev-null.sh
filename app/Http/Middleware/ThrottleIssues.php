<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\CreateIssue;
use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleIssues
{
    private const int DECAY_SECONDS = 86400;

    /**
     * One budget of issues a day, however they are opened. Only issues GitHub opened count, so a rejected draft or a
     * GitHub outage costs nothing.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $key = "issues:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, CreateIssue::MAX_PER_DAY)) {
            return $this->tooMany($request, RateLimiter::availableIn($key));
        }

        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_CREATED) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }

        return $response;
    }

    private function tooMany(Request $request, int $retryAfter): Response
    {
        $message = 'Issue limit reached: '.CreateIssue::MAX_PER_DAY.' a day. Try again tomorrow.';

        // The apps read JSON with a code...
        if ($request->routeIs('api-issue.store')) {
            return new ApiException(ApiErrorCode::RateLimited, $message, ['Retry-After' => $retryAfter])->render();
        }

        return response()->json(['message' => $message], 429);
    }
}
