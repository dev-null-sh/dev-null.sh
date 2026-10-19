<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Models\User;

final readonly class CreateTagWatch
{
    public function handle(User $user, Tag $tag): void
    {
        if ($tag->watches()->where('user_id', $user->id)->exists()) {
            return;
        }

        $tag->watches()->create(['user_id' => $user->id]);
    }
}
