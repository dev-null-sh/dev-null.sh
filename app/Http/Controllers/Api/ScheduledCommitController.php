<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateScheduledCommit;
use App\Http\Requests\CreateApiScheduledCommitRequest;
use App\Http\Resources\ScheduledCommitResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class ScheduledCommitController
{
    /**
     * Schedules the commit, as the web's Schedule commit does. Only the user sees it until it is due, when it is
     * submitted for review like any commit. Answers with the scheduled commit.
     */
    public function store(CreateApiScheduledCommitRequest $request, #[CurrentUser] User $user, CreateScheduledCommit $action): JsonResponse
    {
        $scheduled = $action->handle($user, $request->string('body')->value(), $request->scheduledAt());

        return ScheduledCommitResource::make($scheduled)
            ->response()
            ->setStatusCode(201);
    }
}
