<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class BookmarkCollectionsQuery
{
    /**
     * The user's collections by name, each counting only the saved commits the Saved page would show.
     *
     * @return Collection<int, BookmarkCollection>
     */
    public function get(User $user): Collection
    {
        return BookmarkCollection::query()
            ->where('user_id', $user->id)
            ->select(['id', 'name'])
            ->withCount(['bookmarks as commits_count' => fn (Builder $bookmarks): Builder => $bookmarks
                ->whereIn('bookmarks.commit_id', Commit::query()->visibleTo($user)->select('commits.id'))])
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->get();
    }
}
