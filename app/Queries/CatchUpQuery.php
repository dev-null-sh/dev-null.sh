<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final readonly class CatchUpQuery
{
    public const int PREVIEW_COUNT = 3;

    public const int LISTED_COUNT = 6;

    /**
     * What came into the user's Following log from others after the newest commit they saw there, once they have been
     * away long enough to miss some: the most starred, a few shown at first and a few more on request, and how many
     * commits came from how many people in all. It looks back a week at most, so a long absence still ranks recent
     * commits, and there is nothing to catch up on when the log itself shows no more than the few.
     *
     * @return array{commits: Collection<int, Commit>, commits_count: int, authors_count: int, since: CarbonInterface}|null
     */
    public function get(User $user, ?CarbonInterface $seenAt): ?array
    {
        if (! $seenAt instanceof CarbonInterface || $seenAt->isAfter(now()->subDays(2))) {
            return null;
        }

        $since = $seenAt->max(now()->subWeek());
        $query = Log::Following->query()->builder($user);
        $query->where($query->qualifyColumn('user_id'), '!=', $user->id)
            ->where($query->qualifyColumn('created_at'), '>', $since);

        $count = (clone $query)->count();

        if ($count <= self::PREVIEW_COUNT) {
            return null;
        }

        return [
            'commits' => (clone $query)->reorder()->orderByDesc('stars_count')->latest()->latest('id')->limit(self::LISTED_COUNT)->get(),
            'commits_count' => $count,
            'authors_count' => $query->distinct()->count($query->qualifyColumn('user_id')),
            'since' => $since,
        ];
    }
}
