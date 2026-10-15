<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class TagLogQuery
{
    /**
     * Unlike the other logs, a tag's log also lists replies, with their parent for context.
     *
     * @return Builder<Commit>
     */
    public function builder(User $user, Tag $tag): Builder
    {
        $commits = $tag->commits();

        return Commit::query()
            ->whereIn('id', $commits->newPivotQuery()->select($commits->getRelatedPivotKeyName()))
            ->visibleTo($user)
            ->withSavedState($user)
            ->withVisibleReplyCount($user)
            ->with([
                'user',
                'linkPreview',
                'images',
                'parent' => fn (BelongsTo $query): BelongsTo => $query
                    ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($user))
                    ->with('user'),
            ])
            ->withStarsFor($user)
            ->latest()
            ->latest('id');
    }
}
