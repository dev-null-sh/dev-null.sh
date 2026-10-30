<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class OrganizationSuggestionsQuery
{
    /**
     * The organizations whose handle or name matches the term, the closest matches first. Only organizations that
     * already have a page, so typing never asks GitHub, and none for a bare "@".
     *
     * @return Collection<int, Organization>
     */
    public function get(string $term, int $limit = 3): Collection
    {
        $term = Str::of($term)
            ->trim()
            ->ltrim('@')
            ->lower()
            ->value();

        if ($term === '') {
            return collect();
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return Organization::query()
            ->select('organizations.*')
            ->join('profiles', 'profiles.id', '=', 'organizations.id')
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(profiles.handle) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(profiles.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
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
