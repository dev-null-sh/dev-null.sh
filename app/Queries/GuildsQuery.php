<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GuildsQuery
{
    /** @return list<string> */
    public function joinedHandles(User $user): array
    {
        return array_values(Guild::query()->whereHas('members', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->orderBy('handle')->get(['handle'])->map(fn (Guild $guild): string => $guild->handle)->all());
    }

    /** @return Collection<int, Guild> */
    public function get(User $user): Collection
    {
        return $this->withPreferencesOf($user)->orderByDesc('members_count')->orderBy('handle')->get();
    }

    public function find(Guild $guild, User $user): Guild
    {
        return $this->withPreferencesOf($user)->whereKey($guild->id)->sole();
    }

    /** @return LengthAwarePaginator<int, Guild> */
    public function paginate(string $term, int $page, User $user): LengthAwarePaginator
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return $this->withPreferencesOf($user)
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereRaw('TRANSLATE(LOWER(handle COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])
                ->orWhereRaw('TRANSLATE(LOWER(name COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])
                ->orWhereRaw('TRANSLATE(LOWER(description COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])))
            ->orderByDesc('members_count')->orderBy('handle')
            ->paginate(24, page: $page);
    }

    /**
     * The member count and whether the user joined or muted each guild come from one read, so they always agree.
     *
     * @return Builder<Guild>
     */
    private function withPreferencesOf(User $user): Builder
    {
        return Guild::query()->withCount('members')->withExists([
            'members as is_joined' => fn (Builder $query): Builder => $query->whereKey($user->id),
            'muters as is_muted' => fn (Builder $query): Builder => $query->whereKey($user->id),
        ]);
    }
}
