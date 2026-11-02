<?php

declare(strict_types=1);

namespace App\Queries;

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
}
