<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class CommitAncestorsQuery
{
    /**
     * @return Collection<int, Commit>
     */
    public function get(Commit $commit, ?User $user, int $limit = 20): Collection
    {
        $ancestors = new Collection;
        $parentId = $commit->parent_id;

        while ($parentId !== null && ($parent = $this->visibleCommit($parentId, $user)) instanceof Commit) {
            if ($ancestors->count() === $limit) {
                $ancestors->first()?->setRelation('parent', $parent);

                break;
            }

            $ancestors->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    private function visibleCommit(string $id, ?User $user): ?Commit
    {
        return Commit::query()->withTrashed()->visibleTo($user)->withSavedState($user)->with(['user', 'linkPreview', 'images'])->withStarsFor($user)->find($id);
    }
}
