<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type ActiveUserCount array{date: string, count: int, returning: int, activated: int, not_activated: int, pending: int}
 */
final readonly class ActiveUserCountsQuery
{
    public function __construct(private ActivationByDayQuery $activationByDay)
    {
        //
    }

    /**
     * The people seen each day of the range, oldest first, counted as each was first seen that day since tracking
     * started. Today's count is still growing. Each count is split into the people who came back, returning, and the
     * people who signed up that day, as every signup is seen on the page it lands on: activated, not activated, or
     * younger than 12 hours, too young to tell. Returning is what's left once the signups are taken out, and isn't
     * split further.
     *
     * @return array<int, ActiveUserCount>
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<object{date: string, signups: int}> $rows */
        $rows = DB::select(<<<'SQL'
            select created_at::date as date, count(*) as signups from users
            where github_id <> all(?::text[]) and created_at >= ? and created_at < ?
            group by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->startOfDay(),
            $to->addDay()->startOfDay(),
        ]);

        $signups = array_column($rows, 'signups', 'date');
        $activation = array_column($this->activationByDay->get($from, $to), null, 'date');

        return DB::table('active_user_counts')
            ->whereBetween('seen_at', [$from->toDateString(), $to->toDateString()])
            ->oldest('seen_at')
            ->get(['seen_at', 'count'])
            ->mapInto(Fluent::class)
            ->map(function (Fluent $row) use ($signups, $activation): array {
                $date = $row->string('seen_at')->value();
                $count = $row->integer('count');
                $new = (int) ($signups[$date] ?? 0);
                $activated = $activation[$date]['activated'] ?? 0;
                $eligible = $activation[$date]['eligible'] ?? 0;

                return [
                    'date' => $date,
                    'count' => $count,
                    'returning' => max(0, $count - $new),
                    'activated' => $activated,
                    'not_activated' => $eligible - $activated,
                    'pending' => $new - $eligible,
                ];
            })
            ->all();
    }
}
