<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class CommitStarCountsQuery
{
    /**
     * What a card counts, as the viewer sees it: its stars, whether they starred it, its pastéis, and the replies they
     * can see.
     *
     * @param  list<string>  $ids
     * @return Collection<int, array{id: string, stars_count: int, starred: bool, pasteis_count: int, replies_count: int}>
     */
    public function get(User $viewer, array $ids): Collection
    {
        return Commit::query()
            ->whereKey($ids)
            ->visibleTo($viewer)
            ->withStarsFor($viewer)
            ->withVisibleReplyCount($viewer)
            ->get()
            ->toBase()
            ->map(fn (Commit $commit): array => [
                'id' => $commit->id,
                'stars_count' => $commit->stars_count,
                'starred' => $commit->starred,
                'pasteis_count' => $commit->pasteis_count,
                'replies_count' => $commit->replies_count,
            ]);
    }
}
