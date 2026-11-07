<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class SuggestedCommitsQuery
{
    /**
     * @return Collection<int, Commit>
     */
    public function get(User $user, int $limit = 3): Collection
    {
        return Commit::query()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withStarsFor($user)
            ->whereNot('author_id', $user->id)
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get()
            ->unique('author_id')
            ->take($limit)
            ->values()
            ->load('author')
            ->toBase();
    }
}
