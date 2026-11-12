<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitRestoration;
use App\Http\Requests\CreateApiCommitRestorationRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class CommitRestorationController
{
    /**
     * Undoes deleting the user's commit within the undo window, as Undo on the web does, and answers with the commit
     * as its page shows it.
     */
    public function store(CreateApiCommitRestorationRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitRestoration $action, CommitPageQuery $page): CommitResource
    {
        return CommitResource::make($page->get($action->handle($commit), $user));
    }
}
