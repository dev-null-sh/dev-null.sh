<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A profile's year in numbers: posts, stars, streaks and community growth.
 *
 * @phpstan-type YearInReview array{year: int, commits: int, replies: int, starsEarned: int, followersGained: int, activeDays: int, longestStreak: int, months: list<int>, weekdays: list<int>, hourCounts: list<int>, comeback: bool, dayCounts: array<string, int>, topCommit: array{id: string, body: string, stars: int}|null}
 */
final readonly class YearInReviewQuery
{
    /**
     * @return YearInReview
     */
    public function get(User $user, ?User $viewer, int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();

        $commits = $user->commits()->visibleTo($viewer)->whereBetween('commits.created_at', [$start, $end]);

        $starsEarned = (int) DB::table('stars')
            ->join('commits', 'commits.id', '=', 'stars.commit_id')
            ->where('commits.user_id', $user->id)
            ->whereBetween('stars.created_at', [$start, $end])
            ->count();

        $top = (clone $commits)->withCount('stars')->orderByDesc('stars_count')->orderBy('commits.created_at')->first();

        /** @var array<string, int> $dayCounts */
        $dayCounts = (clone $commits)
            ->selectRaw('date(commits.created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();

        $days = array_keys($dayCounts);

        $months = array_fill(0, 12, 0);

        foreach ((clone $commits)->selectRaw('extract(month from commits.created_at) as month, count(*) as total')->groupBy('month')->pluck('total', 'month') as $month => $total) {
            $months[(int) $month - 1] = (int) $total;
        }

        $weekdays = array_fill(0, 7, 0);

        foreach ((clone $commits)->selectRaw('extract(dow from commits.created_at) as dow, count(*) as total')->groupBy('dow')->pluck('total', 'dow') as $dow => $total) {
            $weekdays[(int) $dow] = (int) $total;
        }

        $hourCounts = array_fill(0, 24, 0);

        foreach ((clone $commits)->selectRaw('extract(hour from commits.created_at) as hour, count(*) as total')->groupBy('hour')->pluck('total', 'hour') as $hour => $total) {
            $hourCounts[(int) $hour] = (int) $total;
        }

        return [
            'year' => $year,
            'commits' => (clone $commits)->whereNull('commits.parent_id')->count(),
            'replies' => (clone $commits)->whereNotNull('commits.parent_id')->count(),
            'starsEarned' => $starsEarned,
            'followersGained' => $user->followers()->wherePivot('follows.created_at', '>=', $start)->wherePivot('follows.created_at', '<=', $end)->count(),
            'activeDays' => count($days),
            'longestStreak' => $this->longestRun($days),
            'months' => $months,
            'weekdays' => $weekdays,
            'hourCounts' => $hourCounts,
            'comeback' => $this->hasComeback($days),
            'dayCounts' => $dayCounts,
            'topCommit' => $top === null ? null : [
                'id' => $top->id,
                'body' => mb_strimwidth($top->body, 0, 140, '…'),
                'stars' => (int) $top->stars_count,
            ],
        ];
    }

    /**
     * A 7-plus day streak that starts after a 30-plus day silence.
     *
     * @param  list<string>  $days  Ordered Y-m-d dates with at least one commit.
     */
    private function hasComeback(array $days): bool
    {
        $run = 0;
        $gap = 0;
        $previous = null;

        foreach ($days as $day) {
            $current = CarbonImmutable::parse($day);

            if ($previous !== null && $current->diffInDays($previous) === 1) {
                $run++;
            } else {
                $gap = $previous === null ? 0 : $current->diffInDays($previous);
                $run = 1;
            }

            if ($run >= 7 && $gap >= 30) {
                return true;
            }

            $previous = $current;
        }

        return false;
    }

    /**
     * @param  list<string>  $days  Ordered Y-m-d dates with at least one commit.
     */
    private function longestRun(array $days): int
    {
        $longest = 0;
        $run = 0;
        $previous = null;

        foreach ($days as $day) {
            $run = $previous !== null && CarbonImmutable::parse($day)->diffInDays($previous) === 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = CarbonImmutable::parse($day);
        }

        return $longest;
    }
}
