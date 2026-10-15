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
    public function get(string $term, int $limit = 8): Collection
    {
        $raw = Str::of($term)
            ->trim()
            ->ltrim('@')
            ->lower()
            ->value();

        $term = Str::of($raw)
            ->remove(['%', '_', '\\'])
            ->value();

        if ($raw === '') {
            return User::query()
                ->orderBy('username')
                ->limit($limit)
                ->get()
                ->toBase();
        }

        if ($term === '') {
            return new Collection();
        }

        return User::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw('LOWER(username) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(name) LIKE ?', ["%{$term}%"]))
            ->orderByRaw(
                'CASE WHEN LOWER(username) = ? THEN 0 WHEN LOWER(username) LIKE ? THEN 1 WHEN LOWER(name) LIKE ? THEN 2 ELSE 3 END',
                [$term, "{$term}%", "{$term}%"],
            )
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
