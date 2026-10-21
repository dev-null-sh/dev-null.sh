<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIssue;
use App\Http\Requests\CreateIssueRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

final readonly class IssueController
{
    private const int MaxIssuesPerDay = 10;

    /**
     * Only issues that were opened count towards the daily limit, so a rejected draft or a GitHub outage costs nothing.
     */
    public function store(CreateIssueRequest $request, #[CurrentUser] User $user, CreateIssue $action): JsonResponse
    {
        $key = 'issues:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MaxIssuesPerDay)) {
            return response()->json(['message' => 'Issue limit reached: 10 a day. Try again tomorrow.'], 429);
        }

        try {
            $issue = $action->handle(
                $user,
                $request->string('command')->value(),
                $request->string('body')->value(),
                $request->environment(),
            );
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.',
            ], 502);
        } catch (RequestException) {
            return response()->json([
                'message' => 'GitHub did not create the issue. Your draft is safe.',
            ], 502);
        }

        RateLimiter::hit($key, 86400);

        return response()->json($issue, 201);
    }
}
