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
use Illuminate\Pagination\CursorPaginator;

final readonly class CommitContinuationController
{
    /**
     * The commits that follow the commit in its author's thread, as the web reads it, paged like its replies: the
     * cursor continues from the last commit of a page. position is the place in the thread of the commit a page
     * continues from and length how many commits follow it, so position + length is the size of the thread, and
     * is_capped says it goes on past that.
     */
    public function index(ListApiCommitContinuationRequest $request, #[CurrentUser] User $user, Commit $commit, CommitContinuationQuery $continuation): AnonymousResourceCollection
    {
        $thread = $continuation->get($request->continuedCommit() ?? $commit, $user);
        $commits = $thread->commits->values();
        $nextIds = $commits->skip(1)->pluck('id')->push($thread->nextId)->values();

        foreach ($commits as $index => $listed) {
            $listed->setAttribute('continuation_id', $nextIds[$index]);
        }

        // The paginator only links to a next page when it is given one item more than a page.
        $page = new CursorPaginator(
            $thread->nextId === null ? $commits : $commits->concat([new Commit]),
            CommitContinuationQuery::PER_PAGE,
            options: ['path' => $request->url(), 'parameters' => ['id']],
        );

        return CommitResource::collection($page)->additional(['meta' => [
            'position' => $thread->position,
            'length' => $thread->length,
            'is_capped' => $thread->isCapped,
        ]]);
    }
}
