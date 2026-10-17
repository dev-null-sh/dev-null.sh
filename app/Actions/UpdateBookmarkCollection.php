<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BookmarkCollection;

final readonly class UpdateBookmarkCollection
{
    public function handle(BookmarkCollection $collection, string $name): void
    {
        $collection->update(['name' => $name]);
    }
}
