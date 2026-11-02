<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitPollVote;
use App\Http\Requests\CreateApiCommitPollVoteRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitPollVoteController
{
    /**
     * Votes on the commit's poll, as the web's Vote button does. It answers with the commit as its page shows it, so
     * the app gets the poll with its results, which show once the user voted.
     */
    public function store(CreateApiCommitPollVoteRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitPollVote $action, CommitPageQuery $page): CommitResource
    {
        $action->handle($user, $request->option());

        return CommitResource::make($page->get($commit, $user));
    }
}
