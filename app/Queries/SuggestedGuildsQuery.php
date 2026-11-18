<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class SuggestedGuildsQuery
{
    /** @return Collection<int, Guild> */
    public function get(User $user, int $limit = 3): Collection
    {
        $following = $user->following();
        $followingIds = $following->newPivotQuery()->select($following->getRelatedPivotKeyName());

        return Guild::query()
            ->select('id')
            ->whereNotNull('user_id')
            ->whereDoesntHave('members', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->whereDoesntHave('muters', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->withCount([
                'members',
                'members as followed_members_count' => fn (Builder $query): Builder => $query->whereIn('users.id', $followingIds),
            ])
            ->orderByDesc('followed_members_count')
            ->orderByDesc('members_count')
            ->orderBy('handle')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
