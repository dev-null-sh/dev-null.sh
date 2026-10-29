<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Profiles are listed by the month they signed up and commits by the day they were written, in UTC, so a URL never
 * moves to another sitemap and the older sitemaps rarely change. A sitemap may list 50,000 URLs, far more than a month
 * of sign-ups or a day of commits.
 */
final readonly class SitemapQuery
{
    /**
     * The months, as "2026-10", in which the profiles with approved commits signed up, oldest first, with when the
     * last of them changed, so search engines only read again the sitemaps that changed.
     *
     * @return list<array{month: string, updated_at: CarbonImmutable}>
     */
    public function userMonths(): array
    {
        /** @var list<object{month: string, updated_at: string}> $months */
        $months = DB::query()
            ->fromSub($this->users(), 'users')
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, max(updated_at) as updated_at")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->all();

        return array_map(fn (object $month): array => [
            'month' => $month->month,
            'updated_at' => CarbonImmutable::parse($month->updated_at),
        ], $months);
    }

    /**
     * The days, as "2026-10-29", on which approved commits were written, oldest first, with when the last of them
     * changed, so search engines only read again the sitemaps that changed.
     *
     * @return list<array{day: string, updated_at: CarbonImmutable}>
     */
    public function commitDays(): array
    {
        /** @var list<object{day: string, updated_at: string}> $days */
        $days = $this->commits()
            ->toBase()
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, max(updated_at) as updated_at")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->all();

        return array_map(fn (object $day): array => [
            'day' => $day->day,
            'updated_at' => CarbonImmutable::parse($day->updated_at),
        ], $days);
    }

    public function hasUsersIn(CarbonImmutable $month): bool
    {
        return $this->usersSignedUpIn($month)->exists();
    }

    public function hasCommitsOn(CarbonImmutable $day): bool
    {
        return $this->commitsWrittenOn($day)->exists();
    }

    /**
     * Read as plain rows rather than models, since a month lists thousands of them.
     *
     * @return list<array{handle: string, updated_at: CarbonImmutable}>
     */
    public function usersIn(CarbonImmutable $month): array
    {
        /** @var list<object{handle: string, updated_at: string}> $users */
        $users = $this->usersSignedUpIn($month)
            ->orderBy('users.id')
            ->toBase()
            ->get()
            ->all();

        return array_map(fn (object $user): array => [
            'handle' => $user->handle,
            'updated_at' => CarbonImmutable::parse($user->updated_at),
        ], $users);
    }

    /**
     * Read as plain rows rather than models, with the author and the images in the same query, since a day lists
     * thousands of them.
     *
     * @return list<array{id: string, handle: string, updated_at: CarbonImmutable, image_ids: list<string>}>
     */
    public function commitsOn(CarbonImmutable $day): array
    {
        $onDay = $this->commitsWrittenOn($day)->select(['id', 'user_id', 'updated_at']);

        /** @var list<object{id: string, handle: string, updated_at: string, image_id: string|null}> $rows */
        $rows = DB::query()
            ->fromSub($onDay, 'commits')
            ->join('users', 'users.id', '=', 'commits.user_id')
            ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->leftJoin('commit_images', 'commit_images.commit_id', '=', 'commits.id')
            ->orderBy('commits.id')
            ->orderBy('commit_images.position')
            ->get(['commits.id', 'profiles.handle', 'commits.updated_at', 'commit_images.id as image_id'])
            ->all();

        $commits = [];

        foreach ($rows as $row) {
            $commits[$row->id] ??= [
                'id' => $row->id,
                'handle' => $row->handle,
                'updated_at' => CarbonImmutable::parse($row->updated_at),
                'image_ids' => [],
            ];

            if ($row->image_id !== null) {
                $commits[$row->id]['image_ids'][] = $row->image_id;
            }
        }

        return array_values($commits);
    }

    /**
     * Profiles without a single approved commit have nothing to read yet. A profile's page changes with the account,
     * its GitHub profile, and the last of its approved commits.
     *
     * @return Builder<User>
     */
    private function users(): Builder
    {
        $lastCommits = $this->commits()
            ->toBase()
            ->select('user_id')
            ->selectRaw('max(updated_at) as updated_at')
            ->groupBy('user_id');

        return User::query()
            ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->joinSub($lastCommits, 'last_commits', 'last_commits.user_id', '=', 'users.id')
            ->select(['profiles.handle', 'users.created_at'])
            ->selectRaw('greatest(users.updated_at, profiles.updated_at, last_commits.updated_at) as updated_at');
    }

    /**
     * @return Builder<User>
     */
    private function usersSignedUpIn(CarbonImmutable $month): Builder
    {
        return $this->users()
            ->where('users.created_at', '>=', $month)
            ->where('users.created_at', '<', $month->addMonth());
    }

    private function commits(): CommitBuilder
    {
        return Commit::query()->approved();
    }

    private function commitsWrittenOn(CarbonImmutable $day): CommitBuilder
    {
        return $this->commits()
            ->where('created_at', '>=', $day)
            ->where('created_at', '<', $day->addDay());
    }
}
