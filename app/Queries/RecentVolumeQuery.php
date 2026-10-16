<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class RecentVolumeQuery
{
    /**
     * Top-level commits and ordinary replies created in the last 24 hours and 7 days, whatever their status since, deleted commits included.
     *
     * @return array{signups: array{day: int, week: int}, commits: array{day: int, week: int}, replies: array{day: int, week: int}}
     */
    public function get(): array
    {
        $day = now()->subDay();
        $week = now()->subWeek();

        /** @var object{day: int, week: int} $signups */
        $signups = User::query()
            ->where('created_at', '>=', $week)
            ->toBase()
            ->selectRaw('count(case when created_at >= ? then 1 end) as day, count(*) as week', [$day])
            ->first();

        /** @var object{commits_day: int, commits_week: int, replies_day: int, replies_week: int} $commits */
        $commits = Commit::withTrashed()
            ->where('created_at', '>=', $week)
            ->toBase()
            ->selectRaw(
                'count(case when parent_id is null and created_at >= ? then 1 end) as commits_day,'
                .' count(case when parent_id is null then 1 end) as commits_week,'
                .' count(case when parent_id is not null and created_at >= ? then 1 end) as replies_day,'
                .' count(parent_id) as replies_week',
                [$day, $day],
            )
            ->first();

        return [
            'signups' => ['day' => (int) $signups->day, 'week' => (int) $signups->week],
            'commits' => ['day' => (int) $commits->commits_day, 'week' => (int) $commits->commits_week],
            'replies' => ['day' => (int) $commits->replies_day, 'week' => (int) $commits->replies_week],
        ];
    }
}
