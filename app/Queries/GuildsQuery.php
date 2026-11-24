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
    /** @return Collection<int, Guild> */
    public function joined(User $user, User $viewer): Collection
    {
        return $this->withPreferencesOf($viewer)->whereHas('members', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->orderBy('handle')->get();
    }

    /**
     * @param  list<string>  $handles
     * @return Collection<int, Guild>
     */
    public function get(User $user, array $handles): Collection
    {
        return $this->withPreferencesOf($user)->whereIn('handle', $handles)->get();
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
     * @param  list<string>  $handles
     * @return Collection<int, Guild>
     */
    public function references(array $handles): Collection
    {
        return new Collection(collect(array_chunk($handles, 65535))->flatMap(fn (array $chunk): Collection => Guild::query()
            ->select(['handle', 'name', 'emoji', 'icon_path', 'updated_at'])
            ->whereIn('handle', $chunk)->orderBy('handle')->get())->all());
    }

    /** @return Collection<int, Guild> */
    public function suggestions(string $term): Collection
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return Guild::query()->select(['handle', 'name', 'emoji', 'icon_path', 'updated_at'])->withCount('members')
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw('TRANSLATE(LOWER(handle COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])
                ->orWhereRaw('TRANSLATE(LOWER(name COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"]))
            ->orderByRaw('CASE WHEN TRANSLATE(LOWER(handle COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\' THEN 0 ELSE 1 END', ["{$escaped}%"])
            ->orderByDesc('members_count')->orderBy('handle')->limit(6)->get();
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
