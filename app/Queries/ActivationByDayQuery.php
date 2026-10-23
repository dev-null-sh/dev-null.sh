<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ActivationDay array{date: string, activated: int, eligible: int}
 */
final readonly class ActivationByDayQuery
{
    /**
     * Activated, for each signup day that has eligible signups, oldest first: within 12 hours of signing up, someone
     * starred commits by 3 or more other people, or posted and got a reply, a quote or a star from someone
     * else. Only the signups at least 12 hours old are eligible.
     *
     * @return list<ActivationDay>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $approved = CommitStatus::Approved->value;

        /** @var list<object{date: string, activated: int, eligible: int}> $rows */
        $rows = DB::select(<<<SQL
            with humans as not materialized (
                select id, created_at from users where github_id <> all(?::text[])
            ), newcomers as (
                select id, created_at, created_at + interval '12 hours' as until from humans
                where created_at >= ? and created_at < ? and created_at <= ?
            )
            select n.created_at::date as date, count(*) filter (where (
                select count(distinct c.user_id) from commits c
                join humans a on a.id = c.user_id
                where c.user_id <> n.id and c.id in (
                    select s.commit_id from stars s where s.user_id = n.id and s.created_at <= n.until
                )
            ) >= 3 or exists (
                select 1 from commits p
                where p.user_id = n.id and p.created_at <= n.until and (
                    exists (
                        select 1 from stars s join humans h on h.id = s.user_id
                        where s.commit_id = p.id and s.user_id <> n.id and s.created_at <= n.until
                    ) or exists (
                        select 1 from commits r join humans h on h.id = r.user_id
                        where (r.parent_id = p.id or r.quoted_commit_id = p.id) and r.user_id <> n.id
                        and r.status = '{$approved}' and r.created_at <= n.until
                    )
                )
            )) as activated, count(*) as eligible
            from newcomers n
            group by 1
            order by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
            now()->subHours(12),
        ]);

        return array_map(fn (object $row): array => [
            'date' => $row->date,
            'activated' => (int) $row->activated,
            'eligible' => (int) $row->eligible,
        ], $rows);
    }
}
