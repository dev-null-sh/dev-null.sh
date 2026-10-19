<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Feature;
use App\Enums\SearchIndex;
use App\Models\User;
use App\Services\Algolia;
use App\Services\Features;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class UserSearchQuery
{
    /**
     * Algolia doesn't know who the viewer mutes or blocks, so each page asks for more than the limit and the database
     * leaves those out after. Further pages are read until the limit is reached or Algolia runs out of matches.
     */
    private const int ALGOLIA_OVERFETCH = 3;

    public function __construct(private Algolia $algolia, private Features $features)
    {
        //
    }

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

        if ($term === '') {
            return new Collection();
        }

        if ($this->algolia->searches() && $this->features->enabled(Feature::AlgoliaUserSearch)) {
            try {
                return $this->ranked($term, $viewer, $limit);
            } catch (ConnectionException|RequestException $exception) {
                report($exception);
            }
        }

        return $this->matching($term, $viewer, $limit);
    }

    /**
     * @return Collection<int, User>
     */
    private function ranked(string $term, ?User $viewer, int $limit): Collection
    {
        $perPage = $limit * self::ALGOLIA_OVERFETCH;
        $users = new Collection();

        for ($page = 0; $users->count() < $limit; $page++) {
            $ids = $this->algolia->search(SearchIndex::Users, $term, $perPage, $page);
            $positions = array_flip($ids);

            $users = $users
                ->concat($this->reachable($viewer)
                    ->whereKey(array_filter($ids, Str::isUuid(...)))
                    ->get()
                    ->sortBy(fn (User $user): int => $positions[$user->id]))
                ->unique('id');

            if (count($ids) < $perPage) {
                break;
            }
        }

        return $users->take($limit)->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function matching(string $term, ?User $viewer, int $limit): Collection
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return $this->reachable($viewer)
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
            ->orderByRaw(
                "CASE WHEN LOWER(username) = ? THEN 0 WHEN LOWER(username) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$term, "{$escaped}%", "{$escaped}%"],
            )
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /**
     * @return Builder<User>
     */
    private function reachable(?User $viewer): Builder
    {
        return User::query()
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query
                ->whereNotIn('id', $viewer->mutedIds())
                ->whereNotIn('id', $viewer->idsAcrossBlocks()));
    }
}
