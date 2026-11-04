<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use App\Models\UserList;

final readonly class UserListCommitsQuery
{
    /**
     * Reads like the Following log, with the list's members in place of the people followed.
     */
    public function builder(User $user, UserList $list): CommitBuilder
    {
        $members = $list->members();

        return Commit::query()
            ->whereIn('user_id', $members->newPivotQuery()->select($members->getRelatedPivotKeyName()))
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
