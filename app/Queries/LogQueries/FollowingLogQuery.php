<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Database\Eloquent\Builder;

final readonly class FollowingLogQuery implements LogQuery
{
    public function builder(?User $user): CommitBuilder
    {
        if (! $user instanceof User) {
            return Commit::query()->whereRaw('1 = 0');
        }

        $following = $user->following();
        $organizations = $user->followedOrganizations();

        return Commit::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('author_id', $following->newPivotQuery()->select($following->getRelatedPivotKeyName()))
                ->orWhereIn('author_id', $organizations->newPivotQuery()->select($organizations->getRelatedPivotKeyName()))
                ->orWhere('author_id', $user->id))
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->latest()
            ->latest('id');
    }
}
