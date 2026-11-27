<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateIssue;
use App\Exceptions\ApiException;
use App\Http\Requests\CreateApiIssueRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

final readonly class IssueController
{
    /**
     * Opens a /feature or /bug issue as the web does. When GitHub fails, the app keeps the draft.
     */
    public function store(CreateApiIssueRequest $request, #[CurrentUser] User $user, CreateIssue $action): JsonResponse
    {
        try {
            $issue = $action->handle(
                $user,
                $request->string('command')->value(),
                $request->string('body')->value(),
                $request->environment(),
            );
        } catch (ConnectionException) {
            throw ApiException::gitHubIssueUnanswered();
        } catch (RequestException) {
            throw ApiException::gitHubIssueRefused();
        }

        return response()->json($issue, 201);
    }
}
