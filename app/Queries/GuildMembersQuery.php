<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class GuildMembersQuery
{
    /**
     * A guild's members as its page lists them: the moderator first, then the newest joins.
     *
     * @return BelongsToMany<User, Guild>
     */
    public function builder(Guild $guild): BelongsToMany
    {
        return $guild->members()
            ->orderByRaw('(users.id = ?) desc nulls last', [$guild->user_id])
            ->orderByPivot('joined_at', 'desc')
            ->latest('users.id');
    }
}
