<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commits whose link card is the given link, oldest first, so they read like the comments under it.
 */
final readonly class LinkCommitsQuery
{
    public function builder(LinkPreview $link, User $user): CommitBuilder
    {
        return Commit::query()
            ->where('link_preview_id', $link->id)
            ->visibleTo($user)
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->with([
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('user');
                },
            ])
            ->orderBy('commits.created_at')->orderBy('commits.id');
    }

    /**
     * @return array{commits: int, people: int}
     */
    public function stats(LinkPreview $link): array
    {
        $commits = Commit::query()->where('link_preview_id', $link->id)->approved();

        return ['commits' => $commits->count(), 'people' => $commits->distinct()->count('user_id')];
    }
}
