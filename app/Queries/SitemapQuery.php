<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class SitemapQuery
{
    /**
     * Well under the 50,000 URLs a sitemap may list, so every page stays quick to build.
     */
    public const int PER_PAGE = 10_000;

    public function userPages(): int
    {
        return (int) ceil($this->users()->count() / self::PER_PAGE);
    }

    public function commitPages(): int
    {
        return (int) ceil($this->commits()->count() / self::PER_PAGE);
    }

    /**
     * @return Collection<int, User>
     */
    public function usersOn(int $page): Collection
    {
        return $this->users()
            ->select(['id', 'username', 'updated_at'])
            ->orderBy('id')
            ->forPage($page, self::PER_PAGE)
            ->get();
    }

    /**
     * @return Collection<int, Commit>
     */
    public function commitsOn(int $page): Collection
    {
        return $this->commits()
            ->select(['id', 'user_id', 'updated_at'])
            ->with('user:id,username')
            ->orderBy('id')
            ->forPage($page, self::PER_PAGE)
            ->get();
    }

    /**
     * Profiles without a single approved commit have nothing to read yet.
     *
     * @return Builder<User>
     */
    private function users(): Builder
    {
        return User::query()->whereHas('commits', fn (CommitBuilder $query): CommitBuilder => $query->approved());
    }

    private function commits(): CommitBuilder
    {
        return Commit::query()->approved();
    }
}
