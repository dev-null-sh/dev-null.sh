<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListCommitStargazersRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitStargazersController
{
    public function index(ListCommitStargazersRequest $request, #[CurrentUser] User $currentUser, User $user, Commit $commit): Response
    {
        return Inertia::render('commit/stargazers', [
            'commit' => $commit->only(['id', 'body']) + ['user' => $user, 'stars_count' => $commit->stars()->count()],
            'users' => Inertia::scroll(fn () => $commit->stars()
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id)])
                ->latest('stars.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ]);
    }
}
