<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Http\Requests\CreateApiScheduledCommitSubmissionRequest;
use App\Http\Resources\ScheduledCommitResource;
use App\Models\ScheduledCommit;
use Illuminate\Http\JsonResponse;

final readonly class ScheduledCommitSubmissionController
{
    /**
     * Commits it now, before its time, and answers with it as the queue now shows it. It goes through the usual review.
     */
    public function store(CreateApiScheduledCommitSubmissionRequest $request, ScheduledCommit $scheduledCommit, UpdateScheduledCommitSubmittedAt $action): ScheduledCommitResource|JsonResponse
    {
        return $action->handle($scheduledCommit->id, early: true)
            ? ScheduledCommitResource::make($scheduledCommit->refresh()->load('commit'))
            : response()->json(['message' => 'This commit was already submitted or canceled. Refresh to see its status.'], 409);
    }
}
