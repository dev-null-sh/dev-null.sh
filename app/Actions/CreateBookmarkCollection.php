<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BookmarkCollection;
use App\Models\User;

final readonly class CreateBookmarkCollection
{
    public function handle(User $user, string $name): BookmarkCollection
    {
        return BookmarkCollection::query()->create([
            'user_id' => $user->id,
            'name' => $name,
        ]);
    }
}
