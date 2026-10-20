<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class UserSearchQuery
{
    /**
     * Bio matches rank after every username and name match, so pickers that only want people by handle can leave them out.
     *
     * @return Collection<int, User>
     */
    public function get(string $term, ?User $viewer, int $limit = 8, bool $bio = false): Collection
    {
        $term = Str::of($term)
            ->trim()
            ->ltrim('@')
            ->lower()
            ->value();

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        if ($term === '') {
            return new Collection();
        }

        return User::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->when($bio, fn (Builder $query): Builder => $query->orWhereRaw("LOWER(bio) LIKE ? ESCAPE '!'", ["%{$escaped}%"])))
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query
                ->whereNotIn('id', $viewer->mutedIds())
                ->whereNotIn('id', $viewer->idsAcrossBlocks()))
            ->orderByRaw(
                "CASE WHEN LOWER(username) = ? THEN 0 WHEN LOWER(username) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 2 WHEN LOWER(username) LIKE ? ESCAPE '!' OR LOWER(name) LIKE ? ESCAPE '!' THEN 3 ELSE 4 END",
                [$term, "{$escaped}%", "{$escaped}%", "%{$escaped}%", "%{$escaped}%"],
            )
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
