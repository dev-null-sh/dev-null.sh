<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class RelatedCommitsQuery
{
    public const int LIMIT = 3;

    public function __construct(private CommitSearchQuery $search)
    {
        //
    }

    /**
     * @return Collection<int, Commit>
     */
    public function get(Commit $commit, ?User $viewer): Collection
    {
        return $this->search->get($commit, $viewer, self::LIMIT);
    }
}
