<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserFollows;
use App\Http\Requests\CreateGitHubPeopleFollowRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class GitHubPeopleFollowController
{
    public function store(CreateGitHubPeopleFollowRequest $request, #[CurrentUser] User $user, CreateUserFollows $action): RedirectResponse
    {
        $count = $action->handle($user, $request->people());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Following :count new developer.|Following :count new developers.', $count, ['count' => $count]),
        ]);

        return to_route('github-people.index');
    }
}
