<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class UserStarsCountQuery
{
    /**
     * Counts the stars other developers gave the user's commits that the viewer may see, so hidden commits never leak their stars.
     */
    public function get(User $user, ?User $viewer): int
    {
        return $this->snapshot($user, $viewer)['total'];
    }

    /**
     * The count with the database time its statement started, in microseconds, so clients can tell which of two counts is newer.
     *
     * @return array{total: int, counted_at: int}
     */
    public function snapshot(User $user, ?User $viewer): array
    {
        /** @var object{total: int|string, counted_at: int|string} $row */
        $row = $user->commits()
            ->visibleTo($viewer)
            ->join('stars', 'stars.commit_id', '=', 'commits.id')
            ->where('stars.user_id', '!=', $user->id)
            ->toBase()
            ->selectRaw('count(*) as total, (extract(epoch from statement_timestamp()) * 1000000)::bigint as counted_at')
            ->first();

        return ['total' => (int) $row->total, 'counted_at' => (int) $row->counted_at];
    }
}
