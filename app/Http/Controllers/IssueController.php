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

final readonly class IssueController
{
    /**
     * Only issues that were opened count towards the hourly limit, so a rejected draft or a GitHub outage costs nothing
     * (see ThrottleIssues).
     */
    public function store(CreateIssueRequest $request, #[CurrentUser] User $user, CreateIssue $action): JsonResponse
    {
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

        return response()->json($issue, 201);
    }
}
