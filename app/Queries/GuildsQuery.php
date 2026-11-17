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
    public function get(User $user): Collection
    {
        return Guild::query()->withCount('members')->withExists([
            'members as is_joined' => fn (Builder $query): Builder => $query->whereKey($user->id),
            'muters as is_muted' => fn (Builder $query): Builder => $query->whereKey($user->id),
        ])->orderByDesc('members_count')->orderBy('handle')->get();
    }

    /** @return LengthAwarePaginator<int, Guild> */
    public function paginate(string $term, int $page): LengthAwarePaginator
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return Guild::query()->select('id')->withCount('members')
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereRaw('TRANSLATE(LOWER(handle COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])
                ->orWhereRaw('TRANSLATE(LOWER(name COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])
                ->orWhereRaw('TRANSLATE(LOWER(description COLLATE "pg_c_utf8"), \'ς\', \'σ\') LIKE TRANSLATE(LOWER(? COLLATE "pg_c_utf8"), \'ς\', \'σ\') ESCAPE \'!\'', ["%{$escaped}%"])))
            ->orderByDesc('members_count')->orderBy('handle')
            ->paginate(24, page: $page);
    }
}
