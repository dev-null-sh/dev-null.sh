<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class UserConnectionsQuery
{
    /**
     * Any of a user's connection lists, as the web lists them: the newest follow first, with whether the
     * viewer follows each one, and whether each one follows them.
     *
     * @param  BelongsToMany<User, User>  $connections
     * @return BelongsToMany<User, User>
     */
    public function builder(BelongsToMany $connections, User $viewer): BelongsToMany
    {
        return $connections
            ->withExists([
                'followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
                'following as follows_you' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
            ])
            ->latest('follows.created_at')
            ->latest('users.id');
    }

    /**
     * The people a user follows who follow them back.
     *
     * @return BelongsToMany<User, User>
     */
    public function mutuals(User $user): BelongsToMany
    {
        return $user->following()
            ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query->from('follows', 'follows_back')
                ->whereColumn('follows_back.follower_id', 'users.id')
                ->where('follows_back.followed_id', $user->id));
    }

    /**
     * Which of a list a page loaded, as a digest of the sorted ids of as many as it loaded from the start, so a page
     * that may have missed follows changing can tell whether its loaded pages still hold the same, even when as many
     * left as arrived. An empty page asks about one, as anyone at all is news to it.
     *
     * @param  BelongsToMany<User, User>|BelongsToMany<Organization, User>  $connections
     */
    public function loadedDigest(BelongsToMany $connections, int $loaded): string
    {
        /** @var list<string> $ids */
        $ids = $connections->limit(max($loaded, 1))->pluck($connections->getRelated()->getQualifiedKeyName())->all();
        sort($ids, SORT_STRING);

        return hash('sha256', implode(',', $ids));
    }
}
