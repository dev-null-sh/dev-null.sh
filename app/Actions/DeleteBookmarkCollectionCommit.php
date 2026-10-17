<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark;
use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;

final readonly class DeleteBookmarkCollectionCommit
{
    /**
     * Takes the commit out of the collection only: it stays saved and in the user's other collections.
     */
    public function handle(User $user, BookmarkCollection $collection, Commit $commit): void
    {
        $collection->bookmarks()->detach(
            Bookmark::query()
                ->where('user_id', $user->id)
                ->where('commit_id', $commit->id)
                ->pluck('id')
                ->all(),
        );
    }
}
