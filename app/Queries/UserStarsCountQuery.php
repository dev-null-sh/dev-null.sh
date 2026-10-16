<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class UserStarsCountQuery
{
    /**
     * Counts the stars on every commit of the user that the viewer may see, so hidden commits never leak their stars.
     */
    public function get(User $user, ?User $viewer): int
    {
        return $user->commits()
            ->visibleTo($viewer)
            ->join('stars', 'stars.commit_id', '=', 'commits.id')
            ->count();
    }
}
