<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class UserConnectionsQuery
{
    /**
     * A user's followers, or the people they follow, as the web lists them: the newest follow first, with whether the
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
