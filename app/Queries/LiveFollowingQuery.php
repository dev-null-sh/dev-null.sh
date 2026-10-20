<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Support\Collection;

final readonly class LiveFollowingQuery
{
    /**
     * @return Collection<int, User>
     */
    public function get(User $user): Collection
    {
        return $user->following()
            ->where('live_ends_at', '>', now())
            ->whereNotNull('live_platform')
            ->whereNotNull('live_url')
            ->whereNotIn('users.id', $user->mutedIds())
            ->whereNotIn('users.id', $user->idsAcrossBlocks())
            ->inRandomOrder()
            ->limit(3)
            ->get()->toBase();
    }
}
