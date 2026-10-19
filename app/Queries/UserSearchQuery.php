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
     * @return Collection<int, User>
     */
    public function get(string $term, ?User $viewer, int $limit = 8): Collection
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
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query
                ->whereNotIn('id', $viewer->mutedIds())
                ->whereNotIn('id', $viewer->idsAcrossBlocks()))
            ->orderByRaw(
                "CASE WHEN LOWER(username) = ? THEN 0 WHEN LOWER(username) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$term, "{$escaped}%", "{$escaped}%"],
            )
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
