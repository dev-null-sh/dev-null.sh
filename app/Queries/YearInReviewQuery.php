<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A profile's year in numbers: posts, stars, streaks and community growth.
 *
 * @phpstan-type YearInReview array{year: int, commits: int, replies: int, starsEarned: int, followersGained: int, activeDays: int, longestStreak: int, months: list<int>, topCommit: array{id: string, body: string, stars: int}|null}
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

        /** @var list<string> $days */
        $days = (clone $commits)
            ->selectRaw('date(commits.created_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $months = array_fill(0, 12, 0);

        foreach ((clone $commits)->selectRaw('extract(month from commits.created_at) as month, count(*) as total')->groupBy('month')->pluck('total', 'month') as $month => $total) {
            $months[(int) $month - 1] = (int) $total;
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
            'topCommit' => $top === null ? null : [
                'id' => $top->id,
                'body' => mb_strimwidth($top->body, 0, 140, '…'),
                'stars' => (int) $top->stars_count,
            ],
        ];
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
