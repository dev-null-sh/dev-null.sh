<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PendingFollow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class PendingFollowersQuery
{
    private const int Shown = 5;

    /**
     * How many people follow a GitHub account that isn't on /dev/null yet, and the latest few of them the viewer may
     * see, who will follow it here once it signs in.
     *
     * @return array{count: int, users: Collection<int, User>}
     */
    public function get(string $githubId, ?User $viewer): array
    {
        return [
            'count' => PendingFollow::query()->where('github_id', $githubId)->count(),
            'users' => User::query()
                ->select('users.*')
                ->join('pending_follows', 'pending_follows.user_id', '=', 'users.id')
                ->where('pending_follows.github_id', $githubId)
                ->when($viewer, fn (Builder $query, User $viewer): Builder => $query->whereNotIn('users.id', $viewer->idsAcrossBlocks()))
                ->orderByDesc('pending_follows.id')
                ->limit(self::Shown)
                ->get(),
        ];
    }
}
