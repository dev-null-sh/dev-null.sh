<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A profile's year in numbers: posts, stars, streaks and community growth.
 *
 * @phpstan-type YearInReview array{year: int, commits: int, replies: int, starsEarned: int, followersGained: int, activeDays: int, longestStreak: int, months: list<int>, weekdays: list<int>, hourCounts: list<int>, comeback: bool, languages: array<array{language: string, commits: int}>, dayCounts: array<string, int>, topCommit: array{id: string, body: string, stars: int}|null}
 */
final readonly class YearInReviewQuery
{
    /**
     * Fence tags authors already wrote, so no API calls and no new tables.
     */
    private const FENCE_EXCLUDED = ['ansi', 'diff', 'fix', 'log', 'output', 'plaintext', 'terminal', 'text', 'txt'];

    /** @var array<string, string> */
    private const LANGUAGE_CANONICAL = [
        'js' => 'javascript',
        'sh' => 'bash',
        'shell' => 'bash',
        'ts' => 'typescript',
        'yml' => 'yaml',
    ];

    /** @var array<string, string> */
    private const LANGUAGE_DISPLAY = [
        'bash' => 'Shell',
        'blade' => 'Blade',
        'c#' => 'C#',
        'c++' => 'C++',
        'css' => 'CSS',
        'go' => 'Go',
        'html' => 'HTML',
        'java' => 'Java',
        'javascript' => 'JavaScript',
        'js' => 'JavaScript',
        'json' => 'JSON',
        'jsx' => 'JSX',
        'kotlin' => 'Kotlin',
        'php' => 'PHP',
        'python' => 'Python',
        'ruby' => 'Ruby',
        'rust' => 'Rust',
        'sh' => 'Shell',
        'shell' => 'Shell',
        'sql' => 'SQL',
        'swift' => 'Swift',
        'ts' => 'TypeScript',
        'tsx' => 'TSX',
        'typescript' => 'TypeScript',
        'vue' => 'Vue',
        'xml' => 'XML',
        'yaml' => 'YAML',
        'yml' => 'YAML',
    ];

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
            ->where('commits.author_id', $user->id)
            ->whereBetween('stars.created_at', [$start, $end])
            ->count();

        $top = (clone $commits)->withCount('stars')->orderByDesc('stars_count')->orderBy('commits.created_at')->first();

        /** @var array<string, int> $dayCounts */
        $dayCounts = (clone $commits)->whereNull('commits.parent_id')
            ->selectRaw('date(commits.created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();

        $days = array_keys($dayCounts);

        $months = array_fill(0, 12, 0);

        foreach ((clone $commits)->whereNull('commits.parent_id')->selectRaw('extract(month from commits.created_at) as month, count(*) as total')->groupBy('month')->pluck('total', 'month') as $month => $total) {
            $months[(int) $month - 1] = (int) $total;
        }

        $weekdays = array_fill(0, 7, 0);

        foreach ((clone $commits)->whereNull('commits.parent_id')->selectRaw('extract(dow from commits.created_at) as dow, count(*) as total')->groupBy('dow')->pluck('total', 'dow') as $dow => $total) {
            $weekdays[(int) $dow] = (int) $total;
        }

        $hourCounts = array_fill(0, 24, 0);

        foreach ((clone $commits)->whereNull('commits.parent_id')->selectRaw('extract(hour from commits.created_at) as hour, count(*) as total')->groupBy('hour')->pluck('total', 'hour') as $hour => $total) {
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
            'languages' => $this->topLanguages((clone $commits)->whereNull('commits.parent_id')->pluck('commits.body')),
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

            if ($previous !== null && $current->toDateString() === $previous->addDay()->toDateString()) {
                $run++;
            } else {
                $gap = $previous === null ? 0 : (int) $previous->diffInDays($current);
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
     * Top 3 fence-tagged languages across the year's posts. Each post counts
     * once per language, no matter how many blocks it holds.
     *
     * @return array<array{language: string, commits: int}>
     */
    private function topLanguages(iterable $bodies): array
    {
        $counts = [];

        foreach ($bodies as $body) {
            preg_match_all('/```([a-zA-Z0-9_+#-]+)/', (string) $body, $matches);

            $tags = array_map(strtolower(...), $matches[1]);
            $tags = array_map(fn ($tag) => self::LANGUAGE_CANONICAL[$tag] ?? $tag, $tags);

            foreach (array_unique($tags) as $tag) {
                if (in_array($tag, self::FENCE_EXCLUDED, true)) {
                    continue;
                }

                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        arsort($counts);

        $top = [];

        foreach (array_slice($counts, 0, 3, true) as $tag => $commits) {
            $tag = (string) $tag;
            $top[] = ['language' => self::LANGUAGE_DISPLAY[$tag] ?? ucfirst($tag), 'commits' => $commits];
        }

        return $top;
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
            $current = CarbonImmutable::parse($day);
            $run = $previous !== null && $current->toDateString() === $previous->addDay()->toDateString() ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $current;
        }

        return $longest;
    }
}
