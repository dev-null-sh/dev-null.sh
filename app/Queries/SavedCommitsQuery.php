<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final readonly class SavedCommitsQuery
{
    /**
     * The user's saved commits, newest saved first, optionally only those in one of their collections.
     * Each commit carries the ids of the user's collections it is in.
     */
    public function builder(User $user, ?BookmarkCollection $collection = null): CommitBuilder
    {
        return Commit::query()
            ->join('bookmarks', 'bookmarks.commit_id', '=', 'commits.id')
            ->where('bookmarks.user_id', $user->id)
            ->when($collection, fn (CommitBuilder $query, BookmarkCollection $collection): CommitBuilder => $query->whereExists(
                fn (QueryBuilder $items): QueryBuilder => $items
                    ->from('bookmark_collection_items')
                    ->whereColumn('bookmark_collection_items.bookmark_id', 'bookmarks.id')
                    ->where('bookmark_collection_items.bookmark_collection_id', $collection->id),
            ))
            ->select('commits.*')
            ->addSelect('bookmarks.id as saved_order')
            ->visibleTo($user)
            ->withVisibleReplyCount($user)
            ->withCardDataFor($user)
            ->with([
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('user');
                },
            ])
            ->afterQuery($this->withCollectionIds(...))
            ->orderByDesc('saved_order');
    }

    /**
     * @param  Collection<int, Commit>  $commits
     */
    private function withCollectionIds(Collection $commits): void
    {
        if ($commits->isEmpty()) {
            return;
        }

        $collectionIds = DB::table('bookmark_collection_items')
            ->whereIn('bookmark_id', $commits->pluck('saved_order')->all())
            ->orderBy('bookmark_collection_id')
            ->get(['bookmark_id', 'bookmark_collection_id'])
            ->groupBy('bookmark_id');

        foreach ($commits as $commit) {
            $commit->setAttribute('collection_ids', $collectionIds->get($commit->saved_order, collect())->pluck('bookmark_collection_id')->values()->all());
        }
    }
}
