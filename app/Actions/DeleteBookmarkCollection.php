<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BookmarkCollection;

final readonly class DeleteBookmarkCollection
{
    /**
     * Only the collection and its assignments go: the saved commits stay saved.
     */
    public function handle(BookmarkCollection $collection): void
    {
        $collection->delete();
    }
}
