<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark;
use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;

final readonly class CreateBookmarkCollectionCommit
{
    /**
     * Adds the user's save of the commit to the collection, keeping any earlier assignment as it is.
     */
    public function handle(User $user, BookmarkCollection $collection, Commit $commit): void
    {
        $bookmark = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('commit_id', $commit->id)
            ->firstOrFail();

        $collection->bookmarks()->syncWithoutDetaching([$bookmark->id]);
    }
}
