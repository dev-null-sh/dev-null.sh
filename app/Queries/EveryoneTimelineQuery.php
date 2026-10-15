<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class EveryoneTimelineQuery implements TimelineQuery
{
    /**
     * @return Builder<Post>
     */
    public function builder(User $user): Builder
    {
        return Post::query()
            ->latest()
            ->latest('id');
    }
}
