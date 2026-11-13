<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type ActiveUserCount array{date: string, count: int, regular: int, back: int, lost: int|null, lost_complete: bool, backfilled: bool, activated: int, not_activated: int, pending: int}
 */
final readonly class ActiveUserCountsQuery
{
    public function __construct(private ActivationByDayQuery $activationByDay, private BackAndLostQuery $backAndLost)
    {
        //
    }

    /**
     * The people seen each day of the range, oldest first, counted as each was first seen that day since tracking
     * started. Today's count is still growing. Each count is split into the people who came back and the people who
     * signed up that day, as every signup is seen on the page it lands on: activated, not activated, or younger than 12
     * hours, too young to tell. The people who came back are what's left once the signups are taken out, split into the
     * ones back after a week or more away, as BackAndLostQuery counts them, and regulars. Lost comes with them, and
     * backfilled says the split is approximate.
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
        $backAndLost = array_column($this->backAndLost->get($from, $to), null, 'date');

        return DB::table('active_user_counts')
            ->whereBetween('seen_at', [$from->toDateString(), $to->toDateString()])
            ->oldest('seen_at')
            ->get(['seen_at', 'count'])
            ->mapInto(Fluent::class)
            ->map(function (Fluent $row) use ($signups, $activation, $backAndLost): array {
                $date = $row->string('seen_at')->value();
                $count = $row->integer('count');
                $new = (int) ($signups[$date] ?? 0);
                $activated = $activation[$date]['activated'] ?? 0;
                $eligible = $activation[$date]['eligible'] ?? 0;
                $returning = max(0, $count - $new);
                $back = min($returning, $backAndLost[$date]['back'] ?? 0);

                return [
                    'date' => $date,
                    'count' => $count,
                    'regular' => $returning - $back,
                    'back' => $back,
                    'lost' => $backAndLost[$date]['lost'] ?? null,
                    'lost_complete' => $backAndLost[$date]['lost_complete'] ?? false,
                    'backfilled' => $backAndLost[$date]['backfilled'] ?? false,
                    'activated' => $activated,
                    'not_activated' => $eligible - $activated,
                    'pending' => $new - $eligible,
                ];
            })
            ->all();
    }
}
