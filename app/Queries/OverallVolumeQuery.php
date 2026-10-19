<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class OverallVolumeQuery
{
    /**
     * Everyone and everything since the first signup: users, top-level commits, replies, and the people behind the
     * commits and replies, whatever the status of the commits since, deleted ones included like the activity graph.
     *
     * @return array{since: string|null, signups: int, commits: int, replies: int, authors: int}
     */
    public function get(): array
    {
        $since = User::query()->min('created_at');

        /** @var object{commits: int, replies: int, authors: int} $commits */
        $commits = Commit::withTrashed()
            ->toBase()
            ->selectRaw('count(case when parent_id is null then 1 end) as commits, count(parent_id) as replies, count(distinct user_id) as authors')
            ->first();

        return [
            'since' => is_string($since) ? CarbonImmutable::parse($since)->toDateString() : null,
            'signups' => User::query()->count(),
            'commits' => (int) $commits->commits,
            'replies' => (int) $commits->replies,
            'authors' => (int) $commits->authors,
        ];
    }
}
