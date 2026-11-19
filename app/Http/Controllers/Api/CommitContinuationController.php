<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiCommitContinuationRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CommitContinuationController
{
    /**
     * The author's thread below the commit, as the web shows it, a page at a time. The next page starts from the last
     * commit in data, as next_id only names the commit that follows it.
     */
    public function index(ListApiCommitContinuationRequest $request, #[CurrentUser] User $user, Commit $commit, CommitContinuationQuery $continuation): AnonymousResourceCollection
    {
        $thread = $continuation->get($commit, $user);

        return CommitResource::collection($thread->commits)->additional([
            'position' => $thread->position,
            'length' => $thread->length,
            'is_capped' => $thread->isCapped,
            'next_id' => $thread->nextId,
        ]);
    }
}
