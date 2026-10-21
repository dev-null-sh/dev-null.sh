<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListUserSearchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\UserSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class UserSearchController
{
    /**
     * People matching a name or GitHub username, at most eight, as the web search does. A signed request also gets
     * each person's lane and whether you follow each other, and leaves out muted people and people across a block.
     */
    public function index(ListUserSearchRequest $request, #[CurrentUser] ?User $currentUser, UserSearchQuery $search): AnonymousResourceCollection
    {
        $users = $search->get($request->string('q')->value(), $currentUser);

        if ($currentUser instanceof User && $users->isNotEmpty()) {
            $flags = User::query()
                ->whereKey($users->pluck('id'))
                ->withExists([
                    'followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id),
                    'following as follows_you' => fn (Builder $query): Builder => $query->whereKey($currentUser->id),
                ])
                ->get()
                ->keyBy('id');

            $users = $users->map(function (User $user) use ($flags): User {
                $match = $flags->get($user->id);

                return $match instanceof User ? $match : $user;
            });
        }

        return UserResource::collection($users);
    }
}
