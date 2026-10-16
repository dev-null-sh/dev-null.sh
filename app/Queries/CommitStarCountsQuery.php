<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class CommitStarCountsQuery
{
    /**
     * @param  list<string>  $ids
     * @return Collection<int, array{id: string, stars_count: int, starred: bool}>
     */
    public function get(User $viewer, array $ids): Collection
    {
        return Commit::query()
            ->select('id')
            ->whereKey($ids)
            ->visibleTo($viewer)
            ->withStarsFor($viewer)
            ->get()
            ->toBase()
            ->map(fn (Commit $commit): array => [
                'id' => $commit->id,
                'stars_count' => $commit->stars_count,
                'starred' => $commit->starred,
            ]);
    }
}
