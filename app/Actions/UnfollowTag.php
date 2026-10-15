<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Models\User;

final readonly class UnfollowTag
{
    public function handle(User $user, Tag $tag): void
    {
        $user->followedTags()->detach($tag->id);
    }
}
