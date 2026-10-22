<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListUserFriendshipsRequest;
use App\Models\User;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFriendshipsController
{
    /**
     * The people a user follows who follow them back.
     */
    public function index(ListUserFriendshipsRequest $request, #[CurrentUser] ?User $currentUser, User $user, PageMetadata $metadata): Response
    {
        $countedUser = null;
        $getCountedUser = function () use (&$countedUser, $user): User {
            return $countedUser ??= $user->loadPublicCounts();
        };

        return Inertia::render('user/connections', [
            'user' => fn (): User => $currentUser instanceof User ? $user : $getCountedUser(),
            'type' => 'friendships',
            'users' => Inertia::scroll(fn () => $user->following()
                ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query->from('follows', 'follows_back')
                    ->whereColumn('follows_back.follower_id', 'users.id')
                    ->where('follows_back.followed_id', $user->id))
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $currentUser instanceof User ? $query->whereKey($currentUser->id) : $query->whereRaw('1 = 0')])
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->connections($getCountedUser(), 'friendships')]);
    }
}
