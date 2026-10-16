<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class CommitSeriesQuery
{
    /**
     * @return Collection<int, Commit>
     */
    public function before(Commit $commit, ?User $user): Collection
    {
        return $this->parts($commit, $user, '<');
    }

    /**
     * @return Collection<int, Commit>
     */
    public function after(Commit $commit, ?User $user): Collection
    {
        return $this->parts($commit, $user, '>');
    }

    /**
     * Deleted parts stay in the series as tombstones, so it still reads in order.
     *
     * @return Collection<int, Commit>
     */
    private function parts(Commit $commit, ?User $user, string $operator): Collection
    {
        if ($commit->series_id === null) {
            return new Collection;
        }

        return Commit::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('series_id', $commit->series_id)
            ->where('series_position', $operator, $commit->series_position)
            ->withVisibleReplyCount($user)
            ->withSavedState($user)
            ->with(['user', 'linkPreview', 'images'])
            ->withStarsFor($user)
            ->oldest('series_position')
            ->get();
    }
}
