<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class EveryoneLogQuery implements LogQuery
{
    /**
     * @return Builder<Commit>
     */
    public function builder(User $user): Builder
    {
        return Commit::query()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withSavedState($user)
            ->with(['user', 'linkPreview', 'images'])
            ->withStarsFor($user)
            ->latest()
            ->latest('id');
    }
}
