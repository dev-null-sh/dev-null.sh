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
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitController
{
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

        return back();
    }
}
