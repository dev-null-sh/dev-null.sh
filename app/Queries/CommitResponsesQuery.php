<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CommitResponsesQuery
{
    /**
     * Whether the approved top-level commits written in the range found an audience: how many got a reply, a quote or
     * a star from someone else within 24 hours, and how long the first one took, in minutes, as a median. First commits
     * are the ones that were their author's first post of any kind. Deleted commits count, so a commit deleted for lack
     * of an answer still does. Bots are left out on both sides, and commits younger than 24 hours wait their turn.
     *
     * @return array{commits: int, answered: int, median_minutes: int|null, first_commits: int, first_answered: int}
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $approved = CommitStatus::Approved->value;
        $from = $from->startOfDay();
        $until = $to->addDay()->startOfDay();
        $answeredUntil = $until->addDay();

        /** @var object{commits: int, answered: int, median_seconds: float|null, first_commits: int, first_answered: int} $row */
        $row = DB::selectOne(<<<SQL
            with humans as materialized (
                select id from users where github_id <> all(?::text[])
            ), commits_in_range as materialized (
                select c.id, c.user_id, c.created_at from commits c join humans a on a.id = c.user_id
                where c.parent_id is null and c.status = '{$approved}'
                and c.created_at >= ? and c.created_at < ? and c.created_at <= ?
            ), responses as (
                select parent_id as commit_id, user_id, created_at from commits
                where parent_id in (select id from commits_in_range) and status = '{$approved}' and created_at >= ? and created_at < ?
                union all
                select quoted_commit_id, user_id, created_at from commits
                where quoted_commit_id in (select id from commits_in_range) and status = '{$approved}' and created_at >= ? and created_at < ?
                union all
                select commit_id, user_id, created_at from stars
                where commit_id in (select id from commits_in_range) and created_at >= ? and created_at < ?
            ), first_responses as (
                select c.id, min(r.created_at) - c.created_at as waited
                from commits_in_range c
                join responses r on r.commit_id = c.id and r.user_id <> c.user_id and r.created_at <= c.created_at + interval '24 hours'
                join humans h on h.id = r.user_id
                group by c.id, c.created_at
            ), first_commits as (
                select user_id, min(created_at) as created_at from commits
                where user_id in (select user_id from commits_in_range)
                group by user_id
            ), measured as (
                select f.waited, c.created_at = fc.created_at as is_first
                from commits_in_range c
                join first_commits fc on fc.user_id = c.user_id
                left join first_responses f on f.id = c.id
            )
            select count(*) as commits,
                count(*) filter (where waited <= interval '24 hours') as answered,
                percentile_cont(0.5) within group (order by extract(epoch from waited)) filter (where waited <= interval '24 hours') as median_seconds,
                count(*) filter (where is_first) as first_commits,
                count(*) filter (where is_first and waited <= interval '24 hours') as first_answered
            from measured
            SQL, [
            User::botGitHubIdsArray(),
            $from,
            $until,
            now()->subDay(),
            $from,
            $answeredUntil,
            $from,
            $answeredUntil,
            $from,
            $answeredUntil,
        ]);

        return [
            'commits' => (int) $row->commits,
            'answered' => (int) $row->answered,
            'median_minutes' => $row->median_seconds === null ? null : (int) round($row->median_seconds / 60),
            'first_commits' => (int) $row->first_commits,
            'first_answered' => (int) $row->first_answered,
        ];
    }
}
