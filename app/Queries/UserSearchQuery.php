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
            ->select('users.*')
            ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(profiles.handle) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(profiles.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query
                ->whereNotIn('users.id', $viewer->mutedIds())
                ->whereNotIn('users.id', $viewer->idsAcrossBlocks()))
            ->orderByRaw(
                "CASE WHEN LOWER(profiles.handle) = ? THEN 0 WHEN LOWER(profiles.handle) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(profiles.name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$term, "{$escaped}%", "{$escaped}%"],
            )
            ->orderBy('profiles.handle')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
