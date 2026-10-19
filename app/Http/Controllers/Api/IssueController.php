<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateIssue;
use App\Enums\ApiErrorCode;
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
     * Opens an issue upstream, as the web's /feature and /bug commands do, and answers as the web does, with its number
     * and URL. When GitHub didn't respond, the issue may have been opened anyway, so the message says to check first.
     */
    public function store(CreateApiIssueRequest $request, #[CurrentUser] User $user, CreateIssue $action): JsonResponse
    {
        try {
            $issue = $action->handle($user, $request->string('command')->value(), $request->string('body')->value());
        } catch (ConnectionException) {
            return new ApiException(ApiErrorCode::GitHubIssueFailed, __('GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.'))->render();
        } catch (RequestException) {
            return new ApiException(ApiErrorCode::GitHubIssueFailed, __('GitHub did not create the issue. Your draft is safe.'))->render();
        }

        return response()->json($issue, 201);
    }
}
