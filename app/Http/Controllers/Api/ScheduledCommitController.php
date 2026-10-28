<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateScheduledCommit;
use App\Actions\DeleteScheduledCommit;
use App\Http\Requests\CreateApiScheduledCommitRequest;
use App\Http\Requests\DeleteApiScheduledCommitRequest;
use App\Http\Resources\ScheduledCommitResource;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Queries\ScheduledCommitsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class ScheduledCommitController
{
    /**
     * The user's queue, as the web's Scheduled page lists it, 20 a page.
     */
    public function index(#[CurrentUser] User $user, ScheduledCommitsQuery $scheduled): AnonymousResourceCollection
    {
        return ScheduledCommitResource::collection($scheduled->builder($user)->paginate(20));
    }

    /**
     * Only the user sees a scheduled commit until it is submitted.
     */
    public function store(CreateApiScheduledCommitRequest $request, #[CurrentUser] User $user, CreateScheduledCommit $action): JsonResponse
    {
        $scheduled = $action->handle($user, $request->string('body')->value(), CarbonImmutable::parse($request->string('scheduled_at')->value())->utc(), $request->images(), $request->imageDescriptions(), $request->poll(), $request->linkPreview());

        return ScheduledCommitResource::make($scheduled)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * One that was already submitted or canceled can't be canceled, so the app is told to refresh, as the web says.
     */
    public function destroy(DeleteApiScheduledCommitRequest $request, #[CurrentUser] User $user, ScheduledCommit $scheduledCommit, DeleteScheduledCommit $action): Response|JsonResponse
    {
        return $action->handle($user, $scheduledCommit)
            ? response()->noContent()
            : response()->json(['message' => 'This commit was already submitted or canceled. Refresh to see its status.'], 409);
    }
}
