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
     * The thresholds that best told the people who came back from the others, as measured in PRODUCT_METRICS.md.
     */
    private const int STARRED_AUTHORS = 2;

    private const int FOLLOWS = 10;

    /**
     * Activated, for each signup day that has eligible signups, oldest first: within 12 hours of signing up, someone
     * starred commits by 2 or more other people, followed 10 or more people themselves, or posted and got a reply, a
     * quote or a star from someone else. Follows imported from GitHub are left out: they are created in the
     * transaction that records the import, so follows made in the 10 seconds before either side's import are taken
     * for imported ones. Only the signups at least 12 hours old are eligible.
     *
     * @return list<ActivationDay>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $approved = CommitStatus::Approved->value;
        $starredAuthors = self::STARRED_AUTHORS;
        $follows = self::FOLLOWS;

        /** @var list<object{date: string, activated: int, eligible: int}> $rows */
        $rows = DB::select(<<<SQL
            with humans as not materialized (
                select id, created_at, github_follows_imported_at from users where github_id <> all(?::text[])
            ), newcomers as (
                select id, created_at, created_at + interval '12 hours' as until, github_follows_imported_at from humans
                where created_at >= ? and created_at < ? and created_at <= ?
            )
            select n.created_at::date as date, count(*) filter (where (
                select count(distinct c.user_id) from commits c
                join humans a on a.id = c.user_id
                where c.user_id <> n.id and c.id in (
                    select s.commit_id from stars s where s.user_id = n.id and s.created_at <= n.until
                )
            ) >= {$starredAuthors} or (
                select count(*) from follows f
                join humans followed on followed.id = f.followed_id
                where f.follower_id = n.id and f.created_at <= n.until
                and not coalesce(f.created_at between n.github_follows_imported_at - interval '10 seconds' and n.github_follows_imported_at, false)
                and not coalesce(f.created_at between followed.github_follows_imported_at - interval '10 seconds' and followed.github_follows_imported_at, false)
            ) >= {$follows} or exists (
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
