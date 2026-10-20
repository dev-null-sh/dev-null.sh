<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommitStatus;
use App\Http\Requests\ListCommitRevisionsRequest;
use App\Models\Commit;
use App\Models\CommitRevision;
use App\Services\CommitBodies;
use Illuminate\Http\JsonResponse;

final readonly class CommitRevisionController
{
    /**
     * Every body the commit has had, newest first, each with the moment it was committed.
     */
    public function index(ListCommitRevisionsRequest $request, Commit $commit, CommitBodies $bodies): JsonResponse
    {
        $originalId = $commit->revisions()->reorder()->oldest()->oldest('id')->value('id');

        $current = collect([[
            'body' => $commit->body,
            'mentions' => $commit->mentions,
            'is_original' => $originalId === null,
            'review' => match ($commit->status) {
                CommitStatus::Pending => 'pending',
                CommitStatus::Rejected => 'rejected',
                default => null,
            },
            'created_at' => $commit->edited_at ?? $commit->created_at,
        ]]);

        $versions = $current
            ->concat($commit->revisions()->get()->map(fn (CommitRevision $revision): array => [
                'body' => $revision->body,
                'mentions' => $revision->mentions,
                'is_original' => $revision->id === $originalId,
                'review' => null,
                'created_at' => $revision->created_at,
            ]))
            ->map(fn (array $version): array => [
                'body_segments' => $bodies->segments($version['body'])['body_segments'],
                'created_at' => $version['created_at']->toJSON(),
                'mentions' => $version['mentions'],
                'is_original' => $version['is_original'],
                'review' => $version['review'],
            ]);

        return response()->json(['data' => $versions->values()]);
    }
}
