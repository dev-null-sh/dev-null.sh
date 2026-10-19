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

        /** @var object{commits: int, answered: int, median_seconds: float|null, first_commits: int, first_answered: int} $row */
        $row = DB::selectOne(<<<SQL
            with humans as not materialized (
                select id from users where github_id <> all(?::text[])
            ), commits_in_range as (
                select c.created_at,
                    not exists (select 1 from commits e where e.user_id = c.user_id and e.created_at < c.created_at) as is_first,
                    least(
                        (
                            select min(r.created_at) from commits r join humans h on h.id = r.user_id
                            where (r.parent_id = c.id or r.quoted_commit_id = c.id) and r.user_id <> c.user_id and r.status = '{$approved}'
                        ),
                        (
                            select min(s.created_at) from stars s join humans h on h.id = s.user_id
                            where s.commit_id = c.id and s.user_id <> c.user_id
                        )
                    ) - c.created_at as waited
                from commits c join humans a on a.id = c.user_id
                where c.parent_id is null and c.status = '{$approved}'
                and c.created_at >= ? and c.created_at < ? and c.created_at <= ?
            )
            select count(*) as commits,
                count(*) filter (where waited <= interval '24 hours') as answered,
                percentile_cont(0.5) within group (order by extract(epoch from waited)) filter (where waited <= interval '24 hours') as median_seconds,
                count(*) filter (where is_first) as first_commits,
                count(*) filter (where is_first and waited <= interval '24 hours') as first_answered
            from commits_in_range
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            now()->subDay(),
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
