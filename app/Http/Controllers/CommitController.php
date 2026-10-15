<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Http\Requests\DeleteCommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitController
{
    public function show(#[CurrentUser] User $currentUser, User $user, Commit $commit, CommitAncestorsQuery $ancestors, CommitRepliesQuery $replies): Response
    {
        $commit = Commit::query()
            ->visibleTo($currentUser)
            ->withVisibleReplyCount($currentUser)
            ->with('user')
            ->findOrFail($commit->id);

        return Inertia::render('commit/show', [
            'commit' => $commit,
            'ancestors' => $ancestors->get($commit, $currentUser),
            'replies' => $replies->get($commit, $currentUser),
        ]);
    }

    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $request->string('body')->value(), $request->parent());

        Inertia::flash('committed', [
            'id' => $commit->id,
            'body' => $commit->body,
        ]);

        if ($commit->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Your commit is pending review. Everyone will see it once it is approved.'),
            ]);
        }

        return back();
    }

    public function destroy(DeleteCommitRequest $request, Commit $commit, DeleteCommit $action): RedirectResponse
    {
        $action->handle($commit);

        if (url()->previous() === route('commit.show', [$commit->user, $commit])) {
            return to_route('user.show', $commit->user);
        }

        return back();
    }
}
