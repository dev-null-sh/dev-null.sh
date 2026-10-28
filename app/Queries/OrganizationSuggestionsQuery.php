<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProfileType;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class OrganizationSuggestionsQuery
{
    /**
     * The organizations whose handle or name matches the term, the closest matches first. Only organizations that
     * already have a page, so typing never asks GitHub, and none for a bare "@".
     *
     * @return Collection<int, Profile>
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

        return Profile::query()
            ->where('type', ProfileType::Organization)
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(handle) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
            ->orderByRaw(
                "CASE WHEN LOWER(handle) = ? THEN 0 WHEN LOWER(handle) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$term, "{$escaped}%", "{$escaped}%"],
            )
            ->orderBy('handle')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
