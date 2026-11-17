<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class GuildsQuery
{
    /** @return Collection<int, Guild> */
    public function get(User $user): Collection
    {
        return Guild::query()->withCount('members')->withExists([
            'members as is_joined' => fn (Builder $query): Builder => $query->whereKey($user->id),
            'muters as is_muted' => fn (Builder $query): Builder => $query->whereKey($user->id),
        ])->orderByDesc('members_count')->orderBy('handle')->get();
    }
}
