<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleIssues
{
    private const int PER_DAY = 10;

    /**
     * One budget of issues a day, from the web or the mobile app. Only issues that were opened count towards it, so a
     * rejected draft or a GitHub outage costs nothing.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $key = "issues:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, self::PER_DAY)) {
            $message = 'Issue limit reached: 10 a day. Try again tomorrow.';
            $headers = ['Retry-After' => RateLimiter::availableIn($key)];

            // The mobile app reads JSON with a code...
            return $request->routeIs('api-issue.store')
                ? new ApiException(ApiErrorCode::RateLimited, $message, $headers)->render()
                : response()->json(['message' => $message], 429, $headers);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 201) {
            RateLimiter::hit($key, 86400);
        }

        return $response;
    }
}
