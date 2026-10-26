<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserCommitVisibility;
use App\Http\Requests\UpdateUserCommitVisibilityRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserCommitVisibilityController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('commit-visibility/edit', [
            'hiddenCommitChannels' => $user->hidden_commit_channels,
        ]);
    }

    public function update(UpdateUserCommitVisibilityRequest $request, #[CurrentUser] User $user, UpdateUserCommitVisibility $action): RedirectResponse
    {
        $action->handle($user, $request->channels());

        Inertia::clearHistory();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Commit visibility saved.',
        ]);

        return back();
    }
}
