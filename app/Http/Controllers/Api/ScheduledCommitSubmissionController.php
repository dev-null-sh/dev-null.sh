<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Exceptions\ApiException;
use App\Exceptions\GuildPaused;
use App\Http\Requests\CreateApiScheduledCommitSubmissionRequest;
use App\Http\Resources\ScheduledCommitResource;
use App\Models\ScheduledCommit;
use Illuminate\Http\JsonResponse;

final readonly class ScheduledCommitSubmissionController
{
    /**
     * Commits it now, before its time, and answers with it as the queue now shows it. It goes through the usual review.
     * A board post to a paused guild stays queued, and is a guild_paused error.
     */
    public function store(CreateApiScheduledCommitSubmissionRequest $request, ScheduledCommit $scheduledCommit, UpdateScheduledCommitSubmittedAt $action): ScheduledCommitResource|JsonResponse
    {
        try {
            $submitted = $action->handle($scheduledCommit->id, early: true);
        } catch (GuildPaused) {
            return ApiException::guildPaused()->render();
        }

        return $submitted
            ? ScheduledCommitResource::make($scheduledCommit->refresh()->load('commit'))
            : response()->json(['message' => 'This commit was already submitted or canceled. Refresh to see its status.'], 409);
    }
}
