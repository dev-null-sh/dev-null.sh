<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class ActiveUsersQuery
{
    /**
     * The people seen today, in the last 7 days and in the last 30, today included, as UTC days, and the people who
     * wrote a commit or a reply in the last 7 days, deleted ones included. Bots are left out.
     *
     * @return array{daily: int, weekly: int, monthly: int, weekly_authors: int}
     */
    public function get(): array
    {
        $today = CarbonImmutable::today();

        /** @var object{daily: int, weekly: int, monthly: int} $seen */
        $seen = User::query()
            ->whereNotIn('github_id', User::BOT_GITHUB_IDS)
            ->where('last_seen_at', '>=', $today->subDays(29)->toDateString())
            ->toBase()
            ->selectRaw('count(case when last_seen_at >= ? then 1 end) as daily, count(case when last_seen_at >= ? then 1 end) as weekly, count(*) as monthly', [
                $today->toDateString(),
                $today->subDays(6)->toDateString(),
            ])
            ->first();

        return [
            'daily' => (int) $seen->daily,
            'weekly' => (int) $seen->weekly,
            'monthly' => (int) $seen->monthly,
            'weekly_authors' => Commit::withTrashed()
                ->where('created_at', '>=', $today->subDays(6))
                ->whereNotIn('user_id', User::query()->whereIn('github_id', User::BOT_GITHUB_IDS)->select('id'))
                ->distinct()
                ->count('user_id'),
        ];
    }
}
