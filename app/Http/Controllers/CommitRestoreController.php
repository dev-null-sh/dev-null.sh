<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RestoreCommit;
use App\Http\Requests\RestoreCommitRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitRestoreController
{
    public function __invoke(RestoreCommitRequest $request, #[CurrentUser] User $user, Commit $commit, RestoreCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $commit);

        Inertia::flash('restored', Commit::query()->withVisibleReplyCount($user)->with([
            'user',
            'parent' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->visibleTo($user)->withVisibleReplyCount($user)->with('user'),
        ])->findOrFail($commit->id));

        return back();
    }
}
