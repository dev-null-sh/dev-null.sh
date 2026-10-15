<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StarCommit;
use App\Actions\UnstarCommit;
use App\Http\Requests\CreateCommitStarRequest;
use App\Http\Requests\DeleteCommitStarRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitStarController
{
    public function store(CreateCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, StarCommit $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return back();
    }

    public function destroy(DeleteCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit, UnstarCommit $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return back();
    }
}
