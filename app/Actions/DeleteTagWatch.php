<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Models\User;

final readonly class DeleteTagWatch
{
    public function handle(User $user, Tag $tag): void
    {
        $tag->watches()->where('user_id', $user->id)->delete();
    }
}
