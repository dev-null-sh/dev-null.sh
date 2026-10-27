<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use App\Enums\CommitStatus;
use App\Models\Commit;
use Carbon\CarbonImmutable;

final readonly class ActivityBreakdownsQuery
{
    private const int AUTHORS = 10;

    /**
     * Everyone's commits and replies over the window of the activity graph, deleted ones included, by status in a
     * fixed order with the empty ones kept, and by author, the ten busiest first.
     *
     * @return array{statuses: list<array{name: string, count: int}>, authors: list<array{name: string, count: int}>}
     */
    public function get(ActivityScale $scale): array
    {
        $commits = Commit::withTrashed()
            ->where('commits.created_at', '>=', $scale->rollingStart(CarbonImmutable::now()))
            ->toBase();

        $statuses = (clone $commits)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0);

        /** @var list<object{name: string, aggregate: int}> $authors */
        $authors = (clone $commits)
            ->join('users', 'users.id', '=', 'commits.user_id')
            ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->selectRaw('profiles.handle as name, count(*) as aggregate')
            ->groupBy('profiles.handle')
            ->orderByDesc('aggregate')
            ->orderBy('name')
            ->limit(self::AUTHORS)
            ->get()
            ->all();

        return [
            'statuses' => array_map(fn (CommitStatus $status): array => [
                'name' => $status->value,
                'count' => $statuses[$status->value] ?? 0,
            ], [CommitStatus::Approved, CommitStatus::Pending, CommitStatus::Rejected]),
            'authors' => array_map(fn (object $author): array => [
                'name' => $author->name,
                'count' => (int) $author->aggregate,
            ], $authors),
        ];
    }
}
