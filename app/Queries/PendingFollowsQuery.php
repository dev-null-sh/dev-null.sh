<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Members waiting to follow a developer who isn't on /dev/null yet.
 */
final readonly class PendingFollowsQuery
{
    public function count(Profile $profile): int
    {
        return $this->waiting($profile)->count();
    }

    public function isWaiting(Profile $profile, User $user): bool
    {
        return $this->waiting($profile)->where('user_id', $user->id)->exists();
    }

    /**
     * Who follows whom is for members, so they only see who is waiting among the people they follow.
     *
     * @return list<array{id: int, handle: string, name: string, avatar_url: string|null}>
     */
    public function youFollow(Profile $profile, User $user): array
    {
        /** @var list<array{id: int, handle: string, name: string, avatar_url: string|null}> */
        return $user->following()
            ->whereIn('users.id', $this->waiting($profile)->select('user_id'))
            ->orderBy('users.id')
            ->limit(3)
            ->get()
            ->map(fn (User $followed): array => $followed->only(['id', 'handle', 'name', 'avatar_url']))
            ->all();
    }

    private function waiting(Profile $profile): Builder
    {
        return DB::table('pending_follows')->where('github_id', $profile->github_id);
    }
}
