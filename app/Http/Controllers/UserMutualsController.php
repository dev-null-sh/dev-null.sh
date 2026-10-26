<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserMutualsRequest;
use App\Models\User;
use App\Queries\UserConnectionsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserMutualsController
{
    /**
     * The people a user follows who follow them back.
     */
    public function index(ListUserMutualsRequest $request, #[CurrentUser] ?User $currentUser, User $user, PageMetadata $metadata, UserConnectionsQuery $connections): Response
    {
        $countedUser = null;
        $getCountedUser = function () use (&$countedUser, $user): User {
            return $countedUser ??= $user->loadPublicCounts();
        };

        return Inertia::render('user/connections', [
            'user' => fn (): User => $currentUser instanceof User ? $user : $getCountedUser(),
            'type' => ConnectionType::Mutuals,
            'users' => Inertia::scroll(function () use ($connections, $currentUser, $user) {
                $mutuals = $connections->builder($user->following()
                    ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query->from('follows', 'follows_back')
                        ->whereColumn('follows_back.follower_id', 'users.id')
                        ->where('follows_back.followed_id', $user->id)), $currentUser);

                if (! $currentUser instanceof User) {
                    $mutuals->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereRaw('1 = 0')]);
                }

                return $mutuals->cursorPaginate(30);
            }),
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->connections($getCountedUser(), ConnectionType::Mutuals)]);
    }
}
