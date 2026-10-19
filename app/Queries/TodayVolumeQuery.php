<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class TodayVolumeQuery
{
    /**
     * Signups, top-level commits, replies, and the people behind the commits and replies, today and yesterday (UTC
     * days), whatever the status of the commits since, deleted ones included like the activity graph.
     *
     * @return array{signups: array{today: int, yesterday: int}, commits: array{today: int, yesterday: int}, replies: array{today: int, yesterday: int}, authors: array{today: int, yesterday: int}}
     */
    public function get(): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();

        /** @var object{today: int, yesterday: int} $signups */
        $signups = User::query()
            ->where('created_at', '>=', $yesterday)
            ->toBase()
            ->selectRaw('count(case when created_at >= ? then 1 end) as today, count(case when created_at < ? then 1 end) as yesterday', [$today, $today])
            ->first();

        /** @var object{commits_today: int, commits_yesterday: int, replies_today: int, replies_yesterday: int, authors_today: int, authors_yesterday: int} $commits */
        $commits = Commit::withTrashed()
            ->where('created_at', '>=', $yesterday)
            ->toBase()
            ->selectRaw(
                'count(case when parent_id is null and created_at >= ? then 1 end) as commits_today,'
                .' count(case when parent_id is null and created_at < ? then 1 end) as commits_yesterday,'
                .' count(case when parent_id is not null and created_at >= ? then 1 end) as replies_today,'
                .' count(case when parent_id is not null and created_at < ? then 1 end) as replies_yesterday,'
                .' count(distinct case when created_at >= ? then user_id end) as authors_today,'
                .' count(distinct case when created_at < ? then user_id end) as authors_yesterday',
                [$today, $today, $today, $today, $today, $today],
            )
            ->first();

        return [
            'signups' => ['today' => (int) $signups->today, 'yesterday' => (int) $signups->yesterday],
            'commits' => ['today' => (int) $commits->commits_today, 'yesterday' => (int) $commits->commits_yesterday],
            'replies' => ['today' => (int) $commits->replies_today, 'yesterday' => (int) $commits->replies_yesterday],
            'authors' => ['today' => (int) $commits->authors_today, 'yesterday' => (int) $commits->authors_yesterday],
        ];
    }
}
