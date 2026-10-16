<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListUserFollowersRequest;
use App\Models\User;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowersGraphController
{
    public function show(ListUserFollowersRequest $request, #[CurrentUser] ?User $currentUser, User $user, PageMetadata $metadata): Response
    {
        // The graph gets unreadable and slow past a few hundred nodes, so cap
        // what is drawn while still reporting the real total.
        $limit = 250;

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $followers */
        $followers = $user->followers()
            ->withCount('followers')
            ->latest('follows.created_at')
            ->latest('users.id')
            ->limit($limit)
            ->get(['users.id', 'users.username', 'users.name', 'users.avatar_url']);

        $followerIds = $followers->pluck('id')->all();

        /** @var list<string> $followedBackIds */
        $followedBackIds = $user->following()->whereKey($followerIds)->pluck('users.id')->all();

        $followers->load(['following' => fn (BelongsToMany $query): BelongsToMany => $query->whereKey($followerIds)]);

        /** @var list<array{source: string, target: string}> $edges */
        $edges = [];

        foreach ($followers as $follower) {
            foreach ($follower->following as $followed) {
                $edges[] = ['source' => $follower->id, 'target' => $followed->id];

                if (count($edges) === 2000) {
                    break 2;
                }
            }
        }

        return Inertia::render('user/followers-graph', [
            'user' => $user->loadPublicCounts(),
            'nodes' => $followers->map(fn (User $follower): array => [
                'id' => $follower->id,
                'username' => $follower->username,
                'name' => $follower->name,
                'avatar_url' => $follower->avatar_url,
                'lane' => $follower->lane(),
                'followers_count' => $follower->followers_count,
                'followed_back' => in_array($follower->id, $followedBackIds, true),
            ])->all(),
            'edges' => $edges,
            'totalFollowers' => $user->followers()->newPivotQuery()->count(),
            'limit' => $limit,
        ])->withViewData(['preview' => $currentUser instanceof User ? null : $metadata->followersGraph($user->loadPublicCounts())]);
    }
}
