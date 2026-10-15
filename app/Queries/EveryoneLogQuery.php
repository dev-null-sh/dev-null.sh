<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class EveryoneLogQuery implements LogQuery
{
    /**
     * @return Builder<Commit>
     */
    public function builder(User $user): Builder
    {
        return Commit::query()
            ->visibleTo($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->with('user')
            ->latest()
            ->latest('id');
    }
}
