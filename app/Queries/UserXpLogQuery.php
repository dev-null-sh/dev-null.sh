<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type XpLogEntry array{source: string, count: int, xp: int}
 * @phpstan-type XpLogDay array{date: string, xp: int, entries: list<XpLogEntry>}
 */
final readonly class UserXpLogQuery
{
    private const int DAYS = 90;

    /**
     * Where the XP of the last 90 days came from, a day at a time, newest first. Days follow the activity graph and the
     * streak: the date of `created_at`. Nothing is stored for this; it is read from the same commits and stars that make
     * up the XP total, with the same visibility as the profile.
     *
     * @return list<XpLogDay>
     */
    public function get(User $user, ?User $viewer): array
    {
        // Today is the first of the 90 days.
        $since = CarbonImmutable::now()->subDays(self::DAYS - 1)->startOfDay();
        $visible = $user->commits()->visibleTo($viewer);

        /** @var Collection<int, object{day: string, commits: int|string, replies: int|string}> $commits */
        $commits = (clone $visible)
            ->where('commits.created_at', '>=', $since)
            ->leftJoin('commits as parents', fn (JoinClause $join): JoinClause => $join
                ->on('parents.id', '=', 'commits.parent_id')
                ->where('parents.user_id', '!=', $user->id))
            ->selectRaw('date(commits.created_at) as day, count(*) as commits, count(parents.id) as replies')
            ->groupBy('day')
            ->toBase()
            ->get();

        /** @var Collection<int, object{day: string, count: int|string}> $starsReceived */
        $starsReceived = DB::table('stars')
            ->whereIn('commit_id', (clone $visible)->select('commits.id'))
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as count')
            ->groupBy('day')
            ->get();

        /** @var Collection<int, object{day: string, count: int|string}> $starsGiven */
        $starsGiven = $user->starredCommits()
            ->where('commits.user_id', '!=', $user->id)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
            ->where('stars.created_at', '>=', $since)
            ->selectRaw('date(stars.created_at) as day, count(*) as count')
            ->groupBy('day')
            ->toBase()
            ->get();

        /** @var array<string, list<XpLogEntry>> $days */
        $days = [];
        $add = function (string $day, string $source, int $count, int $multiplier) use (&$days): void {
            if ($count > 0) {
                $days[$day][] = ['source' => $source, 'count' => $count, 'xp' => $count * $multiplier];
            }
        };

        foreach ($commits as $row) {
            $add($row->day, 'commits', (int) $row->commits, 10);
            $add($row->day, 'replies', (int) $row->replies, 5);
        }

        foreach ($this->longestStreakDays($user, $viewer) as $day) {
            if ($day >= $since->toDateString()) {
                $add($day, 'streak', 1, 15);
            }
        }

        foreach ($starsReceived as $row) {
            $add($row->day, 'stars_received', (int) $row->count, 5);
        }

        foreach ($starsGiven as $row) {
            $add($row->day, 'stars_given', (int) $row->count, 2);
        }

        if ($user->created_at->lt('2026-10-21 14:00:00 UTC') && $user->created_at->gte($since)) {
            $add($user->created_at->toDateString(), 'shell_shocked', 1, 100);
        }

        krsort($days);

        return array_map(fn (string $day, array $entries): array => [
            'date' => $day,
            'xp' => array_sum(array_column($entries, 'xp')),
            'entries' => $entries,
        ], array_keys($days), $days);
    }

    /**
     * The days of the longest streak, the ones that earn streak XP, found the way the streak itself is counted. Equal
     * streaks resolve to the most recent one.
     *
     * @return list<string>
     */
    private function longestStreakDays(User $user, ?User $viewer): array
    {
        /** @var list<string> $days */
        $days = $user->commits()
            ->visibleTo($viewer)
            ->selectRaw('date(created_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $longest = [];
        $run = [];
        $previous = null;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            $run = $previous?->addDay()->isSameDay($date) ? [...$run, $day] : [$day];
            $longest = count($run) >= count($longest) ? $run : $longest;
            $previous = $date;
        }

        return $longest;
    }
}
